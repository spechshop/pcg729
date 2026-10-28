<?php
require dirname(__DIR__,2).'/tests/native_swoole_buffer/common.inc';
$args=Swoole\Thread::getArguments();
if($args===null){
 foreach([new Swoole\Buffer(16),new ByteBuffer(16)]as $obj){
  rejects(fn()=>new Swoole\Thread(__FILE__,$obj),Exception::class);
 }
 echo "Cross-thread transfer of request-local Buffer and ByteBuffer rejected PASS\n";
}else{throw new RuntimeException('unexpected object transfer');}
