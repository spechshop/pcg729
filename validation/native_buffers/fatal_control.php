<?php
$b = new Swoole\Buffer(16);
register_shutdown_function(function()use($b){$b->clear();echo "control shutdown OK\n";});
call_user_func(function(){trigger_error('isolated fatal without native borrow',E_USER_ERROR);});
