<?php
register_shutdown_function(function(){echo "coroutine control shutdown\n";});
Swoole\Coroutine\run(function(){trigger_error('isolated coroutine fatal without borrow',E_USER_ERROR);});
