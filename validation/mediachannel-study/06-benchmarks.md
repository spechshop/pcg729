# Benchmarks e resultados negativos

As variantes baseline e optimization têm MediaChannel idêntica; a única diferença é o monitor acústico. Candidate contém correções funcionais e não entra no ganho CPU. Arquivo efetivo por Reflection, hashes, PID e método estrito nativo registrados a cada run RTP. Hash do binário único em environment.json. Os nomes dos scripts não determinam o método: requireNativeRtpParser falha se não houver suporte. Baseline aqui é a cópia local pré-auditoria, já com melhorias anteriores existentes; não é release upstream.

## Metodologia

Cinco repetições intercaladas AB/BA em loopback,50 datagramas a20ms por execução. one=um membro PCMA; same=dois PCMA; transcode=PCMA→PCMU com fila/pacer; dtmf=dois PCMA e3 END; acoustic=dois PCMA com monitor, igual a same como repetição de controle. **Acústica ativada em todos**, leg b da origem. Silêncio G711 mantém monitor aberto, portanto não há atalho por decisão acústica encerrada. Métricas RTP ativadas. CPU getrusage inclui gerador, receptor e drainer no mesmo processo; não é custo exclusivo da classe. Janela começa após start e inclui espera final40ms. wall e scheduler variam. Não há sockets externos de produção.

| Cenário | CPU baseline mediana ms [min,max] | CPU protótipo mediana ms [min,max] | Delta mediana % | CPU/input baseline µs | CPU baseline % 1 núcleo |
|---|---:|---:|---:|---:|---:|
| one | 16.784 [8.075,21.328] | 18.126 [11.843,21.815] | -8.00 | 335.68 | 1.601 |
| same | 21.116 [10.806,23.427] | 17.120 [11.792,22.401] | 18.92 | 422.32 | 2.003 |
| transcode | 21.295 [15.334,24.225] | 21.733 [13.648,23.620] | -2.06 | 425.90 | 2.029 |
| dtmf | 21.794 [14.022,24.744] | 20.000 [15.685,24.389] | 8.23 | 435.88 | 2.081 |
| acoustic | 19.353 [11.410,21.495] | 18.866 [11.219,23.654] | 2.52 | 387.06 | 1.841 |

Delta positivo significa menor CPU, mas **nenhum percentual de canal é ganho comprovado**: intervalos sobrepostos e stdev alto, mesma alteração alterna piora e melhora. O cenário same isolado sugeria ganho18.9%, mas acoustic equivalente sugere2.5%; essa divergência refuta interpretar diferença de medianas como efeito causal estável. Throughput da carga é ~50pps; não é throughput máximo. Outputs: áudio50/run salvo DTMF47+11 pacotes retransmitidos/repacketizados; RTP total sempre50, sem gaps de rede nos dados. Valores de intervalo p50/p99 e max queue nos JSON; são cadência observada, **não latência fim a fim**. Não foi instrumentado relógio de envio→saída correlacionado; essa latência permanece pendente.

## Microbenchmark corrigido

O primeiro micro usou160 bytes PCM (10ms), metade do frame320B que ativa o fast path. Não serve para avaliar essa hipótese; seus resultados negativos ficam em bench.jsonl e são excluídos da conclusão. Detectado por audio_ms100000 para10000 pushes. tests/bench.php foi corrigido para320B; a versão inicial do script está em evidence/manifests/bench-initial.php para reprodução do erro.

Nova série em micro-corrected.json:9 repetições AB/BA,200000 pushes de320B/run, audio_ms4000000. Baseline mediana 210.740ms, protótipo 193.901ms, redução local 7.99% (84.2ns/push). Faixas baseline[206.289,215.449]ms, protótipo[190.578,199.630]ms. Isso confirma economia **nesse microbenchmark**, não no RTP completo. Reduz montagem de estado pending e chamadas Zend; não reduz FFT/classificação, quantidade de frames nem qualidade. Ganho absoluto a50frames/s seria apenas 0.004210ms CPU/s por stream sob hipótese micro; não é estimativa validada de capacidade do worker. Não integrado porque ganho absoluto pequeno e canal completo inconclusivo.

## perf real

Perf disponível sem mudar configurações globais. Um ensaio inicial não fixado em CPU e série independente3 repetições×5 cenários×2 variantes,30 entradas/run, taskset no primeiro CPU permitido. Comandos/PIDs/saídas em perf-runs.json e CSV em evidence/perf. Eventos task-clock, context-switches, cycles e instructions; CPU híbrida expõe cpu_core e cpu_atom, contadores <not counted> não são zero nem somados. CSV contém runtime/scaling. Task-clock inclui inicialização PHP/autoload/teardown, diferente da janela getrusage; não comparar diretamente os dois denominadores. Runs curtos dão peso alto ao startup; faltam warmup longo e perfil amostral para localizar hotspots. Nenhum perf.data foi criado.

| Cenário | task-clock baseline mediana ms | protótipo ms | switches baseline mediana | cycles core baseline mediana | instructions core baseline mediana |
|---|---:|---:|---:|---:|---:|
| one | 39.41 | 41.74 | 109.00 | 125324449.00 | 183062586.00 |
| same | 45.65 | 43.53 | 92.00 | 115578097.00 | 183904231.00 |
| transcode | 45.68 | 46.72 | 95.00 | 118515960.00 | 185395184.00 |
| dtmf | 45.51 | 44.50 | 96.00 | 117549625.00 | 184662378.00 |
| acoustic | 45.89 | 45.24 | 93.00 | 115486044.00 | 183532469.00 |

Memória: JSON registra Zend peak e ru_maxrss KiB; incluem toda aplicação de teste, não contador de alocações individual. Não há evidência de redução de RSS. Medir mallocs e cópias físicas exige instrumentação adicional; não inferir a partir de atribuições PHP. Context switches de SO não contam todos switches cooperativos de coroutines Swoole. O fast path nativo é pré-existente e não foi comparado contra recvfrom nesta série, portanto nenhum ganho dele é atribuído a esta auditoria.
