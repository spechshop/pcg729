<?php
function cpuTime():float {$r=getrusage();return $r['ru_utime.tv_sec']+$r['ru_utime.tv_usec']/1e6+$r['ru_stime.tv_sec']+$r['ru_stime.tv_usec']/1e6;}
function statusMem():array {$s=file_get_contents('/proc/self/status');preg_match('/^VmRSS:\s+(\d+)/m',$s,$r);preg_match('/^VmHWM:\s+(\d+)/m',$s,$h);return ['rss_kib'=>(int)$r[1],'hwm_kib'=>(int)$h[1]];}
