# Plano e controle do experimento MediaChannel

Comparações independentes:
A: baseline-native versus optimized-native; mesmo PHP receptor e fábrica, apenas binário muda.
B: optimized-native versus optimized-slice; mesmo executável e transporte recvfromInto. Slice não é recvfrom tradicional.

Plano principal: cinco blocos, três cenários por bloco, 60 s ativos cada, ordem alternada predefinida em run.py. 50 datagramas/s no loopback; PCMA PT8 com 160 bytes D5 e telephone-event PT101. Cada ciclo de 150 slots de 20 ms tem onze datagramas DTMF, com oito progressivos e três finais; dígito determinístico. Cerca de 2780 áudios + 220 DTMF e 20 eventos por janela de 60 s. Não é carga de saturação. Emissor separado e fixado na CPU4; receptor na CPU2, núcleos físicos distintos.

O receiver.php existente recebeu somente instrumentação opcional de laboratório e um segundo peer opcional. Cópia anterior preservada em sources/receiver-before.php. Produção, psampler, Swoole e Zend não foram modificados. A configuração efetiva é obtida por Reflection no objeto do canal. receiveOnlyMode=false, nativeRtpReceive=true, exigência da API, métricas extras desabilitadas, probes habilitados; mesmo timeout 0,2 no fonte real, mesmo callback/timer de status a cada segundo. Segundo membro fica exclusivamente nas séries two-*.

Antes da coleta: binário absoluto, SHA-256 esperado, nm, reflexão da API, fonte da fábrica, estado lazy tanto em pacote sintético quanto no primeiro pacote real, /proc/PID/exe e seu hash. Cinco segundos de aquecimento e mínimo 230 datagramas recebidos. Falha desses checks aborta a execução. Manifesto inclui comandos, PID, configurações e hashes.

Perf inicia desabilitado. Uma pipe de controle com acknowledgement habilita os eventos somente depois do aquecimento. Após 60 s, desabilita e confirma; snapshots imediatamente antes/depois delimitam contadores e getrusage. Pequena diferença temporal entre snapshots e controle é registrada. Contagem RTP usa o contador privado já existente packetsProcessed, lido só nas bordas. Áudio usa o callback existente. Datagramas DTMF = RTP-áudio para este tráfego conhecido, sem RTCP ou inválidos. Eventos DTMF são callbacks agrupados com intervalo de 0,5 s (eventos enviados a cada 3 s); callbacks brutos também são registrados.

Sender possui contadores independentes por tipo, erro e atrasos, e responde a snapshots nas bordas. Aceita-se no máximo dois datagramas de diferença por desalinhamento temporal das consultas; resultados individuais permitem conferir. Não há compensação de atraso com rajadas. Janelas com erro de envio ou slot muito atrasado abortam a série, sem ocultar o diretório.

perf stat coleta task-clock, context-switches, cpu-migrations, cpu_core/cycles/ e cpu_core/instructions/. CPU% = task-clock / duração ativa, em percentual de um núcleo. CPU/pacote divide task-clock pelo delta RTP, incluindo áudio e DTMF. getrusage fornece user/kernel em janela muito próxima, mas não exatamente idêntica; não substituir silenciosamente task-clock.

Os PMUs desta máquina são híbridos. CPU2 pertence a cpu_core, portanto eventos explícitos evitam “not counted” do PMU cpu_atom. Afinidade elimina migrações voluntárias; governor/frequência não foram alterados. Load average e frequência instantânea nas bordas são registrados; não equivalem a isolamento térmico ou frequência fixa.

Amostragem separada: uma execução de 60 s por cenário, cpu_core/cycles/, período 100.000 ciclos e pilhas DWARF de 8192 bytes. Não usar task-clock das execuções com amostragem para quantificar ganho principal. perf report apresenta self e inclusive separadamente. Não somar percentuais inclusivos. PHP interpretado costuma aparecer como execute_ex/handlers; sem instrumentação específica não atribuir amostras a métodos PHP individuais.

Série suplementar two-* tem dois peers: fonte PCMA e destino PCMU, mesmo recebimento e parser. Sink externo ao processo medido confere payload PCMU FE x160 (resultado confirmado pela API de codec PHP), PT0 e forwarding DTMF. Uma repetição de 60 s por cenário apenas para contexto funcional, sem inferência estatística equivalente à série principal.

Dados dos pilotos ficam separados. O primeiro piloto revelou que perf stat encerra com sinal SIGINT (-2) após gravar counters completos; o runner reconhece esse encerramento deliberado e valida os dados. O diretório failed permanece registrado.

Acesso perf foi inicialmente negado (paranoid=4). Usuário liberou paranoid=1 e kptr_restrict=0. Nenhum sudo com senha ou mudança em sysctl foi executado pelo agente. Valores originais para restauração: 4 e 1.
