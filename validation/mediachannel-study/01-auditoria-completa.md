# Auditoria integral da MediaChannel

Baseline: 2.538 linhas, 75 métodos (contagem verificável no índice Reflection). Leitura integral incluindo propriedades, comentários e código sem callers internos. As referências são da cópia congelada; números da candidate mudam com os patches. `evidence/manifests/MediaChannel-numbered.txt` permite revisão independente linha a linha.

## Propriedades e invariantes

Linhas 1–57 definem imports, constantes estáticas RTP/AVP, backlog 200ms, estado active e campos de recepção/acústica. nativeRtpReceive inicia true apesar do comentário opt-in. Campos públicos mutáveis impedem cache persistente sem invalidação. `receivedPcmForAcoustics` tem escopo lógico de pacote mas armazenamento por objeto.

Linhas 164–287 expõem socket tipado SocketMutable, tabela de membros por endereço:porta, registries codec/frequency, canais G729 públicos, callbacks e flags. A estrutura members mistura identidade de transporte, codec stateful, fila PCM e wake de coroutine. Encoder/decoder são objetos compartilhados quando arrays são copiados. Campos de gravação, adaptação e VAD não implicam execução no receive atual. rtpStats cresce por SSRC remoto quando métricas ativas; cacheKeys cresce por endpoint. Um peer pode variar SSRC sem variar membro. Strings são refcount/COW: atribuição sozinha não comprova cópia de áudio.

Linhas 310–328 e 459–465 acrescentam índices SSRC/membro, canais RTP e socket de evento, callback destrutor e cacheKeys. Linhas 499, 1407, 2299 e 2356 acrescentam callbacks públicos, dtmfInUse e canais por PT. Estado é por objeto, cache:: usa GLOBALS por processo. Não há sincronização entre workers nesta classe. Swoole é cooperativo: ausência de threads PHP não impede reentrância quando sendto, callback, sleep ou Channel cedem execução.

## Fluxo de start em detalhe

- 646–671: feature gates e buffer Swoole 65.536B por receive coroutine; nenhum zero-copy de payload PHP é prometido: parser aloca snapshot de header/payload.
- 673–685: calcula maxFrequency mas não o utiliza depois; onStart cria coroutine separada.
- 687–725: controla active e fecha sockets no owner. recvfromInto tem timeout 200ms, fallback -1 infinito. Buffer só reutilizado depois de parser copiar strings próprias.
- 726–815: branch idle usa microtime/round, trata timeout e erros; fatal chama unblock antes de if(active), probes limitados a .5s e só fora de receiveOnly. Tráfego inválido atualiza lastPacketTime antes de validação, podendo manter canal vivo.
- 817–864: endpoint aposentado é rejeitado, RTCP só classificado; contabiliza packetsProcessed antes de parser. Validação mínima só com métricas baseline. Nome de codec resolvido para cada pacote. SSRC interno é hash de endpoint, diferente de SSRC remoto usado nas estatísticas.
- 866–919: estatística por SSRC inclui áudio e DTMF; delta modular ignora atrasados no avanço mas lost cumulativo não subtrai tardios. Jitter depende do último clock; wrap timestamp não corrigido.
- 921–944: cria rtpChannel RX uma vez e autojoin por endpoint desconhecido, sem autorização de mídia. Primeiro pacote pode ser DTMF, estabelecendo codec de membro inadequado até atualização externa.
- 946–961: encaminha DTMF antes de callback de áudio e tap; processDtmf cria coroutine por datagrama. callback onReceive é inline e tem acesso mutável a todo canal.
- 963–976: receiveOnly ainda passa áudio ao tap; PCM emprestado deve corresponder ao mesmo pacote e ser consumido antes de novo receive.
- 978–1044: para cada destino snapshot de chaves. Relay idêntico reconstrói header e avança timeline configurada. Caminho divergente decodifica uma vez (exceto false/empty reexecutado em cada destino), converte por destino e enfileira; resampler standalone não preserva FIR como converter de injeção.
- 1045–1063: tap reutiliza PCM quando disponível; GSM unset de campo que não foi preenchido. Debug roda até a cada 160ms e não mede latência fim a fim.
- 1064–1087: finally fecha wakes e sockets, não executa toda política de close, não acorda blockChannel explicitamente nem fecha tap.

## Todos os métodos e frequência

### `setSettings` — linhas 58–61

Configuração por chamada. Substitui todo array: chave de probe pode desaparecer; merge com defaults exigiria definir contrato.

### `setNativeRtpReceive` — linhas 64–67

Configuração anterior ao start; variável local nativeBuffer é selecionada uma vez. Troca durante recepção não muda o loop existente.

### `requireNativeRtpParser` — linhas 70–74

Opt-in estrito. Guard síncrono evita comparar fallback inadvertidamente; nenhuma alteração no parser público.

### `setPacketTime` — linhas 82–123

Evento de configuração. Valida membros herdados antes de mutar; canais RX e TX atualizados, residual e fila descartados. Mudança durante pacer sleep tem verificação posterior de frameBytes.

### `getPacketTime` — linhas 125–128

Consulta O(1) do default; não representa ptime explícito de cada membro.

### `onReceive` — linhas 130–133

Registro/configuração por chamada ou evento: atribuição direta sem trabalho de mídia; invocação externa posterior pode reentrar e capturar objetos. Sem ganho CPU demonstrado para alterar esse contrato.

### `setEarlyMediaAcousticTap` — linhas 136–140

Evento de troca fecha tap antigo sincronamente; callback pode reentrar. Atribuição ocorre depois do close; mesmo tap passado novamente fica released.

### `shareReceivedPcmForAcoustics` — linhas 143–146

Por pacote no callback externo; strings PHP usam COW. Campo único exige consumo síncrono e cuidado com callback que cede execução.

### `setReceiveOnlyMode` — linhas 149–152

Registro/configuração por chamada ou evento: atribuição direta sem trabalho de mídia; invocação externa posterior pode reentrar e capturar objetos. Sem ganho CPU demonstrado para alterar esse contrato.

### `onDtmf` — linhas 154–157

Registro/configuração por chamada ou evento: atribuição direta sem trabalho de mídia; invocação externa posterior pode reentrar e capturar objetos. Sem ganho CPU demonstrado para alterar esse contrato.

### `onVadChange` — linhas 159–162

Registro/configuração por chamada ou evento: atribuição direta sem trabalho de mídia; invocação externa posterior pode reentrar e capturar objetos. Sem ganho CPU demonstrado para alterar esse contrato.

### `block` — linhas 290–296

Espera por chamada, callback executa antes do pop. Canal criado no constructor, fechado por close sem recriação.

### `unblock` — linhas 298–308

Finalização acorda relayWake e fecha blockChannel. active=false torna if(active) do erro fatal inalcançável.

### `onDestruct` — linhas 318–321

Aceita callable porém propriedade Closure; array callable válido pode causar TypeError. Proposta fromCallable sem alterar assinatura.

### `__destruct` — linhas 323–326

Callback não protegido. Pode lançar durante shutdown. Closure default estática evita ciclo próprio; closure fornecida ainda pode capturar sessão.

### `__construct` — linhas 330–358

Por chamada aloca 2 G729, blockChannel e eventSock adicionais, consulta porta. Tipo do argumento aceita Socket base mas propriedade exige SocketMutable; base puro pode falhar atribuição.

### `resolveCodecNameFromPt` — linhas 360–375

Por pacote: RX direcional > legado > estáticos. Explode/trim repetidos; cache precisa invalidar alterações diretas públicas.

### `resolveFrequencyFromPt` — linhas 377–390

Por pacote e estatística/DTMF: interpreta mapeamento novamente. Fallback 8k não garante que clock do codec esteja correto.

### `codecNameFromMapper` — linhas 393–400

Por consulta usa array_key_exists, cast, explode e trim. Não cachear sem observar mapas públicos mutáveis.

### `frequencyFromMapper` — linhas 403–411

Por consulta cria array explode completo, converte rate e valida >0. Parse único codec/rate/channels é proposta ainda não medida.

### `resolveRxCodecNameForMember` — linhas 414–423

Por pacote prioriza mapa do peer, depois resolve global. Uso de snapshot member evita referência mas atualização COW pode copiar tabela.

### `resolveRxFrequencyForMember` — linhas 426–435

Mesmo caminho de resolução, repetido em stats, criação RX e tap; guardar local por datagrama tem menor risco que cache persistente.

### `enableVAD` — linhas 437–441

Registro/configuração por chamada ou evento: atribuição direta sem trabalho de mídia; invocação externa posterior pode reentrar e capturar objetos. Sem ganho CPU demonstrado para alterar esse contrato.

### `setVadRegistrationThreshold` — linhas 446–449

Registro/configuração por chamada ou evento: atribuição direta sem trabalho de mídia; invocação externa posterior pode reentrar e capturar objetos. Sem ganho CPU demonstrado para alterar esse contrato.

### `setVadTimeout` — linhas 454–457

Registro/configuração por chamada ou evento: atribuição direta sem trabalho de mídia; invocação externa posterior pode reentrar e capturar objetos. Sem ganho CPU demonstrado para alterar esse contrato.

### `generateDeterministicSsrc` — linhas 467–495

Por novo endpoint SHA1 com IP local cacheado; cache hit por pacote O(1). Cache global ssrcs usa busca linear e join por cópia COW; colisão não é resolvida.

### `onStart` — linhas 500–503

Registro/configuração por chamada ou evento: atribuição direta sem trabalho de mídia; invocação externa posterior pode reentrar e capturar objetos. Sem ganho CPU demonstrado para alterar esse contrato.

### `forwardDtmfToMembers` — linhas 514–565

Por pacote DTMF × destino. Reinterpreta payload, consulta frequência pelo nome global, não pelo mapa direcional individual. Retransmissões E são encaminhadas.

### `relayDtmfPayloadForMember` — linhas 567–625

Por evento/destino mantém eventKey, duration progress, endForwarded. while pode emitir centenas de pacotes em um datagrama com duration grande; não é pacer. Residual converte clock para áudio.

### `isRtcpPacket` — linhas 627–642

Por datagrama compara versão e tipo após 4 bytes. Sem tamanho RTCP ou compound parse; ambiguidades dependem do contrato RTP/RTCP mux.

### `start` — linhas 644–1087

Uma coroutine por canal; loop por datagrama, mais timeout nativo 5/s quando ocioso. Detalhamento dos ramos abaixo; finally fecha sockets mas não equivale a close completo.

### `queueRelayedPcmForMember` — linhas 1094–1190

Por pacote transcoded: append ByteBuffer; descarta backlog acima de 200ms, O(frames excedentes). Um wake pendente; pacer persistente por destino com identidade do channel. Captura buffer/channel/wake e this até saída.

### `sendPcmFrameForMember` — linhas 1194–1214

Por frame codifica, constrói header, envia e retorna array de metadados. Não verifica resultado sendto; contador posterior pode contar tentativa como sucesso.

### `encodePcmFrameForMember` — linhas 1216–1230

Por frame dispatch match; PCMA/PCMU funções nativas. Codec normalizado por envio. G729/Opus/GSM preservam encoder por membro.

### `isMember` — linhas 1239–1242

Por pacote lookup O(1), redundante após consulta sourceMember mas público permite alterações em callbacks.

### `addMember` — linhas 1244–1330

Por membro negocia TX/RX e ptime, cria ByteBuffer 4KB/channel wake/RTP e codecs separados. Codec uppercase é local: peer original pode continuar minúsculo e perder fast relay. Substituição fecha wake antigo, mas resíduos/métricas de mesmo id merecem teste.

### `processVAD` — linhas 1332–1379

Privado sem chamada interna. Energia e hangover 400ms, estado global entre membros. frameDuration zero com PCM vazio pode dividir por zero. Callback extra[0] pode estar ausente. Não atribuir CPU ativa a esse método.

### `setAudioMetricsEnabled` — linhas 1388–1396

Evento alterna flag, libera rtpStats, mantém contadores e metricJitterFrequency. Inicialização de jitter começa próximo pacote.

### `getAudioMetrics` — linhas 1398–1405

Consulta por evento: copia array COW, adiciona jitter_ms pelo último clock; mistura múltiplos SSRC numa saída escalar.

### `send2833` — linhas 1409–1544

Por dígito/membro, 160ms com progress e três END. Sleep yield, booleano global não é mutex. Snapshot mantém destino removido após yield; catch silencioso. Timeline incrementa ao fim; pacer já ativo não observa dtmfInUse.

### `checkVadTimeouts` — linhas 1546–1559

Privado sem chamada interna, varre registrados e retorna bool sem limpar entradas. Não há timer VAD instalado nesta classe.

### `sendSilenceProbeToMembers` — linhas 1561–1591

Somente idle path: throttle .5s, por membro não injetado cria PCM e passa pela fila síncrona. Não há timer próprio. Fallback infinito impede esse ramo em silêncio.

### `setLegInjectionActive` — linhas 1593–1609

Por evento ajusta set a/b e marker em todos membros da leg. Não suspende relay normal; apenas probe consulta injectedLegs.

### `sendPcmToLeg` — linhas 1619–1623

Por chunk wrapper retorna último metadado ou null; push pode enviar múltiplos frames sem pacing.

### `pushPcmToLeg` — linhas 1626–1637

Por chunk valida active/leg/formato, resolve destino, converte streaming e acumula. Sem backpressure explícita; caller controla cadence.

### `finishPcmToLeg` — linhas 1640–1658

Por fim de stream flush FIR, preenche residual com silêncio e emite. Diferente de close/cancel que descartam. Retorna todos metadados.

### `cancelPcmToLeg` — linhas 1660–1666

Por interrupção descarta conversor e accumulator sem flush; não limpa relayPcmBuffer por ser caminho distinto.

### `readyMemberIdForLeg` — linhas 1668–1678

Por injeção tenta canonical O(1), fallback O(membros). Não verifica leg do id canônico que foi substituído publicamente.

### `pcmFormatForMember` — linhas 1681–1698

Por frame/chunk normaliza G711/G729/GSM 8k mono; retorna novo array pequeno. Demais formatos usam frequência/canais do membro.

### `convertPcmForMember` — linhas 1700–1735

Por chunk verifica alinhamento, identidade retorna mesma string. Mantém conversor por fonte/destino lógico e reinicia se formato muda. Relay de recepção atual não chama esse helper.

### `logicalMemberKey` — linhas 1737–1741

Por conversão escolhe leg estável para sobreviver rebind; colidir legs públicas exige validação externa.

### `queuePcmForMember` — linhas 1746–1775

Por injeção concat residual; cada substr remove prefixo e copia restante. Entrada grande pode ter custo quadrático e burst UDP; saída inclui array por frame.

### `startRelayPacerForMember` — linhas 1779–1845

Privado sem caller interno: pacer alternativo sai quando esvazia, usa relayNextSendAtNs. Não confundir com pacer realmente criado em queueRelayedPcmForMember.

### `relayBufferForMember` — linhas 1847–1862

Helper do pacer não chamado: migra accumulator legado para ByteBuffer. Sem custo no receive normal.

### `clearRelayBufferForMember` — linhas 1864–1871

Por ptime/close limpa buffer e remove accumulator legado. Não apaga métricas e não acorda pacer bloqueado sem frame.

### `initializeRelayMetrics` — linhas 1873–1893

Por membro cria estrutura de counters preservando anterior quando id repetido; atualiza label/ptime/codec, não reinicia série.

### `recordRelayInterval` — linhas 1895–1912

Por frame no pacer sempre; encoded relay só com audioMetrics. Clock hrtime, soma erro e extremos; fonte de custo assimétrico entre caminhos.

### `getRelayMetrics` — linhas 1915–1931

Consulta O(destinos), copia arrays, arredonda métricas. Chave leg pode sobrescrever outro membro da mesma leg.

### `encodeGsmFrameForMember` — linhas 1934–1951

Por frame PCM valida múltiplo 320B, encode multi-frame e comprimento esperado 33B/20ms. Stateful separado por membro.

### `decodeGsmPayloadForMember` — linhas 1953–1966

Por pacote verifica múltiplo 33B e resultado 320B/frame. Não preenche gsmDecodedPcm para callback: teste preexistente espera propriedade que não é produzida.

### `encodeOpusFrameForMember` — linhas 1968–1975

Por frame encoder dedicado com fallback alias opus. Retorno false nativo pode ser coerção ou erro conforme assinatura; caller verifica payload vazio.

### `encodeG729FrameForMember` — linhas 1977–1991

Por frame subdivide PCM em 160B/10ms; encode por bloco e concat payload. Lazy encoder de membro não substitui dois do construtor.

### `memberByLeg` — linhas 1994–2014

Por consulta O(1) com fallback varredura e memoização. Array retornado é snapshot COW, objetos continuam compartilhados.

### `rebindMemberTransport` — linhas 2016–2059

Por evento move member e wake, preserva canal RTP/timeline. Não valida port<=65535 nem colisão com endpoint existente; fila preservada pode ficar sem drainer até novo PCM.

### `makeSilencePayloadForMember` — linhas 2061–2119

Privado usado por testes, sem caller interno no receive. Tabelas PCMA D5/PCMU FF e encode stateful; não substituir probes ativos sem medir.

### `makeSilencePcmForMember` — linhas 2121–2130

Por probe aloca 2*samples*channels de zero; formato respeita membro e ptime.

### `samplesForPacket` — linhas 2132–2135

Helper round(rate*ptime/1000), mínimo 1; taxas não inteiras por ptime podem acumular arredondamento.

### `validatePacketTimeForCodec` — linhas 2137–2142

Por configuração só restringe GSM a múltiplos de 20; Opus e G729 dependem validação tardia do encoder.

### `close` — linhas 2144–2262

Por chamada, fecha tap antes de limpar referência, cancela read, fecha wakes, apaga closures/canais e buffers. Erros de codecs capturados em blocos amplos podem pular restante do membro. Dois G729 do constructor e caches privados ficam até destruição. Não recria blockChannel para restart.

### `findTelephoneEventPt` — linhas 2272–2297

Por DTMF busca global PT por rate, fallback primeiro telephone-event depois101. 101 é fallback legado e não atribuição estática RFC. Mapeamentos direcionais por peer não são usados.

### `registerPtCodecs` — linhas 2301–2349

Por SDP preenche defaults e fornecidos via explode. Repetir não limpa entradas antigas; TX e RX compartilhados nas registries legado. Canais default L16 do preRender não são escritos em ptCodecsChannels nesse primeiro loop.

### `packetOnTimeout` — linhas 2351–2354

Registro/configuração por chamada ou evento: atribuição direta sem trabalho de mídia; invocação externa posterior pode reentrar e capturar objetos. Sem ganho CPU demonstrado para alterar esse contrato.

### `getFrequencyFromPtCodec` — linhas 2358–2361

Wrapper público resolve frequência RX, preserva fallback legado.

### `processDtmf` — linhas 2372–2518

Uma coroutine por datagrama; unpack, chave SSRC/timestamp/event, END/debounce, varredura cache. Callback só na retransmissão na baseline, corrigido no primeiro END em candidate. Cache incompletos não expira sozinho.

### `translateDigit` — linhas 2520–2537

Por primeiro END gera mapa 0–11, A–D não traduzidos. digit calculado não usado no callback baseline; preservar callback inteiro e quatro argumentos evita mudança pública.

## Dependências e custo indireto

`rtpc.php` integral (243 linhas): construtor compatível mantém campos fixos mesmo com CSRC/ext inválidos, padding não removido. Nativo usa psampler_rtp_parse(strict=false) e snapshots próprios; rawPacket lazy usa __get por referência, isset e serialização para compatibilidade com escrita pública. Comparação de campos, clone, serialização e buffer reaproveitado foi executada pelo teste nativo. build declara string e retorna false em entrada vazia sob coerção; não mudar nesta auditoria.

`rtpChannel.php` integral (527 linhas): gera sequence e timestamp aleatórios por membro; pack CCnNN por saída; sequência wire mascarada 16 bits, timestamp 32 bits. Estado DTMF paralelo exige coordenação com áudio. Instancia G729 pelo PT18, logo G729 remapeado dinamicamente exige revisão: switch MediaChannel usa nome do codec, canal RX pode não ter decoder inicializado. Nada alterado nessa dependência.

`PcmStreamConverter.php` integral (75 linhas): identidade não aloca PcmBuffer. Mudança só de canais faz reset, mudança de taxa mantém história FIR em clear, finish faz flush e cancel descarta. `ByteBuffer` mantém fila relay limitada; `queuePcmForMember` usa strings e comportamento de burst distinto.

`EarlyMediaAcousticTap.php` integral (141 linhas), `AcousticRtpContinuity.php` integral (39), `EarlyMediaAcousticMonitor.php` integral (141) e `EarlyMediaVoicePolicy.php` integral (42): tap só analisa leg b, codecs PCMA/PCMU/G729/Opus, 8k ou 48k com limite120ms/pacote. Uma conversão para 8k mono por stream, frames de 320B, analyzer nativo push+state por 20ms, callbacks de transição com WeakReference e dedup bounded. Política requer 1000ms voz qualificada com tolerância200ms, fecha ao decidir. G729/Opus compartilhados falham abertos se histórico não verificável. Nenhum timer periódico acústico. processing_ns mede parede do monitor, não CPU exclusiva; não somar ao task-clock.

`AdaptiveBuffer.php` integral (272 linhas) não é instanciado pela MediaChannel: não é o jitter buffer usado no relay. Não atribuir seu logging, Channel ou adaptação ao caminho auditado. `SocketMutable.php` integral (41 linhas) herda Socket real, destroy põe closed=true antes do parent::close e não verifica retorno; falha de close pode não ser tentada novamente. `cache.php` armazena GLOBALS, get/join não são memória compartilhada entre workers. `network::getLocalIpv6` executa só para cache local_ip ausente, portanto não é descoberta de interface a cada pacote.

Nativo local consultado: `source/php-src/ext/swoole/ext-src/swoole_socket_coro.cc:1792–1918`, `source/php-src/ext/swoole/src/coroutine/socket.cc:1327–1350`, `source/php-src/ext/psampler/binary_buffer.c:307–410`. recvfrom aloca string SW_BUFFER_SIZE_BIG por chamada, recvfromInto usa guard de buffer/socket durante yield e mesma recepção de socket com timer/controller. psampler copia campos e payload, checks de versão/padding dependem strict. Nenhum componente nativo foi modificado. Fonte local é evidência da implementação disponível, hash do executável é a identidade medida; não assumimos prova de build bit a bit dessa fonte.

Integração SipSwoole de produção não foi aberta nem alterada. Portanto custo de callbacks externos, gravação e políticas da aplicação não está incluído nem garantido por esta auditoria. Os callbacks do harness são explícitos e mínimos.
