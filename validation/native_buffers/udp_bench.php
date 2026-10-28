<?php
declare(strict_types=1);
[$script,$method,$size,$count,$batch,$pps,$senderCpu,$validate] = $argv;
$size=(int)$size;$count=(int)$count;$batch=(int)$batch;$pps=(int)$pps;$validate=(bool)(int)$validate;
function cpuTime(): array { $r=getrusage();return [$r['ru_utime.tv_sec']+$r['ru_utime.tv_usec']/1e6,$r['ru_stime.tv_sec']+$r['ru_stime.tv_usec']/1e6]; }
function statusMem():array { $s=file_get_contents('/proc/self/status');preg_match('/^VmRSS:\s+(\d+)/m',$s,$r);preg_match('/^VmHWM:\s+(\d+)/m',$s,$h);return ['rss_kib'=>(int)$r[1],'hwm_kib'=>(int)$h[1]]; }
Swoole\Coroutine\run(function() use($method,$size,$count,$batch,$pps,$senderCpu,$validate) {
 $rx=new Swoole\Coroutine\Socket(AF_INET,SOCK_DGRAM,0);
 if(!$rx->bind('127.0.0.1',0))throw new RuntimeException('bind');
 $rx->setOption(SOL_SOCKET,SO_RCVBUF,4*1024*1024);
 $buffer=new Swoole\Buffer(2048);$peer=null;
 $command=['taskset','-c',$senderCpu,__DIR__.'/udp_sender',(string)$rx->getsockname()['port'],(string)$size,$senderCpu,(string)$pps];
 $proc=proc_open($command,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
 if(!is_resource($proc))throw new RuntimeException('sender');
 $warm=$pps?0:5000;$received=0;$errors=0;$bad=0;
 $expected='';if($validate)for($i=0;$i<$size;++$i)$expected.=chr(($i*37+11)&255);
 if($validate && $size===172)$expected=hex2bin('80001234000000a001020304').substr($expected,12);
 $credit=pack('V',$batch);
 // Warmup uses the same sender and bounded credits; CPU/timer reset below.
 for($offset=0;$offset<$warm;$offset+=$n) {
  $n=min($batch,$warm-$offset);fwrite($pipes[0],$n===$batch?$credit:pack('V',$n));
  for($i=0;$i<$n;++$i) { $ret=$method==='recvfrom'?$rx->recvfrom($peer,2):$rx->recvfromInto($buffer,$peer,2); if($ret===false)throw new RuntimeException('warmup receive'); }
 }
 fwrite($pipes[0],pack('V',0xffffffff));
 unset($ret);$memBefore=memory_get_usage();$realBefore=memory_get_usage(true);$rssBefore=statusMem();
 memory_reset_peak_usage();$cpu0=cpuTime();$t0=hrtime(true);
 for($offset=0;$offset<$count;$offset+=$n) {
  $n=min($batch,$count-$offset);fwrite($pipes[0],$n===$batch?$credit:pack('V',$n));
  for($i=0;$i<$n;++$i) {
   $ret=$method==='recvfrom'?$rx->recvfrom($peer,2):$rx->recvfromInto($buffer,$peer,2);
   if($ret===false){++$errors;break 2;}
   ++$received;
   if($validate) {
    $s=$method==='recvfrom'?$ret:$buffer->toString();
    if($s!==$expected || $peer['address']!=='127.0.0.1' || $peer['port']<=0)++$bad;
   }
  }
 }
 $wall=(hrtime(true)-$t0)/1e9;$cpu1=cpuTime();$memAfter=memory_get_usage();$realAfter=memory_get_usage(true);$peak=memory_get_peak_usage();$peakReal=memory_get_peak_usage(true);$rssAfter=statusMem();
 fwrite($pipes[0],pack('V',0));fclose($pipes[0]);$sender=stream_get_contents($pipes[1]);$stderr=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($proc);
 $send=json_decode($sender,true,512,JSON_THROW_ON_ERROR);$cpu=($cpu1[0]-$cpu0[0])+($cpu1[1]-$cpu0[1]);
 $rx->close();
 echo json_encode(['kind'=>'udp','method'=>$method,'size'=>$size,'requested'=>$count,'received'=>$received,'errors'=>$errors,'integrity_errors'=>$bad,'validation'=>$validate,'warmup'=>$warm,'batch'=>$batch,'rate_limit_pps'=>$pps,'wall_s'=>$wall,'pps'=>$received/$wall,'us_packet'=>$wall*1e6/max(1,$received),'cpu_s'=>$cpu,'cpu_user_s'=>$cpu1[0]-$cpu0[0],'cpu_sys_s'=>$cpu1[1]-$cpu0[1],'cpu_percent'=>$cpu/$wall*100,'cpu_s_million'=>$cpu*1e6/max(1,$received),'php_before'=>$memBefore,'php_after'=>$memAfter,'php_real_before'=>$realBefore,'php_real_after'=>$realAfter,'php_peak'=>$peak,'php_peak_real'=>$peakReal,'rss_before'=>$rssBefore,'rss_after'=>$rssAfter,'sender'=>$send,'sender_exit'=>$exit,'sender_stderr'=>$stderr,'loss'=>$send['sent']-$received],JSON_THROW_ON_ERROR),"\n";
 if($errors||$bad||$exit||$send['sent']!==$received)exit(1);
});
