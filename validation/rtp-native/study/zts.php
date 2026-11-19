<?php
if (!class_exists('Swoole\\Thread')) { echo "SKIP Swoole Thread unavailable\n"; exit; }
if (Swoole\Thread::getArguments()===null) {
 $threads=[]; for($i=0;$i<4;$i++) $threads[]=new Swoole\Thread(__FILE__, $i);
 foreach($threads as $t) { $t->join(); if($t->getExitStatus()!==0) throw new RuntimeException('worker failed'); }
 echo "Joined four threads; inspect worker PASS lines\n";
} else {
 require __DIR__.'/contracts.php';
 echo "Worker PASS\n";
}
