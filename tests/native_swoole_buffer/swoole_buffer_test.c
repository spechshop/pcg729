/* Test fixture only. Never add this extension to the production build. */
#ifdef HAVE_CONFIG_H
#include "config.h"
#endif
#include "php.h"
#include "Zend/zend_exceptions.h"
#include "Zend/zend_fibers.h"
#include "ext/swoole/swoole_buffer_c_api.h"
#ifdef SWOOLE_BUFFER_TEST_PSAMPLER
#include "ext/psampler/psampler_binary.h"
#endif

static const swoole_buffer_read_api_v1 *read_api;

typedef struct {
    zend_fcall_info fci;
    zend_fcall_info_cache fcc;
    zval *result;
    zend_long mode;
} read_context;

static zend_ulong checksum(const unsigned char *data, size_t length)
{
    zend_ulong sum = 0;
    size_t i;
    for (i = 0; i < length; ++i) {
        sum += data[i];
    }
    return sum;
}

static zend_result inspect_view(const swoole_buffer_read_view_v1 *view, void *opaque)
{
    read_context *ctx = opaque;
    zend_ulong before = checksum(view->data, view->length);
    if (ctx->mode == 1) {
        return FAILURE; /* Exercise missing-exception normalization. */
    }
    if (ctx->mode == 2) {
        zend_error_noreturn(E_ERROR, "Buffer reader fixture bailout");
    }
    if (ctx->fci.size) {
        zval retval;
        ZVAL_UNDEF(&retval);
        ctx->fci.retval = &retval;
        if (zend_call_function(&ctx->fci, &ctx->fcc) != SUCCESS) {
            return FAILURE;
        }
        zval_ptr_dtor(&retval);
        if (EG(exception)) {
            return FAILURE;
        }
    }
    if (before != checksum(view->data, view->length)) {
        zend_throw_error(NULL, "Borrowed bytes changed");
        return FAILURE;
    }
    array_init(ctx->result);
    add_assoc_long(ctx->result, "length", (zend_long) view->length);
    add_assoc_long(ctx->result, "capacity", (zend_long) view->capacity);
    add_assoc_long(ctx->result, "checksum", (zend_long) before);
    add_assoc_bool(ctx->result, "fiber_blocked", zend_fiber_switch_blocked());
    return SUCCESS;
}

#ifdef SWOOLE_BUFFER_TEST_PSAMPLER
static zend_result inspect_binary_view(const psampler_binary_view *in, void *opaque)
{
    swoole_buffer_read_view_v1 view = {
        in->source, in->spans[0].data, in->spans[0].length, in->capacity, in->token
    };
    if (in->spans[1].length != 0) {
        zend_throw_error(NULL, "Contiguous Swoole producer returned a second span");
        return FAILURE;
    }
    return inspect_view(&view, opaque);
}
#endif

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_read, 0, 1, IS_ARRAY, 0)
    /* By reference lets the lifetime test remove the last PHP owner in callback. */
    ZEND_ARG_TYPE_INFO(1, source, IS_OBJECT, 0)
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, offset, IS_LONG, 0, "0")
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, length, IS_LONG, 1, "null")
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, callback, IS_CALLABLE, 1, "null")
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, mode, IS_LONG, 0, "0")
ZEND_END_ARG_INFO()

PHP_FUNCTION(swoole_buffer_test_read)
{
    zval *source;
    zend_long offset = 0, length = 0, mode = 0;
    bool length_is_null = true;
    read_context ctx = {0};
    ZEND_PARSE_PARAMETERS_START(1, 5)
        Z_PARAM_ZVAL(source)
        Z_PARAM_OPTIONAL
        Z_PARAM_LONG(offset)
        Z_PARAM_LONG_OR_NULL(length, length_is_null)
        Z_PARAM_FUNC_OR_NULL(ctx.fci, ctx.fcc)
        Z_PARAM_LONG(mode)
    ZEND_PARSE_PARAMETERS_END();
    if (offset < 0 || (!length_is_null && length < 0)) {
        zend_value_error("Negative slice");
        RETURN_THROWS();
    }
    ctx.result = return_value;
    ctx.mode = mode;
#ifdef SWOOLE_BUFFER_TEST_PSAMPLER
    if (mode == 3) {
        if (offset != 0 || !length_is_null) {
            zend_value_error("Test-only psampler reader accepts the full view");
            RETURN_THROWS();
        }
        if (psampler_binary_with_view(source, inspect_binary_view, &ctx) != SUCCESS) {
            RETURN_THROWS();
        }
        return;
    }
#endif
    if (swoole_buffer_with_read_view_v1(source, (size_t) offset,
            length_is_null ? SIZE_MAX : (size_t) length, inspect_view, &ctx) != SUCCESS) {
        RETURN_THROWS();
    }
}

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_protocol, 0, 1, _IS_BOOL, 0)
    ZEND_ARG_TYPE_INFO(0, source, IS_OBJECT, 0)
ZEND_END_ARG_INFO()

PHP_FUNCTION(swoole_buffer_test_protocol)
{
    zval *source;
    zend_object *object;
    swoole_buffer_read_view_v1 first = {0}, second = {0}, rejected = {0};
    uint32_t refs;
    zend_bool ok = 1;
    ZEND_PARSE_PARAMETERS_START(1, 1)
        Z_PARAM_OBJECT(source)
    ZEND_PARSE_PARAMETERS_END();
    object = Z_OBJ_P(source);
    GC_ADDREF(object); /* Mandatory external pin. */
    refs = GC_REFCOUNT(object);
    ok &= read_api->acquire(object, NULL) == FAILURE && EG(exception) != NULL;
    zend_clear_exception();
    ok &= GC_REFCOUNT(object) == refs;
    if (read_api->acquire(object, &first) != SUCCESS) {
        OBJ_RELEASE(object);
        RETURN_THROWS();
    }
    ok &= first.source == object && first.token != NULL && GC_REFCOUNT(object) == refs + 1;
    read_api->release(object, NULL); /* Invalid token cannot unlock. */
    ok &= read_api->acquire(object, &rejected) == FAILURE && EG(exception) != NULL;
    zend_clear_exception();
    read_api->release(object, first.token);
    ok &= GC_REFCOUNT(object) == refs;
    if (read_api->acquire(object, &second) != SUCCESS) {
        OBJ_RELEASE(object);
        RETURN_THROWS();
    }
    ok &= second.token != first.token;
    read_api->release(object, first.token); /* Stale token cannot unlock a new borrow. */
    ok &= read_api->acquire(object, &rejected) == FAILURE && EG(exception) != NULL;
    zend_clear_exception();
    read_api->release(object, second.token);
    read_api->release(object, second.token); /* Duplicate release is harmless while pinned. */
    ok &= GC_REFCOUNT(object) == refs;
    OBJ_RELEASE(object);
    RETURN_BOOL(ok);
}

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_contract, 0, 0, _IS_BOOL, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_OBJ_INFO_EX(arginfo_uninitialized, 0, 0, Swoole\\Buffer, 0)
ZEND_END_ARG_INFO()

PHP_FUNCTION(swoole_buffer_test_uninitialized)
{
    ZEND_PARSE_PARAMETERS_NONE();
    object_init_ex(return_value, read_api->buffer_ce); /* Deliberately no constructor. */
}

PHP_FUNCTION(swoole_buffer_test_contract)
{
    swoole_buffer_read_view_v1 view = {0};
    zend_object foreign = {0};
    zend_bool ok;
    zval invalid;
    ZEND_PARSE_PARAMETERS_NONE();
    ok = swoole_buffer_read_api_version() == 1 && read_api->api_version == 1
        && read_api->struct_size == sizeof(*read_api)
        && read_api->buffer_ce->type == ZEND_INTERNAL_CLASS
        && swoole_buffer_get_read_api(2, sizeof(*read_api)) == NULL
        && swoole_buffer_get_read_api(1, sizeof(*read_api) - 1) == NULL;
    ok &= read_api->acquire(NULL, &view) == FAILURE && EG(exception) != NULL;
    zend_clear_exception();
    /* Same CE, foreign handlers, no private Buffer storage at all. */
    foreign.ce = read_api->buffer_ce;
    foreign.handlers = zend_get_std_object_handlers();
    ok &= read_api->acquire(&foreign, &view) == FAILURE && EG(exception) != NULL;
    zend_clear_exception();
    ok &= view.source == NULL && view.data == NULL && view.token == NULL;
    ZVAL_LONG(&invalid, 42);
    ok &= swoole_buffer_with_read_view_v1(&invalid, 0, SIZE_MAX, inspect_view, NULL) == FAILURE
        && EG(exception) != NULL;
    zend_clear_exception();
    ok &= swoole_buffer_with_read_view_v1(NULL, 0, SIZE_MAX, inspect_view, NULL) == FAILURE
        && EG(exception) != NULL;
    zend_clear_exception();
    ok &= swoole_buffer_with_read_view_v1(&invalid, 0, 0, NULL, NULL) == FAILURE
        && EG(exception) != NULL;
    zend_clear_exception();
    RETURN_BOOL(ok);
}

#ifdef SWOOLE_BUFFER_TEST_PSAMPLER
/* Mirrors the proposed psampler_swoole adapter using public headers only.
 * No offsets, C++ types, allocations, strings or runtime symbol lookups. */
static zend_result producer_acquire(zend_object *source, psampler_binary_view *out)
{
    swoole_buffer_read_view_v1 in = {0};
    if (read_api->acquire(source, &in) != SUCCESS) {
        return FAILURE;
    }
    out->source = in.source;
    out->spans[0].data = in.data;
    out->spans[0].length = in.length;
    out->spans[1].data = NULL;
    out->spans[1].length = 0;
    out->capacity = in.capacity;
    out->token = in.token;
    return SUCCESS;
}

static void producer_release(zend_object *source, const psampler_binary_view *view)
{
    read_api->release(source, view->token);
}
#endif

static const zend_function_entry functions[] = {
    PHP_FE(swoole_buffer_test_read, arginfo_read)
    PHP_FE(swoole_buffer_test_protocol, arginfo_protocol)
    PHP_FE(swoole_buffer_test_contract, arginfo_contract)
    PHP_FE(swoole_buffer_test_uninitialized, arginfo_uninitialized)
    PHP_FE_END
};

PHP_MINIT_FUNCTION(swoole_buffer_test)
{
    read_api = swoole_buffer_get_read_api(SWOOLE_BUFFER_READ_API_VERSION, sizeof(*read_api));
    if (!read_api || read_api->api_version != 1 || read_api->struct_size != sizeof(*read_api)
        || !read_api->buffer_ce || !read_api->acquire || !read_api->release) {
        return FAILURE;
    }
#ifdef SWOOLE_BUFFER_TEST_PSAMPLER
    {
        psampler_binary_provider provider = {
            PSAMPLER_BINARY_API_VERSION, sizeof(psampler_binary_provider),
            read_api->buffer_ce, producer_acquire, producer_release
        };
        if (psampler_binary_api_version() != PSAMPLER_BINARY_API_VERSION
            || !psampler_binary_register_provider(&provider)) {
            return FAILURE;
        }
    }
#endif
    return SUCCESS;
}

PHP_MSHUTDOWN_FUNCTION(swoole_buffer_test)
{
#ifdef SWOOLE_BUFFER_TEST_PSAMPLER
    psampler_binary_unregister_provider(read_api->buffer_ce);
#endif
    read_api = NULL;
    return SUCCESS;
}

PHP_RINIT_FUNCTION(swoole_buffer_test)
{
#if defined(ZTS) && defined(COMPILE_DL_SWOOLE_BUFFER_TEST)
    ZEND_TSRMLS_CACHE_UPDATE();
#endif
    return SUCCESS;
}

static const zend_module_dep deps[] = {
    ZEND_MOD_REQUIRED("swoole")
#ifdef SWOOLE_BUFFER_TEST_PSAMPLER
    ZEND_MOD_REQUIRED("psampler")
#endif
    ZEND_MOD_END
};

zend_module_entry swoole_buffer_test_module_entry = {
    STANDARD_MODULE_HEADER_EX, NULL, deps,
    "swoole_buffer_test", functions,
    PHP_MINIT(swoole_buffer_test), PHP_MSHUTDOWN(swoole_buffer_test),
    PHP_RINIT(swoole_buffer_test), NULL, NULL, "0.0.0", STANDARD_MODULE_PROPERTIES
};

#ifdef COMPILE_DL_SWOOLE_BUFFER_TEST
# ifdef ZTS
ZEND_TSRMLS_CACHE_DEFINE()
# endif
ZEND_GET_MODULE(swoole_buffer_test)
#endif
