<?php
require __DIR__.'/../../../libspech/plugins/Utils/sip/rtpc.php';
use libspech\Rtp\rtpc;
$mode=$argv[1]; $size=(int)($argv[2]??160); $n=60000;
$bytes=pack('CCnNN',128,8,42,160,123).str_repeat('a',$size);
$provider=$argv[3]??'bytebuffer';
if($provider==='swoole'){
 $b=new Swoole\Buffer(strlen($bytes));
 Co\run(function() use($b,$bytes){
  $rx=new Swoole\Coroutine\Socket(AF_INET,SOCK_DGRAM,SOL_UDP);
  $tx=new Swoole\Coroutine\Socket(AF_INET,SOCK_DGRAM,SOL_UDP);
  $rx->bind('127.0.0.1',0); $tx->sendto('127.0.0.1',$rx->getsockname()['port'],$bytes);
  $peer=null; if($rx->recvfromInto($b,$peer,0.2)!==strlen($bytes)) throw new RuntimeException('setup');
  $rx->close(); $tx->close();
 });
} else { $b=new ByteBuffer(strlen($bytes)); $b->append($bytes); }
function cpu(): float { $r=getrusage(); return $r['ru_utime.tv_sec']+$r['ru_utime.tv_usec']/1e6+$r['ru_stime.tv_sec']+$r['ru_stime.tv_usec']/1e6; }
$run=function(int $count) use($mode,$bytes,$b) {
 for($i=0;$i<$count;$i++){
  if($mode==='legacy') $p=new rtpc($bytes);
  elseif($mode==='slice') $p=new rtpc(psampler_binary_slice($b,0,strlen($bytes)));
  else { $p=rtpc::fromNativeBuffer($b,strlen($bytes)); if($mode==='raw') $x=$p->rawPacket; }
 }
};
$run(5000); $m=memory_get_usage(); $c=cpu(); $t=hrtime(true); $run($n); $wall=(hrtime(true)-$t)/$n; $ns=(cpu()-$c)*1e9/$n;
echo json_encode(['provider'=>$provider,'mode'=>$mode,'size'=>$size,'n'=>$n,'cpu_ns'=>$ns,'wall_ns'=>$wall,'ops_s'=>1e9/$ns,'memory_delta'=>memory_get_usage()-$m,'peak'=>memory_get_peak_usage()]),"\n";
