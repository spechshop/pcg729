#ifdef HAVE_CONFIG_H
#include "config.h"
#endif

#define PSAMPLER_BINARY_BUILD
#include "psampler_binary.h"
#include "binary_buffer.h"
#include "Zend/zend_exceptions.h"
#include <string.h>

/* Written only on the process startup/shutdown thread, read-only in requests. */
static HashTable binary_providers;
static zend_bool binary_registry_ready;

static void binary_provider_free(zval *entry)
{
    pefree(Z_PTR_P(entry), 1);
}

void psampler_binary_registry_init(void)
{
    zend_hash_init(&binary_providers, 4, NULL, binary_provider_free, 1);
    binary_registry_ready = 1;
}

void psampler_binary_registry_shutdown(void)
{
    if (binary_registry_ready) {
        binary_registry_ready = 0;
        zend_hash_destroy(&binary_providers);
    }
}

PSAMPLER_BINARY_API uint32_t psampler_binary_api_version(void)
{
    return PSAMPLER_BINARY_API_VERSION;
}

PSAMPLER_BINARY_API zend_bool psampler_binary_register_provider(
    const psampler_binary_provider *provider)
{
    psampler_binary_provider *copy;
    zend_string *name;

    if (!binary_registry_ready || !php_during_module_startup()
        || provider == NULL || provider->api_version != PSAMPLER_BINARY_API_VERSION
        || provider->struct_size != sizeof(*provider) || provider->source_ce == NULL
        || provider->source_ce->type != ZEND_INTERNAL_CLASS
        || provider->acquire == NULL || provider->release == NULL) {
        return 0;
    }
    name = provider->source_ce->name;
    if (zend_hash_exists(&binary_providers, name)) {
        return 0;
    }
    copy = pemalloc(sizeof(*copy), 1);
    *copy = *provider;
    if (zend_hash_str_add_ptr(&binary_providers, ZSTR_VAL(name), ZSTR_LEN(name), copy) == NULL) {
        pefree(copy, 1);
        return 0;
    }
    return 1;
}

PSAMPLER_BINARY_API zend_bool psampler_binary_unregister_provider(zend_class_entry *source_ce)
{
    psampler_binary_provider *entry;

    if (!binary_registry_ready || source_ce == NULL
        || (!php_during_module_startup() && !php_during_module_shutdown())) {
        return 0;
    }
    entry = zend_hash_find_ptr(&binary_providers, source_ce->name);
    if (entry == NULL || entry->source_ce != source_ce) {
        return 0;
    }
    return zend_hash_del(&binary_providers, source_ce->name) == SUCCESS;
}

static const psampler_binary_provider *binary_provider_for(zval *source)
{
    psampler_binary_provider *entry;

    if (source == NULL || !binary_registry_ready) {
        return NULL;
    }
    ZVAL_DEREF(source);
    if (Z_TYPE_P(source) != IS_OBJECT) {
        return NULL;
    }
    entry = zend_hash_find_ptr(&binary_providers, Z_OBJCE_P(source)->name);
    return entry != NULL && entry->source_ce == Z_OBJCE_P(source) ? entry : NULL;
}

PSAMPLER_BINARY_API zend_bool psampler_binary_source_supported(zval *source)
{
    return binary_provider_for(source) != NULL;
}

static zend_bool binary_view_valid(const psampler_binary_view *view, zend_object *source)
{
    size_t first = view->spans[0].length;
    size_t second = view->spans[1].length;
    size_t maximum = (size_t) ZEND_LONG_MAX;

    return view->source == source && view->capacity <= maximum
        && first <= view->capacity && second <= view->capacity - first
        && (first == 0 || view->spans[0].data != NULL)
        && (second == 0 || view->spans[1].data != NULL);
}

PSAMPLER_BINARY_API zend_result psampler_binary_with_view(zval *source,
    psampler_binary_read_handler handler, void *context)
{
    const psampler_binary_provider *provider;
    psampler_binary_view view = {0};
    zend_object *object;
    zend_result result;

    if (EG(exception)) {
        return FAILURE;
    }
    if (handler == NULL) {
        zend_value_error("Native binary read handler must not be NULL");
        return FAILURE;
    }
    provider = binary_provider_for(source);
    if (provider == NULL) {
        zend_value_error("Object has no registered native binary buffer provider");
        return FAILURE;
    }
    ZVAL_DEREF(source);
    object = Z_OBJ_P(source);
    GC_ADDREF(object);
    /* Acquisition may bail out (for example while reporting an error). No
     * successful view exists yet, but our source reference still needs undoing. */
    zend_try {
        result = provider->acquire(object, &view);
    } zend_catch {
        OBJ_RELEASE(object);
        zend_bailout();
    } zend_end_try();
    if (result != SUCCESS) {
        OBJ_RELEASE(object);
        if (!EG(exception)) {
            zend_throw_error(NULL, "Native binary provider acquisition failed without an exception");
        }
        return FAILURE;
    }
    if (EG(exception) || !binary_view_valid(&view, object)) {
        provider->release(object, &view);
        OBJ_RELEASE(object);
        if (!EG(exception)) {
            zend_value_error("Native binary provider returned an invalid view");
        }
        return FAILURE;
    }

    /* The descriptor/object/provider are established before setjmp and never
     * modified afterwards. Only result is modified, and is not read on bailout. */
    zend_try {
        result = handler(&view, context);
    } zend_catch {
        provider->release(object, &view);
        OBJ_RELEASE(object);
        zend_bailout();
    } zend_end_try();

    provider->release(object, &view);
    OBJ_RELEASE(object);
    if (result != SUCCESS || EG(exception)) {
        if (!EG(exception)) {
            zend_throw_error(NULL, "Native binary reader failed without an exception");
        }
        return FAILURE;
    }
    return SUCCESS;
}

typedef struct {
    size_t offset;
    size_t length;
    zend_bool all_remaining;
    zval *result;
} binary_slice_context;

static zend_result binary_slice_view(const psampler_binary_view *view, void *opaque)
{
    binary_slice_context *context = opaque;
    /* with_view has validated both spans against capacity <= ZEND_LONG_MAX. */
    size_t total = view->spans[0].length + view->spans[1].length;
    size_t offset = context->offset, length, remaining;
    unsigned int i;
    char *destination;
    zend_string *result;

    if (offset > total) {
        zend_argument_value_error(2, "must not exceed the native source length");
        return FAILURE;
    }
    length = context->all_remaining ? total - offset : context->length;
    if (length > total - offset) {
        zend_argument_value_error(3, "must not exceed the bytes remaining after offset");
        return FAILURE;
    }
    if (length > ZSTR_MAX_LEN) {
        zend_argument_value_error(3, "exceeds the maximum PHP string length");
        return FAILURE;
    }
    if (length == 0) {
        ZVAL_EMPTY_STRING(context->result);
        return SUCCESS;
    }

    /* Allocation is inside with_view's bailout guard. After allocation, only
     * infallible copies remain; publish ownership directly to the PHP return. */
    result = zend_string_alloc(length, 0);
    ZVAL_STR(context->result, result);
    destination = ZSTR_VAL(result);
    remaining = length;
    for (i = 0; i < 2 && remaining != 0; ++i) {
        size_t available = view->spans[i].length;
        size_t count;
        if (offset >= available) {
            offset -= available;
            continue;
        }
        count = available - offset;
        if (count > remaining) count = remaining;
        memcpy(destination, view->spans[i].data + offset, count);
        destination += count;
        remaining -= count;
        offset = 0;
    }
    ZSTR_VAL(result)[length] = '\0';
    return SUCCESS;
}

PHP_FUNCTION(psampler_binary_slice)
{
    zval *source;
    zend_long offset = 0, length = 0;
    zend_bool length_is_null = 1;
    binary_slice_context context;

    ZEND_PARSE_PARAMETERS_START(1, 3)
        Z_PARAM_OBJECT(source)
        Z_PARAM_OPTIONAL
        Z_PARAM_LONG(offset)
        Z_PARAM_LONG_OR_NULL(length, length_is_null)
    ZEND_PARSE_PARAMETERS_END();

    if (offset < 0) {
        zend_argument_value_error(2, "must be greater than or equal to 0");
        RETURN_THROWS();
    }
    if (!length_is_null && length < 0) {
        zend_argument_value_error(3, "must be greater than or equal to 0 or null");
        RETURN_THROWS();
    }
    context.offset = (size_t) offset;
    context.length = (size_t) length;
    context.all_remaining = length_is_null;
    context.result = return_value;
    if (psampler_binary_with_view(source, binary_slice_view, &context) != SUCCESS) {
        /* Keep the PHP return empty if the existing reader reports failure. */
        zval_ptr_dtor(return_value);
        ZVAL_UNDEF(return_value);
        RETURN_THROWS();
    }
}


/* Experimental RTP hydration. The borrowed view never escapes this callback.
 * Strings are owned before invoking any target property handlers. */
typedef struct {
    size_t length;
    zend_bool strict;
    zend_long fields[9];
    zval header, payload, raw;
} rtp_parse_context;

static unsigned int rtp_byte(const psampler_binary_view *v, size_t i)
{
    return i < v->spans[0].length ? v->spans[0].data[i]
        : v->spans[1].data[i - v->spans[0].length];
}

static uint32_t rtp_uint(const psampler_binary_view *v, size_t i, size_t n)
{
    uint32_t value = 0;
    while (n--) value = (value << 8) | rtp_byte(v, i++);
    return value;
}

static zend_result rtp_copy(const psampler_binary_view *v, size_t offset, size_t length, zval *result)
{
    binary_slice_context slice = {0};
    slice.offset = offset;
    slice.length = length;
    slice.result = result;
    return binary_slice_view(v, &slice);
}

static zend_result rtp_parse_view(const psampler_binary_view *v, void *opaque)
{
    rtp_parse_context *c = opaque;
    size_t n = c->length, offset, payload_length;
    unsigned int first, second, padding_length;
    if (n > v->spans[0].length + v->spans[1].length) {
        zend_argument_value_error(2, "exceeds the native source length");
        return FAILURE;
    }
    if (n < 12) goto malformed;
    first = rtp_byte(v, 0);
    second = rtp_byte(v, 1);
    c->fields[0] = first >> 6;
    c->fields[1] = (first >> 5) & 1;
    c->fields[2] = (first >> 4) & 1;
    c->fields[3] = first & 15;
    c->fields[4] = second >> 7;
    c->fields[5] = second & 127;
    c->fields[6] = rtp_uint(v, 2, 2);
    c->fields[7] = rtp_uint(v, 4, 4);
    c->fields[8] = rtp_uint(v, 8, 4);
    if (c->strict && c->fields[0] != 2) goto malformed;
    offset = 12 + 4 * c->fields[3];
    if (offset > n) goto malformed;
    if (c->fields[2]) {
        size_t extension_length;
        if (n - offset < 4) goto malformed;
        extension_length = 4 * (size_t) rtp_uint(v, offset + 2, 2);
        offset += 4;
        if (extension_length > n - offset) goto malformed;
        offset += extension_length;
    }
    payload_length = n - offset;
    if (c->strict && c->fields[1]) {
        if (!payload_length) goto malformed;
        padding_length = rtp_byte(v, n - 1);
        if (!padding_length || padding_length > payload_length) goto malformed;
        payload_length -= padding_length;
    }
    if (rtp_copy(v, 0, offset, &c->header) != SUCCESS) return FAILURE;
    return rtp_copy(v, offset, payload_length, &c->payload);
malformed:
    if (c->strict) {
        zend_value_error("Malformed RTP packet (version, header, extension or padding)");
        return FAILURE;
    }
    /* Compatibility with rtpc: short packets keep defaults, other malformed
     * packets retain the parsed fixed fields and the entire raw packet. */
    if (n >= 12) return rtp_copy(v, 0, n, &c->raw);
    return SUCCESS;
}

PHP_FUNCTION(psampler_rtp_parse)
{
    zval *source, *target;
    zend_long length;
    zend_bool strict = 1;
    rtp_parse_context context = {0};
    static const char *names[] = {"version", "padding", "extension", "cc", "marker",
        "payloadType", "sequence", "timestamp", "ssrc"};
    ZEND_PARSE_PARAMETERS_START(3, 4)
        Z_PARAM_OBJECT(source)
        Z_PARAM_LONG(length)
        Z_PARAM_OBJECT(target)
        Z_PARAM_OPTIONAL
        Z_PARAM_BOOL(strict)
    ZEND_PARSE_PARAMETERS_END();
    if (length < 0) {
        zend_argument_value_error(2, "must be greater than or equal to 0");
        RETURN_THROWS();
    }
    context.length = (size_t) length;
    context.strict = strict;
    ZVAL_NULL(&context.header);
    ZVAL_EMPTY_STRING(&context.payload);
    ZVAL_EMPTY_STRING(&context.raw);
    if (psampler_binary_with_view(source, rtp_parse_view, &context) == SUCCESS) {
        if (length >= 12) {
            for (size_t i = 0; i < 9 && !EG(exception); i++) {
                zend_update_property_long(Z_OBJCE_P(target), Z_OBJ_P(target), names[i], strlen(names[i]), context.fields[i]);
            }
        }
        if (!EG(exception)) zend_update_property(Z_OBJCE_P(target), Z_OBJ_P(target), ZEND_STRL("payloadRaw"), &context.payload);
        if (!EG(exception) && Z_TYPE(context.header) == IS_NULL)
            zend_update_property(Z_OBJCE_P(target), Z_OBJ_P(target), ZEND_STRL("rawPacket"), &context.raw);
    }
    zval_ptr_dtor(&context.payload);
    zval_ptr_dtor(&context.raw);
    if (EG(exception)) {
        zval_ptr_dtor(&context.header);
        RETURN_THROWS();
    }
    RETURN_COPY_VALUE(&context.header);
}
