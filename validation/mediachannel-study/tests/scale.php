<?php
ini_set('memory_limit','1024M');$root=realpath($argv[1]);chdir($root);require $root.'/plugins/autoloader.php';
use libspech\Rtp\MediaChannel;use Swoole\Coroutine as Co;
function sec($r){return $r['ru_utime.tv_sec']+$r['ru_utime.tv_usec']/1e6+$r['ru_stime.tv_sec']+$r['ru_stime.tv_usec']/1e6;}
Co\run(function(){foreach([1,4,16] as $n){$before=count(glob('/proc/self/fd/*'));$mem=memory_get_usage();$all=[];for($i=0;$i<$n;$i++){$s=new SocketMutable(AF_INET,SOCK_DGRAM,SOL_UDP);$s->bind('127.0.0.1',0);$m=new MediaChannel($s,'idle-scale');$m->requireNativeRtpParser();$m->start();$all[]=$m;}$alloc=memory_get_usage()-$mem;$r=getrusage();Co::sleep(.65);$end=getrusage();foreach($all as $m)$m->close();Co::sleep(.02);unset($m,$s,$all);gc_collect_cycles();echo json_encode(['idle_channels'=>$n,'cpu_s'=>sec($end)-sec($r),'wall_s'=>.65,'nvcsw'=>$end['ru_nvcsw']-$r['ru_nvcsw'],'zend_alloc_bytes'=>$alloc,'fds_before'=>$before,'fds_after'=>count(glob('/proc/self/fd/*'))])."\n";}});
