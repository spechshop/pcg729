# Resultado da investigação do parser RTP

A alteração selecionada é pequena: o psampler interna onze nomes de propriedades no startup e reutiliza esses nomes em zend_update_property_ex. Preserva os handlers Zend, a classe PHP rtpc, seus campos públicos, ownership de strings e o comportamento lazy. Não foi necessário modificar a libspech local de produção, o Swoole ou o Zend.

Para payload de 160 bytes em Swoole Buffer já preenchido, a mediana da fábrica nativa passou de **570,72 para 409,97 ns de CPU/operação**, redução de **28,17% nesse microbenchmark**. Nove repetições: baseline 564,33–579,18 ns, DP 5,47; final 406,75–418,15 ns, DP 3,71. Não é percentual de economia da MediaChannel.

## Entregas

- [Diagnóstico](diagnostico.md): fluxo completo, cópias/alocações, detalhes Zend/ZTS, hipóteses e riscos.
- [Experimentos](experimentos.md): metodologia, resultados positivos/negativos, dispersão, testes e comandos.
- [Dados brutos do parser](bench.json), [resumo CSV](summary.csv), [UDP](udp.json), [resumo UDP](udp-summary.csv).
- [Testes A/B](tests.json), [PHPT](phpt.txt), [ambiente](environment.txt), [build](build.log).
- [Patch incremental](optimization.patch), fonte anterior binary_buffer.baseline.c e executável anterior php-baseline.
- [Receptor equivalente](receiver.php) e [teste finito equivalente](media-compare.php), com modo slice isolado em rtpc-slice.php.
- [Hashes dos binários comparados](bench-binaries.json) e [desmontagem da função final](parser-disassembly.txt).

## Arquivos de produção alterados

1. source/php-src/ext/psampler/binary_buffer.c
2. downloads/psampler/binary_buffer.c

As duas cópias estão iguais. Mudança: array de nomes zend_string permanentes, inicializado em psampler_binary_registry_init, e substituição das escritas genéricas por zend_update_property_ex. Não há cache de classes/offsets nem acesso direto a slots. O contexto de parsing, validações e strings próprias não mudaram.

Todos os demais arquivos desta investigação estão em validation/rtp-native/study, além dos artefatos gerados normalmente pela compilação. optimization.patch é relativo ao estado experimental existente, não ao HEAD upstream. Há alterações preexistentes na raiz e nos repositórios psampler; não foram incorporadas como se fossem novas.

rtpc-slice.php e ptime-fixture.php são cópias exclusivamente de laboratório. Não estão no autoloader de produção. A libspech real externa não foi modificada. mc.php e lsend.php mantiveram seus SHA-256 originais, conferidos em originals.sha256. Não houve commits, pushes ou publicações.

## Decisões técnicas

**Integrar nomes internados.** Retira dez strings transitórias por pacote válido e preserva a validação de tipos, referências e hooks. Mantém apenas onze nomes permanentes de baixo custo por processo. Ganho consistente nos três tamanhos testados.

**Manter buffer reutilizável e strings próprias.** Não reter ponteiros para storage de recepção. originalPayload permanece compartilhado por refcount até mutação; snapshot original continua independente dos campos atuais.

**Manter raw tardio.** Leitura imediata ainda adiciona custo: 563,68 ns/op no final com payload 160, contra 409,97 sem leitura. Não é conveniente pagar esse custo em todos os pacotes.

**Não integrar slice+legado.** Antes da otimização, slice foi mais rápido que a fábrica C. Depois, perdeu no caso sem leitura de raw. Além disso, materializa raw imediatamente e muda estado observável; permanece controle experimental.

**Adiar soluções maiores.** Classificação RTCP combinada é a próxima hipótese plausível, mas requer contrato de retorno/efeitos e testes específicos. Cache de offsets, objeto nativo e integração com I/O têm risco maior. Não foi demonstrado que sejam necessários.

## Validação

Passaram nos binários anterior e final:

- 773 equivalências RTP e 31 divisões de ring;
- 517 casos Swoole/UDP, padding, extensão, limites e reutilização;
- propriedades/referências existentes, hook, setter, TypeError e reentrância;
- 3.000 entradas pseudoaleatórias determinísticas por execução;
- quatro threads ZTS independentes executando os mesmos contratos;
- MediaChannel com peers reais, PCMA→PCMU, callbacks, DTMF e forwarding.

PHPT: quatro PASS, zero FAIL, dezesseis SKIP por fixtures nativas ausentes. Os skips não validam aqueles cenários. Os dois modos do teste finito equivalente da MediaChannel passaram após corrigir o loader do harness.

O teste original de ptime continua falhando, igualmente no baseline e no final. Mock sem construtor causa falha em cancel; a cópia com construtor pai revela expectativa posterior de PCM GSM no callback, anterior à decodificação atual. Nenhuma correção de produção fora do escopo foi aplicada.

Valgrind indisponível, inclusive no caminho temporário histórico; tentativa retorna 127. Não houve ASan/TSan. Portanto a segurança é apoiada pela preservação das APIs/ownership e pelos testes funcionais/threads, sem alegação de verificação completa de memória ou ausência de corridas.

## Binário final

Caminho: /home/lotus/CLionProjects/pcg729/buildroot/bin/php

SHA-256: 18f0763f823d9e1b83ac3c78e9164dc0636772ceff173057550697a0314c6f99

PHP 8.5.12 ZTS; Swoole 6.3.0RC1; psampler 0.7.0 experimental. ELF estático x86-64 com debug_info, não stripped. Reflexão confirmou psampler_rtp_parse e recvfromInto; nm/objdump confirmaram a otimização. Hash igual ao artefato source/php-src/sapi/cli/php.

Comando de compilação solicitado foi executado integralmente. O SPC não atualizou seu destino inicialmente, apesar do log de sucesso. A instalação local foi corrigida copiando o novo artefato para arquivo temporário e renomeando para o destino. A rodada incorreta foi preservada como controle A/A; as conclusões usam somente a repetição com os hashes corretos. Detalhes em experimentos.md.

## Preparação para perf e limites

Para comparar a mudança de nomes, execute receiver.php em modo native com php-baseline e com o binário final, usando o mesmo emissor e configurações. Para investigar alternativa de parsing, compare RTP_STUDY_MODE=native/slice com o mesmo binário. Ambos usam receiveOnlyMode(false), Into, timeout 0,2 e configurações comuns. Startup verifica o caminho efetivo, não apenas sua mensagem.

O controle slice não representa recvfrom tradicional; inclui cópia integral para permitir comparação com o mesmo transporte. Os originais mc.php/mc-native.php têm configurações diferentes e não servem como A/B causal.

Não foi executado perf ou teste massivo da MediaChannel. O pequeno ensaio UDP inclui envio local, socket pronto e recepção no mesmo processo; não modela scheduler sob milhares de chamadas. Capacidade máxima, RSS em escala, custo por chamada e percentual de CPU total continuam para sua avaliação final.
