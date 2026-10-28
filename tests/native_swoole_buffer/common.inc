<?php
function ensure(bool $ok): void {
    if (!$ok) {
        throw new RuntimeException('check failed');
    }
}
function rejects(callable $operation, string $type = Error::class): void {
    $caught = false;
    try {
        $operation();
    } catch (Throwable $error) {
        if (!$error instanceof $type) {
            throw $error;
        }
        $caught = true;
    }
    ensure($caught);
}
function udp_pair(): array {
    $receiver = new Swoole\Coroutine\Socket(AF_INET, SOCK_DGRAM, 0);
    $sender = new Swoole\Coroutine\Socket(AF_INET, SOCK_DGRAM, 0);
    ensure($receiver->bind('127.0.0.1', 0));
    return [$receiver, $sender, $receiver->getsockname()['port']];
}
function receive_packet($receiver, $sender, int $port, $buffer, string $packet): void {
    ensure($sender->sendto('127.0.0.1', $port, $packet) === strlen($packet));
    $peer = null;
    ensure($receiver->recvfromInto($buffer, $peer, 0.5) === strlen($packet));
    ensure($peer['address'] === '127.0.0.1' && $peer['port'] > 0);
}
function byte_sum(string $bytes): int {
    $sum = 0;
    for ($i = 0; $i < strlen($bytes); ++$i) {
        $sum += ord($bytes[$i]);
    }
    return $sum;
}
