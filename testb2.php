<?php

declare(strict_types=1);
use Swoole\Buffer;
use Swoole\Coroutine\Socket;
use Swoole\Coroutine\System;
use function Swoole\Coroutine\go;
use function Swoole\Coroutine\run;


$mode = $argv[1] ?? 'cancel';

if (!in_array($mode, ['cancel', 'concorrencia'], true)) {
    exit("Uso: php testb2.php [cancel|concorrencia]\n");
}

run(function () use ($mode) {
    $socket = new Socket(AF_INET, SOCK_DGRAM, 0);

    if (!$socket->bind('127.0.0.1', 0)) {
        throw new RuntimeException('Falha ao criar socket UDP');
    }

    $port = $socket->getsockname()['port'];
    $buffer = new Buffer(2048);

    echo "Modo: {$mode}\n";
    echo "Porta UDP: {$port}\n";
    echo "Capacidade: {$buffer->getCapacity()}\n\n";

    // =========================================
    // TESTE 1: CANCELAMENTO
    // =========================================

    if ($mode === 'cancel') {

        go(function () use ($socket, $buffer) {
            $peer = null;

            echo "[RX] Aguardando datagrama...\n";

            $start = microtime(true);

            $result = $socket->recvfromInto(
                $buffer,
                $peer,
                3.0
            );

            $elapsed = microtime(true) - $start;

            printf(
                "[RX] Resultado: %s\n" .
                "[RX] Erro: %d\n" .
                "[RX] Duração: %.3fs\n" .
                "[RX] Buffer length: %d\n",
                var_export($result, true),
                $socket->errCode,
                $elapsed,
                $buffer->getLength()
            );

            if ($result !== false) {
                echo "[FALHA] Recepção não foi cancelada\n";
                return;
            }

            echo "\n[RX] Tentando reutilizar o buffer...\n";

            $peer = null;

            $bytes = $socket->recvfromInto(
                $buffer,
                $peer,
                1.0
            );

            $expected = 'APOS_CANCELAR';

            if (
                $bytes === strlen($expected) &&
                $buffer->getLength() === $bytes &&
                $buffer->toString() === $expected
            ) {
                echo "[OK] Buffer reutilizado após cancelamento\n";
            } else {
                echo "[FALHA] Buffer não foi reutilizado corretamente\n";
                echo "Bytes: " . var_export($bytes, true) . "\n";
                echo "Erro: {$socket->errCode}\n";
            }
        });

        go(function () use ($socket, $port) {
            System::sleep(0.1);

            $cid = $socket->getBoundCid(
                SWOOLE_EVENT_READ
            );

            echo "[CTRL] Corrotina aguardando leitura: {$cid}\n";

            $result = $socket->cancel(
                SWOOLE_EVENT_READ
            );

            echo "[CTRL] cancel(): "
                . var_export($result, true) . "\n";

            System::sleep(0.1);

            $sender = new Socket(
                AF_INET,
                SOCK_DGRAM,
                0
            );

            $sent = $sender->sendto(
                '127.0.0.1',
                $port,
                'APOS_CANCELAR'
            );

            echo "[CTRL] Datagrama enviado após cancelamento: "
                . var_export($sent, true) . "\n";

            $sender->close();
        });
    }

    // =========================================
    // TESTE 2: CONCORRÊNCIA DE LEITURA
    // =========================================

    if ($mode === 'concorrencia') {

        go(function () use ($socket, $buffer) {
            $peer = null;

            echo "[CO1] Iniciando primeira leitura...\n";

            $result = $socket->recvfromInto(
                $buffer,
                $peer,
                2.0
            );

            echo "[CO1] Resultado: "
                . var_export($result, true) . "\n";
        });

        go(function () use ($socket) {
            System::sleep(0.1);

            $cid = $socket->getBoundCid(
                SWOOLE_EVENT_READ
            );

            echo "[CO2] Socket ocupado pela corrotina: {$cid}\n";

            if ($cid <= 0) {
                echo "[FALHA] Nenhuma corrotina vinculada à leitura\n";
                return;
            }

            $buffer2 = new Buffer(2048);
            $peer2 = null;

            echo "[CO2] Tentando segunda leitura simultânea...\n";
            echo "[CO2] Esperado: proteção de concorrência do Swoole\n";

            $result = $socket->recvfromInto(
                $buffer2,
                $peer2,
                0.2
            );

            echo "[CO2] Segunda leitura retornou: "
                . var_export($result, true) . "\n";

            echo "[ATENÇÃO] Verificar se a proteção foi acionada\n";
        });
    }
});
