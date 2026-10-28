<?php
require dirname(__DIR__, 3).'/tests/native_swoole_buffer/common.inc';
$buffer = new Swoole\Buffer(16);
class ControlPeer { function __destruct() { trigger_error('frame control bailout', E_USER_ERROR); } }
class ControlSocket extends Swoole\Coroutine\Socket {
 function control($buffer, &$peer, float $timeout) { $peer = null; }
}
$receiver = new ControlSocket(AF_INET, SOCK_DGRAM, 0);
$b0 = swoole_buffer_test_refcount($buffer);
$s0 = swoole_buffer_test_refcount($receiver);
register_shutdown_function(function() use($b0,$s0) {
 global $buffer,$receiver;
 printf("frame control: buffer=%d socket=%d\n", swoole_buffer_test_refcount($buffer)-$b0, swoole_buffer_test_refcount($receiver)-$s0);
 $buffer->clear();
 $receiver->close();
 $buffer=null;$receiver=null;
});
$operation = function() { global $buffer,$receiver; $peer=new ControlPeer; $receiver->control($buffer,$peer,.125); };
if (getenv('RECV_COROUTINE') === '1') { Swoole\Coroutine\run($operation); } else {$operation();}
