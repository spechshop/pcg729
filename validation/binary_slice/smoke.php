<?php
$checks = [
    'slice_function'=>function_exists('psampler_binary_slice'),
    'swoole_buffer'=>class_exists('Swoole\\Buffer'),
    'recvfrom_into'=>method_exists('Swoole\\Coroutine\\Socket','recvfromInto'),
    'zts'=>(bool)PHP_ZTS,
];
$source=new ByteBuffer(64); $source->append("HEADER\0PAYLOAD");
$checks['header']=psampler_binary_slice($source,0,6)==='HEADER';
$checks['payload']=psampler_binary_slice($source,6,8)==="\0PAYLOAD";
$checks['bridge']=ByteBuffer::supportsNativeBuffer(new Swoole\Buffer(64));
echo json_encode($checks,JSON_PRETTY_PRINT),"\n";
if (in_array(false,$checks,true)) exit(1);
