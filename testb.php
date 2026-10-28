<?php

use Swoole\Buffer;
use Swoole\Coroutine\Socket;
use function Swoole\Coroutine\run;
use function Swoole\Coroutine\go;
use Swoole\Coroutine\System;

run(function () {

    $receiver = new Socket(AF_INET, SOCK_DGRAM, 0);
    $sender = new Socket(AF_INET, SOCK_DGRAM, 0);

    if (!$receiver->bind('127.0.0.1', 0)) {
        throw new RuntimeException('Falha no bind');
    }

    $port = $receiver->getsockname()['port'];
    $buffer = new Buffer(2048);

    echo "Porta UDP: $port\n";
    echo "Capacidade: {$buffer->getCapacity()}\n";

    $messages = [
        'RTP-TESTE-001',
        str_repeat('A', 160),
        str_repeat('B', 1024),
    ];

    go(function () use ($sender, $port, $messages) {
        foreach ($messages as $message) {
            System::sleep(0.1);
            $sender->sendto('127.0.0.1', $port, $message);
        }
    });

    go(function () use ($receiver, $buffer, $messages) {
        foreach ($messages as $index => $expected) {
            $peer = null;
            $bytes = $receiver->recvfromInto($buffer, $peer, 1.0);

            if ($bytes === false) {
                echo "FALHA: recvfromInto, erro {$receiver->errCode}\n";
                return;
            }

            $valid = (
                $bytes === strlen($expected) &&
                $buffer->getLength() === $bytes &&
                $buffer->toString() === $expected &&
                ($peer['address'] ?? null) === '127.0.0.1'
            );

            printf(
                "Pacote %d: %s | bytes=%d | length=%d | capacity=%d\n",
                $index + 1,
                $valid ? 'OK' : 'FALHA',
                $bytes,
                $buffer->getLength(),
                $buffer->getCapacity()
            );

            if (!$valid) {
                return;
            }
        }

        // Testar timeout sem nenhum pacote pendente.
        $peer = null;
        $start = microtime(true);
        $result = $receiver->recvfromInto($buffer, $peer, 0.2);
        $elapsed = microtime(true) - $start;

        printf(
            "Timeout: result=%s | erro=%d | duracao=%.3fs\n",
            var_export($result, true),
            $receiver->errCode,
            $elapsed
        );
    });

    // Verificar se outra corrotina continua executando
    // enquanto a recepção está aguardando.
    go(function () {
        for ($i = 1; $i <= 5; $i++) {
            System::sleep(0.05);
            echo "Scheduler ativo: $i\n";
        }
    });
});
