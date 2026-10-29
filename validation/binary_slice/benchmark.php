<?php
// Short steady-state range-copy probe; no claim about end-to-end RTP CPU savings.
$b=new ByteBuffer(256); $b->append(str_repeat('h',12).str_repeat('p',160));
$n=200000;
foreach (['slice'=>fn()=>psampler_binary_slice($b,12,160),
          'whole_then_substr'=>fn()=>substr($b->peek(172),12,160)] as $name=>$read) {
    for($i=0;$i<1000;++$i) $read();
    $start=hrtime(true);
    for($i=0;$i<$n;++$i) $value=$read();
    if ($value!==str_repeat('p',160)) throw new RuntimeException('corruption');
    printf("%s: %d iterations, %.1f ns/op\n",$name,$n,(hrtime(true)-$start)/$n);
}
