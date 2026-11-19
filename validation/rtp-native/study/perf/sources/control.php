<?php
// Included only by the isolated laboratory receiver, after MediaChannel::start().
$control = new Swoole\Coroutine\Socket(AF_INET, SOCK_DGRAM, SOL_UDP);
if (!$control->bind('127.0.0.1', 40001)) throw new RuntimeException('control bind');
$exeHash = hash_file('sha256', PHP_BINARY);
$factory = new ReflectionMethod(libspech\Rtp\rtpc::class, 'fromNativeBuffer');
$channelReflection = new ReflectionClass($mediaChannel);
$config = [];
foreach (['receiveOnlyMode','nativeRtpReceive','requireNativeRtpParser','settings','audioMetricsEnabled'] as $key) {
    $config[$key] = $channelReflection->getProperty($key)->getValue($mediaChannel);
}
$config['codecMapper'] = $mediaChannel->codecMapper;
$config['members'] = count($mediaChannel->members);
$config['receiveTimeout'] = 0.2; // Also verify/hash the actual source in orchestrator.
$config['options'] = $mediaChannel->options;
$processedProperty = $channelReflection->getProperty('packetsProcessed');
Swoole\Coroutine::create(function () use ($control, $exeHash, $factory, $config, $processedProperty, $mediaChannel,
    &$packets, &$perfDtmfCallbacks, &$perfDtmfEvents, &$perfRealFactoryChecked) {
    while (true) {
        $peer = null;
        $request = $control->recvfrom($peer, -1);
        if ($request === false) return;
        $result = ['label'=>$request, 'monotonic_ns'=>hrtime(true), 'pid'=>getmypid(),
            'binary'=>PHP_BINARY, 'binary_sha256'=>$exeHash,
            'factory_file'=>$factory->getFileName(), 'factory_sha256'=>hash_file('sha256',$factory->getFileName()),
            'real_packet_factory_verified'=>$perfRealFactoryChecked,
            'config'=>$config, 'rtp'=>$processedProperty->getValue($mediaChannel),
            'audio'=>$packets, 'dtmf_callbacks'=>$perfDtmfCallbacks, 'dtmf_events'=>$perfDtmfEvents,
            'rusage'=>getrusage()];
        $control->sendto($peer['address'], $peer['port'], json_encode($result, JSON_THROW_ON_ERROR));
    }
});
