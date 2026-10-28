<?php
declare(strict_types=1);
require __DIR__.'/bench_helpers.php';
[$script,$method,$size,$count,$mode]=$argv;$size=(int)$size;$count=(int)$count;
$seed='';for($i=0;$i<$size;++$i)$seed.=chr(($i*37+11)&255);
$native=new PsamplerBinaryTestBuffer($seed);
$block=4096;
// Both sources are fully constructed before timing. The native method still copies.
$dest=new ByteBuffer($mode==='growth'?8:$size*$block);
$run=function(int $n) use($method,$mode,$size,$seed,$native,$block,&$dest):array {
 if($mode==='steady') {
  if($method==='append')for($i=0;$i<$n;++$i){$dest->append($seed);$dest->discard($size);}
  else for($i=0;$i<$n;++$i){$dest->appendBuffer($native);$dest->discard($size);}
 } else {
  for($off=0;$off<$n;$off+=$batch) {
   $batch=min($block,$n-$off);
   if($mode==='growth')$dest=new ByteBuffer(8);
   if($method==='append')for($i=0;$i<$batch;++$i)$dest->append($seed);
   else for($i=0;$i<$batch;++$i)$dest->appendBuffer($native);
   $dest->discard($batch*$size);
  }
 }
 return [$dest->length(),$dest->capacity()];
};
$run(10000);$m0=memory_get_usage();$rss0=statusMem();memory_reset_peak_usage();$c0=cpuTime();$t0=hrtime(true);
[$remaining,$capacity]=$run($count);
$wall=(hrtime(true)-$t0)/1e9;$c1=cpuTime();$m1=memory_get_usage();$peak=memory_get_peak_usage();$peakReal=memory_get_peak_usage(true);$rss1=statusMem();$cpu=$c1-$c0;
// Integrity separate from timing, including slices, wraparound and growth.
$dst=new ByteBuffer(8);$dst->append('123456');$dst->discard(5);$dst->appendBuffer($native);if($dst->pop($size+1)!=='6'.$seed)throw new RuntimeException('integrity');
if($native->stats()[0]!==$native->stats()[1]||$remaining)throw new RuntimeException('lifecycle');
echo json_encode(['kind'=>'bytebuffer','method'=>$method,'mode'=>$mode,'size'=>$size,'count'=>$count,'wall_s'=>$wall,'ops_s'=>$count/$wall,'us_op'=>$wall*1e6/$count,'cpu_s'=>$cpu,'cpu_s_million'=>$cpu*1e6/$count,'cpu_percent'=>$cpu/$wall*100,'mib_s'=>$size*$count/$wall/1048576,'php_before'=>$m0,'php_after'=>$m1,'php_peak'=>$peak,'php_peak_real'=>$peakReal,'rss_before'=>$rss0,'rss_after'=>$rss1,'final_capacity'=>$capacity,'native_stats'=>$native->stats()],JSON_THROW_ON_ERROR),"\n";
