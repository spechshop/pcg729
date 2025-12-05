<?php

function decodePcmaToPcm(string $input): string {
    return "";
}

function decodePcmuToPcm(string $input): string {
    return "";
}

function pcmLeToBe(string $input): string {
    return "";
}

class bcg729Channel {
    public function __construct() {
    }

    public function decode(string $input): string {
        return "";
    }

    public function encode(string $input): string {
        return "";
    }

    public function info() {
    }

    public function close() {
    }

}

class opusChannel {
    public function __construct(int $sample_rate, int $channels) {
    }

    public function encode(string $pcm_data, int $pcm_rate): string {
        return "";
    }

    public function decode(string $encoded_data, int $pcm_rate_out): string {
        return "";
    }

    public function resample(string $pcm_data, int $src_rate, int $dst_rate): string {
        return "";
    }

    public function setBitrate(int $value) {
    }

    public function setVBR(bool $enable) {
    }

    public function setComplexity(int $value) {
    }

    public function setDTX(bool $enable) {
    }

    public function setSignalVoice(bool $enable) {
    }

    public function reset() {
    }

    public function enhanceVoiceClarity(string $pcm_data, float $intensity): string {
        return "";
    }

    public function spatialStereoEnhance(string $pcm_data, float $width, float $depth): string {
        return "";
    }

    public function destroy() {
    }

}

class Swoole\Exception {
}

class Swoole\Error {
}

class Swoole\Event {
    public function add(mixed $fd, callable $read_callback = NULL, callable $write_callback = NULL, int $events = 512) {
    }

    public function del(mixed $fd): bool {
        return false;
    }

    public function set(mixed $fd, callable $read_callback = NULL, callable $write_callback = NULL, int $events = 0): bool {
        return false;
    }

    public function isset(mixed $fd, int $events = 1536): bool {
        return false;
    }

    public function dispatch(): bool {
        return false;
    }

    public function defer(callable $callback): bool {
        return false;
    }

    public function cycle(callable $callback, bool $before = false): bool {
        return false;
    }

    public function write(mixed $fd, string $data): bool {
        return false;
    }

    public function wait() {
    }

    public function rshutdown() {
    }

    public function exit() {
    }

}

class Swoole\Atomic {
    public function __construct(int $value = 0) {
    }

    public function add(int $add_value = 1): int {
        return 0;
    }

    public function sub(int $sub_value = 1): int {
        return 0;
    }

    public function get(): int {
        return 0;
    }

    public function set(int $value) {
    }

    public function wait(float $timeout = 1.0): bool {
        return false;
    }

    public function wakeup(int $count = 1): bool {
        return false;
    }

    public function cmpset(int $cmp_value, int $new_value): bool {
        return false;
    }

}

class Swoole\Atomic\Long {
    public function __construct(int $value = 0) {
    }

    public function add(int $add_value = 1): int {
        return 0;
    }

    public function sub(int $sub_value = 1): int {
        return 0;
    }

    public function get(): int {
        return 0;
    }

    public function set(int $value) {
    }

    public function cmpset(int $cmp_value, int $new_value): bool {
        return false;
    }

}

class Swoole\Lock {
    public function __construct(int $type = 3) {
    }

    public function lock(int $operation = 2, float $timeout = -1): bool {
        return false;
    }

    public function unlock(): bool {
        return false;
    }

}

class Swoole\Process {
    public function __construct(callable $callback, bool $redirect_stdin_and_stdout = false, int $pipe_type = 2, bool $enable_coroutine = false) {
    }

    public function __destruct() {
    }

    public function wait(bool $blocking = true) {
    }

    public function signal(int $signal_no, callable $callback = NULL): bool {
        return false;
    }

    public function alarm(int $usec, int $type = 0): bool {
        return false;
    }

    public function kill(int $pid, int $signal_no = 15): bool {
        return false;
    }

    public function daemon(bool $nochdir = true, bool $noclose = true, array $pipes = array (
)): bool {
        return false;
    }

    public function setAffinity(array $cpu_settings): bool {
        return false;
    }

    public function getAffinity(): array {
    }

    public function setPriority(int $which, int $priority, int $who = NULL): bool {
        return false;
    }

    public function getPriority(int $which, int $who = NULL) {
    }

    public function set(array $settings) {
    }

    public function setTimeout(float $seconds): bool {
        return false;
    }

    public function setBlocking(bool $blocking): bool {
        return false;
    }

    public function useQueue(int $key = 0, int $mode = 2, int $capacity = -1): bool {
        return false;
    }

    public function statQueue() {
    }

    public function freeQueue(): bool {
        return false;
    }

    public function start() {
    }

    public function write(string $data) {
    }

    public function close(int $which = 0): bool {
        return false;
    }

    public function read(int $size = 8192) {
    }

    public function push(string $data): bool {
        return false;
    }

    public function pop(int $size = 65536) {
    }

    public function exit(int $exit_code = 0) {
    }

    public function exec(string $exec_file, array $args): bool {
        return false;
    }

    public function exportSocket() {
    }

    public function name(string $process_name): bool {
        return false;
    }

}

class Swoole\Process\Pool {
    public function __construct(int $worker_num, int $ipc_type = 0, int $msgqueue_key = 0, bool $enable_coroutine = false) {
    }

    public function __destruct() {
    }

    public function set(array $settings) {
    }

    public function on(string $name, callable $callback): bool {
        return false;
    }

    public function getProcess(int $work_id = -1) {
    }

    public function listen(string $host, int $port = 0, int $backlog = 2048): bool {
        return false;
    }

    public function write(string $data): bool {
        return false;
    }

    public function sendMessage(string $data, int $dst_worker_id): bool {
        return false;
    }

    public function detach(): bool {
        return false;
    }

    public function start(): false {
    }

    public function stop() {
    }

    public function shutdown(): bool {
        return false;
    }

}

class Swoole\Table {
    public function __construct(int $table_size, float $conflict_proportion = 0.2) {
    }

    public function column(string $name, int $type, int $size = 0): bool {
        return false;
    }

    public function create(): bool {
        return false;
    }

    public function destroy(): bool {
        return false;
    }

    public function set(string $key, array $value): bool {
        return false;
    }

    public function get(string $key, string $field = NULL) {
    }

    public function count(): int {
        return 0;
    }

    public function del(string $key): bool {
        return false;
    }

    public function delete(string $key): bool {
        return false;
    }

    public function exists(string $key): bool {
        return false;
    }

    public function exist(string $key): bool {
        return false;
    }

    public function incr(string $key, string $column, $incrby = 1) {
    }

    public function decr(string $key, string $column, $incrby = 1) {
    }

    public function getSize(): int {
        return 0;
    }

    public function getMemorySize(): int {
        return 0;
    }

    public function stats() {
    }

    public function rewind() {
    }

    public function valid(): bool {
        return false;
    }

    public function next() {
    }

    public function current(): mixed {
    }

    public function key(): mixed {
    }

}

class Swoole\Timer {
    public function tick(int $ms, callable $callback, mixed $params) {
    }

    public function after(int $ms, callable $callback, mixed $params) {
    }

    public function exists(int $timer_id): bool {
        return false;
    }

    public function info(int $timer_id): array {
    }

    public function stats(): array {
    }

    public function list(): Swoole\Timer\Iterator {
    }

    public function clear(int $timer_id): bool {
        return false;
    }

    public function clearAll(): bool {
        return false;
    }

}

class Swoole\Timer\Iterator {
}

class Swoole\Coroutine {
    public function create(callable $func, mixed $param) {
    }

    public function defer(callable $callback) {
    }

    public function set(array $options) {
    }

    public function getOptions(): array {
    }

    public function exists(int $cid): bool {
        return false;
    }

    public function yield(): bool {
        return false;
    }

    public function cancel(int $cid, bool $throw_exception = false): bool {
        return false;
    }

    public function join(array $cid_array, float $timeout = -1): bool {
        return false;
    }

    public function isCanceled(): bool {
        return false;
    }

    public function setTimeLimit(float $timeout): bool {
        return false;
    }

    public function suspend(): bool {
        return false;
    }

    public function resume(int $cid): bool {
        return false;
    }

    public function stats(): array {
    }

    public function getCid(): int {
        return 0;
    }

    public function getuid(): int {
        return 0;
    }

    public function getPcid(int $cid = 0) {
    }

    public function getContext(int $cid = 0): Swoole\Coroutine\Context {
    }

    public function getBackTrace(int $cid = 0, int $options = 1, int $limit = 0) {
    }

    public function printBackTrace(int $cid = 0, int $options = 0, int $limit = 0) {
    }

    public function getElapsed(int $cid = 0): int {
        return 0;
    }

    public function getStackUsage(int $cid = 0) {
    }

    public function list(): Swoole\Coroutine\Iterator {
    }

    public function listCoroutines(): Swoole\Coroutine\Iterator {
    }

    public function enableScheduler(): bool {
        return false;
    }

    public function disableScheduler(): bool {
        return false;
    }

    public function gethostbyname(string $domain_name, int $type = 2, float $timeout = -1) {
    }

    public function dnsLookup(string $domain_name, float $timeout = 60, int $type = 2) {
    }

    public function exec(string $command, bool $get_error_stream = false) {
    }

    public function sleep(float $seconds): bool {
        return false;
    }

    public function getaddrinfo(string $domain, int $family = 2, int $socktype = 1, int $protocol = 6, string $service = NULL, float $timeout = -1) {
    }

    public function statvfs(string $path): array {
    }

    public function readFile(string $filename, int $flag = 0) {
    }

    public function writeFile(string $filename, string $fileContent, int $flags = 0) {
    }

    public function wait(float $timeout = -1) {
    }

    public function waitPid(int $pid, float $timeout = -1) {
    }

    public function waitSignal($signals, float $timeout = -1) {
    }

    public function waitEvent(mixed $socket, int $events = 512, float $timeout = -1) {
    }

}

class Swoole\Coroutine\Iterator {
}

class Swoole\Coroutine\Context {
}

class Swoole\ExitException {
    public function getFlags(): int {
        return 0;
    }

    public function getStatus(): mixed {
    }

}

class Swoole\Coroutine\CanceledException {
}

class Swoole\Coroutine\TimeoutException {
}

class Swoole\Coroutine\System {
    public function gethostbyname(string $domain_name, int $type = 2, float $timeout = -1) {
    }

    public function dnsLookup(string $domain_name, float $timeout = 60, int $type = 2) {
    }

    public function exec(string $command, bool $get_error_stream = false) {
    }

    public function sleep(float $seconds): bool {
        return false;
    }

    public function getaddrinfo(string $domain, int $family = 2, int $socktype = 1, int $protocol = 6, string $service = NULL, float $timeout = -1) {
    }

    public function statvfs(string $path): array {
    }

    public function readFile(string $filename, int $flag = 0) {
    }

    public function writeFile(string $filename, string $fileContent, int $flags = 0) {
    }

    public function wait(float $timeout = -1) {
    }

    public function waitPid(int $pid, float $timeout = -1) {
    }

    public function waitSignal($signals, float $timeout = -1) {
    }

    public function waitEvent(mixed $socket, int $events = 512, float $timeout = -1) {
    }

}

class Swoole\Coroutine\Scheduler {
    public function add(callable $func, mixed $param) {
    }

    public function parallel(int $n, callable $func, mixed $param) {
    }

    public function set(array $settings) {
    }

    public function getOptions(): array {
    }

    public function start(): bool {
        return false;
    }

}

class Swoole\Coroutine\Lock {
    public function __construct(bool $shared = false) {
    }

    public function lock(int $operation = 2): bool {
        return false;
    }

    public function unlock(): bool {
        return false;
    }

}

class Swoole\Coroutine\Channel {
    public function __construct(int $size = 1) {
    }

    public function push(mixed $data, float $timeout = -1): bool {
        return false;
    }

    public function pop(float $timeout = -1): mixed {
    }

    public function isEmpty(): bool {
        return false;
    }

    public function isFull(): bool {
        return false;
    }

    public function close(): bool {
        return false;
    }

    public function stats(): array {
    }

    public function length(): int {
        return 0;
    }

}

class Swoole\Runtime {
    public function enableCoroutine(int $flags = 2147481599): bool {
        return false;
    }

    public function getHookFlags(): int {
        return 0;
    }

    public function setHookFlags(int $flags): bool {
        return false;
    }

}

class Swoole\Coroutine\Curl\Exception {
}

class Swoole\Coroutine\Socket {
    public function __construct(int $domain, int $type, int $protocol = 0) {
    }

    public function bind(string $address, int $port = 0): bool {
        return false;
    }

    public function listen(int $backlog = 512): bool {
        return false;
    }

    public function accept(float $timeout = 0) {
    }

    public function connect(string $host, int $port = 0, float $timeout = 0): bool {
        return false;
    }

    public function checkLiveness(): bool {
        return false;
    }

    public function getBoundCid(int $event): int {
        return 0;
    }

    public function peek(int $length = 65536) {
    }

    public function recv(int $length = 65536, float $timeout = 0) {
    }

    public function recvAll(int $length = 65536, float $timeout = 0) {
    }

    public function recvLine(int $length = 65536, float $timeout = 0) {
    }

    public function recvWithBuffer(int $length = 65536, float $timeout = 0) {
    }

    public function recvPacket(float $timeout = 0) {
    }

    public function send(string $data, float $timeout = 0) {
    }

    public function readVector(array $io_vector, float $timeout = 0) {
    }

    public function readVectorAll(array $io_vector, float $timeout = 0) {
    }

    public function writeVector(array $io_vector, float $timeout = 0) {
    }

    public function writeVectorAll(array $io_vector, float $timeout = 0) {
    }

    public function sendFile(string $file, int $offset = 0, int $length = 0): bool {
        return false;
    }

    public function sendAll(string $data, float $timeout = 0) {
    }

    public function recvfrom(mixed &$peername, float $timeout = 0) {
    }

    public function sendto(string $addr, int $port, string $data) {
    }

    public function getOption(int $level, int $opt_name): mixed {
    }

    public function setProtocol(array $settings): bool {
        return false;
    }

    public function setOption(int $level, int $opt_name, mixed $opt_value): bool {
        return false;
    }

    public function sslHandshake(): bool {
        return false;
    }

    public function shutdown(int $how = 2): bool {
        return false;
    }

    public function cancel(int $event = 512): bool {
        return false;
    }

    public function close(): bool {
        return false;
    }

    public function getpeername() {
    }

    public function getsockname() {
    }

    public function isClosed(): bool {
        return false;
    }

    public function import($stream) {
    }

}

class Swoole\Coroutine\Socket\Exception {
}

class Swoole\Client {
    public function __construct(int $type, bool $async = false, string $id = '') {
    }

    public function __destruct() {
    }

    public function set(array $settings): bool {
        return false;
    }

    public function connect(string $host, int $port = 0, float $timeout = 0.5, int $sock_flag = 0): bool {
        return false;
    }

    public function recv(int $size = 65536, int $flag = 0) {
    }

    public function send(string $data, int $flag = 0) {
    }

    public function sendfile(string $filename, int $offset = 0, int $length = 0): bool {
        return false;
    }

    public function sendto(string $ip, int $port, string $data): bool {
        return false;
    }

    public function shutdown(int $how): bool {
        return false;
    }

    public function enableSSL(callable $onSslReady = NULL): bool {
        return false;
    }

    public function getPeerCert() {
    }

    public function verifyPeerCert(): bool {
        return false;
    }

    public function isConnected(): bool {
        return false;
    }

    public function getsockname() {
    }

    public function getpeername() {
    }

    public function close(bool $force = false): bool {
        return false;
    }

    public function getSocket() {
    }

}

class Swoole\Client\Exception {
}

class Swoole\Async\Client {
    public function __construct(int $type) {
    }

    public function __destruct() {
    }

    public function connect(string $host, int $port = 0, float $timeout = 0.5, int $sock_flag = 0): bool {
        return false;
    }

    public function sleep(): bool {
        return false;
    }

    public function wakeup(): bool {
        return false;
    }

    public function pause(): bool {
        return false;
    }

    public function resume(): bool {
        return false;
    }

    public function enableSSL(callable $onSslReady = NULL): bool {
        return false;
    }

    public function isConnected(): bool {
        return false;
    }

    public function close(bool $force = false): bool {
        return false;
    }

    public function on(string $host, callable $callback): bool {
        return false;
    }

}

class Swoole\Coroutine\Client {
    public function __construct(int $type) {
    }

    public function __destruct() {
    }

    public function set(array $settings): bool {
        return false;
    }

    public function connect(string $host, int $port = 0, float $timeout = 0, int $sock_flag = 0): bool {
        return false;
    }

    public function recv(float $timeout = 0) {
    }

    public function peek(int $length = 65535) {
    }

    public function send(string $data, float $timeout = 0) {
    }

    public function sendfile(string $filename, int $offset = 0, int $length = 0): bool {
        return false;
    }

    public function sendto(string $address, int $port, string $data): bool {
        return false;
    }

    public function recvfrom(int $length, mixed &$address, mixed &$port = 0) {
    }

    public function enableSSL(): bool {
        return false;
    }

    public function getPeerCert() {
    }

    public function verifyPeerCert(bool $allow_self_signed = false): bool {
        return false;
    }

    public function isConnected(): bool {
        return false;
    }

    public function getsockname() {
    }

    public function getpeername() {
    }

    public function close(): bool {
        return false;
    }

    public function exportSocket() {
    }

}

class Swoole\Coroutine\Http\Client {
    public function __construct(string $host, int $port = 0, bool $ssl = false) {
    }

    public function __destruct() {
    }

    public function set(array $settings): bool {
        return false;
    }

    public function getDefer(): bool {
        return false;
    }

    public function setDefer(bool $defer = true): bool {
        return false;
    }

    public function setMethod(string $method): bool {
        return false;
    }

    public function setHeaders(array $headers): bool {
        return false;
    }

    public function setBasicAuth(string $username, string $password) {
    }

    public function setCookies(array $cookies): bool {
        return false;
    }

    public function setData($data): bool {
        return false;
    }

    public function addFile(string $path, string $name, string $type = NULL, string $filename = NULL, int $offset = 0, int $length = 0): bool {
        return false;
    }

    public function addData(string $path, string $name, string $type = NULL, string $filename = NULL): bool {
        return false;
    }

    public function execute(string $path): bool {
        return false;
    }

    public function getpeername() {
    }

    public function getsockname() {
    }

    public function get(string $path): bool {
        return false;
    }

    public function post(string $path, mixed $data): bool {
        return false;
    }

    public function download(string $path, string $file, int $offset = 0): bool {
        return false;
    }

    public function getBody() {
    }

    public function getHeaders() {
    }

    public function getCookies() {
    }

    public function getStatusCode() {
    }

    public function getHeaderOut() {
    }

    public function getPeerCert() {
    }

    public function upgrade(string $path): bool {
        return false;
    }

    public function push(mixed $data, int $opcode = 1, int $flags = 1): bool {
        return false;
    }

    public function recv(float $timeout = 0) {
    }

    public function close(): bool {
        return false;
    }

    public function ping(string $data = ''): bool {
        return false;
    }

    public function disconnect(int $code = 1000, string $reason = ''): bool {
        return false;
    }

}

class Swoole\Coroutine\Http\Client\Exception {
}

class Swoole\Coroutine\Http2\Client {
    public function __construct(string $host, int $port = 80, bool $open_ssl = false) {
    }

    public function __destruct() {
    }

    public function set(array $settings): bool {
        return false;
    }

    public function connect(): bool {
        return false;
    }

    public function stats(string $key = '') {
    }

    public function isStreamExist(int $stream_id): bool {
        return false;
    }

    public function send(Swoole\Http2\Request $request) {
    }

    public function write(int $stream_id, mixed $data, bool $end_stream = false): bool {
        return false;
    }

    public function recv(float $timeout = 0) {
    }

    public function read(float $timeout = 0) {
    }

    public function goaway(int $error_code = 0, string $debug_data = ''): bool {
        return false;
    }

    public function ping(): bool {
        return false;
    }

    public function close(): bool {
        return false;
    }

}

class Swoole\Coroutine\Http2\Client\Exception {
}

class Swoole\Http2\Request {
}

class Swoole\Http2\Response {
}

class Swoole\Server {
    public function __construct(string $host = '0.0.0.0', int $port = 0, int $mode = 1, int $sock_type = 1) {
    }

    public function __destruct() {
    }

    public function listen(string $host, int $port, int $sock_type) {
    }

    public function addlistener(string $host, int $port, int $sock_type) {
    }

    public function on(string $event_name, callable $callback): bool {
        return false;
    }

    public function getCallback(string $event_name) {
    }

    public function set(array $settings): bool {
        return false;
    }

    public function start(): bool {
        return false;
    }

    public function send($fd, string $send_data, int $serverSocket = -1): bool {
        return false;
    }

    public function sendto(string $ip, int $port, string $send_data, int $server_socket = -1): bool {
        return false;
    }

    public function sendwait(int $conn_fd, string $send_data): bool {
        return false;
    }

    public function exists(int $fd): bool {
        return false;
    }

    public function exist(int $fd): bool {
        return false;
    }

    public function protect(int $fd, bool $is_protected = true): bool {
        return false;
    }

    public function sendfile(int $conn_fd, string $filename, int $offset = 0, int $length = 0): bool {
        return false;
    }

    public function close(int $fd, bool $reset = false): bool {
        return false;
    }

    public function confirm(int $fd): bool {
        return false;
    }

    public function pause(int $fd): bool {
        return false;
    }

    public function resume(int $fd): bool {
        return false;
    }

    public function task(mixed $data, int $taskWorkerIndex = -1, callable $finishCallback = NULL) {
    }

    public function taskwait(mixed $data, float $timeout = 0.5, int $taskWorkerIndex = -1): mixed {
    }

    public function taskWaitMulti(array $tasks, float $timeout = 0.5) {
    }

    public function taskCo(array $tasks, float $timeout = 0.5) {
    }

    public function finish(mixed $data): bool {
        return false;
    }

    public function reload(bool $only_reload_taskworker = false): bool {
        return false;
    }

    public function shutdown(): bool {
        return false;
    }

    public function stop(int $workerId = -1): bool {
        return false;
    }

    public function getLastError(): int {
        return 0;
    }

    public function heartbeat(bool $ifCloseConnection = true) {
    }

    public function getClientInfo(int $fd, int $reactor_id = -1, bool $ignoreError = false) {
    }

    public function getClientList(int $start_fd = 0, int $find_count = 10) {
    }

    public function getWorkerId() {
    }

    public function getWorkerPid(int $worker_id = -1) {
    }

    public function getWorkerStatus(int $worker_id = -1) {
    }

    public function getManagerPid(): int {
        return 0;
    }

    public function getMasterPid(): int {
        return 0;
    }

    public function connection_info(int $fd, int $reactor_id = -1, bool $ignoreError = false) {
    }

    public function connection_list(int $start_fd = 0, int $find_count = 10) {
    }

    public function sendMessage(mixed $message, int $dst_worker_id): bool {
        return false;
    }

    public function command(string $name, int $process_id, int $process_type, mixed $data, bool $json_decode = true) {
    }

    public function addCommand(string $name, int $accepted_process_types, callable $callback): bool {
        return false;
    }

    public function addProcess(Swoole\Process $process) {
    }

    public function stats(): array {
    }

    public function getSocket(int $port = 0) {
    }

    public function bind(int $fd, int $uid): bool {
        return false;
    }

}

class Swoole\Server\Task {
    public function finish(mixed $data): bool {
        return false;
    }

    public function pack(mixed $data) {
    }

    public function unpack(string $data): mixed {
    }

}

class Swoole\Server\Event {
}

class Swoole\Server\Packet {
}

class Swoole\Server\PipeMessage {
}

class Swoole\Server\StatusInfo {
}

class Swoole\Server\TaskResult {
}

class Swoole\Connection\Iterator {
    public function __construct() {
    }

    public function __destruct() {
    }

    public function rewind() {
    }

    public function next() {
    }

    public function current(): mixed {
    }

    public function key(): mixed {
    }

    public function valid(): bool {
        return false;
    }

    public function count(): int {
        return 0;
    }

    public function offsetExists(mixed $fd): bool {
        return false;
    }

    public function offsetGet(mixed $fd): mixed {
    }

    public function offsetSet(mixed $fd, mixed $value) {
    }

    public function offsetUnset(mixed $fd) {
    }

}

class Swoole\Server\Port {
    public function __destruct() {
    }

    public function set(array $settings) {
    }

    public function on(string $event_name, callable $callback): bool {
        return false;
    }

    public function getCallback(string $event_name): Closure {
    }

    public function getSocket() {
    }

}

class Swoole\Http\Request {
    public function getContent() {
    }

    public function rawContent() {
    }

    public function getData() {
    }

    public function create(array $options = array (
)): Swoole\Http\Request {
    }

    public function parse(string $data) {
    }

    public function isCompleted(): bool {
        return false;
    }

    public function getMethod() {
    }

}

class Swoole\Http\Response {
    public function initHeader(): bool {
        return false;
    }

    public function isWritable(): bool {
        return false;
    }

    public function cookie($name_or_object, string $value = '', int $expires = 0, string $path = '/', string $domain = '', bool $secure = false, bool $httponly = false, string $samesite = '', string $priority = '', bool $partitioned = false): bool {
        return false;
    }

    public function setCookie($name_or_object, string $value = '', int $expires = 0, string $path = '/', string $domain = '', bool $secure = false, bool $httponly = false, string $samesite = '', string $priority = '', bool $partitioned = false): bool {
        return false;
    }

    public function rawcookie($name_or_object, string $value = '', int $expires = 0, string $path = '/', string $domain = '', bool $secure = false, bool $httponly = false, string $samesite = '', string $priority = '', bool $partitioned = false): bool {
        return false;
    }

    public function setRawCookie($name_or_object, string $value = '', int $expires = 0, string $path = '/', string $domain = '', bool $secure = false, bool $httponly = false, string $samesite = '', string $priority = '', bool $partitioned = false): bool {
        return false;
    }

    public function status(int $http_code, string $reason = ''): bool {
        return false;
    }

    public function setStatusCode(int $http_code, string $reason = ''): bool {
        return false;
    }

    public function header(string $key, $value, bool $format = true): bool {
        return false;
    }

    public function setHeader(string $key, $value, bool $format = true): bool {
        return false;
    }

    public function trailer(string $key, string $value): bool {
        return false;
    }

    public function ping(string $data = ''): bool {
        return false;
    }

    public function goaway(int $error_code = 0, string $debug_data = ''): bool {
        return false;
    }

    public function write(string $content): bool {
        return false;
    }

    public function end(string $content = NULL): bool {
        return false;
    }

    public function sendfile(string $filename, int $offset = 0, int $length = 0): bool {
        return false;
    }

    public function redirect(string $location, int $http_code = 302): bool {
        return false;
    }

    public function detach(): bool {
        return false;
    }

    public function create($server = -1, int $fd = -1) {
    }

    public function upgrade(): bool {
        return false;
    }

    public function push($data, int $opcode = 1, int $flags = 1): bool {
        return false;
    }

    public function recv(float $timeout = 0) {
    }

    public function close(): bool {
        return false;
    }

    public function disconnect(int $code = 1000, string $reason = ''): bool {
        return false;
    }

}

class Swoole\Http\Cookie {
    public function __construct(bool $encode = true) {
    }

    public function withName(string $name): Swoole\Http\Cookie {
    }

    public function withValue(string $value = ''): Swoole\Http\Cookie {
    }

    public function withExpires(int $expires = 0): Swoole\Http\Cookie {
    }

    public function withPath(string $path = '/'): Swoole\Http\Cookie {
    }

    public function withDomain(string $domain = ''): Swoole\Http\Cookie {
    }

    public function withSecure(bool $secure = false): Swoole\Http\Cookie {
    }

    public function withHttpOnly(bool $httpOnly = false): Swoole\Http\Cookie {
    }

    public function withSameSite(string $sameSite = ''): Swoole\Http\Cookie {
    }

    public function withPriority(string $priority = ''): Swoole\Http\Cookie {
    }

    public function withPartitioned(bool $partitioned = false): Swoole\Http\Cookie {
    }

    public function toString() {
    }

    public function toArray(): array {
    }

    public function reset() {
    }

}

class Swoole\Http\Server {
}

class Swoole\Coroutine\Http\Server {
    public function __construct(string $host, int $port = 0, bool $ssl = false, bool $reuse_port = false) {
    }

    public function set(array $settings): bool {
        return false;
    }

    public function handle(string $pattern, callable $callback): bool {
        return false;
    }

    public function start(): bool {
        return false;
    }

    public function shutdown() {
    }

}

class Swoole\WebSocket\Server {
    public function push(int $fd, $data, int $opcode = 1, int $flags = 1): bool {
        return false;
    }

    public function disconnect(int $fd, int $code = 1000, string $reason = ''): bool {
        return false;
    }

    public function ping(int $fd, string $data = ''): bool {
        return false;
    }

    public function isEstablished(int $fd): bool {
        return false;
    }

    public function pack($data, int $opcode = 1, int $flags = 1): string {
        return "";
    }

    public function unpack(string $data): Swoole\WebSocket\Frame {
    }

}

class Swoole\WebSocket\Frame {
    public function __toString(): string {
        return "";
    }

    public function pack($data, int $opcode = 1, int $flags = 1): string {
        return "";
    }

    public function unpack(string $data): Swoole\WebSocket\Frame {
    }

}

class Swoole\WebSocket\CloseFrame {
}

class Swoole\Redis\Server {
    public function setHandler(string $command, callable $callback): bool {
        return false;
    }

    public function getHandler(string $command): Closure {
    }

    public function format(int $type, mixed $value = NULL) {
    }

}

class Swoole\NameResolver\Context {
    public function __construct(int $family = 2, bool $withPort = false) {
    }

}

class Swoole\Thread {
    public function __construct(string $script_file, mixed $args) {
    }

    public function isAlive(): bool {
        return false;
    }

    public function join(): bool {
        return false;
    }

    public function joinable(): bool {
        return false;
    }

    public function getExitStatus(): int {
        return 0;
    }

    public function detach(): bool {
        return false;
    }

    public function getArguments(): array {
    }

    public function getId(): int {
        return 0;
    }

    public function getInfo(): array {
    }

    public function activeCount(): int {
        return 0;
    }

    public function yield() {
    }

    public function setName(string $name): bool {
        return false;
    }

    public function setAffinity(array $cpu_settings): bool {
        return false;
    }

    public function getAffinity(): array {
    }

    public function setPriority(int $priority, int $policy = 0): bool {
        return false;
    }

    public function getPriority(): array {
    }

    public function getNativeId(): int {
        return 0;
    }

}

class Swoole\Thread\Error {
}

class Swoole\Thread\Atomic {
    public function __construct(int $value = 0) {
    }

    public function add(int $add_value = 1): int {
        return 0;
    }

    public function sub(int $sub_value = 1): int {
        return 0;
    }

    public function get(): int {
        return 0;
    }

    public function set(int $value) {
    }

    public function wait(float $timeout = 1.0): bool {
        return false;
    }

    public function wakeup(int $count = 1): bool {
        return false;
    }

    public function cmpset(int $cmp_value, int $new_value): bool {
        return false;
    }

}

class Swoole\Thread\Atomic\Long {
    public function __construct(int $value = 0) {
    }

    public function add(int $add_value = 1): int {
        return 0;
    }

    public function sub(int $sub_value = 1): int {
        return 0;
    }

    public function get(): int {
        return 0;
    }

    public function set(int $value) {
    }

    public function cmpset(int $cmp_value, int $new_value): bool {
        return false;
    }

}

class Swoole\Thread\Lock {
    public function __construct(int $type = 3) {
    }

    public function lock(int $operation = 2, float $timeout = -1): bool {
        return false;
    }

    public function unlock(): bool {
        return false;
    }

}

class Swoole\Thread\Barrier {
    public function __construct(int $count) {
    }

    public function wait() {
    }

}

class Swoole\Thread\Queue {
    public function __construct() {
    }

    public function push(mixed $value, int $notify_which = 0) {
    }

    public function pop(float $wait = 0): mixed {
    }

    public function clean() {
    }

    public function count(): int {
        return 0;
    }

}

class Swoole\Thread\Map {
    public function __construct(array $array = NULL) {
    }

    public function offsetGet(mixed $key): mixed {
    }

    public function offsetExists(mixed $key): bool {
        return false;
    }

    public function offsetSet(mixed $key, mixed $value) {
    }

    public function offsetUnset(mixed $key) {
    }

    public function find(mixed $value): mixed {
    }

    public function count(): int {
        return 0;
    }

    public function incr(mixed $key, mixed $value = 1): mixed {
    }

    public function decr(mixed $key, mixed $value = 1): mixed {
    }

    public function add(mixed $key, mixed $value): bool {
        return false;
    }

    public function update(mixed $key, mixed $value): bool {
        return false;
    }

    public function clean() {
    }

    public function keys(): array {
    }

    public function values(): array {
    }

    public function toArray(): array {
    }

    public function sort() {
    }

}

class Swoole\Thread\ArrayList {
    public function __construct(array $array = NULL) {
    }

    public function offsetGet(mixed $key): mixed {
    }

    public function offsetExists(mixed $key): bool {
        return false;
    }

    public function offsetSet(mixed $key, mixed $value) {
    }

    public function offsetUnset(mixed $key) {
    }

    public function find(mixed $value): int {
        return 0;
    }

    public function incr(mixed $key, mixed $value = 1): mixed {
    }

    public function decr(mixed $key, mixed $value = 1): mixed {
    }

    public function clean() {
    }

    public function count(): int {
        return 0;
    }

    public function toArray(): array {
    }

    public function sort() {
    }

}

class Swoole\Constant {
}

class Swoole\StringObject {
    /**
     * StringObject constructor.
     */
    public function __construct(string $string = '') {
    }

    public function __toString(): string {
        return "";
    }

    public function from(string $string = ''): static {
    }

    public function length(): int {
        return 0;
    }

    public function indexOf(string $needle, int $offset = 0) {
    }

    public function lastIndexOf(string $needle, int $offset = 0) {
    }

    public function pos(string $needle, int $offset = 0) {
    }

    public function rpos(string $needle, int $offset = 0) {
    }

    public function reverse(): static {
    }

    /**
     * @return false|int
     */
    public function ipos(string $needle) {
    }

    public function lower(): static {
    }

    public function upper(): static {
    }

    public function trim(string $characters = ''): static {
    }

    /**
     * @return static
     */
    public function ltrim(): Swoole\StringObject {
    }

    /**
     * @return static
     */
    public function rtrim(): Swoole\StringObject {
    }

    /**
     * @return static
     */
    public function substr(int $offset, int $length = NULL) {
    }

    public function repeat(int $times): static {
    }

    public function append(mixed $str): static {
    }

    /**
     * @param int|null $count
     */
    public function replace(string $search, string $replace, &$count = NULL): static {
    }

    public function startsWith(string $needle): bool {
        return false;
    }

    public function endsWith(string $needle): bool {
        return false;
    }

    public function equals($str, bool $strict = false): bool {
        return false;
    }

    public function contains(string $subString): bool {
        return false;
    }

    public function split(string $delimiter, int $limit = 9223372036854775807): Swoole\ArrayObject {
    }

    public function char(int $index): string {
        return "";
    }

    /**
     * Get a new string object by splitting the string of current object into smaller chunks.
     *
     * @param int $length The chunk length.
     * @param string $separator The line ending sequence.
     * @see https://www.php.net/chunk_split
     */
    public function chunkSplit(int $length = 76, string $separator = '
'): static {
    }

    /**
     * Convert a string to an array object of class \Swoole\ArrayObject.
     *
     * @param int $length Maximum length of the chunk.
     * @see https://www.php.net/str_split
     */
    public function chunk(int $length = 1): Swoole\ArrayObject {
    }

    public function toString(): string {
        return "";
    }

}

class Swoole\MultibyteStringObject {
    public function length(): int {
        return 0;
    }

    public function indexOf(string $needle, int $offset = 0, string $encoding = NULL) {
    }

    public function lastIndexOf(string $needle, int $offset = 0, string $encoding = NULL) {
    }

    public function pos(string $needle, int $offset = 0, string $encoding = NULL) {
    }

    public function rpos(string $needle, int $offset = 0, string $encoding = NULL) {
    }

    public function ipos(string $needle, int $offset = 0, string $encoding = NULL) {
    }

    /**
     * @see https://www.php.net/mb_substr
     */
    public function substr(int $start, int $length = NULL, string $encoding = NULL): static {
    }

    /**
     * {@inheritDoc}
     * @see https://www.php.net/mb_str_split
     */
    public function chunk(int $length = 1): Swoole\ArrayObject {
    }

}

class Swoole\Exception\ArrayKeyNotExists {
}

class Swoole\ArrayObject {
    /**
     * ArrayObject constructor.
     */
    public function __construct(array $array = array (
)) {
    }

    public function __toArray(): array {
    }

    public function __serialize(): array {
    }

    public function __unserialize(array $data) {
    }

    public function from(array $array = array (
)): static {
    }

    public function toArray(): array {
    }

    public function isEmpty(): bool {
        return false;
    }

    public function count(): int {
        return 0;
    }

    /**
     * @return mixed
     */
    public function current() {
    }

    /**
     * @return mixed
     */
    public function key() {
    }

    public function valid(): bool {
        return false;
    }

    /**
     * @return mixed
     */
    public function rewind() {
    }

    /**
     * @return mixed
     */
    public function next() {
    }

    /**
     * @return ArrayObject|StringObject
     */
    public function get(mixed $key) {
    }

    /**
     * @return ArrayObject|StringObject
     */
    public function getOr(mixed $key, mixed $default = NULL) {
    }

    /**
     * @return mixed
     */
    public function last() {
    }

    /**
     * @return int|string|null
     */
    public function firstKey() {
    }

    /**
     * @return int|string|null
     */
    public function lastKey() {
    }

    /**
     * @return mixed
     */
    public function first() {
    }

    /**
     * @return $this
     */
    public function set(mixed $key, mixed $value): Swoole\ArrayObject {
    }

    /**
     * @return $this
     */
    public function delete(mixed $key): Swoole\ArrayObject {
    }

    /**
     * @return $this
     */
    public function remove(mixed $value, bool $strict = true, bool $loop = false): Swoole\ArrayObject {
    }

    /**
     * @return $this
     */
    public function clear(): Swoole\ArrayObject {
    }

    /**
     * @return mixed|null
     */
    public function offsetGet(mixed $key) {
    }

    public function offsetSet(mixed $key, mixed $value) {
    }

    public function offsetUnset(mixed $key) {
    }

    /**
     * @return bool
     */
    public function offsetExists(mixed $key) {
    }

    public function exists(mixed $key): bool {
        return false;
    }

    public function contains(mixed $value, bool $strict = true): bool {
        return false;
    }

    /**
     * @return mixed
     */
    public function indexOf(mixed $value, bool $strict = true) {
    }

    /**
     * @return mixed
     */
    public function lastIndexOf(mixed $value, bool $strict = true) {
    }

    /**
     * @return mixed
     */
    public function search(mixed $needle, bool $strict = true) {
    }

    public function join(string $glue = ''): Swoole\StringObject {
    }

    public function serialize(): string {
        return "";
    }

    public function unserialize($string): Swoole\ArrayObject {
    }

    /**
     * @return float|int
     */
    public function sum() {
    }

    /**
     * @return float|int
     */
    public function product() {
    }

    /**
     * @return int
     */
    public function push(mixed $value) {
    }

    /**
     * @return int
     */
    public function pushFront(mixed $value) {
    }

    public function append($values): Swoole\ArrayObject {
    }

    /**
     * @return int
     */
    public function pushBack(mixed $value) {
    }

    /**
     * @return $this
     */
    public function insert(int $offset, mixed $value): Swoole\ArrayObject {
    }

    /**
     * @return mixed
     */
    public function pop() {
    }

    /**
     * @return mixed
     */
    public function popFront() {
    }

    /**
     * @return mixed
     */
    public function popBack() {
    }

    public function slice(int $offset, int $length = NULL, bool $preserve_keys = false): static {
    }

    /**
     * @return ArrayObject|mixed|StringObject
     */
    public function randomGet() {
    }

    public function each(callable $fn): Swoole\ArrayObject {
    }

    /**
     * @param array $args
     */
    public function map(callable $fn, $args): static {
    }

    /**
     * @param null $initial
     * @return mixed
     */
    public function reduce(callable $fn, $initial = NULL) {
    }

    /**
     * @param array $args
     */
    public function keys($args): static {
    }

    public function values(): static {
    }

    public function column(mixed $column_key, mixed $index = NULL): static {
    }

    public function unique(int $sort_flags = 2): static {
    }

    public function reverse(bool $preserve_keys = false): static {
    }

    public function chunk(int $size, bool $preserve_keys = false): static {
    }

    /**
     * Swap keys and values in an array.
     */
    public function flip(): static {
    }

    public function filter(callable $fn, int $flag = 0): static {
    }

    /**
     * @return $this
     */
    public function asort(int $sort_flags = 0): Swoole\ArrayObject {
    }

    public function arsort(int $sort_flags = 0): Swoole\ArrayObject {
    }

    public function krsort(int $sort_flags = 0): Swoole\ArrayObject {
    }

    public function ksort(int $sort_flags = 0): Swoole\ArrayObject {
    }

    /**
     * @return $this
     */
    public function natcasesort(): Swoole\ArrayObject {
    }

    /**
     * @return $this
     */
    public function natsort(): Swoole\ArrayObject {
    }

    /**
     * @return $this
     */
    public function rsort(int $sort_flags = 0): Swoole\ArrayObject {
    }

    public function shuffle(): Swoole\ArrayObject {
    }

    public function sort(int $sort_flags = 0): Swoole\ArrayObject {
    }

    public function uasort(callable $value_compare_func): Swoole\ArrayObject {
    }

    public function uksort(callable $value_compare_func): Swoole\ArrayObject {
    }

    public function usort(callable $value_compare_func): Swoole\ArrayObject {
    }

}

class Swoole\ObjectProxy {
    public function __construct(object $object) {
    }

    public function __getObject() {
    }

    public function __get(string $name) {
    }

    public function __set(string $name, $value) {
    }

    public function __isset($name) {
    }

    public function __unset(string $name) {
    }

    public function __call(string $name, array $arguments) {
    }

    public function __invoke($arguments) {
    }

}

class Swoole\Coroutine\WaitGroup {
    public function __construct(int $delta = 0) {
    }

    public function add(int $delta = 1) {
    }

    public function done() {
    }

    public function wait(float $timeout = -1.0): bool {
        return false;
    }

    public function count(): int {
        return 0;
    }

}

class Swoole\Coroutine\Server {
    /**
     * Server constructor.
     * @throws Exception
     */
    public function __construct(string $host, int $port = 0, bool $ssl = false, bool $reuse_port = false) {
    }

    public function set(array $setting) {
    }

    public function handle(callable $fn) {
    }

    public function shutdown(): bool {
        return false;
    }

    public function start(): bool {
        return false;
    }

}

class Swoole\Coroutine\Server\Connection {
    public function __construct(Swoole\Coroutine\Socket $conn) {
    }

    public function recv(float $timeout = 0.0) {
    }

    public function send(string $data) {
    }

    public function close(): bool {
        return false;
    }

    public function exportSocket(): Swoole\Coroutine\Socket {
    }

}

class Swoole\Coroutine\Barrier {
    public function __destruct() {
    }

    public function make(): Swoole\Coroutine\Barrier {
    }

    /**
     * @param-out null $barrier
     */
    public function wait(Swoole\Coroutine\Barrier &$barrier, float $timeout = -1.0) {
    }

}

class Swoole\Coroutine\Http\ClientProxy {
    public function __construct(string $body, int $statusCode, array $headers, array $cookies) {
    }

    public function getBody(): string {
        return "";
    }

    public function getStatusCode(): int {
        return 0;
    }

    public function getHeaders(): array {
    }

    public function getCookies(): array {
    }

}

class Swoole\ConnectionPool {
    public function __construct(callable $constructor, int $size = 64, string $proxy = NULL) {
    }

    public function fill() {
    }

    /**
     * Get a connection from the pool.
     *
     * @param float $timeout > 0 means waiting for the specified number of seconds. other means no waiting.
     * @return mixed|false Returns a connection object from the pool, or false if the pool is full and the timeout is reached.
     */
    public function get(float $timeout = -1.0) {
    }

    public function put($connection) {
    }

    public function close() {
    }

}

class Swoole\Database\ObjectProxy {
    public function __clone() {
    }

}

class Swoole\Database\MysqliConfig {
    public function getHost(): string {
        return "";
    }

    public function withHost(string $host): Swoole\Database\MysqliConfig {
    }

    public function getPort(): int {
        return 0;
    }

    public function getUnixSocket(): string {
        return "";
    }

    public function withUnixSocket(string $unixSocket): Swoole\Database\MysqliConfig {
    }

    public function withPort(int $port): Swoole\Database\MysqliConfig {
    }

    public function getDbname(): string {
        return "";
    }

    public function withDbname(string $dbname): Swoole\Database\MysqliConfig {
    }

    public function getCharset(): string {
        return "";
    }

    public function withCharset(string $charset): Swoole\Database\MysqliConfig {
    }

    public function getUsername(): string {
        return "";
    }

    public function withUsername(string $username): Swoole\Database\MysqliConfig {
    }

    public function getPassword(): string {
        return "";
    }

    public function withPassword(string $password): Swoole\Database\MysqliConfig {
    }

    public function getOptions(): array {
    }

    public function withOptions(array $options): Swoole\Database\MysqliConfig {
    }

}

class Swoole\Database\MysqliException {
}

/**
 * @method \mysqli|MysqliProxy get()
 * @method void put(mysqli|MysqliProxy $connection)
 */
class Swoole\Database\MysqliPool {
    public function __construct(Swoole\Database\MysqliConfig $config, int $size = 64) {
    }

}

/**
 * @method \mysqli __getObject()
 */
class Swoole\Database\MysqliProxy {
    public function __construct(callable $constructor) {
    }

    public function __call(string $name, array $arguments) {
    }

    public function getRound(): int {
        return 0;
    }

    public function reconnect() {
    }

    public function options(int $option, $value): bool {
        return false;
    }

    public function set_opt(int $option, $value): bool {
        return false;
    }

    public function set_charset(string $charset): bool {
        return false;
    }

    public function change_user(string $user, string $password, string $database): bool {
        return false;
    }

}

class Swoole\Database\MysqliStatementProxy {
    public function __construct(mysqli_stmt $object, string $queryString, Swoole\Database\MysqliProxy $parent) {
    }

    public function __call(string $name, array $arguments) {
    }

    public function attr_set($attr, $mode): bool {
        return false;
    }

    public function bind_param($types, &$arguments): bool {
        return false;
    }

    public function bind_result(&$arguments): bool {
        return false;
    }

}

class Swoole\Database\DetectsLostConnections {
    public function causedByLostConnection(Throwable $e): bool {
        return false;
    }

}

class Swoole\Database\PDOConfig {
    public function getDriver(): string {
        return "";
    }

    public function withDriver(string $driver): Swoole\Database\PDOConfig {
    }

    public function getHost(): string {
        return "";
    }

    public function withHost(string $host): Swoole\Database\PDOConfig {
    }

    public function getPort(): int {
        return 0;
    }

    public function hasUnixSocket(): bool {
        return false;
    }

    public function getUnixSocket(): string {
        return "";
    }

    public function withUnixSocket(string $unixSocket): Swoole\Database\PDOConfig {
    }

    public function withPort(int $port): Swoole\Database\PDOConfig {
    }

    public function getDbname(): string {
        return "";
    }

    public function withDbname(string $dbname): Swoole\Database\PDOConfig {
    }

    public function getCharset(): string {
        return "";
    }

    public function withCharset(string $charset): Swoole\Database\PDOConfig {
    }

    public function getUsername(): string {
        return "";
    }

    public function withUsername(string $username): Swoole\Database\PDOConfig {
    }

    public function getPassword(): string {
        return "";
    }

    public function withPassword(string $password): Swoole\Database\PDOConfig {
    }

    public function getOptions(): array {
    }

    public function withOptions(array $options): Swoole\Database\PDOConfig {
    }

    /**
     * Returns the list of available drivers
     *
     * @return string[]
     */
    public function getAvailableDrivers(): array {
    }

}

/**
 * @method void put(PDO|PDOProxy $connection)
 */
class Swoole\Database\PDOPool {
    public function __construct(Swoole\Database\PDOConfig $config, int $size = 64) {
    }

    /**
     * Get a PDO connection from the pool. The PDO connection (a PDO object) is wrapped in a PDOProxy object returned.
     *
     * @param float $timeout > 0 means waiting for the specified number of seconds. other means no waiting.
     * @return PDOProxy|false Returns a PDOProxy object from the pool, or false if the pool is full and the timeout is reached.
     *                        {@inheritDoc}
     */
    public function get(float $timeout = -1.0) {
    }

}

/**
 * @method \PDO __getObject()
 */
class Swoole\Database\PDOProxy {
    public function __construct(callable $constructor) {
    }

    public function __call(string $name, array $arguments) {
    }

    public function getRound(): int {
        return 0;
    }

    public function reconnect() {
    }

    public function setAttribute(int $attribute, $value): bool {
        return false;
    }

    public function inTransaction(): bool {
        return false;
    }

    public function reset() {
    }

}

/**
 * The proxy class for PHP class PDOStatement.
 *
 * @see https://www.php.net/PDOStatement The PDOStatement class
 */
class Swoole\Database\PDOStatementProxy {
    public function __construct(PDOStatement $object, Swoole\Database\PDOProxy $parent) {
    }

    public function __call(string $name, array $arguments) {
    }

    public function setAttribute(int $attribute, $value): bool {
        return false;
    }

    /**
     * Set the default fetch mode for this statement.
     *
     * @see https://www.php.net/manual/en/pdostatement.setfetchmode.php
     */
    public function setFetchMode(int $mode, $params): bool {
        return false;
    }

    public function bindParam($parameter, &$variable, $data_type = 2, $length = 0, $driver_options = NULL): bool {
        return false;
    }

    public function bindColumn($column, &$param, $type = NULL, $maxlen = NULL, $driverdata = NULL): bool {
        return false;
    }

    public function bindValue($parameter, $value, $data_type = 2): bool {
        return false;
    }

}

class Swoole\Database\RedisConfig {
    public function getHost(): string {
        return "";
    }

    public function withHost(string $host): Swoole\Database\RedisConfig {
    }

    public function getPort(): int {
        return 0;
    }

    public function withPort(int $port): Swoole\Database\RedisConfig {
    }

    public function getTimeout(): float {
    }

    public function withTimeout(float $timeout): Swoole\Database\RedisConfig {
    }

    public function getReserved(): string {
        return "";
    }

    public function withReserved(string $reserved): Swoole\Database\RedisConfig {
    }

    public function getRetryInterval(): int {
        return 0;
    }

    public function withRetryInterval(int $retry_interval): Swoole\Database\RedisConfig {
    }

    public function getReadTimeout(): float {
    }

    public function withReadTimeout(float $read_timeout): Swoole\Database\RedisConfig {
    }

    public function getAuth(): string {
        return "";
    }

    public function withAuth(string $auth): Swoole\Database\RedisConfig {
    }

    public function getDbIndex(): int {
        return 0;
    }

    public function withDbIndex(int $dbIndex): Swoole\Database\RedisConfig {
    }

    /**
     * Add a configurable option.
     */
    public function withOption(int $option, mixed $value): Swoole\Database\RedisConfig {
    }

    /**
     * Add/override configurable options.
     *
     * @param array<int, mixed> $options
     */
    public function setOptions(array $options): Swoole\Database\RedisConfig {
    }

    /**
     * Get configurable options.
     *
     * @return array<int, mixed>
     */
    public function getOptions(): array {
    }

}

/**
 * @method \Redis get(float $timeout = -1)
 * @method void put(Redis $connection)
 */
class Swoole\Database\RedisPool {
    public function __construct(Swoole\Database\RedisConfig $config, int $size = 64) {
    }

}

class Swoole\Http\Status {
    public function getReasonPhrases(): array {
    }

    public function getReasonPhrase(int $value): string {
        return "";
    }

}

class Swoole\Curl\Exception {
}

class Swoole\Curl\Handler {
    public function __construct(string $url = '') {
    }

    public function __toString(): string {
        return "";
    }

    public function isAvailable(): bool {
        return false;
    }

    public function setOpt(int $opt, $value): bool {
        return false;
    }

    public function exec() {
    }

    public function getInfo() {
    }

    public function errno(): int {
        return 0;
    }

    public function error(): string {
        return "";
    }

    public function reset() {
    }

    public function getContent() {
    }

    public function close() {
    }

}

/**
 * FastCGI constants.
 */
class Swoole\FastCGI {
}

/**
 * FastCGI record.
 */
class Swoole\FastCGI\Record {
    /**
     * Returns the binary message representation of record
     */
    public function __toString(): string {
        return "";
    }

    /**
     * Unpacks the message from the binary data buffer
     */
    public function unpack(string $binaryData): static {
    }

    /**
     * Sets the content data and adjusts the length fields
     *
     * @return static
     */
    public function setContentData(string $data): Swoole\FastCGI\Record {
    }

    /**
     * Returns the context data from the record
     */
    public function getContentData(): string {
        return "";
    }

    /**
     * Returns the version of record
     */
    public function getVersion(): int {
        return 0;
    }

    /**
     * Returns record type
     */
    public function getType(): int {
        return 0;
    }

    /**
     * Returns request ID
     */
    public function getRequestId(): int {
        return 0;
    }

    /**
     * Sets request ID
     *
     * There should be only one unique ID for all active requests,
     * use random number or preferably resetting auto-increment.
     *
     * @return static
     */
    public function setRequestId(int $requestId): Swoole\FastCGI\Record {
    }

    /**
     * Returns the size of content length
     */
    public function getContentLength(): int {
        return 0;
    }

    /**
     * Returns the size of padding length
     */
    public function getPaddingLength(): int {
        return 0;
    }

}

/**
 * Params request record
 */
class Swoole\FastCGI\Record\Params {
    /**
     * Constructs a param request
     *
     * @phpstan-param array<string, string> $values
     */
    public function __construct(array $values) {
    }

    /**
     * Returns an associative list of parameters
     *
     * @phpstan-return array<string, string>
     */
    public function getValues(): array {
    }

}

/**
 * The Web server sends a FCGI_ABORT_REQUEST record to abort a request
 */
class Swoole\FastCGI\Record\AbortRequest {
    public function __construct(int $requestId) {
    }

}

/**
 * The Web server sends a FCGI_BEGIN_REQUEST record to start a request.
 */
class Swoole\FastCGI\Record\BeginRequest {
    public function __construct(int $role = 3, int $flags = 0, string $reserved = '') {
    }

    /**
     * Returns the role
     *
     * The role component sets the role the Web server expects the application to play.
     * The currently-defined roles are:
     *   FCGI_RESPONDER
     *   FCGI_AUTHORIZER
     *   FCGI_FILTER
     */
    public function getRole(): int {
        return 0;
    }

    /**
     * Returns the flags
     *
     * The flags component contains a bit that controls connection shutdown.
     *
     * flags & FCGI_KEEP_CONN:
     *   If zero, the application closes the connection after responding to this request.
     *   If not zero, the application does not close the connection after responding to this request;
     *   the Web server retains responsibility for the connection.
     */
    public function getFlags(): int {
        return 0;
    }

}

/**
 * Data binary stream
 *
 * FCGI_DATA is a second stream record type used to send additional data to the application.
 */
class Swoole\FastCGI\Record\Data {
    public function __construct(string $contentData) {
    }

}

/**
 * The application sends a FCGI_END_REQUEST record to terminate a request, either because the application
 * has processed the request or because the application has rejected the request.
 */
class Swoole\FastCGI\Record\EndRequest {
    public function __construct(int $protocolStatus = 0, int $appStatus = 0, string $reserved = '') {
    }

    /**
     * Returns app status
     *
     * The appStatus component is an application-level status code. Each role documents its usage of appStatus.
     */
    public function getAppStatus(): int {
        return 0;
    }

    /**
     * Returns the protocol status
     *
     * The possible protocolStatus values are:
     *   FCGI_REQUEST_COMPLETE: normal end of request.
     *   FCGI_CANT_MPX_CONN: rejecting a new request.
     *      This happens when a Web server sends concurrent requests over one connection to an application that is
     *      designed to process one request at a time per connection.
     *   FCGI_OVERLOADED: rejecting a new request.
     *      This happens when the application runs out of some resource, e.g. database connections.
     *   FCGI_UNKNOWN_ROLE: rejecting a new request.
     *      This happens when the Web server has specified a role that is unknown to the application.
     */
    public function getProtocolStatus(): int {
        return 0;
    }

}

/**
 * GetValues API
 *
 * The Web server can query specific variables within the application.
 * The server will typically perform a query on application startup in order to to automate certain aspects of
 * system configuration.
 *
 * The application responds by sending a record {FCGI_GET_VALUES_RESULT, 0, ...} with the values supplied.
 * If the application doesn't understand a variable name that was included in the query, it omits that name from
 * the response.
 *
 * FCGI_GET_VALUES is designed to allow an open-ended set of variables.
 *
 * The initial set provides information to help the server perform application and connection management:
 *   FCGI_MAX_CONNS:  The maximum number of concurrent transport connections this application will accept,
 *                    e.g. "1" or "10".
 *   FCGI_MAX_REQS:   The maximum number of concurrent requests this application will accept, e.g. "1" or "50".
 *   FCGI_MPXS_CONNS: "0" if this application does not multiplex connections (i.e. handle concurrent requests
 *                    over each connection), "1" otherwise.
 */
class Swoole\FastCGI\Record\GetValues {
    /**
     * Constructs a request
     *
     * @param array $keys List of keys to receive
     *
     * @phpstan-param list<string> $keys
     */
    public function __construct(array $keys) {
    }

}

/**
 * GetValues API
 *
 * The Web server can query specific variables within the application.
 * The server will typically perform a query on application startup in order to to automate certain aspects of
 * system configuration.
 *
 * The application responds by sending a record {FCGI_GET_VALUES_RESULT, 0, ...} with the values supplied.
 * If the application doesn't understand a variable name that was included in the query, it omits that name from
 * the response.
 *
 * FCGI_GET_VALUES is designed to allow an open-ended set of variables.
 *
 * The initial set provides information to help the server perform application and connection management:
 *   FCGI_MAX_CONNS:  The maximum number of concurrent transport connections this application will accept,
 *                    e.g. "1" or "10".
 *   FCGI_MAX_REQS:   The maximum number of concurrent requests this application will accept, e.g. "1" or "50".
 *   FCGI_MPXS_CONNS: "0" if this application does not multiplex connections (i.e. handle concurrent requests
 *                    over each connection), "1" otherwise.
 */
class Swoole\FastCGI\Record\GetValuesResult {
    /**
     * Constructs a param request
     *
     * @phpstan-param array<string, string> $values
     */
    public function __construct(array $values) {
    }

}

/**
 * Stdin binary stream
 *
 * FCGI_STDIN is a stream record type used in sending arbitrary data from the Web server to the application
 */
class Swoole\FastCGI\Record\Stdin {
    public function __construct(string $contentData) {
    }

}

/**
 * Stdout binary stream
 *
 * FCGI_STDOUT is a stream record for sending arbitrary data from the application to the Web server
 */
class Swoole\FastCGI\Record\Stdout {
    public function __construct(string $contentData) {
    }

}

/**
 * Stderr binary stream
 *
 * FCGI_STDERR is a stream record for sending arbitrary data from the application to the Web server
 */
class Swoole\FastCGI\Record\Stderr {
    public function __construct(string $contentData) {
    }

}

/**
 * Record for unknown queries
 *
 * The set of management record types is likely to grow in future versions of this protocol.
 * To provide for this evolution, the protocol includes the FCGI_UNKNOWN_TYPE management record.
 * When an application receives a management record whose type T it does not understand, the application responds
 * with {FCGI_UNKNOWN_TYPE, 0, {T}}.
 */
class Swoole\FastCGI\Record\UnknownType {
    public function __construct(int $type, string $reserved = '') {
    }

    /**
     * Returns the unrecognized type
     */
    public function getUnrecognizedType(): int {
        return 0;
    }

    /**
     * {@inheritdoc}
     * @param static $self
     */
    public function unpackPayload(Swoole\FastCGI\Record $self, string $binaryData) {
    }

}

/**
 * Utility class to simplify parsing of FastCGI protocol data.
 */
class Swoole\FastCGI\FrameParser {
    /**
     * Checks if the buffer contains a valid frame to parse
     */
    public function hasFrame(string $binaryBuffer): bool {
        return false;
    }

    /**
     * Parses a frame from the binary buffer
     *
     * @return Record One of the corresponding FastCGI record
     */
    public function parseFrame(string &$binaryBuffer): Swoole\FastCGI\Record {
    }

}

class Swoole\FastCGI\Message {
    public function getParam(string $name): string {
        return "";
    }

    public function withParam(string $name, string $value): static {
    }

    public function withoutParam(string $name): static {
    }

    public function getParams(): array {
    }

    public function withParams(array $params): static {
    }

    public function withAddedParams(array $params): static {
    }

    public function getBody(): string {
        return "";
    }

    public function withBody($body): Swoole\FastCGI\Message {
    }

    public function getError(): string {
        return "";
    }

    public function withError(string $error): static {
    }

}

class Swoole\FastCGI\Request {
    public function __toString(): string {
        return "";
    }

    public function getKeepConn(): bool {
        return false;
    }

    public function withKeepConn(bool $keepConn): Swoole\FastCGI\Request {
    }

}

class Swoole\FastCGI\Response {
    /**
     * @param array<Stdout|Stderr|EndRequest> $records
     */
    public function __construct(array $records) {
    }

}

class Swoole\FastCGI\HttpRequest {
    public function getScheme(): string {
        return "";
    }

    public function withScheme(string $scheme): Swoole\FastCGI\HttpRequest {
    }

    public function withoutScheme() {
    }

    public function getMethod(): string {
        return "";
    }

    public function withMethod(string $method): Swoole\FastCGI\HttpRequest {
    }

    public function withoutMethod() {
    }

    public function getDocumentRoot(): string {
        return "";
    }

    public function withDocumentRoot(string $documentRoot): Swoole\FastCGI\HttpRequest {
    }

    public function withoutDocumentRoot() {
    }

    public function getScriptFilename(): string {
        return "";
    }

    public function withScriptFilename(string $scriptFilename): Swoole\FastCGI\HttpRequest {
    }

    public function withoutScriptFilename() {
    }

    public function getScriptName(): string {
        return "";
    }

    public function withScriptName(string $scriptName): Swoole\FastCGI\HttpRequest {
    }

    public function withoutScriptName() {
    }

    public function withUri(string $uri): Swoole\FastCGI\HttpRequest {
    }

    public function getDocumentUri(): string {
        return "";
    }

    public function withDocumentUri(string $documentUri): Swoole\FastCGI\HttpRequest {
    }

    public function withoutDocumentUri() {
    }

    public function getRequestUri(): string {
        return "";
    }

    public function withRequestUri(string $requestUri): Swoole\FastCGI\HttpRequest {
    }

    public function withoutRequestUri() {
    }

    public function withQuery($query): Swoole\FastCGI\HttpRequest {
    }

    public function getQueryString(): string {
        return "";
    }

    public function withQueryString(string $queryString): Swoole\FastCGI\HttpRequest {
    }

    public function withoutQueryString() {
    }

    public function getContentType(): string {
        return "";
    }

    public function withContentType(string $contentType): Swoole\FastCGI\HttpRequest {
    }

    public function withoutContentType() {
    }

    public function getContentLength(): int {
        return 0;
    }

    public function withContentLength(int $contentLength): Swoole\FastCGI\HttpRequest {
    }

    public function withoutContentLength() {
    }

    public function getGatewayInterface(): string {
        return "";
    }

    public function withGatewayInterface(string $gatewayInterface): Swoole\FastCGI\HttpRequest {
    }

    public function withoutGatewayInterface() {
    }

    public function getServerProtocol(): string {
        return "";
    }

    public function withServerProtocol(string $serverProtocol): Swoole\FastCGI\HttpRequest {
    }

    public function withoutServerProtocol() {
    }

    public function withProtocolVersion(string $protocolVersion): Swoole\FastCGI\HttpRequest {
    }

    public function getServerSoftware(): string {
        return "";
    }

    public function withServerSoftware(string $serverSoftware): Swoole\FastCGI\HttpRequest {
    }

    public function withoutServerSoftware() {
    }

    public function getRemoteAddr(): string {
        return "";
    }

    public function withRemoteAddr(string $remoteAddr): Swoole\FastCGI\HttpRequest {
    }

    public function withoutRemoteAddr() {
    }

    public function getRemotePort(): int {
        return 0;
    }

    public function withRemotePort(int $remotePort): Swoole\FastCGI\HttpRequest {
    }

    public function withoutRemotePort() {
    }

    public function getServerAddr(): string {
        return "";
    }

    public function withServerAddr(string $serverAddr): Swoole\FastCGI\HttpRequest {
    }

    public function withoutServerAddr() {
    }

    public function getServerPort(): int {
        return 0;
    }

    public function withServerPort(int $serverPort): Swoole\FastCGI\HttpRequest {
    }

    public function withoutServerPort() {
    }

    public function getServerName(): string {
        return "";
    }

    public function withServerName(string $serverName): Swoole\FastCGI\HttpRequest {
    }

    public function withoutServerName() {
    }

    public function getRedirectStatus(): string {
        return "";
    }

    public function withRedirectStatus(string $redirectStatus): Swoole\FastCGI\HttpRequest {
    }

    public function withoutRedirectStatus() {
    }

    public function getHeader(string $name): string {
        return "";
    }

    public function withHeader(string $name, string $value): Swoole\FastCGI\HttpRequest {
    }

    public function withoutHeader(string $name) {
    }

    public function getHeaders(): array {
    }

    public function withHeaders(array $headers): Swoole\FastCGI\HttpRequest {
    }

    public function withBody($body): Swoole\FastCGI\HttpRequest {
    }

}

class Swoole\FastCGI\HttpResponse {
    /**
     * @param array<Stdout|Stderr|EndRequest> $records
     */
    public function __construct(array $records = array (
)) {
    }

    public function getStatusCode(): int {
        return 0;
    }

    public function withStatusCode(int $statusCode): Swoole\FastCGI\HttpResponse {
    }

    public function getReasonPhrase(): string {
        return "";
    }

    public function withReasonPhrase(string $reasonPhrase): Swoole\FastCGI\HttpResponse {
    }

    public function getHeader(string $name): string {
        return "";
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array {
    }

    public function withHeader(string $name, string $value): Swoole\FastCGI\HttpResponse {
    }

    /**
     * @param array<string, string> $headers
     */
    public function withHeaders(array $headers): Swoole\FastCGI\HttpResponse {
    }

    /**
     * @return array<string>
     */
    public function getSetCookieHeaderLines(): array {
    }

    public function withSetCookieHeaderLine(string $value): Swoole\FastCGI\HttpResponse {
    }

}

class Swoole\Coroutine\FastCGI\Client {
    public function __construct(string $host, int $port = 0, bool $ssl = false) {
    }

    /**
     * @return ($request is HttpRequest ? HttpResponse : Response)
     * @throws Exception
     */
    public function execute(Swoole\FastCGI\Request $request, float $timeout = -1.0): Swoole\FastCGI\Response {
    }

    public function parseUrl(string $url): array {
    }

    public function call(string $url, string $path, $data = '', float $timeout = -1.0): string {
        return "";
    }

}

class Swoole\Coroutine\FastCGI\Client\Exception {
}

class Swoole\Coroutine\FastCGI\Proxy {
    public function __construct(string $url, string $documentRoot = '/') {
    }

    public function withTimeout(float $timeout): Swoole\Coroutine\FastCGI\Proxy {
    }

    public function withHttps(bool $https): Swoole\Coroutine\FastCGI\Proxy {
    }

    public function withIndex(string $index): Swoole\Coroutine\FastCGI\Proxy {
    }

    public function getParam(string $name): string {
        return "";
    }

    public function withParam(string $name, string $value): Swoole\Coroutine\FastCGI\Proxy {
    }

    public function withoutParam(string $name): Swoole\Coroutine\FastCGI\Proxy {
    }

    public function getParams(): array {
    }

    public function withParams(array $params): Swoole\Coroutine\FastCGI\Proxy {
    }

    public function withAddedParams(array $params): Swoole\Coroutine\FastCGI\Proxy {
    }

    public function withStaticFileFilter(callable $filter): Swoole\Coroutine\FastCGI\Proxy {
    }

    public function translateRequest(Swoole\Http\Request $userRequest): Swoole\FastCGI\HttpRequest {
    }

    public function translateResponse(Swoole\FastCGI\HttpResponse $response, Swoole\Http\Response $userResponse) {
    }

    public function pass($userRequest, Swoole\Http\Response $userResponse) {
    }

    /**
     * Send content of a static file to the client, if the file is accessible and is not a PHP file.
     *
     * @return bool True if the file doesn't have an extension of 'php', false otherwise. Note that the file may not be
     *              accessible even the return value is true.
     */
    public function staticFileFiltrate(Swoole\FastCGI\HttpRequest $request, Swoole\Http\Response $userResponse): bool {
        return false;
    }

}

class Swoole\Process\Manager {
    public function __construct(int $ipcType = 0, int $msgQueueKey = 0) {
    }

    public function add(callable $func, bool $enableCoroutine = false): Swoole\Process\Manager {
    }

    public function addBatch(int $workerNum, callable $func, bool $enableCoroutine = false): Swoole\Process\Manager {
    }

    public function start() {
    }

    public function setIPCType(int $ipcType): Swoole\Process\Manager {
    }

    public function getIPCType(): int {
        return 0;
    }

    public function setMsgQueueKey(int $msgQueueKey): Swoole\Process\Manager {
    }

    public function getMsgQueueKey(): int {
        return 0;
    }

}

class Swoole\Server\Admin {
    public function init(Swoole\Server $server) {
    }

    public function getAccessToken(): string {
        return "";
    }

    public function start(Swoole\Server $server) {
    }

    /**
     * @return false|string
     */
    public function handlerGetResources(Swoole\Server $server, string $msg) {
    }

    /**
     * @return false|string
     */
    public function handlerGetWorkerInfo(Swoole\Server $server, string $msg) {
    }

    /**
     * @return false|string
     */
    public function handlerCloseSession(Swoole\Server $server, string $msg) {
    }

    /**
     * @return false|string
     */
    public function handlerGetTimerList(Swoole\Server $server, string $msg) {
    }

    /**
     * @return false|string
     */
    public function handlerGetCoroutineList(Swoole\Server $server, string $msg) {
    }

    public function handlerGetObjects(Swoole\Server $server, string $msg) {
    }

    public function handlerGetClassInfo(Swoole\Server $server, string $msg) {
    }

    public function handlerGetFunctionInfo(Swoole\Server $server, string $msg) {
    }

    public function handlerGetObjectByHandle(Swoole\Server $server, string $msg) {
    }

    public function handlerGetVersionInfo(Swoole\Server $server, string $msg) {
    }

    public function handlerGetDefinedFunctions(Swoole\Server $server, string $msg) {
    }

    public function handlerGetDeclaredClasses(Swoole\Server $server, string $msg) {
    }

    public function handlerGetServerMemoryUsage(Swoole\Server $server, string $msg) {
    }

    public function handlerGetServerCpuUsage(Swoole\Server $server, string $msg) {
    }

    public function handlerGetStaticPropertyValue(Swoole\Server $server, string $msg) {
    }

}

class Swoole\Server\Helper {
    public function checkOptions(array $input_options) {
    }

    public function onBeforeStart(Swoole\Server $server) {
    }

    public function onBeforeShutdown(Swoole\Server $server) {
    }

    public function onWorkerStart(Swoole\Server $server, int $workerId) {
    }

    public function onWorkerExit(Swoole\Server $server, int $workerId) {
    }

    public function onWorkerStop(Swoole\Server $server, int $workerId) {
    }

    public function onStart(Swoole\Server $server) {
    }

    public function onShutdown(Swoole\Server $server) {
    }

    public function onBeforeReload(Swoole\Server $server) {
    }

    public function onAfterReload(Swoole\Server $server) {
    }

    public function onManagerStart(Swoole\Server $server) {
    }

    public function onManagerStop(Swoole\Server $server) {
    }

    public function onWorkerError(Swoole\Server $server) {
    }

}

class Swoole\NameResolver {
    public function __construct($url, $prefix = 'swoole_service_') {
    }

    public function join(string $name, string $ip, int $port, array $options = array (
)): bool {
        return false;
    }

    public function leave(string $name, string $ip, int $port): bool {
        return false;
    }

    public function getCluster(string $name): Swoole\NameResolver\Cluster {
    }

    public function withFilter(callable $fn): Swoole\NameResolver {
    }

    public function getFilter() {
    }

    public function hasFilter(): bool {
        return false;
    }

    /**
     * return string: final result, non-empty string must be a valid IP address,
     * and an empty string indicates name lookup failed, and lookup operation will not continue.
     * return Cluster: has multiple nodes and failover is possible
     * return false or null: try another name resolver
     * @return Cluster|false|string|null
     */
    public function lookup(string $name) {
    }

}

class Swoole\NameResolver\Exception {
}

class Swoole\NameResolver\Cluster {
    /**
     * @throws Exception
     */
    public function add(string $host, int $port, int $weight = 100) {
    }

    /**
     * @return false|string
     */
    public function pop() {
    }

    public function count(): int {
        return 0;
    }

}

class Swoole\NameResolver\Redis {
    public function __construct($url, $prefix = 'swoole:service:') {
    }

    public function join(string $name, string $ip, int $port, array $options = array (
)): bool {
        return false;
    }

    public function leave(string $name, string $ip, int $port): bool {
        return false;
    }

    public function getCluster(string $name): Swoole\NameResolver\Cluster {
    }

}

class Swoole\NameResolver\Nacos {
    /**
     * @throws Coroutine\Http\Client\Exception|Exception
     */
    public function join(string $name, string $ip, int $port, array $options = array (
)): bool {
        return false;
    }

    /**
     * @throws Coroutine\Http\Client\Exception|Exception
     */
    public function leave(string $name, string $ip, int $port): bool {
        return false;
    }

    /**
     * @throws Coroutine\Http\Client\Exception|Exception|\Swoole\Exception
     */
    public function getCluster(string $name): Swoole\NameResolver\Cluster {
    }

}

class Swoole\NameResolver\Consul {
    public function join(string $name, string $ip, int $port, array $options = array (
)): bool {
        return false;
    }

    public function leave(string $name, string $ip, int $port): bool {
        return false;
    }

    public function enableMaintenanceMode(string $name, string $ip, int $port): bool {
        return false;
    }

    public function getCluster(string $name): Swoole\NameResolver\Cluster {
    }

}

/**
 * @since 6.0.0-beta
 */
class Swoole\Thread\Pool {
    public function __construct(string $runnableClass, int $threadNum) {
    }

    public function withArguments($arguments): static {
    }

    public function withAutoloader(string $autoloader): static {
    }

    public function withClassDefinitionFile(string $classDefinitionFile): static {
    }

    /**
     * @throws \ReflectionException
     */
    public function start() {
    }

    public function shutdown() {
    }

}

/**
 * @since 6.0.0-beta
 */
class Swoole\Thread\Runnable {
    public function __construct($running, $index) {
    }

    public function run(array $args) {
    }

}

class SwooleLibrary {
}

class swoole\process\processmanager {
}

