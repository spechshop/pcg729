--TEST--
Native PCM v1 registers, inspects bytes, rejects mutations and releases exceptions over 10000 reuse cycles
--EXTENSIONS--
psampler
pcm_api_test
--FILE--
<?php require __DIR__.'/pcm_api.inc'; ?>
--EXPECT--
PCM native v1 registration, invoke, integrity, guards, exceptions and 10000 cycles PASS
