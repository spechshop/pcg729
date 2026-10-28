--TEST--
Native binary consumer releases acquisition after callback bailout
--EXTENSIONS--
psampler
psampler_binary_test
--INI--
display_errors=1
log_errors=0
error_reporting=24575
--FILE--
<?php require __DIR__.'/binary_bailout.inc'; ?>
--EXPECTF--
%AFatal error: binary isolated bailout in %s on line %d
%Abinary bailout guard/release PASS
