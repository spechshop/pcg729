<?php
ini_set('memory_limit','1024M');$root=realpath($argv[1]);chdir($root);require $root.'/plugins/autoloader.php';$r=new ReflectionClass('libspech\\Rtp\\MediaChannel');$out=[];foreach($r->getMethods() as $m)$out[$m->name]=[$m->getStartLine(),$m->getEndLine()];echo json_encode($out,JSON_PRETTY_PRINT);
