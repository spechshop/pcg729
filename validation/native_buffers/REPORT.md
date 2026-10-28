> Complemento de 28/10/2026: o cleanup nativo de `recvfromInto()` foi corrigido e revalidado. Ver [relatório de lifecycle](lifecycle/REPORT.md), que distingue os pins nativos das referências de frames/trace mantidas até o teardown fatal. Este documento preserva os resultados anteriores; o defeito PCM e as perdas gerais do ambiente continuam pendentes.

# Validação técnica dos buffers nativos

Execução local em 28 de outubro de 2026, no repositório `spechshop/pcg729`.

**A infraestrutura ainda tem problemas bloqueantes de lifecycle.** Foram reproduzidos dois caminhos de bailout que deixam guards ativos: atribuição de `$peer` em `recvfromInto()` e execução de um handler da API PCM v1. Os casos funcionais normais, a regressão existente e os empréstimos pela API C do Swoole passaram. Memcheck também encontrou retenção de uma coroutine e stack de aproximadamente 2 MiB em fatal, reproduzida no controle sem empréstimo, além de perdas nos globals de requests ZTS. As medições não justificam afirmar um ganho consistente do caminho UDP nativo; `appendBuffer()` tem overhead frente a uma string que já existe.

A ponte de produção `psampler_swoole` não foi implementada. `supportsNativeBuffer(new Swoole\Buffer(2048)) === false` no PHP original é o resultado esperado.

## Ambiente e fidelidade dos executáveis

- `buildroot/bin/php`: PHP 8.5.10 ZTS, Swoole 6.3.0RC1 modificado, psampler 0.6.1, Linux x86_64, musl, executável estático. OPcache CLI desligado; JIT desabilitado; `memory_limit=128M`.
- Intel Core i5-13450HX, 16 CPUs lógicas. Receptor fixado na CPU 2 e emissor na CPU 4, cores físicos distintos. Frequência dinâmica e carga de desktop não foram controladas.
- SHA256 do PHP original antes/depois: `17319dac0290c0ed7f01b54bc9164f2520733da397426b45000e6d046b59f71d`.
- PHP original estático recusa carregar `.so` com “Dynamic loading not supported”. As fixtures foram compiladas com a toolchain `/usr/local/musl/bin/x86_64-linux-musl-gcc`, headers do build, e incorporadas em executáveis isolados por **relink dos objetos existentes**, sem recompilar o PHP inteiro ou substituir o binário original.
- O relink de diagnóstico resultou em executáveis PIE com loader musl dinâmico e libstdc++/libgcc dinâmicos; módulos PHP/Swoole/psampler vêm dos objetos existentes. Isso difere da linkagem do PHP original e é uma limitação de fidelidade explícita, inclusive no benchmark ByteBuffer. A comparação append/appendBuffer usa o mesmo executável; seu hash medido está em [benchmark-fixture-sha256.txt](results/benchmark-fixture-sha256.txt).
- `php-fixtures`: produtor nativo isolado, leitor Swoole e consumidor PCM de teste; sem provider Swoole registrado na psampler. `php-contract`: acrescenta somente o adaptador de contrato já preparado na fixture Swoole. `php-no-swoole`: tabela de módulos sem Swoole, para o teste independente.
- Os sete arquivos críticos da psampler examinados no checkout externo e na árvore do build são idênticos; hashes em [source-hashes.json](results/source-hashes.json). Os benchmarks UDP usam o PHP original; os de ByteBuffer usam o mesmo executável de fixtures para ambos os métodos.

Não houve commit, release, acesso à produção, mudanças em `libspech`/`sipswoole`, alterações no código de produção ou implementação da ponte definitiva.

## Inventário e resultados funcionais

| Grupo | Aprovados | Reprovados | Pulados | Evidência |
| --- | --- | --- | --- | --- |
| psampler no PHP original | 21 | 0 | 6 | [PHPT original](results/phpt-original.txt) |
| Swoole mais psampler com fixtures, sem adaptador | 32 | 0 | 4 | [PHPT fixtures](results/phpt-fixtures.txt) |
| Nove PHPTs Swoole com adaptador de contrato | 9 | 0 | 0 | [PHPT contrato](results/phpt-contract.txt) |
| psampler sem Swoole | 1 | 0 | 0 | [PHPT sem Swoole](results/phpt-no-swoole.txt) |
| PHPTs suplementares | 6 | 2 | 0 | [PHPT adicionais e diffs](results/phpt-additional.txt) |
| Processos isolados com conferência de exit/sinal | 15 | 2 | 0 | [isolated.json](results/isolated.json) |

As linhas se sobrepõem e **não devem ser somadas**. Dos 36 PHPTs preparados, 34 foram efetivamente cobertos entre as configurações; os dois restantes exigem a futura extensão `psampler_swoole`. Dos oito PHPTs novos, seis passaram e dois preservam reproduções de defeitos, sem XFAIL. As saídas 255 dos testes de fatal são deliberadas; nenhum dos 17 processos da rodada isolada recebeu SIGSEGV, SIGABRT ou outro sinal. Uma saída fatal com cleanup ausente foi classificada como falha.

`testb.php` e `testb2.php cancel` executam diretamente e confirmaram integridade, scheduler, timeout e recepção posterior ao cancelamento. `testb2.php concorrencia` termina com 255 e a proteção fatal documentada no Swoole; a rejeição não é capturável por `try/catch` neste build. O PHPT suplementar verifica essa mensagem em processo isolado. Isso não é aprovação de um crash inesperado.

Os PHPTs de APIs C exigem fixtures. `psampler_contract.phpt` exige a variante de contrato; ela não constitui a ponte de produção. `binary_provider_*` usa o produtor isolado. Os testes `*_swoole_future.phpt` e `binary_provider_swoole_guards_future.phpt` permanecem dependentes da futura extensão e não foram considerados aprovados.

### UDP e corrotinas

| Caso | Comportamento observado | Resultado |
| --- | --- | --- |
| 0, 1, 12, 160, 1024 e 2048 bytes | Comprimento inteiro correto, conteúdo binário incluindo NUL, peer com endereço e porta exata do emissor | Passou |
| Capacidade exata 2048 | Aceita 2048; capacidade pública não inclui byte privado de overflow | Passou |
| 2049, 4096 e 65507 bytes | `false`, `EMSGSIZE`, length zero, capacidade 2048 e peer anterior preservado; pacote seguinte funciona | Passou |
| Vazio versus erro | Datagramas vazios retornam inteiro `0` com peer; timeout/cancelamento retornam `false` | Passou |
| Reutilização funcional | 10000 datagramas binários com sequência e comparação integral no mesmo objeto | Passou |
| Scheduler cooperativo | Outra coroutine avança enquanto receive aguarda | Passou |
| Timeout | `ETIMEDOUT`, peer preservado, length zero e nova leitura utilizável | Passou |
| Cancelamento | `ECANCELED=125`, liberação de receive e recepção seguinte correta | Passou |
| Mesmo buffer em outro socket enquanto pendente | Error antes de alterar peer ou armazenamento | Passou |
| Duas leituras no mesmo socket | Fatal de concorrência nativo do Swoole em processo isolado | Rejeição confirmada |
| Exceção recuperável no destrutor de peer | Guard liberado; clear, leitura C e novo receive funcionam | Passou |
| Bailout no destrutor de peer | Guard e referência adicional permanecem no shutdown | **Falhou** |

O contrato distingue preservação de conteúdo durante uma **rejeição por empréstimo** de erro de recepção: no primeiro caso bytes/length/peer ficam intactos e o datagrama permanece enfileirado; truncamento e timeout deixam length zero, conforme a implementação. O método legado `recvfrom()` devolve string vazia para datagrama vazio; o código legado não atualiza peer nesse ramo. As medições usam apenas payloads positivos, com o mesmo tratamento de peer em ambos os caminhos.

### API C do Swoole

Confirmados por execução: versão 1, tabela de 40 bytes, view de 40 bytes, rejeição de versão/tamanho incompatíveis, objeto errado, handlers falsos com a mesma classe e objeto não construído; acquire/release reais; slices completos, vazios no fim e parciais; negativos e extremos `PHP_INT_MAX`; integridade por checksum antes/depois do callback; rejeição de aquisição recursiva; tokens NULL, antigos e duplicados; liberação balanceada; destruição via WeakReference; remoção do último dono PHP durante callback; reutilização após exceção/falha/release.

Os três símbolos aparecem como símbolos globais `T` na tabela ELF normal e resolvem no relink C: `swoole_buffer_read_api_version`, `swoole_buffer_get_read_api`, `swoole_buffer_with_read_view_v1`. O ELF original estático não tem tabela dinâmica utilizável; na variante de diagnóstico, os três símbolos também aparecem na dynsym. Não se afirma validação de `dlsym`/DLL ou carregamento de módulos PHP compartilhados. `binary_provider=40` e `binary_view=56` bytes no mesmo ABI. Evidência em [abi-sizes.txt](results/abi-sizes.txt).

## Segurança de memória e lifetime

### Exclusão e mudanças de contexto

A fixture confirmou que `clear()`, reconstrução, receive com datagrama já enfileirado e aquisição recursiva são recusados durante leitura; os bytes e o peer não mudam. Outros buffers continuam utilizáveis. A referência mantida pelo wrapper e a referência adicional do produtor sobreviveram à retirada do último dono PHP e foram devolvidas no retorno.

`Fiber::start()` e `Fiber::suspend()` são bloqueados durante empréstimo Swoole. Guards aninhados não desbloqueiam prematuramente o guard externo; a Fiber volta a suspender normalmente após release. Criação de coroutine e suspensão por sleep durante empréstimo geram fatal **antes da troca**, e os testes verificaram cleanup no shutdown. Um callback ocupado por 50 ms com preempção automática ativada não cedeu enquanto emprestado; outra coroutine voltou a avançar após release.

A API binária genérica da psampler exige consumidor C síncrono que não suspenda. O provider ByteBuffer não possui o bloqueio Zend/Swoole do produtor Swoole. Essa restrição é contratual; não se deve estender a todos os providers a garantia de bloqueio demonstrada para `Swoole\Buffer`.

Em ZTS, quatro threads executaram 40000 ciclos com objetos independentes, protocolos de tokens, checksum e appendBuffer; todas concluíram. A transferência de Buffer/ByteBuffer pelos argumentos de `Swoole\Thread` foi rejeitada pela serialização. Isso não testa nem autoriza passar um `zend_object*` cru entre threads: não há mutex no guard, e acquire não substitui a regra de ownership do request. Release do Swoole verifica a thread da aquisição; os tokens/contadores usam TLS e o registry da psampler fica imutável durante requests.

### Falha bloqueante no receive

Reprodução: [udp_peer_bailout.phpt](udp_peer_bailout.phpt), implementação [peer_bailout.php](peer_bailout.php).

```text
GUARD LEAK: Swoole\Buffer has a pending receive
destroyed=no
```

O receive foi bem sucedido, mas ao substituir o valor anterior de `$peer`, seu destrutor chamou `trigger_error(..., E_USER_ERROR)`. O bailout Zend salta para fora do frame C++; o cleanup existe somente no destrutor RAII de `ReceiveGuard`. No shutdown, `clear()` encontra `receiving` ainda ativo e a WeakReference continua viva mesmo depois de retirar o dono externo. A retenção observada é anterior ao teardown final do request; não foi quantificada como vazamento persistente entre requests.

Gravidade alta para o critério de lifecycle desta etapa: a regra de liberação em todos os caminhos falha em uma reentrada PHP real. Não houve segfault reproduzido e isso não demonstra UAF. A causa foi examinada em `source/php-src/ext/swoole/ext-src/swoole_socket_coro.cc`, no guard e na atribuição `ZEND_TRY_ASSIGN_VALUE`. O stack do [GDB](results/peer-bailout-gdb.txt) confirma o bailout iniciado pelo destrutor do peer. O CLion não tinha configuração ou ponto de execução nativo disponível; foi usado GDB batch como fallback. Os objetos Swoole não têm informação de linha suficiente para inspecionar diretamente o guard; a evidência de estado vem do teste PHP no shutdown.

Correção proposta: proteger as operações falíveis da recepção/atribuição com `zend_try/zend_catch`, controlar a posse da metadata local e tornar explícita e idempotente a devolução dos dois pins e do estado `receiving`; repropagar o bailout, sem tentar continuar o request. Repetir fatal, exceção, cancelamento, peer com destrutor e falha de alocação. A alteração não foi aplicada porque envolve o modelo de ownership e bailout.

### Falha na API PCM v1

Reprodução: [pcm_handler_bailout.phpt](pcm_handler_bailout.phpt).

```text
PCM GUARD LEAK: PcmBuffer cannot be modified or invoked recursively during invoke()
```

`PcmBuffer::invoke()` ativa `invoking`, chama o handler C e só desativa o flag no retorno normal. Bailout dentro do handler pula essa instrução; no shutdown, `clear()` continua recusado. Exceções PHP recuperáveis passaram e liberaram o guard. Trata-se de um defeito da API atual detectado na validação, sem evidência para atribuí-lo especificamente à nova ABI binária.

Correção proposta: cleanup do guard em `zend_catch`, inclusive descarte correto do resultado parcial e repropagação do bailout. Não foi aplicada automaticamente por ser mudança de lifecycle da API nativa. O dispatcher **binário** passou no teste equivalente de bailout, com contadores acquire/release `[1,1,false]` e nova cópia funcionando no shutdown.

### Instrumentação e limites

ASan e UBSan instrumentaram os testes C independentes `pcm_stream_asan`, `pcm_analyzer_asan` e `pcm_cache_threads`, juntamente com os núcleos que exercitam. Passaram: 200 rodadas de quatro streams; 100000 chamadas do analyzer; oito threads no cache FIR. A rodada final inteira está em [asan-final.txt](results/asan-final.txt). Esses binários usam glibc e não cobrem o heap PHP; essa cobertura vem do Memcheck descrito a seguir.

O PHP original estático musl reportou zero alocações no Valgrind, inclusive com uma string de 1 MB; esses probes não são aprovação. Na variante de diagnóstico com runtime musl dinâmico, `--soname-synonyms=somalloc=*` e `USE_ZEND_ALLOC=0` fizeram a interceptação funcionar: o probe reconheceu 82176 alocações e 19461693 bytes acumulados. Isso instrumenta o processo inteiro, incluindo os objetos PHP/Zend/Swoole/psampler e bibliotecas, e não somente a fixture.

Foram executados **24 casos sob Memcheck**: os nove PHPTs Swoole, sete casos binários/ring psampler, integridade UDP com 10000 reutilizações, exceptions/bailouts, Fiber/preempção, PCM e quatro threads ZTS. **17 concluíram com zero erros**; sete terminaram com 97 por leaks, sem sinais. Nenhum log apontou Invalid read/write/free, double free ou uso de valor não inicializado. Não se afirma ausência universal de defeitos a partir disso.

O primeiro modo `USE_ZEND_ALLOC=0` perde a liberação automática do request em fatal. Para separar esse efeito de vazamentos C reais, os casos de fatal foram repetidos com **`USE_TRACKED_ALLOC=1`**, suportado pelo Zend deste build, e controles equivalentes sem empréstimo. Esse modo mantém alocações de payload/objetos visíveis ao Memcheck e conserva a limpeza automática dos blocos Zend no final do request. Não foi aplicada supressão de leaks ou de acesso inválido.

| Memcheck com alocações rastreadas | Definitivamente perdidos B | Indiretamente perdidos B | Possivelmente perdidos B | Resultado |
| --- | --- | --- | --- | --- |
| Bailout direto no leitor Swoole | 0 | 0 | 0 | Guard e memória liberados |
| Fatal de criação de coroutine durante empréstimo | 0 | 0 | 0 | Guard e memória liberados |
| Fatal de suspensão durante empréstimo | 168 | 2097152 | 0 | Reprovado no critério de leak |
| Bailout do leitor binário psampler | 0 | 0 | 0 | Guard e memória liberados |
| Bailout de peer durante receive | 168 | 2097152 | 0 | Reprovado; guard também permanece ativo |
| Bailout de handler PCM | 0 | 0 | 0 | Sem leak final, mas guard permanece ativo no shutdown |
| Fatal PHP sem coroutine e sem empréstimo | 0 | 0 | 0 | Controle limpo |
| Fatal em coroutine sem empréstimo | 168 | 2097152 | 0 | Mesmo leak de coroutine do controle |
| Quatro threads com buffers | 8320 | 0 | 0 | Leak em globals das extensões; não aprovado como execução sem leaks |
| Quatro threads sem operações de buffer | 8104 | 0 | 216 | Controle também perde 8320 B somando definite e possible |

O leak de aproximadamente 2 MiB tem stack em `swoole::Coroutine::create` e persiste no controle de fatal **sem empréstimo**. Portanto há um problema de teardown de coroutine neste ambiente, sem evidência para atribuí-lo à nova ABI. O PHPT de suspensão passa suas asserções de guard, mas não recebeu aprovação irrestrita de memória. A proposta é auditar a destruição da coroutine/stack no bailout antes de aprovar o lifecycle do scheduler.

O teste ZTS instrumentado teve 2908343 alocações; a primeira perda aponta para `zm_globals_ctor_pgsql` durante criação de recursos TSRM. As demais stacks estão nos globals das extensões carregadas. O controle com threads vazias reproduz o mesmo volume total, logo não há evidência de perda adicional dos buffers. Esse é um achado do ambiente completo que merece investigação separada; não foi corrigido nem ocultado. Há avisos de troca de stack no Valgrind, sem erros de acesso correspondentes; não foram suprimidos.

Logs completos e números em [memcheck-all-summary.json](results/memcheck-all-summary.json), [rodada sem tracking](results/memcheck-summary.json), [rodada rastreada](results/memcheck-tracked-summary.json) e `results/memcheck-tracked-*.txt`. O controle ZTS e o ZTS rastreado têm logs próprios. Saída 97 é reprovação do Memcheck, não crash.

A linkagem e o allocator desses diagnósticos diferem do PHP original. ASan/UBSan do PHP inteiro, falta de memória durante acquire e shutdown/unload entre múltiplos requests continuam sem validação. Objetos Zend crus não foram compartilhados entre threads, pois isso viola o contrato. Windows, 32 bits, NTS, outras versões PHP e módulos PHP compartilhados não tinham ambiente correspondente. Esses limites não apagam os resultados positivos de Memcheck nos caminhos exercitados.

## Regressão da psampler

Passaram ByteBuffer legado e nativo, wraparound em duas regiões, crescimento, descarte, pop/peek, limites e ausência de alteração parcial; PcmBuffer, transforms, reset, resampling, Resampler legado/stream/cache FIR, PCMAnalyzer, ring evidence, stereo e module_info. Os testes da API anterior continuam aprovados nos caminhos cobertos.

O produtor isolado cobriu regiões contíguas, split, second-only e NULL vazias; descritores inválidos, overflow, origem incorreta, fonte não construída, falha sem exceção, SUCCESS com exceção e release em erros. Autoappend, tipos/faixas inválidas e origem sem produtor são recusados; guard impede mutações mesmo de tamanho zero. A origem e o destino mantêm armazenamento independente; não ficam referências à origem após appendBuffer.

O consumidor PCM adicional registrou uma operação real em MINIT, verificou versão, duplicatas e NULLs, bloqueio do registry em request, `psampler_pcm_from_zval` com NULL/tipo inválido, identidade, referência Zend e objeto sem construtor; chamou getters C de dados/metadados por invoke, testou guards e exceções e reutilizou 10000 vezes. O defeito de bailout é a exceção explícita à aprovação de lifecycle.

Testes prolongados existentes: 160000 frames intercalados de stream, reset/flush/destruição; RSS 30688 → 32988 KiB, estável em 32988 KiB nas cinco últimas rodadas. Analyzer: 100000 chamadas mais 1024 casos de fuzz; RSS inicial 29832, pico/final 31732 KiB. Esse crescimento limitado não substitui instrumentação de memória. Logs em [pcm-stream-stress.txt](results/pcm-stream-stress.txt) e [pcm-analyzer-stress.txt](results/pcm-analyzer-stress.txt).

## Método dos benchmarks UDP

Loopback IPv4 UDP, buffer nativo de 2048 bytes, emissor C separado e mesmos bytes por tamanho em ambos os métodos. O cenário principal de 172 bytes contém cabeçalho RTP sintético de 12 bytes e 160 bytes restantes; **não há parser, decodificação RTP, codec ou integração psampler medidos**.

Cada amostra tem 5000 pacotes de aquecimento e depois 1000000 medidos. São cinco pares por tamanho, ordem dos tamanhos embaralhada com seed 729 e ordem dos métodos alternada. Após cada crédito enviado por pipe, o emissor envia no máximo 32 datagramas; o receptor consome esse lote antes do próximo crédito. `SO_RCVBUF` foi solicitado em 4 MiB; o limite do kernel é 999384 bytes, suficiente para os lotes. Esse é throughput controlado por créditos; não é uma estimativa do máximo irrestrito do kernel.

Nenhum `toString()`, hash, comparação de payload ou logging individual ocorre nas amostras medidas. Dez execuções separadas validam integralmente 10000 pacotes por método/tamanho. No emissor, os bytes são gerados antes do timer. No receptor, o peer é tratado em ambas as chamadas. Há uma escrita de controle por lote e contagem por pacote, comuns aos caminhos.

Wall time usa `hrtime`; CPU do receptor usa diferenças de `getrusage(RUSAGE_SELF)` no intervalo. O emissor mede sua própria CPU depois do aquecimento. CPU% = CPU/wall × 100; CPU s/M = segundos de CPU por milhão de pacotes. CPU não inclui espera ociosa, mas inclui syscalls, reactor, metadata e protocolo de créditos do receptor. O tempo wall e throughput incluem disponibilidade do emissor; eles não são tempo exclusivo da chamada recv. O tráfego de ACK por pipe e a execução de rede no kernel limitam a interpretação de diferenças pequenas.

Memória PHP vem de uso real e reservado Zend; RSS e HWM vêm de `/proc/self/status`. HWM é o pico do processo desde startup; o pico PHP é reiniciado depois do aquecimento. Alocações diretas por malloc C/C++ não são integralmente representadas em `memory_get_usage()`.

`perf stat` não disponibilizou nenhum dos eventos pedidos (`task-clock`, `cycles`, `instructions`), com `perf_event_paranoid=4`. Não há contadores de hardware disponíveis. O XHProf expõe CPU/memória, sem contador de alocações nativas; a medição separada de alocações veio do Valgrind, no allocator de diagnóstico. Na inspeção de código, recvfrom aloca uma zend_string de `SW_BUFFER_SIZE_BIG` por chamada, enquanto Into reutiliza o payload; peer continua criando metadata PHP. Essa inspeção foi complementada pelo perfil de alocações separado abaixo.

### Perfil separado de alocações

Sob Memcheck, na variante de diagnóstico, com `USE_ZEND_ALLOC=0 USE_TRACKED_ALLOC=1`, cada método recebeu 5000 pacotes de aquecimento e 10000 medidos de 172 bytes. Os totais abaixo incluem startup e aquecimento; o emissor fica fora do processo perfilado. Não são números de throughput, nem contagem do allocator Zend normal de produção.

| Método | Alocações no processo | Bytes acumulados em alocações | Erros Memcheck | Definite ou indirect leak |
| --- | --- | --- | --- | --- |
| recvfrom | 218929 | 1015461604 | 0 | 0 B |
| recvfromInto | 204332 | 31962608 | 0 | 0 B |

A diferença acumulada foi 983498996 bytes, cerca de 65567 bytes por recepção contando as 15000 chamadas, coerente com evitar a zend_string de 64 KiB do método legado. A contagem total também varia com reactor/metadata e não isola exatamente uma alocação por pacote. **Bytes acumulados não são memória simultânea, RSS ou pico.** Isso confirma economia de materialização no allocator de diagnóstico, enquanto o benchmark do PHP original mostra que ela não se converteu em ganho consistente de CPU. Ver [recvfrom](results/memcheck-tracked-alloc-recvfrom.txt) e [recvfromInto](results/memcheck-tracked-alloc-recvfromInto.txt).

## Resultados UDP com quantidade fixa

Medianas de cinco amostras; cada linha representa 5000000 pacotes recebidos. CPU refere-se somente ao receptor.

| Payload | Método | Pacotes/s | Wall s por 1 M | µs/pacote | CPU s/M | CPU % | RSS KiB |
| --- | --- | --- | --- | --- | --- | --- | --- |
| 160 | recvfrom | 560892 | 1.783 | 1.783 | 1.733 | 96.75 | 30244 |
| 160 | recvfromInto | 536945 | 1.862 | 1.862 | 1.802 | 96.74 | 30236 |
| 172 | recvfrom | 553619 | 1.806 | 1.806 | 1.750 | 96.84 | 30244 |
| 172 | recvfromInto | 561795 | 1.780 | 1.780 | 1.723 | 96.97 | 30236 |
| 320 | recvfrom | 540782 | 1.849 | 1.849 | 1.793 | 96.77 | 30244 |
| 320 | recvfromInto | 544026 | 1.838 | 1.838 | 1.791 | 96.79 | 30232 |
| 1024 | recvfrom | 530426 | 1.885 | 1.885 | 1.830 | 97.06 | 30244 |
| 1024 | recvfromInto | 528239 | 1.893 | 1.893 | 1.840 | 96.84 | 30236 |
| 2048 | recvfrom | 515746 | 1.939 | 1.939 | 1.884 | 96.80 | 30240 |
| 2048 | recvfromInto | 501699 | 1.993 | 1.993 | 1.927 | 96.53 | 30236 |

Percentuais calculados como `(nativo / tradicional − 1) × 100`; CPU/tempo menores favorecem o nativo.

| Bytes | Δ throughput | Δ tempo por pacote ou operação | Δ CPU s por milhão | Δ RSS | Δ pico PHP |
| --- | --- | --- | --- | --- | --- |
| 160 | -4.27% | 4.46% | 3.95% | -0.03% | -5.78% |
| 172 | 1.48% | -1.46% | -1.53% | -0.03% | -5.78% |
| 320 | 0.60% | -0.60% | -0.08% | -0.04% | -5.78% |
| 1024 | -0.41% | 0.41% | 0.55% | -0.03% | -5.78% |
| 2048 | -2.72% | 2.80% | 2.31% | -0.01% | -5.78% |

### Dispersão e custo do emissor

| Bytes | Método | Pacotes/s mínimo–máximo | IQR pacotes/s | CPU s/M mínimo–máximo | IQR CPU s/M | CPU emissor s/M |
| --- | --- | --- | --- | --- | --- | --- |
| 160 | recvfrom | 526064–564122 | 19094 | 1.715–1.839 | 0.072 | 1.542 |
| 160 | recvfromInto | 514113–568717 | 11428 | 1.708–1.874 | 0.022 | 1.614 |
| 172 | recvfrom | 543649–558286 | 4101 | 1.731–1.778 | 0.009 | 1.576 |
| 172 | recvfromInto | 540239–565553 | 2831 | 1.717–1.795 | 0.015 | 1.527 |
| 320 | recvfrom | 515346–550175 | 22311 | 1.759–1.858 | 0.048 | 1.646 |
| 320 | recvfromInto | 529814–559574 | 20541 | 1.728–1.826 | 0.058 | 1.528 |
| 1024 | recvfrom | 527841–542725 | 13790 | 1.779–1.842 | 0.050 | 1.633 |
| 1024 | recvfromInto | 511592–533403 | 12714 | 1.819–1.893 | 0.038 | 1.594 |
| 2048 | recvfrom | 499597–522736 | 6091 | 1.851–1.935 | 0.035 | 1.721 |
| 2048 | recvfromInto | 488664–537643 | 11839 | 1.800–1.975 | 0.040 | 1.753 |

### Memória do receptor

| Bytes | Método | PHP antes→depois B | Δ PHP B | Pico PHP B | Pico reservado Zend B | Δ RSS KiB | HWM KiB |
| --- | --- | --- | --- | --- | --- | --- | --- |
| 160 | recvfrom | 2225880→2296600 | 70720 | 2366080 | 4194304 | 84 | 30244 |
| 160 | recvfromInto | 2225880→2226968 | 1088 | 2229384 | 4194304 | 84 | 30236 |
| 172 | recvfrom | 2225880→2296600 | 70720 | 2366080 | 4194304 | 84 | 30244 |
| 172 | recvfromInto | 2225880→2226968 | 1088 | 2229384 | 4194304 | 84 | 30236 |
| 320 | recvfrom | 2225880→2296600 | 70720 | 2366080 | 4194304 | 84 | 30244 |
| 320 | recvfromInto | 2225880→2226968 | 1088 | 2229384 | 4194304 | 84 | 30232 |
| 1024 | recvfrom | 2225880→2296600 | 70720 | 2366080 | 4194304 | 84 | 30244 |
| 1024 | recvfromInto | 2225880→2226968 | 1088 | 2229384 | 4194304 | 84 | 30236 |
| 2048 | recvfrom | 2225880→2296600 | 70720 | 2366080 | 4194304 | 84 | 30240 |
| 2048 | recvfromInto | 2225880→2226968 | 1088 | 2229384 | 4194304 | 84 | 30236 |

### Estabilidade prolongada em 172 bytes

Uma amostra de 10000000 pacotes por método, além das cinco amostras fixas; ela não define dispersão estatística.

| Método | Recebidos | Wall s | Pacotes/s | µs/pacote | CPU s/M | CPU % | RSS KiB | Δ RSS KiB | Perdas/erros |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| recvfrom | 10000000 | 18.302 | 546382 | 1.830 | 1.779 | 97.21 | 30244 | 84 | 0/0 |
| recvfromInto | 10000000 | 18.181 | 550012 | 1.818 | 1.766 | 97.11 | 30236 | 84 | 0/0 |

| Bytes | Δ throughput | Δ tempo por pacote ou operação | Δ CPU s por milhão | Δ RSS | Δ pico PHP |
| --- | --- | --- | --- | --- | --- |
| 172 | 0.66% | -0.66% | -0.77% | -0.03% | -5.78% |

### Cadência de 50 pacotes por segundo em 172 bytes

Cinco pares de 250 pacotes, aproximadamente cinco segundos cada. O pacing usa clock_nanosleep somente no emissor; não há sleep usado para calcular CPU de processamento do receptor. Esta é uma amostra curta de cadência, separada do stress. Wall por pacote inclui os 20 ms de intervalo; CPU s/M é uma extrapolação das 250 recepções, sensível ao scheduler.

| Método | Pacotes/s mediana | Intervalo pacotes/s | Wall s | µs/pacote wall | CPU s/M | CPU % | RSS KiB | Recebidos totais |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| recvfrom | 49.995 | 49.994–49.998 | 5.001 | 20002.125 | 142.704 | 0.713 | 30220 | 1250 |
| recvfromInto | 49.995 | 49.994–49.996 | 5.000 | 20001.811 | 141.264 | 0.706 | 30212 | 1250 |

| Bytes | Δ throughput | Δ tempo por pacote ou operação | Δ CPU s por milhão | Δ RSS | Δ pico PHP |
| --- | --- | --- | --- | --- | --- |
| 172 | 0.00% | -0.00% | -1.01% | -0.03% | -5.78% |

Nas séries UDP finais foram efetivamente enviados e recebidos **70,002,500 pacotes medidos**, mais 100,000 para integridade separada; aquecimentos não entram nessas contagens. Perdas totais: 0; erros de recepção: 0; erros de envio: 0; falhas de integridade: 0. Todos os processos emissores terminaram com zero.

## Benchmark ByteBuffer com produtor nativo

Os dois sources são construídos antes das medições: string PHP já existente versus `PsamplerBinaryTestBuffer` nativo já preenchido. Não se mede geração de dados, `toString()` ou checksum no trecho principal. Isso compara custo de append; não mede o benefício de evitar uma string que o consumidor real precisaria gerar. `appendBuffer()` ainda **copia** bytes para o armazenamento do ring; crescimento também copia conteúdo anterior.

Cinco amostras de 2000000 operações por combinação de tamanho/método/modo, com aquecimento de 10000. O custo inclui descarte, comum aos métodos. Integridade, slices, fontes/destinos em wraparound e guards são validados separadamente nos PHPTs; cada amostra também confere cópia e contadores após o timer. CPU usa getrusage e fonte/destino permanecem no mesmo processo receptor.

### Ring previamente alocado com descarte após cada append

| Bytes | Método | Operações/s | µs/op | Wall s | CPU s/M | MiB/s | RSS KiB | Pico PHP B |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 160 | append | 19131028 | 0.0523 | 0.105 | 0.0523 | 2919.2 | 40376 | 2849848 |
| 160 | appendBuffer | 11273872 | 0.0887 | 0.177 | 0.0887 | 1720.3 | 40376 | 2849856 |
| 172 | append | 19055322 | 0.0525 | 0.105 | 0.0525 | 3125.7 | 40364 | 2899064 |
| 172 | appendBuffer | 11273250 | 0.0887 | 0.177 | 0.0887 | 1849.2 | 40376 | 2899072 |
| 320 | append | 18784977 | 0.0532 | 0.106 | 0.0532 | 5732.7 | 40376 | 3505560 |
| 320 | appendBuffer | 11169484 | 0.0895 | 0.179 | 0.0895 | 3408.7 | 40376 | 3505568 |
| 1024 | append | 17913616 | 0.0558 | 0.112 | 0.0558 | 17493.8 | 40372 | 6390768 |
| 1024 | appendBuffer | 10955225 | 0.0913 | 0.183 | 0.0913 | 10698.5 | 40316 | 6390776 |
| 2048 | append | 17744059 | 0.0564 | 0.113 | 0.0564 | 34656.4 | 40352 | 10587376 |
| 2048 | appendBuffer | 10854497 | 0.0921 | 0.184 | 0.0921 | 21200.2 | 40376 | 10587384 |

| Bytes | Δ throughput | Δ tempo por pacote ou operação | Δ CPU s por milhão | Δ RSS | Δ pico PHP |
| --- | --- | --- | --- | --- | --- |
| 160 | -41.07% | 69.69% | 69.72% | 0.00% | 0.00% |
| 172 | -40.84% | 69.03% | 69.07% | 0.03% | 0.00% |
| 320 | -40.54% | 68.18% | 68.16% | 0.00% | 0.00% |
| 1024 | -38.84% | 63.52% | 63.52% | -0.14% | 0.00% |
| 2048 | -38.83% | 63.47% | 63.46% | 0.06% | 0.00% |

| Bytes | Método | µs/op mínimo–máximo | IQR µs/op | CPU % | Δ PHP B | Δ RSS KiB | HWM KiB | Pico Zend reservado B |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 160 | append | 0.0519–0.0530 | 0.0001 | 100.00 | 496 | 76 | 40376 | 4194304 |
| 160 | appendBuffer | 0.0879–0.0894 | 0.0005 | 99.99 | 496 | 76 | 40376 | 4194304 |
| 172 | append | 0.0520–0.0530 | 0.0006 | 100.00 | 496 | 76 | 40364 | 4194304 |
| 172 | appendBuffer | 0.0882–0.0888 | 0.0005 | 99.99 | 496 | 76 | 40376 | 4194304 |
| 320 | append | 0.0529–0.0539 | 0.0004 | 100.00 | 496 | 76 | 40376 | 4194304 |
| 320 | appendBuffer | 0.0887–0.0900 | 0.0009 | 99.99 | 496 | 76 | 40376 | 4194304 |
| 1024 | append | 0.0550–0.0576 | 0.0004 | 100.00 | 496 | 76 | 40372 | 8388608 |
| 1024 | appendBuffer | 0.0899–0.0915 | 0.0008 | 100.00 | 496 | 76 | 40316 | 8388608 |
| 2048 | append | 0.0563–0.0571 | 0.0003 | 100.00 | 496 | 76 | 40352 | 12582912 |
| 2048 | appendBuffer | 0.0910–0.0926 | 0.0009 | 99.99 | 496 | 76 | 40376 | 12582912 |

No modo growth, criação/destruição e realocações do destino fazem parte do custo. No preallocated, o ring é alocado antes do timer. Em steady, o descarte esvazia o ring a cada operação; os lotes observam custo amortizado com múltiplos appends.

### Ring previamente alocado em lotes de 4096 appends

| Bytes | Método | Operações/s | µs/op | Wall s | CPU s/M | MiB/s | RSS KiB | Pico PHP B |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 160 | append | 37358796 | 0.0268 | 0.054 | 0.0268 | 5700.5 | 41000 | 2849856 |
| 160 | appendBuffer | 15649082 | 0.0639 | 0.128 | 0.0639 | 2387.9 | 41008 | 2849864 |
| 172 | append | 37526735 | 0.0266 | 0.053 | 0.0266 | 6155.6 | 41064 | 2899072 |
| 172 | appendBuffer | 15717224 | 0.0636 | 0.127 | 0.0636 | 2578.1 | 41060 | 2899080 |
| 320 | append | 36307591 | 0.0275 | 0.055 | 0.0275 | 11080.2 | 41648 | 3505568 |
| 320 | appendBuffer | 15527497 | 0.0644 | 0.129 | 0.0644 | 4738.6 | 41660 | 3505576 |
| 1024 | append | 27928866 | 0.0358 | 0.072 | 0.0358 | 27274.3 | 44472 | 6390776 |
| 1024 | appendBuffer | 15114342 | 0.0662 | 0.132 | 0.0662 | 14760.1 | 44468 | 6390784 |
| 2048 | append | 15990075 | 0.0625 | 0.125 | 0.0625 | 31230.6 | 48560 | 10587384 |
| 2048 | appendBuffer | 13100884 | 0.0763 | 0.153 | 0.0763 | 25587.7 | 48560 | 10587392 |

| Bytes | Δ throughput | Δ tempo por pacote ou operação | Δ CPU s por milhão | Δ RSS | Δ pico PHP |
| --- | --- | --- | --- | --- | --- |
| 160 | -58.11% | 138.73% | 138.73% | 0.02% | 0.00% |
| 172 | -58.12% | 138.76% | 138.72% | -0.01% | 0.00% |
| 320 | -57.23% | 133.83% | 133.80% | 0.03% | 0.00% |
| 1024 | -45.88% | 84.78% | 84.78% | -0.01% | 0.00% |
| 2048 | -18.07% | 22.05% | 22.05% | 0.00% | 0.00% |

| Bytes | Método | µs/op mínimo–máximo | IQR µs/op | CPU % | Δ PHP B | Δ RSS KiB | HWM KiB | Pico Zend reservado B |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 160 | append | 0.0267–0.0285 | 0.0018 | 100.00 | 496 | 76 | 41000 | 4194304 |
| 160 | appendBuffer | 0.0634–0.0677 | 0.0023 | 100.00 | 496 | 76 | 41008 | 4194304 |
| 172 | append | 0.0266–0.0268 | 0.0002 | 100.00 | 496 | 76 | 41064 | 4194304 |
| 172 | appendBuffer | 0.0631–0.0643 | 0.0004 | 99.99 | 496 | 76 | 41060 | 4194304 |
| 320 | append | 0.0273–0.0279 | 0.0003 | 100.00 | 496 | 76 | 41648 | 4194304 |
| 320 | appendBuffer | 0.0637–0.0651 | 0.0006 | 99.99 | 496 | 76 | 41660 | 4194304 |
| 1024 | append | 0.0357–0.0376 | 0.0001 | 100.00 | 496 | 76 | 44472 | 8388608 |
| 1024 | appendBuffer | 0.0655–0.0681 | 0.0021 | 100.00 | 496 | 76 | 44468 | 8388608 |
| 2048 | append | 0.0604–0.0672 | 0.0012 | 100.00 | 496 | 76 | 48560 | 12582912 |
| 2048 | appendBuffer | 0.0740–0.0858 | 0.0019 | 100.00 | 496 | 76 | 48560 | 12582912 |

No modo growth, criação/destruição e realocações do destino fazem parte do custo. No preallocated, o ring é alocado antes do timer. Em steady, o descarte esvazia o ring a cada operação; os lotes observam custo amortizado com múltiplos appends.

### Ring novo de 8 bytes crescendo a cada lote de até 4096 appends

| Bytes | Método | Operações/s | µs/op | Wall s | CPU s/M | MiB/s | RSS KiB | Pico PHP B |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 160 | append | 29920981 | 0.0334 | 0.067 | 0.0334 | 4565.6 | 41956 | 3764720 |
| 160 | appendBuffer | 14356379 | 0.0697 | 0.139 | 0.0696 | 2190.6 | 41912 | 3764728 |
| 172 | append | 29864326 | 0.0335 | 0.067 | 0.0335 | 4898.7 | 42012 | 3764784 |
| 172 | appendBuffer | 14389578 | 0.0695 | 0.139 | 0.0695 | 2360.4 | 42028 | 3764792 |
| 320 | append | 9163585 | 0.1091 | 0.218 | 0.1091 | 2796.5 | 42396 | 5337960 |
| 320 | appendBuffer | 6746269 | 0.1482 | 0.296 | 0.1482 | 2058.8 | 42392 | 5337968 |
| 1024 | append | 2628682 | 0.3804 | 0.761 | 0.3804 | 2567.1 | 43548 | 8485312 |
| 1024 | appendBuffer | 2387517 | 0.4188 | 0.838 | 0.4188 | 2331.6 | 43524 | 8485320 |
| 2048 | append | 1111024 | 0.9001 | 1.800 | 0.9000 | 2170.0 | 44648 | 14779072 |
| 2048 | appendBuffer | 1070297 | 0.9343 | 1.869 | 0.9342 | 2090.4 | 44660 | 14779080 |

| Bytes | Δ throughput | Δ tempo por pacote ou operação | Δ CPU s por milhão | Δ RSS | Δ pico PHP |
| --- | --- | --- | --- | --- | --- |
| 160 | -52.02% | 108.42% | 108.40% | -0.10% | 0.00% |
| 172 | -51.82% | 107.54% | 107.55% | 0.04% | 0.00% |
| 320 | -26.38% | 35.83% | 35.83% | -0.01% | 0.00% |
| 1024 | -9.17% | 10.10% | 10.10% | -0.06% | 0.00% |
| 2048 | -3.67% | 3.81% | 3.81% | 0.03% | 0.00% |

| Bytes | Método | µs/op mínimo–máximo | IQR µs/op | CPU % | Δ PHP B | Δ RSS KiB | HWM KiB | Pico Zend reservado B |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 160 | append | 0.0330–0.0335 | 0.0001 | 99.99 | -261648 | 720 | 41956 | 6291456 |
| 160 | appendBuffer | 0.0693–0.0713 | 0.0001 | 99.99 | -261648 | 720 | 41912 | 6291456 |
| 172 | append | 0.0334–0.0368 | 0.0003 | 99.99 | -261648 | 768 | 42012 | 6291456 |
| 172 | appendBuffer | 0.0694–0.0744 | 0.0001 | 100.00 | -261648 | 768 | 42028 | 6291456 |
| 320 | append | 0.1081–0.1093 | 0.0008 | 100.00 | -523792 | 564 | 43608 | 8388608 |
| 320 | appendBuffer | 0.1470–0.1520 | 0.0026 | 99.99 | -523792 | 564 | 43604 | 8388608 |
| 1024 | append | 0.3741–0.3808 | 0.0012 | 99.99 | 496 | 480 | 46424 | 12582912 |
| 1024 | appendBuffer | 0.4109–0.4295 | 0.0096 | 99.99 | 496 | 480 | 46400 | 12582912 |
| 2048 | append | 0.8734–0.9130 | 0.0362 | 99.99 | 496 | -208 | 50468 | 18874368 |
| 2048 | appendBuffer | 0.9033–0.9825 | 0.0405 | 99.99 | 496 | -208 | 50480 | 18874368 |

No modo growth, criação/destruição e realocações do destino fazem parte do custo. No preallocated, o ring é alocado antes do timer. Em steady, o descarte esvazia o ring a cada operação; os lotes observam custo amortizado com múltiplos appends.

## Interpretação dos números e gargalos

Em 172 bytes, o delta mediano do nativo foi **1.48% em throughput**, **-1.46% em tempo wall por pacote** e **-1.53% em CPU por milhão**. Com cinco pares, faixa observada e ambiente de desktop, isso não estabelece um ganho generalizável. As tabelas mostram o sinal real de cada cenário; não foi feito teste de significância nem intervalo de confiança.

O syscall UDP, o trabalho do kernel, o emissor, as trocas com o reactor e a criação de metadata continuam presentes nos dois caminhos. O caminho Into acrescenta guards e pins; a economia da string de payload não elimina esses custos. RSS tem granularidade de páginas e ruído de startup; variações pequenas não demonstram redução estável de memória total. O tamanho menor de pico Zend do caminho nativo é coerente com a ausência da string de recepção, sem constituir prova de ausência de alocações C.

No ByteBuffer steady de 172 bytes, appendBuffer mudou throughput em **-40.84%** e CPU por milhão em **69.07%**. O registro de provider, pin, acquire, validação de spans/slice, release e checagens adicionais têm custo frente a append de uma string pronta. A cópia permanece em ambos. Não há evidência aqui para prometer ganho final da ponte; será necessário medir a produção dos dados e o consumidor real em uma etapa própria.

## Alterações realizadas e reprodução

Foram adicionados somente fixtures, testes, scripts de benchmark e evidências em `validation/native_buffers/`. Nenhum defeito de produção foi corrigido automaticamente. Os defeitos estruturais permaneceram reproduzíveis nos dois PHPTs reprovados.

Correções do próprio harness: toolchain musl correta para o relink; separação entre scripts `.inc` e PHPT para evitar sobrescrita pelo runner; reset dos contadores/CPU do emissor depois do aquecimento; crédito binário pré-calculado; 172 bytes com cabeçalho sintético explícito; exclusão de integridade dos timers. Uma rodada inicial com payload determinístico sem cabeçalho RTP ficou em `results/benchmarks-initial.jsonl`; os números UDP das tabelas usam a série final inteira refeita com o harness definitivo. Os dados ByteBuffer não dependem dessas alterações e foram preservados da rodada completa original.

```bash
python3 validation/native_buffers/build_fixtures.py
python3 validation/native_buffers/no_swoole.py
TEST_PHP_EXECUTABLE="$PWD/validation/native_buffers/php-fixtures" \
  buildroot/bin/php -n source/php-src/run-tests.php -q --no-color --show-diff \
  tests/native_swoole_buffer /home/lotus/projetos/psampler/tests
TEST_PHP_EXECUTABLE="$PWD/validation/native_buffers/php-contract" \
  buildroot/bin/php -n source/php-src/run-tests.php -q --no-color tests/native_swoole_buffer
TEST_PHP_EXECUTABLE="$PWD/validation/native_buffers/php-fixtures" \
  buildroot/bin/php -n source/php-src/run-tests.php -q --no-color validation/native_buffers
python3 validation/native_buffers/run_isolated.py
python3 validation/native_buffers/run_benchmarks.py
python3 validation/native_buffers/summarize.py
python3 validation/native_buffers/write_report.py
bash validation/native_buffers/run_asan.sh
python3 validation/native_buffers/run_valgrind.py
python3 validation/native_buffers/run_valgrind_tracked.py
python3 validation/native_buffers/summarize_memcheck.py
```

Os dois PHPTs suplementares de bailout devem continuar reprovando até a correção de produção; não interprete o status final do runner como regressão dos demais casos. O runner de processos isolados salva cada saída e sinal, inclusive os defeitos. Os scripts Memcheck usam Valgrind 3.22 extraído em `/tmp/pcg729-valgrind` e os casos de leaks continuam reprovados. `run_asan.sh` depende de GCC com ASan/UBSan e dos sources externos da psampler. Os binários, objetos e arquivos gerados do runner são ignorados no Git; os scripts dependem da árvore de objetos PHP local já compilada.

Dados completos: [benchmarks.jsonl](results/benchmarks.jsonl), [samples.csv](results/samples.csv), [summary.json](results/summary.json), [ambiente do benchmark](results/benchmark-environment.json) e [ambiente PHP](results/php-environment.json). Cada amostra JSONL inclui contagens, CPU usuário/sistema, memória antes/depois, RSS/HWM, métricas próprias do emissor e comando exato. A sumarização usa mediana, quartis inclusivos, IQR e mínimo/máximo; também conserva os deltas de CPU por par.

## Prontidão para a ponte nativa

**Ainda existem problemas bloqueantes.** O ABI e os caminhos normais estão suficientemente demonstrados para projetar o adaptador, mas a validação desta etapa não pode ser encerrada como aprovada para produção.

Antes de implementar e aprovar `psampler_swoole`, corrigir e repetir os dois caminhos de guard em bailout, resolver ou caracterizar formalmente o leak de coroutine, investigar os leaks ZTS do ambiente e repetir a instrumentação no formato final do executável, incluindo lifecycle entre requests e falta de memória. Depois, o adaptador deve continuar usando somente os headers públicos, preservar o pin externo, devolver exatamente uma aquisição e manter as restrições de thread/suspensão. A ausência atual do provider Swoole no PHP original é esperada e não participa da reprovação.
