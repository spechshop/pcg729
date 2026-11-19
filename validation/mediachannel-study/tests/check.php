<?php
ini_set('memory_limit','1024M');
$root=realpath($argv[1]); chdir($root); require $root.'/plugins/autoloader.php';
use libspech\Rtp\MediaChannel;
use libspech\Rtp\rtpc;
use Swoole\Coroutine as Co;
 $exitStatus=0;
$mode=$argv[2]??'dtmf';
Co\run(function()use($mode,$root,&$exitStatus){
 $s=new SocketMutable(AF_INET,SOCK_DGRAM,SOL_UDP);$s->bind('127.0.0.1',0);$m=new MediaChannel($s,'study');
 $result=['mode'=>$mode,'source'=>(new ReflectionClass($m))->getFileName()];
 if($mode==='dtmf'){
  $calls=[];$m->onDtmf(function(...$args)use(&$calls){$calls[]=$args[0];});
  $method=new ReflectionMethod($m,'processDtmf');
  foreach([['single',[10]],['triple',[20,21,22]],['wrap',[65535,0,1]]] as $k=>[$label,$seqs]){
   $before=count($calls);foreach($seqs as $seq){$p=new rtpc(pack('CCnNNCCn',128,101,$seq,10000+$k*2000,42,5,138,1280));$method->invoke($m,$p,['address'=>'127.0.0.1','port'=>9],static function(){});}
   $result[$label]=count($calls)-$before; Co::sleep(.11);
  }
  $result['pass']=$result['single']===1&&$result['triple']===1&&$result['wrap']===1;
 }elseif($mode==='idle'){
  $m->setNativeRtpReceive(false);$m->connectTimeout=0;$fired=0;$m->packetOnTimeout(function()use(&$fired){$fired++;});$m->start();Co::sleep(.45);$result['timeout_callbacks']=$fired;$result['pass']=$fired===1;
 }elseif($mode==='invalid'){
  $m->setReceiveOnlyMode(true);$seen=0;$m->onReceive(function()use(&$seen){$seen++;});$m->start();
  $tx=new SocketMutable(AF_INET,SOCK_DGRAM,SOL_UDP);$tx->sendto('127.0.0.1',$s->getsockname()['port'],'x');Co::sleep(.02);$result['callbacks']=$seen;$result['members']=count($m->members);$result['pass']=$seen===0&&count($m->members)===0;$tx->close();
 }elseif($mode==='reentrant'){
  $m->onReceive(function($p,$peer,$ch){$ch->close();});$m->start();$tx=new SocketMutable(AF_INET,SOCK_DGRAM,SOL_UDP);$tx->sendto('127.0.0.1',$s->getsockname()['port'],pack('CCnNN',128,8,1,100,2).str_repeat(chr(213),160));Co::sleep(.02);$result['pass']=!$m->active;$tx->close();
 }
 $m->close();Co::sleep(.01);if(!$s->isClosed())$s->destroy(); $exitStatus=$result["pass"]?0:1; echo json_encode($result)."\n";
});

exit($exitStatus);
