# Resultado dos benchmarks perf da MediaChannel

**Manter o parser nativo com nomes internados.** O experimento encontrou redução consistente de instruções executadas, mas **não demonstrou redução conclusiva do tempo total de CPU** da MediaChannel a 50 RTP/s. As diferenças de task-clock são menores que a variabilidade entre rodadas. Também não demonstrou superioridade conclusiva em CPU total sobre slice + legado. A recomendação combina compatibilidade, menor trabalho executado, resultado anterior da fábrica e baixo custo de manutenção; não é uma promessa de economia percentual em produção.

## Escopo, metodologia e ambiente

Foram concluídas 15 janelas principais: cinco de 60 segundos por cenário, em blocos com ordem alternada. Depois foram coletados três perfis de 60 segundos e três janelas suplementares de 60 segundos com dois membros. Pilotos estão separados e não entram nas estatísticas.

Host: Intel Core i5-13450HX, Linux 7.0.0-43-generic, perf 7.0.14. PHP 8.5.12 ZTS, Swoole 6.3.0RC1, psampler 0.7.0 experimental; ELF estático, símbolos preservados. Receptor CPU2 e emissor CPU4, núcleos físicos distintos. PMU cpu_core explícito. Governor powersave/intel_pstate permaneceu como encontrado; turbo/frequência não foram fixados. O host não ficou exclusivo ao experimento.

| Cenário | Executável relativo à raiz | Fábrica real | Comparação |
|---|---|---|---|
| Baseline + nativo | validation/rtp-native/study/php-baseline | rtpc::fromNativeBuffer → psampler_rtp_parse anterior | A |
| Otimizado + nativo | buildroot/bin/php | mesma fábrica → parser com nomes internados | A e B |
| Otimizado + slice/legado | buildroot/bin/php | cópia isolada rtpc-slice.php → slice integral → construtor legado | B |

SHA-256 baseline: 352186d2e57558321df65b5ae32a9c21778f17f9087913542a29933ebf2a7e79

SHA-256 otimizado: 18f0763f823d9e1b83ac3c78e9164dc0636772ceff173057550697a0314c6f99

Mesmo receiver.php experimental e transporte recvfromInto, receiveOnlyMode(false), timeout 0,2 s, um peer PCMA/8000/mono PT8 e telephone-event PT101. Probes habilitados, métricas adicionais desabilitadas, mesmo timer de status e mesmos callbacks. Slice **não** representa recvfrom tradicional.

O emissor isolado envia um slot a cada 20 ms: 150 slots por ciclo, dos quais onze são RFC 4733 (oito progressivos e três finais), com dígitos determinísticos. A comparação usa tráfego real da MediaChannel, não um loop de parsing. Cinco segundos de aquecimento e prova de pelo menos 230 RTP precedem cada coleta. As janelas iniciais de status com taxa parcial não entram nas medições.

Perf inicia desabilitado; pipes enable/disable com acknowledgement delimitam 60 segundos ativos. Snapshots nas bordas consultam o contador privado já existente packetsProcessed, áudio do callback, DTMF e getrusage. Há pequeno desalinhamento entre snapshot e controle perf, registrado por monotonic_ns. A tolerância predefinida de até dois datagramas evita interpretar um pacote de borda como perda; normalização usa contagem observada.

Detalhes e comandos: [metodologia.md](metodologia.md), [README.txt](README.txt), [run.py](run.py). Cada diretório contém manifesto JSON com comandos, hashes, configuração efetiva, PID, logs e dados brutos. Repetições novas recebem nomes novos; o runner recusa sobrescrita.

## Resultados individuais principais

Task-clock em ms; CPU% de um núcleo; user/kernel em ms por getrusage; CPU/RTP em microssegundos. Context switches abreviado CS. Zero migrações em todas as execuções principais. Todas receberam 220 datagramas DTMF e 20 eventos/callbacks DTMF; o restante é áudio.

| Bloco | Cenário | Task-clock | CPU% | User | Kernel | CS | RTP | Áudio | µs/RTP |
|---|---|---:|---:|---:|---:|---:|---:|---:|---:|
| 1 | Baseline + nativo | 438.58 | 0.7310 | 344.866 | 124.985 | 3069 | 3000 | 2780 | 146.193 |
| 1 | Otimizado + nativo | 425.23 | 0.7087 | 332.716 | 131.348 | 3067 | 3000 | 2780 | 141.743 |
| 1 | Otimizado + slice/legado | 406.01 | 0.6767 | 317.624 | 124.747 | 3072 | 3000 | 2780 | 135.337 |
| 2 | Otimizado + slice/legado | 540.69 | 0.9011 | 412.521 | 157.902 | 3070 | 3000 | 2780 | 180.230 |
| 2 | Otimizado + nativo | 645.85 | 1.0764 | 477.659 | 202.994 | 3078 | 3000 | 2780 | 215.283 |
| 2 | Baseline + nativo | 632.65 | 1.0544 | 467.665 | 196.686 | 3084 | 3000 | 2780 | 210.883 |
| 3 | Otimizado + nativo | 594.87 | 0.9914 | 440.833 | 182.492 | 3064 | 3000 | 2780 | 198.290 |
| 3 | Baseline + nativo | 667.26 | 1.1121 | 494.140 | 205.868 | 3080 | 3000 | 2780 | 222.420 |
| 3 | Otimizado + slice/legado | 654.21 | 1.0903 | 494.550 | 192.978 | 3083 | 3000 | 2780 | 218.070 |
| 4 | Otimizado + slice/legado | 678.47 | 1.1308 | 476.637 | 238.325 | 3087 | 3000 | 2780 | 226.157 |
| 4 | Baseline + nativo | 670.54 | 1.1176 | 516.788 | 189.070 | 3079 | 3001 | 2781 | 223.439 |
| 4 | Otimizado + nativo | 631.22 | 1.0520 | 466.454 | 197.750 | 3082 | 3000 | 2780 | 210.407 |
| 5 | Baseline + nativo | 655.26 | 1.0921 | 492.320 | 197.541 | 3079 | 3000 | 2780 | 218.420 |
| 5 | Otimizado + slice/legado | 663.92 | 1.1065 | 477.179 | 221.425 | 3079 | 3000 | 2780 | 221.307 |
| 5 | Otimizado + nativo | 641.26 | 1.0688 | 468.021 | 205.228 | 3069 | 3000 | 2780 | 213.753 |

Contadores de hardware individuais (milhões):

| Bloco | Cenário | Cycles M | Instructions M |
|---|---|---:|---:|
| 1 | Baseline + nativo | 1115.359 | 208.659 |
| 1 | Otimizado + nativo | 1143.244 | 200.534 |
| 1 | Otimizado + slice/legado | 1289.455 | 206.464 |
| 2 | Otimizado + slice/legado | 891.927 | 209.476 |
| 2 | Otimizado + nativo | 676.581 | 201.743 |
| 2 | Baseline + nativo | 612.908 | 208.935 |
| 3 | Otimizado + nativo | 706.026 | 202.061 |
| 3 | Baseline + nativo | 685.991 | 210.220 |
| 3 | Otimizado + slice/legado | 612.120 | 208.085 |
| 4 | Otimizado + slice/legado | 607.275 | 208.522 |
| 4 | Baseline + nativo | 608.968 | 208.142 |
| 4 | Otimizado + nativo | 573.838 | 200.906 |
| 5 | Baseline + nativo | 583.546 | 208.646 |
| 5 | Otimizado + slice/legado | 598.227 | 208.745 |
| 5 | Otimizado + nativo | 581.258 | 201.565 |

Dados completos: [individual.csv](individual.csv), [individual.json](individual.json), manifests e measurement.json por execução. Nenhuma janela principal foi removida por resultado desfavorável. Nos casos em que sender e receiver diferem em um pacote, suas consultas ocorreram em instantes ligeiramente diferentes; logs/tempos permitem conferir, sem afirmar perda.

User+kernel NÃO é igual ao task-clock. A diferença observada é de 28,46–38,83 ms nas janelas principais. O desalinhamento entre duração dos snapshots e perf foi no máximo 1,65 ms, insuficiente para explicar essa diferença; não se trata apenas de arredondamento ou borda. A consulta diagnóstica de schedstat viu dois threads, com o auxiliar sem avanço de CPU no intervalo observado, mas não resolve integralmente a diferença de contabilização. Mantivemos ambos os valores, sem “corrigir” um pelo outro nem atribuir causa não demonstrada. Task-clock é a métrica principal solicitada.

## Agregados e dispersão

DP amostral entre cinco repetições; não é erro padrão.

| Cenário | Task-clock mediana ms | Mín–máx ms | DP ms | CPU% mediana | µs/RTP mediana | µs/RTP mín–máx | DP µs/RTP |
|---|---:|---:|---:|---:|---:|---:|---:|
| Baseline + nativo | 655.26 | 438.58–670.54 | 98.55 | 1.0921 | 218.420 | 146.193–223.439 | 32.839 |
| Otimizado + nativo | 631.22 | 425.23–645.85 | 92.99 | 1.0520 | 210.407 | 141.743–215.283 | 30.998 |
| Otimizado + slice/legado | 654.21 | 406.01–678.47 | 115.85 | 1.0903 | 218.070 | 135.337–226.157 | 38.618 |

A primeira rodada ocorreu com frequências efetivas maiores que várias rodadas posteriores. Frequências instantâneas nas bordas variaram de aproximadamente 0,8 a 4,6 GHz. Em algumas janelas task-clock aumenta enquanto cycles diminui: ciclos de stall/CPU e frequência não são um fator constante de conversão. Load average/frequência estão nos manifests; snapshots de frequência não descrevem toda a janela.

A análise foi ajustada de forma controlada usando contrastes por bloco e instruções por pacote, preservando as 15 janelas iniciais. Não alteramos governor, código ou carga no meio da série, nem repetimos até obter um resultado favorável. As instruções ajudam a avaliar trabalho executado; não substituem CPU total.

### Comparações A e B

Sinal positivo significa menor CPU/RTP do nativo otimizado. Contrastes em µs/RTP, por bloco:

| Comparação | Bloco 1 | Bloco 2 | Bloco 3 | Bloco 4 | Bloco 5 | Média | Mediana |
|---|---:|---:|---:|---:|---:|---:|---:|
| A | 4.450 | -4.400 | 24.130 | 13.032 | 4.667 | 8.376 | 4.667 |
| B | -6.407 | -35.053 | 19.780 | 15.750 | 7.553 | 0.325 | 7.553 |

**A:** média do contraste 8.376 µs/RTP; intervalo t exploratório de 95% [-4.970, 21.722] µs/RTP. Enumeração de sinais pareados: p bilateral=0.1250.

**B:** média do contraste 0.325 µs/RTP; intervalo t exploratório de 95% [-27.196, 27.845] µs/RTP. Enumeração de sinais pareados: p bilateral=0.9375.

Esses cálculos são descritivos/exploratórios: apenas cinco blocos, ordem alternada predefinida (não sorteio independente), host compartilhado e normalidade dos contrastes não demonstrada. Ambos os intervalos abrangem zero. A não conclusividade não demonstra equivalência perfeita; significa que este experimento não resolveu a diferença de tempo de CPU.

A razão entre medianas de task-clock sugere cerca de 3,7% menos no A e 3,5% menos no B, mas **não deve ser anunciada como ganho comprovado**. No B, a diferença média pareada é quase zero, mostrando como a escolha da estatística pode criar uma impressão enganosa.

### Instruções executadas

| Cenário | Mediana M | Mín–máx M | DP M |
|---|---:|---:|---:|
| Baseline + nativo | 208.659 | 208.142–210.220 | 0.781 |
| Otimizado + nativo | 201.565 | 200.534–202.061 | 0.626 |
| Otimizado + slice/legado | 208.522 | 206.464–209.476 | 1.122 |

O nativo otimizado apresentou aproximadamente **3.40% menos instruções** que baseline+nativo e **3.34% menos** que slice, pela razão das medianas. As faixas observadas de instruções do otimizado ficaram abaixo das outras duas. É evidência consistente de menor trabalho nesse fluxo completo; não mede sozinho tempo/energia/CPU em produção nem garante comportamento sob saturação.

## Perfis de CPU e custos por camada

Amostragem separada, 60 s por cenário, evento cpu_core/cycles/, período 100.000 ciclos, DWARF 8192 bytes. Perf report self e inclusive, perf script e perf.data estão em profile-1 (baseline+nativo), profile-2 (otimizado+nativo) e profile-3 (otimizado+slice). As execuções de profiling não entram nas estatísticas anteriores. Não converter percentuais de ciclos diretamente em milissegundos de CPU de outra execução.

As categorias abaixo contam cada amostra no máximo uma vez DENTRO da categoria. Categorias podem se sobrepor (por exemplo, bridge dentro de parser, propriedade dentro de parser). **Não somar colunas.** Pilhas podem truncar na fronteira coroutine/musl; PHP interpretado não fornece aqui nome de cada método PHP. Os valores não são uma decomposição exaustiva.

| Perfil | Amostras | Kernel no topo | Parser nativo incl. | Bridge incl. | Recepção UDP incl. | Propriedades Zend incl. | Alocação/cópia incl. |
|---|---:|---:|---:|---:|---:|---:|---:|
| profile-1 | 6456 | 25.90% | 3.56% | 2.48% | 29.71% | 4.37% | 4.66% |
| profile-2 | 6387 | 25.54% | 2.29% | 2.60% | 30.64% | 2.79% | 4.84% |
| profile-3 | 6476 | 25.65% | 0.00% | 1.98% | 30.28% | 1.95% | 5.37% |

Critérios reproduzíveis em [analyze-profiles.py](analyze-profiles.py); contagens em [profile-summary.json](profile-summary.json). Percentuais de perfil não são intervalos estatísticos nem diferenças absolutas de consumo.

### Funções de maior custo próprio

**profile-1:**

| Self | DSO | Símbolo |
|---:|---|---|
| 13.99% | php-baseline | execute_ex |
| 2.18% | php-baseline | zend_hash_find |
| 1.21% | [kernel.kallsyms] | aa_dfa_match_len |
| 1.21% | php-baseline | zim_swoole_socket_coro_recvfromInto |
| 1.05% | php-baseline | memcpy |
| 1.04% | php-baseline | _emalloc |
| 0.81% | php-baseline | zend_std_write_property |
| 0.76% | php-baseline | strlen |
| 0.68% | php-baseline | _efree |
| 0.67% | php-baseline | printf_core |

**profile-2:**

| Self | DSO | Símbolo |
|---:|---|---|
| 14.29% | php | execute_ex |
| 1.35% | php | zend_hash_find |
| 1.22% | php | zim_swoole_socket_coro_recvfromInto |
| 1.13% | php | memcpy |
| 0.99% | [kernel.kallsyms] | aa_dfa_match_len |
| 0.92% | php | printf_core |
| 0.92% | php | zend_std_write_property |
| 0.86% | php | get_meta |
| 0.67% | php | _emalloc |
| 0.63% | php | swoole::ReactorEpoll::wait() |

**profile-3:**

| Self | DSO | Símbolo |
|---:|---|---|
| 14.81% | php | execute_ex |
| 1.28% | php | memcpy |
| 1.19% | php | zend_hash_find |
| 1.07% | php | zim_swoole_socket_coro_recvfromInto |
| 1.05% | php | zif_unpack |
| 1.00% | php | _emalloc |
| 0.93% | php | printf_core |
| 0.90% | [kernel.kallsyms] | aa_dfa_match_len |
| 0.85% | php | _efree |
| 0.69% | [kernel.kallsyms] | fdget |

## Interpretação técnica dos perfis

A recepção aparece como uma cadeia ampla: recvfromInto, Socket::recvfrom, syscall/UDP e verificações de endereço/permissão. Sua participação inclusiva inclui trabalho abaixo dessas funções. O reactor/epoll/scheduler é outra categoria com sobreposição: a pilha do reactor pode conter retomada da coroutine e processamento posterior. Não é correto somar “recepção” e “reactor” para obter custo da rede.

No baseline, o parser nativo ocupa 3,56% das amostras inclusivas; no otimizado, 2,29%. As escritas Zend como categoria passam de 4,37% para 2,79%. Isso é coerente com retirada de nomes temporários e hashes repetidos, mas esses percentuais pertencem a execuções de profiling distintas, com frequências e denominadores diferentes. Não representam redução absoluta de CPU de 35% nem ganho de 1,27 ponto percentual no processo principal.

O custo próprio de zend_std_write_property não necessariamente cai: as validações legítimas continuam. Seu self aparece aproximadamente 0,81% no baseline e 0,92% no otimizado. Não é evidência de regressão dessa função; remover trabalho em outros frames muda as participações. zend_hash_find e _emalloc mostram menor participação no otimizado, compatível com a alteração, sem permitir contagem exata de alocações por amostragem.

execute_ex é a maior função própria agregada, aproximadamente 14–15%, além de muitos handlers ZEND_* distribuídos. Isso inclui execução PHP de classificação RTCP, seleção de codec/membro, callbacks, fábrica e controle da MediaChannel. O perfil nativo não resolve cada método PHP interpretado. Logo não se pode dizer que todo execute_ex é parser, callback ou classificação.

O caminho slice introduz zif_unpack de cabeçalho, além do unpack de DTMF comum aos cenários. A fábrica nativa evita esse array intermediário. A comprovação de seleção combina hash/fábrica/state lazy e amostras: perfil slice não deve conter a função de parser nativo na janela; a chamada sintética de startup ocorreu antes de habilitar perf.

Construção/destruição de objetos e alocação/cópia aparecem em funções Zend/musl compartilhadas por muitas classes e strings. A categoria de lifecycle não isola apenas rtpc; as amostras não são um contador de bytes copiados ou de objetos alocados. Os contratos de ownership e snapshot continuam justificando strings próprias mesmo quando seu custo aparece no perfil.

AppArmor (aa_dfa_match_len/aa_inet_msg_perm), fdget, epoll e outras funções de kernel participam. Não são oportunidades para remover garantias de segurança visando números melhores. printf_core também aparece: logs/timer fazem parte deste receptor e foram mantidos iguais. O custo do observador perf é visível em algumas pilhas e não foi subtraído arbitrariamente.

### Quanto custa recepção em relação ao parsing?

A resposta quantitativa segura é uma aproximação por participação de ciclos: no perfil otimizado, cerca de 30,64% das amostras pertencem a pilhas de recepção UDP e 2,29% a pilhas do parser C, aproximadamente 13 vezes mais presença nas pilhas de recepção. Essa razão NÃO inclui todo o custo PHP da fábrica nem separa perfeitamente scheduler/syscall, e não é uma razão de latência. Há truncamentos de pilha na fronteira de coroutine/musl.

Não é possível extrair destes dados um valor exclusivo e preciso em ns para “UDP puro” versus “parser completo”. O microbenchmark anterior mede fábrica com buffer já preenchido e cache quente; o fluxo de 20 ms tem outras condições. Extrapolar diretamente os 161 ns economizados por operação naquele teste ignoraria cache, DVFS e scheduling.

Perf amostra CPU ativa. Os aproximadamente 59 segundos por minuto sem CPU do receptor são principalmente espera e não aparecem como 98% de “custo de epoll”. Bloquear aguardando datagrama não significa consumir CPU durante todo o bloqueio.

### Oportunidades restantes, sem implementação

1. **Classificação RTCP combinada ao parsing.** O caminho atual faz slice de até quatro bytes, aquisição/liberação e chamada PHP antes do parser. Pode reduzir fronteiras e uma string pequena; preservar exatamente curtos, versão, PT200–207, keepalive, contadores e efeitos. Precisa API separada/compatível e teste A/B próprio.
2. **Normalizar mapas de codec/frequência com invalidação segura.** MediaChannel::codecNameFromMapper e frequencyFromMapper chamam explode repetidamente. Registrar metadados normalizados pode reduzir arrays/opcodes, mas mapas públicos e alterações por callbacks impedem cache ingênuo.
3. **Metadados e sincronização no receive.** Peer array, string de endereço e propriedades de socket continuam por datagrama. O perfil dá motivo para investigação, não autorização para ignorar handlers, erros, referências ou guards do Swoole.
4. **Escala de sockets, timers e buffers.** Muitas chamadas podem mudar o peso do reactor e memória persistente. A presente série de um fluxo não mede esse regime; qualquer experimento futuro deve aumentar canais de forma gradual e limitada.
5. **Custos de relay/codecs.** O suplemento prova conversão; em cenários reais, resampling, packetização e filas podem superar o parser. Priorizar conforme perfil da configuração real, sem transportar percentuais deste laboratório.
6. **Evitar arquiteturas incompatíveis.** Objeto nativo especial, payload emprestado de buffer reutilizado ou raw eager não são trocas transparentes de rtpc. Mais complexidade não é justificada pelo pequeno peso atual do parser.

generateDeterministicSsrc já possui cache por endpoint; não propor retirar um SHA-1 “por pacote” que não ocorre no caminho aquecido. Também não há um array de campos retornado pelo parser C atual a eliminar.

## Respostas às seis perguntas

1. **Nomes internados reduziram CPU total de forma mensurável?** Houve tendência descritiva favorável e menos instruções, mas os cinco blocos não resolveram o ganho em task-clock com confiança. Resultado de CPU total inconclusivo neste cenário.
2. **Nativo otimizado supera slice+legado?** Executou menos instruções, mas a diferença média pareada de CPU foi praticamente zero diante da dispersão. Não há superioridade conclusiva em CPU total. Slice permanece um controle com raw eager, não uma substituição integralmente compatível.
3. **Recepção versus parsing?** Aproximadamente 30,6% versus 2,3% das amostras inclusivas no perfil otimizado, com as ressalvas de escopo/overlap acima. Não há decomposição precisa de CPU por etapa.
4. **Maiores consumidores?** VM execute_ex e handlers Zend; buscas em tabelas; cadeia de recvfromInto/syscall/UDP; reactor/epoll/scheduler; memória e validações de kernel. O relatório self lista funções e valores sem somar inclusivos.
5. **Potencial significativo?** Reduzir operações repetidas em mapas e no caminho de recepção/classificação é mais promissor que sofisticar apenas leitura de 12 bytes. Ganhos adicionais continuam hipóteses, exigindo manutenção dos contratos.
6. **Qual manter?** Nativo com nomes internados: compatibilidade preservada, menos instruções e alocações transitórias, ganho anterior da fábrica, patch pequeno. Manter sem anunciar percentual comprovado de economia da MediaChannel; não substituir por slice nem ampliar arquitetura com base em ruído.

## Série suplementar com dois membros

Mesmos caminhos A/B, mesma fonte PCMA, acrescido destino PCMU e sink externo ao PID medido. Um ensaio por cenário; não interpretar como cinco repetições ou conclusão estatística. O sink compara bytes PCMU FE x160 com o resultado esperado da conversão de D5 x160, validado também pela API de codec PHP em preflight/pcmu-expected.txt.

| Cenário | Task-clock ms | CPU% | µs/RTP | RTP | PCMU validado no sink | DTMF encaminhado |
|---|---:|---:|---:|---:|---:|---:|
| Baseline + nativo | 1088.54 | 1.8142 | 362.847 | 3000 | 2781 | 220 |
| Otimizado + nativo | 1030.03 | 1.7167 | 343.343 | 3000 | 2780 | 220 |
| Otimizado + slice/legado | 1075.70 | 1.7928 | 358.447 | 3001 | 2780 | 220 |

A validação comprova processamento de mídia, não só envelope RTP. O cenário principal tem um único membro e seu loop de relay exclui a própria origem: não foi tratado como transcodificação por pacote. Custos adicionais do suplemento incluem codec, fila de relay, packetização e sendto; não atribuir a diferença apenas ao codec.

A chave options.transcodingEnabled permaneceu false como no receptor original; o ramo real de relay inspecionado decide por codec/frequência/canais/ptime, sem consultar essa chave. A prova de transcodificação é a saída PCMU validada, não o rótulo dessa opção. Contagem do sink pode diferir em um pacote pela janela ligeiramente mais ampla das consultas externas.

## Integridade, limitações e reprodução

Antes de cada janela, o runner verifica caminho absoluto e hash do executável, símbolo/API, fábrica real, configuração do objeto, PID e /proc/PID/exe. O primeiro pacote recebido também comprova lazy/native versus eager/slice. O audit final confere contadores e hashes de todas as fontes monitoradas. Rótulos sozinhos não foram usados como prova.

Nenhum build foi necessário nesta etapa. psampler, Swoole, Zend, libspech local de produção e libspech externa não foram alterados. mc.php e lsend.php foram preservados. receiver.php recebeu apenas instrumentação de laboratório; a versão anterior está arquivada. Não houve commit, push, publicação ou carga massiva.

Acesso inicialmente negado foi documentado em preflight. Usuário liberou temporariamente paranoid=1 e kptr_restrict=0; a política é explicada pela [documentação do kernel](https://www.kernel.org/doc/html/latest/admin-guide/perf-security.html). Os valores originais eram 4 e 1. A tentativa automática de restauração falhou porque sudo exige senha (preflight/restore.txt). Os valores permaneceram 1 e 0 ao concluir. Para restaurar: sudo sysctl -w kernel.perf_event_paranoid=4 kernel.kptr_restrict=1.

Pilotos e falhas foram preservados. O piloto inicial marcou SIGINT do perf como falha; counters tinham sido finalizados corretamente. O runner passou a aceitar o encerramento deliberado -2 após disable+ack, sem aceitar outros erros. Não houve substituição de resultados principais.

Limites: um fluxo a 50 PPS, CPU híbrida com DVFS, host compartilhado, cinco repetições, pequena incerteza das bordas e overhead do observador. Perf mostra CPU ativa, não os 60 segundos de parede passados majoritariamente aguardando datagramas. Não é estimativa de capacidade máxima nem validação de milhares de chamadas.

Principais dados: [individual.csv](individual.csv), [aggregates.json](aggregates.json), [comparisons.json](comparisons.json), [profile-summary.json](profile-summary.json), [audit.json](audit.json). Comandos reais e arquivos por execução tornam possível auditar ou repetir sem confundir binários.
