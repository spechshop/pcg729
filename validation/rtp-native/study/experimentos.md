# Experimentos controlados do parser RTP

## Ambiente e reprodução

Executados em Linux x86-64, PHP 8.5.12 ZTS, Swoole 6.3.0RC1 e psampler 0.7.0 experimental. Inventário completo: environment.txt, incluindo CPU, extensões, hashes, reflexão, formato ELF e símbolos. Compilação -Os e -g, estática e sem strip. Não foi usado php-config 8.4.

Comandos a partir da raiz:

    bin/spc build --build-cli "bcg729,psampler,opus,swoole,sockets,filter,standard" --enable-zts --no-strip
    python3 validation/rtp-native/study/run-bench.py
    python3 validation/rtp-native/study/run-udp.py
    python3 validation/rtp-native/study/run-tests.py
    bash validation/rtp-native/study/environment.sh

Scripts não executam perf. run-bench verifica hashes distintos e símbolo rtp_property_names antes de medir. Preservamos php-baseline e binary_buffer.baseline.c; optimization.patch registra a mudança exata. run-tests registra falhas em JSON: seu exit zero como coletor NÃO significa que todos os testes passaram.

## Incidente de instalação do binário e controle A/A

O SPC terminou com sucesso em 99,324 s e compilou o novo objeto/ELF em source/php-src/sapi/cli/php. Contudo, o destino buildroot/bin/php manteve o hash anterior. nm/objdump confirmaram que ainda chamava zend_update_property_long. A primeira rodada, preservada integralmente em control-aa/, comparou duas cópias do baseline. Rótulos “interned” nessa pasta NÃO significam otimização efetiva.

O fonte do builder em src/SPC/builder/unix/UnixBuilderBase.php executa cp; UnixShell suprime stdout/stderr sem debug; src/globals/functions.php, f_passthru, ignora retorno não zero (throw comentado). Isso permite mensagem de sucesso apesar de falha de comando. A causa específica da cópia malsucedida não foi recuperada do log e não é atribuída sem evidência.

Instalação local corrigida após a compilação:

    cp source/php-src/sapi/cli/php buildroot/bin/php.rtp-study-new
    mv buildroot/bin/php.rtp-study-new buildroot/bin/php

Hashes de origem/destino ficaram iguais; símbolo rtp_property_names e chamadas a zend_update_property_ex foram confirmados. Todas as comparações A/B e testes finais foram repetidos. O controle A/A serve para visualizar ruído, não para concluir ausência de benefício.

## Microbenchmark da fábrica

bench.php: Swoole Buffer preenchido uma vez por UDP fora do intervalo cronometrado; conteúdo e tamanho fixos. Warmup de 5.000 operações, seguido de 60.000 operações por amostra; nove processos por combinação; afinidade CPU 2; ordem baseline/interned alternada. Três payloads: 4, 160 e 1200 bytes, cabeçalho RTP fixo de 12 bytes. Nenhum tráfego MediaChannel durante medição.

cpu_ns soma usuário+sistema de getrusage dividido por operações. wall_ns e throughput recíproco de cpu_ns estão em bench.json. Construção e destruição dos objetos entram no tempo; alocação/preenchimento inicial do produtor e I/O não entram. Sem JIT/opcache adicional configurado pelo script. O sistema não foi isolado de todas as tarefas externas, frequência/turbo não foram fixados; dispersão é publicada sem remoção de outliers.

Modos:
- legacy: construtor com string previamente existente; controle do parser PHP, sem custo de adquirir/copiar do buffer.
- native: fábrica fromNativeBuffer; inclui objeto, borrow, interpretação, hidratação, snapshots e destruição.
- raw: native mais leitura imediata de rawPacket.
- slice: slice integral do mesmo buffer seguido do construtor legado.

legacy e native têm entradas de ownership distintas. Não apresentar essa comparação como custo total da recepção. slice é o controle de transporte mais próximo, mas materializa raw imediatamente e não é substituto compatível do estado lazy.

### Todos os resultados da rodada A/B

Tempos em ns de CPU por operação. DP amostral; mediana, mínimo e máximo de nove amostras.

| Binário | Modo | Payload | Mediana | Mínimo | Máximo | DP |
|---|---|---:|---:|---:|---:|---:|
| baseline | legacy | 4 | 426.43 | 421.88 | 444.13 | 6.60 |
| interned | legacy | 4 | 425.80 | 423.43 | 443.23 | 6.09 |
| baseline | native | 4 | 570.45 | 564.25 | 589.05 | 7.74 |
| interned | native | 4 | 405.18 | 399.20 | 424.73 | 7.96 |
| baseline | raw | 4 | 711.40 | 703.22 | 734.95 | 10.13 |
| interned | raw | 4 | 553.37 | 546.15 | 626.40 | 24.86 |
| baseline | slice | 4 | 541.20 | 537.78 | 546.20 | 3.26 |
| interned | slice | 4 | 538.23 | 534.03 | 548.00 | 4.48 |
| baseline | legacy | 160 | 426.35 | 421.83 | 432.25 | 3.21 |
| interned | legacy | 160 | 426.33 | 422.12 | 430.50 | 2.60 |
| baseline | native | 160 | 570.72 | 564.33 | 579.18 | 5.47 |
| interned | native | 160 | 409.97 | 406.75 | 418.15 | 3.71 |
| baseline | raw | 160 | 727.05 | 717.77 | 732.63 | 5.08 |
| interned | raw | 160 | 563.68 | 560.73 | 576.57 | 4.82 |
| baseline | slice | 160 | 546.27 | 542.12 | 555.87 | 5.08 |
| interned | slice | 160 | 544.83 | 539.40 | 592.55 | 16.24 |
| baseline | legacy | 1200 | 435.53 | 431.25 | 440.18 | 2.56 |
| interned | legacy | 1200 | 433.98 | 428.58 | 440.10 | 3.72 |
| baseline | native | 1200 | 573.25 | 571.27 | 592.72 | 6.93 |
| interned | native | 1200 | 413.50 | 410.87 | 426.17 | 4.63 |
| baseline | raw | 1200 | 731.38 | 726.62 | 738.50 | 4.15 |
| interned | raw | 1200 | 573.70 | 569.55 | 613.25 | 14.17 |
| baseline | slice | 1200 | 558.60 | 549.90 | 589.55 | 11.60 |
| interned | slice | 1200 | 554.82 | 551.30 | 990.15 | 144.81 |

### Interpretação e resultados negativos

Para 160 bytes, native passa de 570,72 para 409,97 ns/op: redução de 160,75 ns ou 28,17% NO CUSTO MEDIDO DA FÁBRICA. Throughput recíproco aproximado: 1,75 para 2,44 milhões de operações/s de CPU. Não é capacidade de recepção UDP nem economia de CPU total.

Os controles legacy são praticamente iguais (426,35 e 426,33 ns), enquanto native melhora de forma consistente nos três tamanhos. Isso sustenta atribuir o efeito à hidratação modificada, sem afirmar decomposição exata de cada ciclo.

O nativo baseline perde para slice e legacy nessas condições. “Mais C” não foi suficiente. Após internar nomes, native supera slice sem raw. Quando raw é lido imediatamente, native otimizado custa 563,68 ns contra 544,83 ns do slice para 160 bytes. Portanto a arquitetura lazy não é vencedora universal: preservar o contrato e favorecer o consumidor que não lê raw é a decisão.

O outlier de slice/interned/1200 (990,15 ns) foi mantido. Máximo e DP deixam seu efeito visível. A rodada A/A também contém outliers e diferenças pequenas sem alteração de implementação.

bench.json registra memory_delta e peak. Essas métricas do Zend por processo não contam alocações transitórias, não equivalem a RSS, e não provam ausência de leaks. A redução de dez nomes transitórios é evidência estática do código, não medição de malloc. Não foi estimada economia percentual de memória.

## Ensaio UDP separado

udp.php mede 1.500 trocas sequenciais locais após warmup de 200; sete repetições por modo, ordem alternada e CPU 2. Mesmo pacote PCMA de 160 bytes, peers, timeout 0,2 s e sockets. Cada operação inclui envio e recepção no mesmo processo; fila já pronta favorece caminho sem suspensão. Isso NÃO isola exclusivamente a CPU do receptor e NÃO representa milhares de chamadas esparsas.

Modos buffer/string apenas recebem; sufixo parse acrescenta a respectiva fábrica/construtor. Somente binário final. Dados em udp.json; resumo em udp-summary.csv.

| Modo | Mediana ns/troca | Mínimo | Máximo | DP |
|---|---:|---:|---:|---:|
| buffer | 3003.33 | 2974.67 | 3225.33 | 88.08 |
| buffer-parse | 3584.67 | 3518.00 | 3823.33 | 117.44 |
| string | 2981.33 | 2920.67 | 2989.33 | 24.16 |
| string-parse | 3642.67 | 3599.33 | 3858.00 | 96.03 |

A diferença entre buffer e string é pequena frente à dispersão; não há base para declarar superioridade universal da recepção Into. As diferenças com parser não devem ser subtraídas como uma decomposição exata: alocador, cache e execução se afetam. O experimento mostra a escala do custo I/O, não ganho final da MediaChannel.

## Compatibilidade e ptime

Resultados completos em tests.json, baseline-tests.json, phpt.txt e media-*.txt.

- Baseline e final: 773 casos equivalentes ao legado e 31 divisões de ring; snapshot, propriedades, referências, clone, serialize e build.
- Baseline e final: 517 casos com Swoole Buffer e UDP; limites estritos, extensão, padding e reutilização.
- Baseline e final: MediaChannel com dois peers, PCMA→PCMU, callbacks, RFC 4733 e forwarding.
- contracts.php: referências já existentes nos campos hidratados, hook PHP 8.5, __set, TypeError, reentrância e limpeza de source durante setter; 3.000 entradas pseudoaleatórias com seed 729 comparadas via serialização ao legado.
- zts.php: quatro threads Swoole com requests/buffers próprios, cada uma executa contracts.php; join e status de saída verificados. PASS nos dois binários. Não é teste de objetos compartilhados nem detector de data races.
- PHPT selecionados: quatro PASS, zero FAIL, dezesseis SKIP por ausência de swoole_buffer_test ou psampler_binary_test. Não tratar skips como cobertura de bailout/guards.
- media-compare.php: ambos os modos com receiveOnlyMode(false), mesmo transporte Into/timeout 0,2, mapas, peers, probes e callbacks; seleção comprovada pelo estado real de rawPacket. Passaram áudio/transcoding/DTMF.

ptime original falha nos dois binários em MediaChannel::close, ao chamar cancel sobre mock de socket cujo construtor é vazio. Uma cópia isolada ptime-fixture.php chama o construtor pai: passa dessa etapa, mas revela falha posterior nos dois binários (“callback ... único decode GSM”, esperado 320, obtido 0). O teste consulta gsmDecodedPcm no callback; no fonte atual, callback é anterior ao relay e a chave só aparece em unset, sem atribuição correspondente. Evidência aponta fixture/expectativa desatualizados, não regressão desta mudança. Produção e teste original ficaram intactos; não foi alegado PASS de ptime completo.

Valgrind não existe no PATH nem no caminho temporário usado pela validação histórica. A tentativa ficou em memcheck-output.txt/memcheck-exit.txt (127). Não houve ASan/TSan nem verificação efetiva de erros de memória. Testes de ownership e threads são evidência funcional limitada, não substitutos dessas ferramentas.

## Comparação futura com MediaChannel

receiver.php oferece RTP_STUDY_MODE=native ou slice no mesmo código receptor. Ambos usam Into, requireNativeRtpParser, receiveOnlyMode(false), timeout interno 0,2, mesmos peers/codec/probes/timer/callback. O modo slice usa uma cópia isolada do autoloader para selecionar rtpc-slice.php. Startup verifica a fábrica com pacote conhecido e Reflection; não confia no nome impresso.

    RTP_STUDY_MODE=native buildroot/bin/php validation/rtp-native/study/receiver.php
    RTP_STUDY_MODE=slice buildroot/bin/php validation/rtp-native/study/receiver.php

Para isolar a alteração dos nomes, usar modo native nos binários php-baseline e buildroot/bin/php, com o MESMO tráfego do emissor. Para comparar arquitetura, native/slice muda apenas o processamento a partir do buffer, inclusive ownership/lazy. Esse controle não é o recvfrom tradicional.

Receptor preparado e lintado; seu loop contínuo não foi usado como benchmark. A versão finita media-compare.php foi executada. O receptor de laboratório tem apenas um membro: não prova transcoding por pacote; media-compare usa dois. Manter distribuição áudio/DTMF, tamanho, intervalos, callbacks e duração iguais na avaliação externa. Contador de áudio não inclui DTMF; lacunas de sequência nesse callback não são automaticamente perdas.

mc.php e lsend.php não foram modificados. Não houve perf nem carga massiva.

### Correção do harness

A primeira tentativa do modo slice falhou por classe redeclarada: o autoloader da libspech inclui todos os arquivos diretamente, mesmo com rtpc já carregado. Log preservado em media-slice-initial-failure.txt. A correção ficou exclusivamente em study/autoloader.php, cópia do loader que seleciona o arquivo de rtpc na própria inclusão. Nenhum autoloader de produção foi alterado. Ambos os modos foram repetidos com saída zero. RTP_STUDY_CHECK=1 verifica a fábrica do receptor sem iniciar seu loop.
