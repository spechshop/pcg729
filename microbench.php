<?php
$t=microtime(true);
$s=str_repeat("\x01\x02",960);

$n=0;
for($j=0;$j<100000;$j++){
    for($i=0,$l=strlen($s);$i<$l;$i+=2){
        $x=substr($s,$i,2);
        $n+=strlen($x);
    }
}
echo microtime(true)-$t," ",$n,PHP_EOL;