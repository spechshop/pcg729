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
