<?php
declare(strict_types=1);
require __DIR__.'/../../../libspech/plugins/Utils/sip/rtpc.php';
use libspech\Rtp\rtpc;
function ck($b,$s){if(!$b) throw new RuntimeException($s);}
$b=new ByteBuffer(256); $bytes=pack('CCnNN',128,8,42,160,123).'audio'; $b->append($bytes);
$p=new rtpc(null); $r=&$p->sequence; $payload=&$p->payloadRaw;
psampler_rtp_parse($b,strlen($bytes),$p,false);
ck($r===42 && $payload==='audio','existing references');
class Hooks {
 public array $seen=[];
 public int $version { set { $this->seen[]=$value; $this->version=$value; } }
 public function __set($k,$v){$this->seen[$k]=$v;}
}
$h=new Hooks; psampler_rtp_parse($b,strlen($bytes),$h,false);
ck($h->seen[0]===2 && $h->seen['payloadRaw']==='audio','hooks and magic setter');
class Reject { public array $version=[]; }
try { psampler_rtp_parse($b,strlen($bytes),new Reject,false); throw new RuntimeException('missing type error'); } catch(TypeError){}
class Reenter {
 public function __construct(public ByteBuffer $source){}
 public function __set($k,$v) {
  if($k==='version') { $p=new rtpc(null); psampler_rtp_parse($this->source,17,$p,false); ck($p->sequence===42,'reentry'); $this->source->clear(); }
  if($k==='payloadRaw') ck($v==='audio','owned payload after setter');
 }
}
psampler_rtp_parse($b,strlen($bytes),new Reenter($b),false);
mt_srand(729);
for($i=0;$i<3000;$i++){
 $n=mt_rand(0,256); $s=''; for($j=0;$j<$n;$j++) $s.=chr(mt_rand(0,255));
 $b->clear(); $b->append($s);
 ck(serialize(rtpc::fromNativeBuffer($b,$n))===serialize(new rtpc($s)),"fuzz $i");
}
echo "References, hooks, types, reentry, 3000 deterministic malformed packets OK\n";
