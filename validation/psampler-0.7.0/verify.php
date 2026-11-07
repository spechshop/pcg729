<?php
declare(strict_types=1);
function check(bool $ok, string $why): void { if (!$ok) throw new RuntimeException($why); }
$old=json_decode(file_get_contents(__DIR__.'/original-modules.json'), true, 512, JSON_THROW_ON_ERROR);
$modules=get_loaded_extensions(); sort($modules); sort($old);
check($modules === $old, 'Module list changed');
check(PHP_VERSION==='8.5.10' && (bool)PHP_ZTS, 'PHP version/ZTS');
check(phpversion('psampler')==='0.7.0', 'Extension version');
check(!extension_loaded('psampler_binary_test'), 'Fixture leaked into final PHP');
foreach (['on','push','state','reset'] as $m) check(method_exists(AcousticStreamAnalyzer::class,$m), 'Missing '.$m);
$codec=new bcg729Channel;
$encoded=$codec->encode(str_repeat("\0",160));
check(strlen($encoded)===10 && strlen($codec->decode($encoded))===160, 'bcg729 codec');
$opus=new opusChannel(48000,1);
$encoded=$opus->encode(str_repeat("\0",1920));
check(strlen($encoded)>0 && strlen($opus->decode($encoded))===1920, 'opus codec');
$pdo=new PDO('sqlite::memory:');
check($pdo->query('SELECT 42')->fetchColumn()===42, 'SQLite');
$oldHash=trim(file_get_contents(__DIR__.'/original-php.sha256'));
check(hash_file('sha256','/usr/local/bin/php')===$oldHash,'Installed PHP modified');
echo json_encode(['php'=>PHP_VERSION,'zts'=>PHP_ZTS,'psampler'=>phpversion('psampler'),'class'=>AcousticStreamAnalyzer::class,'methods'=>['on','push','state','reset'],'module_count'=>count($modules),'modules'=>$modules,'opus_codec'=>'OK','bcg729_codec'=>'OK','sqlite'=>'OK','installed_php_unchanged'=>true],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),"\n";
