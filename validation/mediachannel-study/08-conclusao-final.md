# Conclusão da auditoria e estado de validação

Revisão integral da MediaChannel e mapeamento dos caminhos/dependências registrados. Baseline é a implementação local de2538 linhas, com estado pré-existente preservado. A análise não homologa aplicação em produção: a matriz funcional solicitada ainda tem lacunas explícitas em05-testes-funcionais.md.

Bugs reproduzidos e corrigidos somente em lab/candidate: callback DTMF perdido sem retransmissão e em wrap; timeout fallback ocioso; datagrama curto admitido sem métricas; relay G711 com duração wire divergente da configuração. Patches isolados e provas antes/depois disponíveis. Mantidos argumentos do callback, propriedades públicas e rtpc original. Não se atribui redução CPU a essas correções.

Problemas reproduzidos ainda sem correção: telemetria acústica acusa três pacotes DTMF recebidos como perdas e reinicia época; retenção de sockets/FDs durante reactor com liberação ao seu término. Suite preexistente de ptime falha no mock cancel e, com fixture adaptada, em expectativa de PCM GSM no callback anterior ao decode. Esses resultados estão preservados e não ocultados por uma suite artificialmente verde.

O protótipo acústico otimizado mantém os estados/eventos testados. Microbenchmark320B mostra economia local, mas benchmark de canal completo não comprova ganho estável. Não integrado. Micro inicial160B não acionava o fast path e foi invalidado para essa comparação, preservando dados e script inicial. Perf real coletou task-clock, switches, cycles e instructions sem alterar segurança global. Nenhum gargalo dominante foi localizado por amostragem; números de processo não identificam sozinhos hotspot de classe.

Alternativas adiadas: cache persistente de SDP com arrays públicos mutáveis; FIR contínuo de relay; unificação de pacers; lazy dos G729 públicos; teardown/restart centralizado; política autenticada de autojoin; expiração bounded de DTMF. Requerem contratos e reproduções adicionais, não refatoração automática.

Para SipSwoole em produção: revisar patches individualmente; antes de aproveitar, ampliar testes de exceção/cancelamento/restart, retenção FD por worker, DTMF simultâneo, matriz RX multicodec/ptime e corpus acústico real. Manter análise acústica integral, detectar descontinuidade separada de perda de rede e acompanhar fila/backlog/atraso. Não desligar funcionalidade para reduzir números. Nenhuma mudança de produção autorizada ou executada.

O pacote permite revisão independente: fontes congeladas, patches, hashes, comandos, dados brutos, limitações e resultados negativos. Não declarar investigação e homologação integralmente concluídas enquanto testes pendentes e retenção de FDs não forem resolvidos.
