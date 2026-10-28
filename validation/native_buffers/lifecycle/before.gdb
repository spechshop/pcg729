set pagination off
set confirm off
set breakpoint pending on
set debuginfod enabled off
set $destructors = 0
break /home/lotus/CLionProjects/pcg729/validation/native_buffers/lifecycle/baseline/swoole_socket_coro.cc:1895
commands
silent
printf "BEFORE PEER ASSIGN receiving=%d buffer_refs=%u\n", buffer->receiving, buffer->std.gc.refcount
bt 4
continue
end
break /home/lotus/CLionProjects/pcg729/validation/native_buffers/lifecycle/baseline/swoole_socket_coro.cc:1869
commands
silent
set $destructors = $destructors + 1
printf "AUTOMATIC RECEIVE GUARD DESTRUCTOR\n"
continue
end
break _zend_bailout
commands
silent
printf "BAILOUT\n"
bt 3
continue
end
run
printf "AUTOMATIC RECEIVE GUARD DESTRUCTORS=%d\n", $destructors
