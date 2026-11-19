# Referências e problemas

Referências da baseline congelada em `lab/baseline/plugins/Utils/`. Intervalos são inclusivos. Classificações iniciais dependem dos experimentos; resultados finais aparecem ao final deste arquivo.

## F01 — processDtmf

- Classificação inicial: **Risco potencial (antes da reprodução)**.
- Arquivo e linhas exatos na baseline: `sip/MediaChannel.php:2372–2518` (`processDtmf`).
- Implementação e mecanismo: O primeiro END marca processed sem chamar onDtmf; somente retransmissão seq+1 dispara callback. END único perde tecla e duplicata pode disparar repetidamente.
- Frequência: por pacote DTMF. Impacto: segurança/estabilidade quando estado inválido; CPU só é ganho comprovado com medição.
- Evidência inicial: leitura integral da baseline; reprodução ainda não equivale a hipótese confirmada.
- Proposta e compatibilidade: Reproduzir END isolado, três END e sequência wrap; mover callback para primeiro END conservando argumentos inteiros.
- Risco: alterações de ordem, callback, estado DSP e propriedades públicas exigem regressão; nenhuma autorização de aplicação em produção.

## F02 — start / __construct

- Classificação inicial: **Risco potencial**.
- Arquivo e linhas exatos na baseline: `sip/MediaChannel.php:644–1087` (`start`); `sip/rtpc.php:34–79`; instrução crítica em MediaChannel:839.
- Implementação e mecanismo: Validação mínima depende de audioMetricsEnabled. Parser compatível aceita versão errada e objetos default para datagramas curtos; autojoin admite peers não autenticados.
- Frequência: por datagrama. Impacto: segurança/estabilidade quando estado inválido; CPU só é ganho comprovado com medição.
- Evidência inicial: leitura integral da baseline; reprodução ainda não equivale a hipótese confirmada.
- Proposta e compatibilidade: Reproduzir pacote curto com métricas desligadas; validar na fronteira sem alterar contrato público rtpc.
- Risco: alterações de ordem, callback, estado DSP e propriedades públicas exigem regressão; nenhuma autorização de aplicação em produção.

## F03 — start

- Classificação inicial: **Risco potencial**.
- Arquivo e linhas exatos na baseline: `sip/MediaChannel.php:644–1087` (`start`).
- Implementação e mecanismo: recvfrom usa timeout -1; recvfromInto usa 0.2. Timeout de chamada e probes do fallback não progridem em silêncio.
- Frequência: por espera ociosa. Impacto: segurança/estabilidade quando estado inválido; CPU só é ganho comprovado com medição.
- Evidência inicial: leitura integral da baseline; reprodução ainda não equivale a hipótese confirmada.
- Proposta e compatibilidade: Reproduzir connectTimeout curto; usar 0.2 nos dois caminhos, medir custo de wakeups.
- Risco: alterações de ordem, callback, estado DSP e propriedades públicas exigem regressão; nenhuma autorização de aplicação em produção.

## F04 — callback / close

- Classificação inicial: **Risco potencial**.
- Arquivo e linhas exatos na baseline: `sip/MediaChannel.php:644–1087` (`start`); `sip/MediaChannel.php:2144–2262` (`close`).
- Implementação e mecanismo: Callback inline pode fechar/remover estado antes de acessar rtpChans ou prosseguir encaminhamento. finally não chama close completo nem libera blockChannel/tap.
- Frequência: por callback e encerramento. Impacto: segurança/estabilidade quando estado inválido; CPU só é ganho comprovado com medição.
- Evidência inicial: leitura integral da baseline; reprodução ainda não equivale a hipótese confirmada.
- Proposta e compatibilidade: Reproduzir fechamento inline e exceção; guarda active e geração de membro após callbacks. Definir política de exceções antes de integrar.
- Risco: alterações de ordem, callback, estado DSP e propriedades públicas exigem regressão; nenhuma autorização de aplicação em produção.

## F05 — setEarlyMediaAcousticTap / close

- Classificação inicial: **Risco potencial**.
- Arquivo e linhas exatos na baseline: `sip/MediaChannel.php:136–140` (`setEarlyMediaAcousticTap`); `sip/MediaChannel.php:318–321` (`onDestruct`); `sip/MediaChannel.php:2144–2262` (`close`).
- Implementação e mecanismo: close do tap entrega eventos sincronamente antes de desassociar tap: onEvent pode reentrar close. onDestruct aceita callable mas propriedade é Closure.
- Frequência: por evento/encerramento. Impacto: segurança/estabilidade quando estado inválido; CPU só é ganho comprovado com medição.
- Evidência inicial: leitura integral da baseline; reprodução ainda não equivale a hipótese confirmada.
- Proposta e compatibilidade: Reprodução isolada; desassociar antes de chamar tap->close e Closure::fromCallable.
- Risco: alterações de ordem, callback, estado DSP e propriedades públicas exigem regressão; nenhuma autorização de aplicação em produção.

## F06 — relay / acústica

- Classificação inicial: **Oportunidade de otimização**.
- Arquivo e linhas exatos na baseline: `sip/MediaChannel.php:644–1087` (`start`).
- Implementação e mecanismo: decode PCM ocorre uma vez para transcode e é emprestado ao tap; no relay idêntico tap precisa decodificar. Múltiplos alvos iguais repetem conversão canais/rate.
- Frequência: 50/s/origem e por destino. Impacto: segurança/estabilidade quando estado inválido; CPU só é ganho comprovado com medição.
- Evidência inicial: leitura integral da baseline; reprodução ainda não equivale a hipótese confirmada.
- Proposta e compatibilidade: Manter análise integral. Cache de formato por pacote pode reduzir conversões; não compartilhar encoder stateful entre destinos.
- Risco: alterações de ordem, callback, estado DSP e propriedades públicas exigem regressão; nenhuma autorização de aplicação em produção.

## F07 — relay resampler

- Classificação inicial: **Risco potencial**.
- Arquivo e linhas exatos na baseline: `sip/MediaChannel.php:644–1087` (`start`); `sip/MediaChannel.php:1700–1735` (`convertPcmForMember`).
- Implementação e mecanismo: Relay chama resampler standalone por datagrama; injeção usa conversor streaming FIR. Mudanças de formato podem perder continuidade.
- Frequência: por pacote/destino. Impacto: segurança/estabilidade quando estado inválido; CPU só é ganho comprovado com medição.
- Evidência inicial: leitura integral da baseline; reprodução ainda não equivale a hipótese confirmada.
- Proposta e compatibilidade: Comparar saída contínua versus blocos antes de substituir. Protótipo streaming de maior risco.
- Risco: alterações de ordem, callback, estado DSP e propriedades públicas exigem regressão; nenhuma autorização de aplicação em produção.

## F08 — encoded relay

- Classificação inicial: **Risco potencial**.
- Arquivo e linhas exatos na baseline: `sip/MediaChannel.php:644–1087` (`start`).
- Implementação e mecanismo: ptime do canal RX vem da configuração do membro, não da duração real recebida. Mesmo codec pode encaminhar 40ms com avanço timestamp de 20ms.
- Frequência: por pacote/destino. Impacto: segurança/estabilidade quando estado inválido; CPU só é ganho comprovado com medição.
- Evidência inicial: leitura integral da baseline; reprodução ainda não equivale a hipótese confirmada.
- Proposta e compatibilidade: Reproduzir payload PCMA 320B com membro configurado 20ms; duração real precisa orientar packetização sem heurística para Opus.
- Risco: alterações de ordem, callback, estado DSP e propriedades públicas exigem regressão; nenhuma autorização de aplicação em produção.

## F09 — send2833 / pacer

- Classificação inicial: **Risco potencial**.
- Arquivo e linhas exatos na baseline: `sip/MediaChannel.php:1409–1544` (`send2833`); `sip/MediaChannel.php:1094–1190` (`queueRelayedPcmForMember`).
- Implementação e mecanismo: dtmfInUse só interrompe loop de entrada; pacer já iniciado e injeção continuam enviando. Chamadas send2833 concorrentes compartilham booleano e timeline; membros são enviados sequencialmente.
- Frequência: por evento/ptime. Impacto: segurança/estabilidade quando estado inválido; CPU só é ganho comprovado com medição.
- Evidência inicial: leitura integral da baseline; reprodução ainda não equivale a hipótese confirmada.
- Proposta e compatibilidade: Reprodução concorrente necessária. Serializar eventos por destino e pacers com geração/cancelamento.
- Risco: alterações de ordem, callback, estado DSP e propriedades públicas exigem regressão; nenhuma autorização de aplicação em produção.

## F10 — DTMF cache

- Classificação inicial: **Risco potencial**.
- Arquivo e linhas exatos na baseline: `sip/MediaChannel.php:2372–2518` (`processDtmf`).
- Implementação e mecanismo: Limpeza só acontece no final de END não filtrado; eventos incompletos com timestamp novo acumulam indefinidamente. Debounce assume 8kHz, não trata wrap timestamp.
- Frequência: por pacote DTMF / cache O(eventos). Impacto: segurança/estabilidade quando estado inválido; CPU só é ganho comprovado com medição.
- Evidência inicial: leitura integral da baseline; reprodução ainda não equivale a hipótese confirmada.
- Proposta e compatibilidade: Bound por stream e expiração periódica sem timer por evento. Não inferir perda por sequência de áudio isolada.
- Risco: alterações de ordem, callback, estado DSP e propriedades públicas exigem regressão; nenhuma autorização de aplicação em produção.

## F11 — código não chamado internamente

- Classificação inicial: **Melhoria arquitetural**.
- Arquivo e linhas exatos na baseline: `sip/MediaChannel.php:1779–1845` (`startRelayPacerForMember`); `sip/MediaChannel.php:1332–1379` (`processVAD`); `sip/MediaChannel.php:1546–1559` (`checkVadTimeouts`).
- Implementação e mecanismo: Segundo pacer privado, VAD privado e checkVadTimeouts sem callers nesta classe. enableVAD não ativa processamento neste receive loop. recordingEnabled/path e adaptação não executam pipeline aqui.
- Frequência: nenhuma no fluxo interno atual. Impacto: segurança/estabilidade quando estado inválido; CPU só é ganho comprovado com medição.
- Evidência inicial: leitura integral da baseline; reprodução ainda não equivale a hipótese confirmada.
- Proposta e compatibilidade: Documentar alcance real; não remover propriedades públicas nem declarar custo ativo inexistente.
- Risco: alterações de ordem, callback, estado DSP e propriedades públicas exigem regressão; nenhuma autorização de aplicação em produção.

## F12 — rebind / addMember

- Classificação inicial: **Risco potencial**.
- Arquivo e linhas exatos na baseline: `sip/MediaChannel.php:2016–2059` (`rebindMemberTransport`); `sip/MediaChannel.php:1244–1330` (`addMember`).
- Implementação e mecanismo: Rebind não limpa relayPacerRunning no id antigo nem inicia drain novo. Colisão com endpoint existente sobrescreve membro. Falta removeMember público; members público permite quebrar invariantes.
- Frequência: por mudança de transporte. Impacto: segurança/estabilidade quando estado inválido; CPU só é ganho comprovado com medição.
- Evidência inicial: leitura integral da baseline; reprodução ainda não equivale a hipótese confirmada.
- Proposta e compatibilidade: Reproduzir rebind com PCM pendente e substituição; preservar timeline e identidade por geração.
- Risco: alterações de ordem, callback, estado DSP e propriedades públicas exigem regressão; nenhuma autorização de aplicação em produção.

## F13 — constructor / SSRC

- Classificação inicial: **Oportunidade de otimização**.
- Arquivo e linhas exatos na baseline: `sip/MediaChannel.php:330–358` (`__construct`); `sip/MediaChannel.php:467–495` (`generateDeterministicSsrc`); `sip/MediaChannel.php:1244–1330` (`addMember`).
- Implementação e mecanismo: Dois G729 alocados por chamada independentemente de codec; registry global ssrcs faz in_array e cresce sem remoção; canal por membro duplica objetos RX/TX.
- Frequência: por chamada/membro novo. Impacto: segurança/estabilidade quando estado inválido; CPU só é ganho comprovado com medição.
- Evidência inicial: leitura integral da baseline; reprodução ainda não equivale a hipótese confirmada.
- Proposta e compatibilidade: Medir memória incremental e tempo criação; lazy só com contrato público preservado. Registry público exige avaliar consumidores.
- Risco: alterações de ordem, callback, estado DSP e propriedades públicas exigem regressão; nenhuma autorização de aplicação em produção.

## F14 — classificação RTCP / padding

- Classificação inicial: **Risco potencial**.
- Arquivo e linhas exatos na baseline: `sip/MediaChannel.php:627–642` (`isRtcpPacket`).
- Implementação e mecanismo: RTCP classifica só byte 2 200..207, sem length composto; RTP marker+PT72..79 conflita. Padding RTP permanece em payload por compatibilidade; build não preserva CSRC/ext.
- Frequência: por datagrama. Impacto: segurança/estabilidade quando estado inválido; CPU só é ganho comprovado com medição.
- Evidência inicial: leitura integral da baseline; reprodução ainda não equivale a hipótese confirmada.
- Proposta e compatibilidade: Não alterar rtpc; separar validação do transporte. Verificar SDP mux antes de rejeitar intervalo de PT.
- Risco: alterações de ordem, callback, estado DSP e propriedades públicas exigem regressão; nenhuma autorização de aplicação em produção.

## F15 — push

- Classificação inicial: **Oportunidade de otimização**.
- Arquivo e linhas exatos na baseline: `audio/EarlyMediaAcousticMonitor.php:51–89` (`push`).
- Implementação e mecanismo: Frame completo de 320 bytes faz substr e concat antes de analyzer push; pode emprestar string PCM alinhada sem alterar análise.
- Frequência: 50/s/stream monitorado. Impacto: segurança/estabilidade quando estado inválido; CPU só é ganho comprovado com medição.
- Evidência inicial: leitura integral da baseline; reprodução ainda não equivale a hipótese confirmada.
- Proposta e compatibilidade: Experimento isolado comparando mesmos estados/eventos; medir microbenchmark e canal real com acústica.
- Risco: alterações de ordem, callback, estado DSP e propriedades públicas exigem regressão; nenhuma autorização de aplicação em produção.

## F16 — continuidade acústica / DTMF

- Classificação inicial: **Risco potencial**.
- Arquivo e linhas exatos na baseline: `sip/MediaChannel.php:644–1087` (`start`); `sip/AcousticRtpContinuity.php:17–30`.
- Implementação e mecanismo: DTMF continua antes do tap; continuidade vê lacunas áudio como perda apesar de datagramas DTMF recebidos. G729/Opus compartilhado falha aberto após reset.
- Frequência: por transição áudio DTMF. Impacto: segurança/estabilidade quando estado inválido; CPU só é ganho comprovado com medição.
- Evidência inicial: leitura integral da baseline; reprodução ainda não equivale a hipótese confirmada.
- Proposta e compatibilidade: Reproduzir com sequência completa; distinguir descontinuidade de áudio e perda da rede sem enfraquecer evidência acústica.
- Risco: alterações de ordem, callback, estado DSP e propriedades públicas exigem regressão; nenhuma autorização de aplicação em produção.

## F17 — erro / teardown

- Classificação inicial: **Risco potencial**.
- Arquivo e linhas exatos na baseline: `sip/MediaChannel.php:644–1087` (`start`); `sip/MediaChannel.php:2144–2262` (`close`); `sip/MediaChannel.php:298–308` (`unblock`).
- Implementação e mecanismo: unblock fixa active=false antes de if(active), callback de erro fatal inalcançável. close mantém rtpStats, caches DTMF, retired e SSRC; restart reutiliza blockChannel fechado.
- Frequência: por erro/restart. Impacto: segurança/estabilidade quando estado inválido; CPU só é ganho comprovado com medição.
- Evidência inicial: leitura integral da baseline; reprodução ainda não equivale a hipótese confirmada.
- Proposta e compatibilidade: Reprodução lifecycle; tratar restart como contrato a esclarecer e manter encerramento idempotente.
- Risco: alterações de ordem, callback, estado DSP e propriedades públicas exigem regressão; nenhuma autorização de aplicação em produção.

## F18 — queuePcmForMember

- Classificação inicial: **Oportunidade de otimização**.
- Arquivo e linhas exatos na baseline: `sip/MediaChannel.php:1746–1775` (`queuePcmForMember`).
- Implementação e mecanismo: Accumulator string remove prefixo por substr a cada frame: cópia do restante quadrática em injeções grandes. Relay usa ByteBuffer limitado a 200ms.
- Frequência: por injeção/frame. Impacto: segurança/estabilidade quando estado inválido; CPU só é ganho comprovado com medição.
- Evidência inicial: leitura integral da baseline; reprodução ainda não equivale a hipótese confirmada.
- Proposta e compatibilidade: Protótipo offset ou ByteBuffer preservando lista de metadados e fragmentação. Medir antes de integrar.
- Risco: alterações de ordem, callback, estado DSP e propriedades públicas exigem regressão; nenhuma autorização de aplicação em produção.


## Classificação final e provas

| ID | Classificação final | Evidência e manifestação | Impacto e decisão |
|---|---|---|---|
| F01 | Bug confirmado | `functional.json`: baseline single=0, wrap=0; candidate ambos=1; triple=1 em ambos | Perda funcional DTMF, prioridade alta. Corrigido somente candidate. A hipótese de múltiplos callbacks no triple simples não se confirmou. |
| F02 | Bug confirmado, validação mínima | Datagram 'x' cria 1 membro e 1 callback baseline, zero candidate | Entrada inválida alcança aplicação. Corrigido comprimento/versão; CSRC/ext/padding completos continuam pendentes. |
| F03 | Bug confirmado | connectTimeout=0, espera .45s: baseline callback=0; candidate=1 em ~.2s | Recepção fallback ociosa não realiza timeout. Correção aumenta wakeups ociosos e reativa probes. |
| F04 | Risco potencial | reentrant básico passa em ambos | Não alterar por inferência. Exceção/suspensão/substituição concorrente ainda exigem testes. |
| F08 | Bug confirmado PCMA/PCMU | `ptime-wire.json`: payload320, delta160; `ptime-frame.json`: baseline [320], candidate [160,160] | Candidate força fila PCM no G711 com duração divergente; demais codecs permanecem pendentes. |
| F15 | Oportunidade refutada para integração | `micro-corrected.json`, `bench.jsonl`, perf: benefício completo não consistente | Fast path só no protótipo isolado optimization. Não integrado em candidate. |
| F16 | Bug confirmado de telemetria acústica | bench dtmf: RTP total=50, rede lost=0; acústica lost=3, epoch=1 | As três sequências são DTMF recebidos. Não reportar esses 3 como perda de rede. Correção DSP não integrada sem política de continuidade por tipo de mídia. |
| F05–F07, F09–F14, F17–F18 | Classificações iniciais mantidas | Evidência estática nas referências acima | Não são bugs reproduzidos nem gargalos comprovados de CPU. |

Nenhum gargalo de CPU dominante foi comprovado. Há custos medidos do processo e propostas; estes não identificam automaticamente a fração exclusiva de MediaChannel.

## F19 — Descritores retidos durante o reactor

**Bug confirmado de retenção no cenário; causa raiz pendente.** `MediaChannel.php:644–1087,2144–2262`; `network/SocketMutable.php:7–21`. `fds.json`: objeto MediaChannel não retido após GC, sockets marcados isClosed=true, mas dois socket FDs permanecem em /proc/self/fd após close e espera. Após saída de Coroutine::run desaparecem. `scale.jsonl`: +2, +6, +24 FDs acumulados nas rodadas de 1,4,16 canais. Controle `socket-control.json` com Socket base e SocketMutable sem MediaChannel fecha FD normalmente.

Frequência: encerramento por chamada, efeito acumulado em worker de reactor duradouro. Impacto potencial: limite de FDs, portas e kernel memory. Não prova vazamento até término do processo nem culpa exclusiva do wrapper. A fonte local native Socket::close tem fechamento adiado quando bound. Ainda falta controlar modo recvfrom, callbacks, eventos e temporização para localizar o responsável. Não alterar Swoole/PHP/psampler nem aplicar remendo não provado. Prioridade alta para revisão independente; manter contagem /proc do worker em soak test limitado.

## F20 — Fixture GSM e callback anterior ao decode

`MediaChannel.php:946–953,1000–1017,1953–1966`: onReceive roda antes do decode relay; nunca preenche gsmDecodedPcm. O teste existente espera 320 bytes nesse campo. Primeiro o teste falha em cancel de socket mock sem construtor; adaptando só cancel com assinatura nativa, chega à asserção GSM e falha 320 versus0 tanto baseline quanto candidate. **Inconsistência confirmada entre teste e implementação**, contrato externo GSM ainda não estabelecido. Não mover decode automaticamente pois aumenta custo de receiveOnly e altera histórico stateful. Evidência `adapted-fixture.json` e cópias de teste no laboratório. Não atribuir teste inteiro como aprovado.
