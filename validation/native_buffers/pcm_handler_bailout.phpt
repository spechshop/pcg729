--TEST--
PcmBuffer invoke must release mutation guard after handler bailout
--EXTENSIONS--
psampler
pcm_api_test
--INI--
display_errors=1
log_errors=0
error_reporting=24575
--FILE--
<?php require __DIR__.'/pcm_bailout.php'; ?>
--EXPECTF--
%AFatal error: isolated PCM handler bailout in %s on line %d
%APCM bailout cleanup PASS
