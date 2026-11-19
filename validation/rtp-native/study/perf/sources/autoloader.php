<?php
// Isolated copy: same eager loader, only rtpc source selection differs.
$rtpStudyPluginDir = dirname(__DIR__, 3).'/libspech/plugins';
$rtpStudyUseSlice = getenv('RTP_STUDY_MODE') === 'slice';

$envFile =  '.env';
if (!file_exists($envFile)) {
    if (file_exists('.env.example')) {
        copy('.env.example', $envFile);
    } else {
        throw new Exception("Arquivo .env não encontrado e .env.example também não existe.");
    }
}
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            [$key, $value] = explode('=', $line, 2);
            putenv(trim($key) . '=' . trim($value));
        }
    }
}





$interface = json_decode(file_get_contents($rtpStudyPluginDir . '/configInterface.json'), true);
$paths = $interface['autoload'];
$allowObservable = $interface['reloadCaseFileModify'];
$nameFiles = [];
$cachePages = [];
foreach ($paths as $path) {
    $directory = new DirectoryIterator($rtpStudyPluginDir . "/$path");
    foreach ($directory as $fileInfo) {
        $nameFile = $fileInfo->getFilename();
        if (strlen($nameFile) > 2) {
            $nameFiles[] = (strlen($path) > 1) ? $rtpStudyPluginDir . '/' . $path . "/" . $nameFile : $nameFile;
        }
    }
}
foreach ($nameFiles as $key => $file) if (!str_contains($file, 'vendor')) include ($rtpStudyUseSlice && $file === $rtpStudyPluginDir.'/Utils/sip/rtpc.php'
    ? __DIR__.'/rtpc-slice.php' : $file);