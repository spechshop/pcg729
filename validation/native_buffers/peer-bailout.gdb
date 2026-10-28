set pagination off
set confirm off
set breakpoint pending on
break /home/lotus/CLionProjects/pcg729/source/php-src/ext/swoole/ext-src/swoole_socket_coro.cc:1902
commands
silent
printf "PEER_ASSIGN before bailout: length=%lu capacity=%lu receiving=%d refcount=%u\n", buffer->length, buffer->capacity, buffer->receiving, buffer->std.gc.refcount
bt 6
continue
end
break /home/lotus/CLionProjects/pcg729/source/php-src/ext/swoole/ext-src/swoole_socket_coro.cc:1869
commands
silent
printf "RECEIVE_GUARD destructor reached\n"
continue
end
break _zend_bailout
commands
silent
printf "ZEND BAILOUT\n"
bt 12
continue
end
run
