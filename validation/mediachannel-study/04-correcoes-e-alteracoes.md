# Correções e alterações experimentais

Somente `validation/mediachannel-study/lab/candidate/plugins/Utils/sip/MediaChannel.php` foi alterada como candidata. Baseline e libspech original permanecem intactas. Nenhum componente nativo, mc.php, lsend.php ou SipSwoole foi modificado. Não há commits.

| Alteração | Antes e depois | Evidência | Compatibilidade e risco |
|---|---|---|---|
| DTMF | Callback dependia de END retransmitido seq+1. Agora primeiro END dispara e retransmissões só encaminham. | fix-dtmf*.patch; functional.json | Mantidos quatro argumentos `(event,peer,event,this)`, todos os inteiros e coroutine do callback. Ordem observável muda para corrigir perda; debounce legado preservado. |
| Idle fallback | recvfrom -1 → .2s | fix-idle.patch; functional.json | Reativa timeout/probes existentes. Custo adicional até5 wakeups/s/canal ocioso. Não contabilizado como economia CPU. |
| Validação mínima | Comprimento<12/versão!=2 descartado mesmo sem métricas | fix-validation.patch; functional.json | rtpc público intacto e parser compatível inalterado. Não é validação completa de RTP. Pacotes malformados ainda podem refrescar timeout antes do descarte. |
| Duração G711 | Relay direto só quando payload PCMA/PCMU tem samplesPerPacket bytes no destino | fix-g711-duration.patch; ptime-frame.json | 40ms recebidos com configuração20ms passam por decode/encode e pacer. Custo maior nesse caso para corrigir timeline. Comparação exata de waveform G711, padding, fragmentos e ptime dinâmico ainda precisam ampliação. |

Patches são pequenos e separados, reversíveis sem Git. A variante optimization difere só em EarlyMediaAcousticMonitor.php, conserva todas análises e callbacks; é **protótipo rejeitado para integração** e fica congelado para reprodução das medições. Não foi aplicado na candidate. O ramo evita montagem intermediária de string para320B alinhados; mantém caminho fragmentado. Não se alegam cópias físicas eliminadas sem medir allocator, pois substr de string inteira pode retornar referência em Zend.

As correções não foram misturadas à comparação de CPU. Não houve refatoração ampla. Propostas de teardown único, FIR contínuo, cache de mapas públicos e scheduler compartilhado ficaram documentadas.
