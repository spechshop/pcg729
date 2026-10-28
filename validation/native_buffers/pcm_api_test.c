/* Isolated consumer of psampler_native.h. Never linked into production PHP. */
#include "php.h"
#include "Zend/zend_exceptions.h"
#include "ext/psampler/psampler_native.h"
static zend_result inspect_pcm(psampler_pcm_buffer *pcm, uint32_t argc, zval *argv, zval *out) {
    size_t n = psampler_pcm_size(pcm), i; zend_long sum = 0;
    const unsigned char *p = psampler_pcm_data(pcm);
    zval invalid;
    ZVAL_LONG(&invalid,42);
    if (psampler_pcm_from_zval(NULL) != NULL || psampler_pcm_from_zval(&invalid) != NULL
        || psampler_register_pcm_operation("validation.request", inspect_pcm)
        || psampler_unregister_pcm_operation("validation.inspect")) {
        zend_throw_error(NULL,"PCM API request contract failed"); return FAILURE;
    }
    if (argc > 1) {
        zval uninitialized, reference;
        if (psampler_pcm_from_zval(&argv[1]) != pcm) {
            zend_throw_error(NULL,"PCM identity failed");return FAILURE;
        }
        ZVAL_COPY(&reference,&argv[1]); ZVAL_MAKE_REF(&reference);
        if (psampler_pcm_from_zval(&reference) != pcm) {
            zval_ptr_dtor(&reference);zend_throw_error(NULL,"PCM reference failed");return FAILURE;
        }
        zval_ptr_dtor(&reference);
        object_init_ex(&uninitialized,Z_OBJCE(argv[1]));
        if (psampler_pcm_from_zval(&uninitialized) != NULL) {
            zval_ptr_dtor(&uninitialized);zend_throw_error(NULL,"PCM initialization failed");return FAILURE;
        }
        zval_ptr_dtor(&uninitialized);
    }
    if (argc && Z_TYPE(argv[0]) != IS_NULL) {
        zval rv; ZVAL_UNDEF(&rv);
        if (call_user_function(EG(function_table), NULL, &argv[0], &rv, 0, NULL) != SUCCESS) {
            zend_throw_error(NULL,"PCM test hook failed");
        }
        zval_ptr_dtor(&rv);
        if (EG(exception)) return FAILURE;
    }
    for(i=0; i<n; ++i) sum+=p[i];
    array_init(out); add_assoc_long(out,"size",n);add_assoc_long(out,"checksum",sum);
    add_assoc_long(out,"rate",psampler_pcm_sample_rate(pcm));
    add_assoc_long(out,"channels",psampler_pcm_channels(pcm));
    return SUCCESS;
}
PHP_MINIT_FUNCTION(pcm_api_test) {
    if (psampler_native_api_version()!=1 || psampler_register_pcm_operation(NULL,inspect_pcm)
        || psampler_register_pcm_operation("",inspect_pcm)
        || psampler_register_pcm_operation("validation.null",NULL)) return FAILURE;
    if (!psampler_register_pcm_operation("validation.inspect",inspect_pcm)) return FAILURE;
    if (psampler_register_pcm_operation("validation.inspect",inspect_pcm)) return FAILURE;
    return SUCCESS;
}
PHP_MSHUTDOWN_FUNCTION(pcm_api_test) {return psampler_unregister_pcm_operation("validation.inspect")?SUCCESS:FAILURE;}
static const zend_module_dep deps[]={ZEND_MOD_REQUIRED("psampler") ZEND_MOD_END};
zend_module_entry pcm_api_test_module_entry={STANDARD_MODULE_HEADER_EX,NULL,deps,"pcm_api_test",NULL,
 PHP_MINIT(pcm_api_test),PHP_MSHUTDOWN(pcm_api_test),NULL,NULL,NULL,"0.0.0",STANDARD_MODULE_PROPERTIES};
