<?php
if(Swoole\Thread::getArguments()===null){
 $t=[];for($i=0;$i<4;++$i)$t[]=new Swoole\Thread(__FILE__,$i);
 foreach($t as $thread)$thread->join();echo "4 empty ZTS requests control\n";
}
