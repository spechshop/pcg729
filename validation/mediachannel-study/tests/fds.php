<?php
ini_set('memory_limit','1024M');$root=realpath($argv[1]);chdir($root);require $root.'/plugins/autoloader.php';
use libspech\Rtp\MediaChannel;use Swoole\Coroutine as Co;
function snapshot(){ $out=[];foreach(glob('/proc/self/fd/*') as $p){$target=@readlink($p);if($target!==false&&preg_match('/^(socket:|anon_inode:)/',$target))$out[basename($p)]=$target;}return $out; }
Co\run(function(){ $before=snapshot();$s=new SocketMutable(AF_INET,SOCK_DGRAM,SOL_UDP);$s->bind('127.0.0.1',0);$m=new MediaChannel($s,'fd');$m->requireNativeRtpParser();$m->start();Co::sleep(.01);$opened=snapshot();$weak=WeakReference::create($m);$m->close();Co::sleep(.3);$closed=snapshot();$state=["socket_closed"=>$s->isClosed(),"event_closed"=>$m->eventSock->isClosed(),"socket_error"=>$s->errCode];unset($m,$s);gc_collect_cycles();$released=snapshot();echo json_encode(compact('before','opened','closed','released','state')+['object_retained'=>$weak->get()!==null])."\n";});
echo json_encode(["after_reactor"=>snapshot()])."\n";
