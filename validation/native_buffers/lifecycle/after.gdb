set pagination off
set confirm off
set breakpoint pending on
set debuginfod enabled off
set $releases = 0
set $frees = 0
break /home/lotus/CLionProjects/pcg729/source/php-src/ext/swoole/ext-src/swoole_socket_coro.cc:1870
commands
silent
set $releases = $releases + 1
printf "NATIVE RELEASE receiving=%d buffer_refs=%u socket_refs=%u\n", buffer->receiving, buffer->std.gc.refcount, socket_object->gc.refcount
bt 4
continue
end
break /home/lotus/CLionProjects/pcg729/source/php-src/ext/swoole/ext-src/swoole_socket_coro.cc:1873
commands
silent
printf "FLAG RESET receiving=%d\n", buffer->receiving
continue
end
break 'buffer_free_object(_zend_object*)'
commands
silent
set $frees = $frees + 1
printf "BUFFER FREE EVENT %d\n", $frees
bt 4
continue
end
break _zend_bailout
commands
silent
printf "BAILOUT PROPAGATION\n"
bt 3
continue
end
break /home/lotus/CLionProjects/pcg729/source/php-src/ext/swoole/ext-src/swoole_socket_coro.cc:1928
commands
silent
printf "AFTER RELEASE receiving=%d buffer_refs=%u socket_refs=%u\n", guard.buffer->receiving, guard.buffer->std.gc.refcount, guard.socket_object->gc.refcount
continue
end
run
printf "FINAL RELEASES=%d BUFFER_FREES=%d\n", $releases, $frees
