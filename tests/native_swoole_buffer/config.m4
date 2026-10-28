PHP_ARG_ENABLE([swoole_buffer_test], [whether to build the isolated Buffer ABI fixture],
  [AS_HELP_STRING([--enable-swoole-buffer-test], [Build test-only Swoole Buffer reader])], [no])
PHP_ARG_ENABLE([swoole_buffer_test_psampler], [whether to test the optional psampler producer],
  [AS_HELP_STRING([--enable-swoole-buffer-test-psampler], [Require psampler and register a test-only bridge])], [no], [no])

if test "$PHP_SWOOLE_BUFFER_TEST" != "no"; then
  PHP_NEW_EXTENSION([swoole_buffer_test], [swoole_buffer_test.c], [$ext_shared])
  PHP_ADD_EXTENSION_DEP([swoole_buffer_test], [swoole])
  if test "$PHP_SWOOLE_BUFFER_TEST_PSAMPLER" != "no"; then
    AC_DEFINE([SWOOLE_BUFFER_TEST_PSAMPLER], [1], [Enable test-only psampler adapter])
    PHP_ADD_EXTENSION_DEP([swoole_buffer_test], [psampler])
  fi
fi
