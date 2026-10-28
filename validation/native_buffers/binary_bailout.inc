<?php
require dirname(__DIR__,2).'/tests/native_swoole_buffer/common.inc';
$native = new PsamplerBinaryTestBuffer("\0ok");
$dst = new ByteBuffer(4);$dst->append('keep');
register_shutdown_function(function () use ($native,$dst) {
 ensure($native->stats()===[1,1,false]);
 $native->__construct('after'); $dst->clear();$dst->appendBuffer($native);
 ensure($dst->pop(5)==='after');echo "binary bailout guard/release PASS\n";
});
psampler_binary_test_read($native, function () { trigger_error('binary isolated bailout',E_USER_ERROR); });
