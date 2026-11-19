<?php
/**
 * Prototipo independente do receive loop de libspech MediaChannel::start().
 *
 * Uso:
 *   php rtp_mediachannel_start_preview.php --self-test
 *   php rtp_mediachannel_start_preview.php --demo    # requer ext-swoole
 *   php rtp_mediachannel_start_preview.php --listen --port=40000 --duration=0 --record-wav=/tmp/received.wav
 *
 * Nao e uma implementacao do psampler nem um substituto para MediaChannel.
 * O parser PHP imita o contrato proposto para a futura extensao nativa.
 */

declare(strict_types=1);

final readonly class PreviewRtpPacket
{
    public function __construct(
        public int $payloadType,
        public int $sequence,
        public int $timestamp,
        public int $ssrc,
        public bool $marker,
        public string $payload,
        public string $originalBytes,
    ) {}
}

final readonly class PreviewParseResult
{
    /** @param 'rtp'|'rtcp'|'invalid' $kind */
    public function __construct(public string $kind, public ?PreviewRtpPacket $rtp = null) {}
}

/** Futuro: trocar exclusivamente este parser por uma classe interna do psampler. */
final class PreviewRtpParser
{
    public static function fromBuffer(object $buffer, int $length): PreviewParseResult
    {
        // Emulacao: UMA copia de buffer para string. Na versao nativa nao haveria
        // psampler_binary_slice() nem string temporaria para classificar/capturar cabecalho.
        if ($length <= 0 || !function_exists('psampler_binary_slice')) {
            return new PreviewParseResult('invalid');
        }
        return self::fromBytes(\psampler_binary_slice($buffer, 0, $length));
    }

    public static function fromBytes(string $bytes): PreviewParseResult
    {
        $size = strlen($bytes);
        if ($size < 4 || (ord($bytes[0]) >> 6) !== 2) {
            return new PreviewParseResult('invalid');
        }

        // Classificacao RTCP simplificada para o laboratorio (200..211).
        // Em producao, RFC 5761/RTCP-mux e PTs negociados precisam desambiguar
        // marcadores RTP que colidam com tipos RTCP.
        $secondByte = ord($bytes[1]);
        if ($secondByte >= 200 && $secondByte <= 211) {
            $words = unpack('n', substr($bytes, 2, 2))[1];
            $rtcpBytes = ($words + 1) * 4;
            if ($rtcpBytes <= $size) {
                return new PreviewParseResult('rtcp');
            }
        }

        if ($size < 12) {
            return new PreviewParseResult('invalid');
        }
        $first = ord($bytes[0]);
        $cc = $first & 0x0f;
        $offset = 12 + 4 * $cc;
        if ($offset > $size) {
            return new PreviewParseResult('invalid');
        }
        if (($first & 0x10) !== 0) {
            if ($offset + 4 > $size) {
                return new PreviewParseResult('invalid');
            }
            $words = unpack('n', substr($bytes, $offset + 2, 2))[1];
            $offset += 4 + $words * 4;
            if ($offset > $size) {
                return new PreviewParseResult('invalid');
            }
        }
        $end = $size;
        if (($first & 0x20) !== 0) {
            $padding = ord($bytes[$size - 1]);
            if ($padding === 0 || $padding > $size - $offset) {
                return new PreviewParseResult('invalid');
            }
            $end -= $padding;
        }
        $header = unpack('nsequence/Ntimestamp/Nssrc', $bytes, 2);
        return new PreviewParseResult('rtp', new PreviewRtpPacket(
            payloadType: $secondByte & 0x7f,
            sequence: $header['sequence'],
            timestamp: $header['timestamp'],
            ssrc: $header['ssrc'],
            marker: (bool) ($secondByte & 0x80),
            payload: substr($bytes, $offset, $end - $offset),
            originalBytes: $bytes,
        ));
    }
}

/** Em producao, seria um RtpBuilder nativo com memoria exclusiva por destino. */
final class PreviewRtpBuilder
{
    public static function build(PreviewRtpPacket $source, array &$destination): string
    {
        $sequence = $destination['sequence'];
        $timestamp = $destination['timestamp'];
        $destination['sequence'] = ($sequence + 1) & 0xffff;
        $destination['timestamp'] = ($timestamp + $destination['samplesPerPacket']) & 0xffffffff;

        return pack(
            'CCnNN',
            0x80,
            ($source->marker ? 0x80 : 0) | $destination['pt'],
            $sequence,
            $timestamp,
            $destination['ssrc'],
        ) . $source->payload;
    }
}

/**
 * Modelo pequeno do MediaChannel::start(): dois peers estaticamente negociados,
 * RX RTP -> classificacao -> callback -> relay codificado -> TX.
 */
final class PreviewMediaChannel
{
    private bool $active = false;
    private array $members = [];
    private bool $nativeReceive = false;
    private bool $passiveListener = false;
    private string $receiveMode = 'auto';
    private $onReceive = null;
    private $onDatagram = null;
    public array $metrics = ['rtp' => 0, 'rtcp' => 0, 'invalid' => 0, 'dtmf' => 0, 'sent' => 0];

    public function __construct(private readonly \Swoole\Coroutine\Socket $socket) {}

    public function addMember(string $ip, int $port, string $codec, int $pt, int $frequency = 8000, int $ptimeMs = 20): void
    {
        if ($port < 1 || $port > 65535 || $frequency <= 0 || $ptimeMs <= 0 || $pt < 0 || $pt > 127) {
            throw new \InvalidArgumentException('Parametros de peer invalidos');
        }
        $id = $ip . ':' . $port;
        $this->members[$id] = [
            'ip' => $ip, 'port' => $port, 'codec' => strtoupper($codec),
            'pt' => $pt, 'frequency' => $frequency, 'ptime' => $ptimeMs,
            'samplesPerPacket' => (int) round($frequency * $ptimeMs / 1000),
            'sequence' => random_int(0, 65535),
            'timestamp' => random_int(0, 0xffffffff),
            'ssrc' => random_int(1, 0xffffffff),
        ];
    }

    public function onReceive(callable $callback): void { $this->onReceive = $callback; }

    /** Listener de laboratorio: aceita qualquer origem, contabiliza e nao reencaminha. */
    public function setPassiveListener(bool $enabled): void { $this->passiveListener = $enabled; }

    /** auto escolhe recvfromInto quando disponivel; recvfrom/into forcados para comparacao. */
    public function setReceiveMode(string $mode): void
    {
        if (!in_array($mode, ['auto', 'recvfrom', 'into'], true)) {
            throw new \InvalidArgumentException('Modo deve ser auto, recvfrom ou into');
        }
        $this->receiveMode = $mode;
    }

    /** Callback sincrono chamado inclusive para datagramas invalidos e RTCP. */
    public function onDatagram(callable $callback): void { $this->onDatagram = $callback; }

    public function actualReceiveMode(): string { return $this->nativeReceive ? 'into' : 'recvfrom'; }

    public function start(): void
    {
        if ($this->active) return;
        $this->active = true;
        \Swoole\Coroutine::create(function (): void {
            $buffer = null;
            $nativeAvailable = class_exists(\Swoole\Buffer::class, false)
                && method_exists($this->socket, 'recvfromInto')
                && function_exists('psampler_binary_slice');
            if ($this->receiveMode === 'into' && !$nativeAvailable) {
                throw new \RuntimeException('recvfromInto requer Swoole\\Buffer, Socket::recvfromInto e psampler_binary_slice');
            }
            $this->nativeReceive = $this->receiveMode !== 'recvfrom' && $nativeAvailable;
            if ($this->nativeReceive) {
                $buffer = new \Swoole\Buffer(65536);
            }
            try {
                while ($this->active) {
                    $peer = ['address' => '0.0.0.0', 'port' => 0];
                    if ($buffer !== null) {
                        // Mantem o timeout atual do caminho nativo da libspech.
                        $bytes = $this->socket->recvfromInto($buffer, $peer, 0.2);
                        $result = is_int($bytes) && $bytes > 0
                            ? PreviewRtpParser::fromBuffer($buffer, $bytes)
                            : null;
                    } else {
                        // Compara com o caminho tradicional sem polling constante.
                        $bytes = $this->socket->recvfrom($peer, -1);
                        $result = is_string($bytes) && $bytes !== ''
                            ? PreviewRtpParser::fromBytes($bytes)
                            : null;
                    }
                    if (!$this->active) break;
                    if ($result === null) continue;

                    if ($this->passiveListener) {
                        if ($result->kind === 'rtp') {
                            if ($result->rtp?->payloadType === 101) {
                                $this->metrics['dtmf']++;
                            } else {
                                $this->metrics['rtp']++;
                            }
                        } else {
                            $this->metrics[$result->kind]++;
                        }
                        if ($this->onDatagram !== null) {
                            ($this->onDatagram)($result, $peer);
                        }
                        continue;
                    }

                    $id = ($peer['address'] ?? '') . ':' . ($peer['port'] ?? 0);
                    $origin = $this->members[$id] ?? null;
                    if ($origin === null) continue; // nao aceitar destinos aprendidos automaticamente

                    if ($result->kind !== 'rtp') {
                        $this->metrics[$result->kind]++;
                        continue;
                    }
                    $packet = $result->rtp;
                    if ($packet === null) continue;
                    if ($packet->payloadType === 101) {
                        $this->metrics['dtmf']++;
                        // TODO: DTMF RFC 4733 com duracao, E-bit e retransmissoes.
                        continue;
                    }
                    if ($packet->payloadType !== $origin['pt']) continue;
                    $this->metrics['rtp']++;
                    if ($this->onReceive !== null) {
                        ($this->onReceive)($packet, $peer); // inline: sem coroutine por datagrama
                    }
                    foreach ($this->members as $targetId => &$target) {
                        if ($targetId === $id) continue;
                        if ($target['codec'] !== $origin['codec']
                            || $target['frequency'] !== $origin['frequency']
                            || $target['ptime'] !== $origin['ptime']) {
                            // TODO: decode, resample, encode, fila e pacing por destino.
                            continue;
                        }
                        $out = PreviewRtpBuilder::build($packet, $target);
                        $sent = $this->socket->sendto($target['ip'], $target['port'], $out);
                        if ($sent === strlen($out)) $this->metrics['sent']++;
                    }
                    unset($target);
                }
            } finally {
                $this->active = false;
                $this->socket->close(); // apenas a coroutine proprietaria fecha
            }
        });
    }

    public function stop(): void
    {
        $this->active = false;
        if (defined('SWOOLE_EVENT_READ')) {
            $this->socket->cancel(SWOOLE_EVENT_READ);
        }
    }
}

function assertPreview(bool $condition, string $description): void
{
    if (!$condition) throw new \RuntimeException("Falha: {$description}");
}

function runSelfTest(): void
{
    $rtpBytes = pack('CCnNN', 0x80, 8, 32, 160, 1234) . str_repeat("\xd5", 160);
    $parsed = PreviewRtpParser::fromBytes($rtpBytes);
    assertPreview($parsed->kind === 'rtp', 'classificacao RTP');
    assertPreview($parsed->rtp !== null && $parsed->rtp->sequence === 32, 'sequence');
    assertPreview($parsed->rtp !== null && strlen($parsed->rtp->payload) === 160, 'payload');
    assertPreview(PreviewRtpParser::fromBytes("\x80")->kind === 'invalid', 'curto');
    assertPreview(PreviewRtpParser::fromBytes(pack('CCnN', 0x80, 200, 1, 1234))->kind === 'rtcp', 'RTCP SR minimo');
    $target = ['pt' => 8, 'sequence' => 100, 'timestamp' => 8000, 'ssrc' => 999, 'samplesPerPacket' => 160];
    $out = PreviewRtpBuilder::build($parsed->rtp, $target);
    $reparsed = PreviewRtpParser::fromBytes($out);
    assertPreview($reparsed->rtp?->sequence === 100, 'sequence TX');
    assertPreview($reparsed->rtp?->ssrc === 999, 'SSRC TX');
    assertPreview($reparsed->rtp?->payload === $parsed->rtp->payload, 'payload bit-exact');
    assertPreview($target['timestamp'] === 8160, 'timestamp avancou');
    echo "OK: classificacao, leitura, imutabilidade logica e construcao RTP\n";
}

function runDemo(): void
{
    if (!extension_loaded('swoole')) {
        throw new \RuntimeException('Este modo requer ext-swoole');
    }
    \Swoole\Coroutine\run(function (): void {
        $server = new \Swoole\Coroutine\Socket(AF_INET, SOCK_DGRAM, SOL_UDP);
        $source = new \Swoole\Coroutine\Socket(AF_INET, SOCK_DGRAM, SOL_UDP);
        $sink = new \Swoole\Coroutine\Socket(AF_INET, SOCK_DGRAM, SOL_UDP);
        foreach ([$server, $source, $sink] as $socket) {
            if (!$socket->bind('127.0.0.1', 0)) throw new \RuntimeException('Falha no bind');
        }
        $mediaPort = $server->getsockname()['port'];
        $sourcePort = $source->getsockname()['port'];
        $sinkPort = $sink->getsockname()['port'];
        $channel = new PreviewMediaChannel($server);
        $channel->addMember('127.0.0.1', $sourcePort, 'PCMA', 8);
        $channel->addMember('127.0.0.1', $sinkPort, 'PCMA', 8);
        $channel->start();
        $sent = pack('CCnNN', 0x80, 8, 10, 160, 4567) . str_repeat("\xd5", 160);
        $source->sendto('127.0.0.1', $mediaPort, $sent);
        $peer = [];
        $received = $sink->recvfrom($peer, 1.0);
        assertPreview(is_string($received), 'pacote RX no peer de destino');
        $parsed = PreviewRtpParser::fromBytes($received);
        assertPreview($parsed->rtp?->payload === str_repeat("\xd5", 160), 'relay PCMA bit-exact');
        echo "OK: RX -> RTP parser -> relay -> sendto; ";
        echo json_encode($channel->metrics, JSON_UNESCAPED_SLASHES), "\n";
        $channel->stop();
        \Swoole\Coroutine::sleep(0.02);
        $source->close();
        $sink->close();
    });
}


/** Gravador opcional de audio RTP PCMA em WAV PCM16 mono / 8000 Hz. */
final class PreviewWavRecorder
{
    private $output;
    private ?int $ssrc = null;
    private int $pcmBytes = 0;
    private int $lastFlushNs = 0;
    private array $decode = [];

    public function __construct(private readonly string $path)
    {
        $this->output = @fopen($path, 'w+b');
        if (!is_resource($this->output)) {
            throw new \RuntimeException("Nao foi possivel criar WAV de recepcao: {$path}");
        }
        for ($i = 0; $i < 256; $i++) {
            $a = $i ^ 0x55;
            $sample = (($a & 0x0f) << 4) + 8;
            $segment = ($a & 0x70) >> 4;
            if ($segment > 0) $sample += 256;
            if ($segment > 1) $sample <<= $segment - 1;
            if (($a & 0x80) === 0) $sample = -$sample;
            $this->decode[$i] = pack('v', $sample & 0xffff);
        }
        $this->syncHeader();
        fseek($this->output, 44);
    }

    /** @return bool true se o pacote foi gravado */
    public function append(PreviewRtpPacket $packet): bool
    {
        if ($packet->payloadType !== 8) return false;
        if ($this->ssrc === null) $this->ssrc = $packet->ssrc;
        if ($packet->ssrc !== $this->ssrc) return false;
        if ($this->pcmBytes + strlen($packet->payload) * 2 > 0xffffff00) {
            throw new \RuntimeException('WAV atingiu limite de 4 GiB; encerre a captura');
        }
        $pcm = '';
        foreach (str_split($packet->payload) as $byte) {
            $pcm .= $this->decode[ord($byte)];
        }
        if ($pcm !== '' && fwrite($this->output, $pcm) !== strlen($pcm)) {
            throw new \RuntimeException('Erro de escrita no arquivo WAV');
        }
        $this->pcmBytes += strlen($pcm);
        return true;
    }

    public function seconds(): float { return $this->pcmBytes / 16000; }

    /** Atualizar header periodicamente deixa o WAV recuperavel ao interromper o processo. */
    public function syncHeader(): void
    {
        $pos = ftell($this->output);
        $header = 'RIFF' . pack('V', 36 + $this->pcmBytes)
            . 'WAVEfmt ' . pack('VvvVVvv', 16, 1, 1, 8000, 16000, 2, 16)
            . 'data' . pack('V', $this->pcmBytes);
        fseek($this->output, 0);
        fwrite($this->output, $header);
        fseek($this->output, $pos);
        fflush($this->output);
    }

    public function __destruct()
    {
        if (is_resource($this->output)) {
            $this->syncHeader();
            fclose($this->output);
        }
    }
}

/** Opcoes simples no formato --chave=valor. */
function previewOptions(array $argv): array
{
    $options = [
        'bind' => '127.0.0.1', 'port' => '40000',
        'duration' => '0', 'mode' => 'auto', 'quiet' => '1',
        'record-wav' => '', 'stats-interval' => '1',
    ];
    foreach (array_slice($argv, 2) as $arg) {
        if (!preg_match('/^--([a-z-]+)=(.*)$/D', $arg, $match) || !array_key_exists($match[1], $options)) {
            throw new \InvalidArgumentException("Opcao desconhecida: {$arg}");
        }
        $options[$match[1]] = $match[2];
    }
    foreach (['port' => [1, 65535], 'duration' => [0, 86400], 'quiet' => [0, 1], 'stats-interval' => [1, 60]] as $key => [$min, $max]) {
        if (!ctype_digit($options[$key]) || (int)$options[$key] < $min || (int)$options[$key] > $max) {
            throw new \InvalidArgumentException("Opcao {$key} invalida");
        }
    }
    if (!filter_var($options['bind'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        throw new \InvalidArgumentException('O listener usa endereco IPv4');
    }
    if (!in_array($options['mode'], ['auto', 'recvfrom', 'into'], true)) {
        throw new \InvalidArgumentException('Modo invalido');
    }
    return $options;
}

/** Executa o receive loop como servidor UDP continuo; grava PCMA em WAV opcional. */
function runListener(array $argv): void
{
    if (!extension_loaded('swoole')) {
        throw new \RuntimeException('Listener requer ext-swoole; use o PHP compilado com Swoole');
    }
    $opts = previewOptions($argv);
    $host = $opts['bind'];
    $port = (int)$opts['port'];
    $duration = (int)$opts['duration'];
    $quiet = $opts['quiet'] === '1';
    $statsInterval = (int)$opts['stats-interval'];
    $recordPath = $opts['record-wav'];
    $stop = false;
    if (function_exists('pcntl_async_signals')) {
        pcntl_async_signals(true);
        pcntl_signal(SIGINT, static function () use (&$stop): void { $stop = true; });
        pcntl_signal(SIGTERM, static function () use (&$stop): void { $stop = true; });
    }

    \Swoole\Coroutine\run(function () use ($host, $port, $duration, $quiet, $statsInterval, $recordPath, $opts, &$stop): void {
        $socket = new \Swoole\Coroutine\Socket(AF_INET, SOCK_DGRAM, SOL_UDP);
        if (!$socket->bind($host, $port)) {
            throw new \RuntimeException("Nao foi possivel bind {$host}:{$port}; erro {$socket->errCode}");
        }
        $channel = new PreviewMediaChannel($socket);
        $channel->setPassiveListener(true);
        $channel->setReceiveMode($opts['mode']);
        $recorder = $recordPath !== '' ? new PreviewWavRecorder($recordPath) : null;
        $loss = 0;
        $outOfOrder = 0;
        $jitterMs = 0.0;
        $streams = [];
        $totalAudioBytes = 0;
        $channel->onDatagram(static function (PreviewParseResult $result, array $peer) use (
            $quiet, $recorder, &$loss, &$outOfOrder, &$jitterMs, &$streams, &$totalAudioBytes
        ): void {
            $remote = ($peer['address'] ?? '?') . ':' . ($peer['port'] ?? '?');
            if ($result->kind === 'rtp' && $result->rtp !== null) {
                $p = $result->rtp;
                if ($p->payloadType === 8) {
                    $totalAudioBytes += strlen($p->payload);
                    $streamKey = $remote . ':' . $p->ssrc;
                    $nowNs = hrtime(true);
                    if (isset($streams[$streamKey])) {
                        $previous = $streams[$streamKey];
                        $delta = ($p->sequence - $previous['seq']) & 0xffff;
                        if ($delta > 0 && $delta < 0x8000) {
                            if ($delta > 1 && $delta < 1000) $loss += $delta - 1;
                            $tsDelta = ($p->timestamp - $previous['timestamp']) & 0xffffffff;
                            if ($tsDelta > 0x7fffffff) $tsDelta -= 0x100000000;
                            $transitDifference = (($nowNs - $previous['arrival']) / 1e9) * 8000 - $tsDelta;
                            $jitter = $previous['jitter'] + (abs($transitDifference) - $previous['jitter']) / 16.0;
                            $streams[$streamKey] = ['seq' => $p->sequence, 'timestamp' => $p->timestamp,
                                'arrival' => $nowNs, 'jitter' => $jitter];
                            $jitterMs = $jitter / 8.0;
                        } else {
                            $outOfOrder++;
                        }
                    } else {
                        $streams[$streamKey] = ['seq' => $p->sequence, 'timestamp' => $p->timestamp,
                            'arrival' => $nowNs, 'jitter' => 0.0];
                    }
                    $recorder?->append($p);
                }
                if (!$quiet) {
                    printf("RTP  %-22s pt=%-3d seq=%-5d ts=%-10u ssrc=%-10u payload=%d B%s\n",
                        $remote, $p->payloadType, $p->sequence, $p->timestamp, $p->ssrc,
                        strlen($p->payload), $p->payloadType === 101 ? ' [DTMF]' : '');
                }
            } elseif (!$quiet) {
                printf("%-7s %s\n", strtoupper($result->kind), $remote);
            }
        });
        $channel->start();
        printf("RTP SERVIDOR %s:%d | recepcao=%s | PCMA/8000 mono | duracao=%s\n",
            $host, $port, $channel->actualReceiveMode(), $duration === 0 ? 'continua' : $duration . 's');
        if ($recorder !== null) printf("Gravando audio recebido em %s\n", $recordPath);
        echo "Resumo periodico ativo; --quiet=0 exibe cada datagrama. Ctrl+C encerra.\n";
        $startNs = hrtime(true);
        $last = $startNs;
        $lastPackets = 0;
        try {
            while (!$stop && ($duration === 0 || hrtime(true) - $startNs < $duration * 1_000_000_000)) {
                \Swoole\Coroutine::sleep((float)$statsInterval);
                $now = hrtime(true);
                $elapsed = ($now - $last) / 1e9;
                $packets = $channel->metrics['rtp'];
                $pps = $elapsed > 0 ? ($packets - $lastPackets) / $elapsed : 0.0;
                printf("RX RTP=%d (%.1f pps) RTCP=%d DTMF=%d invalidos=%d perda_est=%d fora_ordem=%d jitter=%.2f ms audio=%.1fs\n",
                    $packets, $pps, $channel->metrics['rtcp'], $channel->metrics['dtmf'],
                    $channel->metrics['invalid'], $loss, $outOfOrder, $jitterMs, $totalAudioBytes / 8000);
                $recorder?->syncHeader();
                $last = $now;
                $lastPackets = $packets;
            }
        } finally {
            $channel->stop();
            \Swoole\Coroutine::sleep(0.25);
            $recorder?->syncHeader();
        }
        echo "Servidor encerrado.\n";
    });
}

try {
    match ($argv[1] ?? '--self-test') {
        '--self-test' => runSelfTest(),
        '--demo' => runDemo(),
        '--listen' => runListener($argv),
        default => throw new \InvalidArgumentException('Use --self-test, --demo ou --listen'),
    };
} catch (\Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
