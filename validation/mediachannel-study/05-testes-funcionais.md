# Testes funcionais

Binário único: `buildroot/bin/php`, PHP8.5.12 ZTS, Swoole6.3.0RC1, psampler0.7.0. Hash e ambiente em manifests/environment.json. Sockets UDP reais em loopback e portas efêmeras. Nenhum OpenSwoole ou servidor SIP externo.

| Teste executado | Resultado e limite |
|---|---|
| check.php dtmf | Baseline falha END único e wrap65535→0→1, candidate passa; triple END exatamente um callback. Evento5, volume10,duration1280, peer real para contexto, processDtmf invocado por Reflection com Swoole real. Não é prova de todos eventos A–D. |
| check.php idle | Baseline falha timeout ocioso fallback; candidate passa. connectTimeout0, janela .45s. |
| check.php invalid | Datagram1B: baseline autojoin/callback, candidate descarta. Não cobre todo fuzz de transporte. |
| check.php reentrant | close dentro onReceive passa em ambos. Não cobre callback que cede execução nem exceções. |
| flows.php | RTCP tipo200 mínimo, relay PCMA, reordenação1,3,2,4, rebind sem backlog, remoção pública, close repetido e socket isClosed passam. Perda cumulativa=1 mesmo após chegada2: métrica não corrige tardios. |
| ptime-wire.php | Baseline PCMA payload320B avança160 samples, reproduz inconsistência. |
| ptime-frame.php | PCMA e PCMU: baseline falha [320]; candidate passa [160,160] via pacer. |
| test_rtpc_native.php | Passa baseline/candidate/optimization: flags, CSRC/ext malformed, owned bytes, serialização, referências, clone e rawPacket lazy. Compatibilidade não significa validade RTP. |
| test_pcm_stream_converter.php | Passa nas três variantes; consultar logs para taxas/canais realmente executados. |
| test_audio_file_playback_resample.php | Passa nas três variantes, incluindo estéreo, rates, pause/resume, interrupção, ptime assíncrono, buffer bounded3000 frames e isolamento. Isso valida AudioFilePlayback/conversor, não todos fluxos RX da MediaChannel. |
| test_media_channel_member_ptime.php | Falha preexistente em socket mock/cancel. Não declarar suite aprovada. |
| test_member_adapted.php | Só mock cancel adaptado; falha posterior gsmDecodedPcm esperado320/obtido0. Assertivas anteriores executadas até ali; teste completo continua falhando. |
| acoustic-parity.php | Dois segundos de tom425Hz PCM, aligned320B, fragmented137B e multi960B: estados e eventos baseline/protótipo iguais, removendo tempos monotônicos. Sem mudança na política/qualidade. Não equivale corpus de fala real. |
| bench.php | Cinco cenários RTP com acústica ligada:50 entradas/run×5 repetições×2 variantes, salvo micro. PCMA→PCMU em transcode; DTMF ocupa seq20..22; total50 com perda de rede0. |
| scale.php / fds.php / socket-control.php | Retenção de FDs durante reactor; controle sockets isolados fecha corretamente; após reactor descriptors desaparecem. |

Reprodução a partir da raiz do projeto:

```sh
python3 validation/mediachannel-study/tests/run.py
python3 validation/mediachannel-study/tests/bench-run.py
python3 validation/mediachannel-study/tests/perf-run.py
python3 validation/mediachannel-study/tests/micro-corrected.py
buildroot/bin/php validation/mediachannel-study/tests/flows.php validation/mediachannel-study/lab/candidate
buildroot/bin/php validation/mediachannel-study/tests/ptime-frame.php validation/mediachannel-study/lab/candidate
```

Os scripts check.php e ptime-frame.php retornam1 em falha funcional e0 em sucesso. run.py preserva falhas esperadas da baseline em JSON; não transforma baseline falha em sucesso. Suites originais podem ser executadas dentro de lab/variant como cwd. Scripts nunca precisam .env de produção; as cópias possuem .env vazio.

Pendências de contratos: exceções onReceive/onStart/onDtmf/onDestruct e consumidor acústico; fechamento com pacer sleep e timer externo ativo; restart do mesmo objeto; transporte rebind com backlog/colisão; DTMF incompleto, duração regressiva, eventos consecutivos e chamadas send2833 simultâneas; Opus/G729/GSM bidirecionais e prolongados em rede; RTP padding/ext e RTCP composto; waveform bit-exata após repacketização; corpus acústico de voz/música/ruído e carga concorrente de áudio. Estas lacunas impedem homologação para produção e cumprimento integral da matriz solicitada. Sem alegar cobertura por simples leitura.

## Ampliação final de rede e timers

`codec-matrix.php` executado em baseline e candidate: PCMA→PCMA, PCMU→PCMA, G729/PT18→PCMA, GSM/PT3→PCMA e Opus/PT111/48k→PCMA, um pacote por combinação, todos produziram RTP172B e encerraram por Timer::after ativo. Tap ligado: PCMU/G729/Opus usam shared_pcm_packets=1; PCMA faz decode próprio; GSM é explicitamente não suportado pelo tap, registra descontinuidade e audio_ms0. Opus primeiro chunk deixa310B pendentes devido FIR, portanto não comprova classificação acústica completa20ms. Isso não é stress prolongado nem cobre codec remapeado G729 ou matriz todos→todos. Dados em codec-matrix.json.

Candidate foi novamente executada após a última correção em transcode e DTMF, sem perdas de rede e com um callback DTMF. As suites originais foram repetidas; falha preexistente de fixture permanece. Esses ensaios são regressão funcional, excluídos dos ganhos CPU.
