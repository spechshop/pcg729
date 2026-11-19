#!/usr/bin/env python3
import pathlib,json,statistics,math,re
P=pathlib.Path(__file__).resolve().parent
rows=json.loads((P/'individual.json').read_text());groups=json.loads((P/'aggregates.json').read_text());pairs=json.loads((P/'comparisons.json').read_text());profiles=json.loads((P/'profile-summary.json').read_text())
names={'baseline-native':'Baseline + nativo','optimized-native':'Otimizado + nativo','optimized-slice':'Otimizado + slice/legado'}
txt="""# Resultado dos benchmarks perf da MediaChannel

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
"""
for r in rows:
 if r['two_members']:continue
 txt+=f"| {r['round']} | {names[r['scenario']]} | {r['task_clock_ms']:.2f} | {r['cpu_percent']:.4f} | {r['user_ms']:.3f} | {r['kernel_ms']:.3f} | {r['context_switches']:.0f} | {r['rtp']} | {r['audio']} | {r['cpu_us_per_rtp']:.3f} |\n"
txt+="""
Contadores de hardware individuais (milhões):

| Bloco | Cenário | Cycles M | Instructions M |
|---|---|---:|---:|
"""
for r in rows:
 if not r['two_members']:txt+=f"| {r['round']} | {names[r['scenario']]} | {r['cycles']/1e6:.3f} | {r['instructions']/1e6:.3f} |\n"
txt+="""
Dados completos: [individual.csv](individual.csv), [individual.json](individual.json), manifests e measurement.json por execução. Nenhuma janela principal foi removida por resultado desfavorável. Nos casos em que sender e receiver diferem em um pacote, suas consultas ocorreram em instantes ligeiramente diferentes; logs/tempos permitem conferir, sem afirmar perda.

User+kernel NÃO é igual ao task-clock. A diferença observada é de 28,46–38,83 ms nas janelas principais. O desalinhamento entre duração dos snapshots e perf foi no máximo 1,65 ms, insuficiente para explicar essa diferença; não se trata apenas de arredondamento ou borda. A consulta diagnóstica de schedstat viu dois threads, com o auxiliar sem avanço de CPU no intervalo observado, mas não resolve integralmente a diferença de contabilização. Mantivemos ambos os valores, sem “corrigir” um pelo outro nem atribuir causa não demonstrada. Task-clock é a métrica principal solicitada.

## Agregados e dispersão

DP amostral entre cinco repetições; não é erro padrão.

| Cenário | Task-clock mediana ms | Mín–máx ms | DP ms | CPU% mediana | µs/RTP mediana | µs/RTP mín–máx | DP µs/RTP |
|---|---:|---:|---:|---:|---:|---:|---:|
"""
for s,g in groups.items():
 t=g['task_clock_ms'];u=g['cpu_us_per_rtp']
 txt+=f"| {names[s]} | {t['median']:.2f} | {t['min']:.2f}–{t['max']:.2f} | {t['stdev']:.2f} | {g['cpu_percent']['median']:.4f} | {u['median']:.3f} | {u['min']:.3f}–{u['max']:.3f} | {u['stdev']:.3f} |\n"
txt+="""
A primeira rodada ocorreu com frequências efetivas maiores que várias rodadas posteriores. Frequências instantâneas nas bordas variaram de aproximadamente 0,8 a 4,6 GHz. Em algumas janelas task-clock aumenta enquanto cycles diminui: ciclos de stall/CPU e frequência não são um fator constante de conversão. Load average/frequência estão nos manifests; snapshots de frequência não descrevem toda a janela.

A análise foi ajustada de forma controlada usando contrastes por bloco e instruções por pacote, preservando as 15 janelas iniciais. Não alteramos governor, código ou carga no meio da série, nem repetimos até obter um resultado favorável. As instruções ajudam a avaliar trabalho executado; não substituem CPU total.

### Comparações A e B

Sinal positivo significa menor CPU/RTP do nativo otimizado. Contrastes em µs/RTP, por bloco:

| Comparação | Bloco 1 | Bloco 2 | Bloco 3 | Bloco 4 | Bloco 5 | Média | Mediana |
|---|---:|---:|---:|---:|---:|---:|---:|
"""
for k,v in pairs.items():
 txt+='| '+k+' | '+' | '.join(f'{x:.3f}' for x in v['difference_us_per_rtp'])+f" | {v['mean_difference']:.3f} | {v['median_difference']:.3f} |\n"
for k,v in pairs.items():
 d=v['difference_us_per_rtp'];se=statistics.stdev(d)/math.sqrt(len(d));lo=statistics.mean(d)-2.776*se;hi=statistics.mean(d)+2.776*se
 txt+=f"\n**{k}:** média do contraste {v['mean_difference']:.3f} µs/RTP; intervalo t exploratório de 95% [{lo:.3f}, {hi:.3f}] µs/RTP. Enumeração de sinais pareados: p bilateral={v['two_sided_sign_randomization_p']:.4f}.\n"
txt+="""
Esses cálculos são descritivos/exploratórios: apenas cinco blocos, ordem alternada predefinida (não sorteio independente), host compartilhado e normalidade dos contrastes não demonstrada. Ambos os intervalos abrangem zero. A não conclusividade não demonstra equivalência perfeita; significa que este experimento não resolveu a diferença de tempo de CPU.

A razão entre medianas de task-clock sugere cerca de 3,7% menos no A e 3,5% menos no B, mas **não deve ser anunciada como ganho comprovado**. No B, a diferença média pareada é quase zero, mostrando como a escolha da estatística pode criar uma impressão enganosa.

### Instruções executadas

| Cenário | Mediana M | Mín–máx M | DP M |
|---|---:|---:|---:|
"""
for s,g in groups.items():
 t=g['instructions'];txt+=f"| {names[s]} | {t['median']/1e6:.3f} | {t['min']/1e6:.3f}–{t['max']/1e6:.3f} | {t['stdev']/1e6:.3f} |\n"
a=groups['baseline-native']['instructions']['median'];b=groups['optimized-native']['instructions']['median'];c=groups['optimized-slice']['instructions']['median']
txt+=f"""
O nativo otimizado apresentou aproximadamente **{(a-b)/a*100:.2f}% menos instruções** que baseline+nativo e **{(c-b)/c*100:.2f}% menos** que slice, pela razão das medianas. As faixas observadas de instruções do otimizado ficaram abaixo das outras duas. É evidência consistente de menor trabalho nesse fluxo completo; não mede sozinho tempo/energia/CPU em produção nem garante comportamento sob saturação.

## Perfis de CPU e custos por camada

Amostragem separada, 60 s por cenário, evento cpu_core/cycles/, período 100.000 ciclos, DWARF 8192 bytes. Perf report self e inclusive, perf script e perf.data estão em profile-1 (baseline+nativo), profile-2 (otimizado+nativo) e profile-3 (otimizado+slice). As execuções de profiling não entram nas estatísticas anteriores. Não converter percentuais de ciclos diretamente em milissegundos de CPU de outra execução.

As categorias abaixo contam cada amostra no máximo uma vez DENTRO da categoria. Categorias podem se sobrepor (por exemplo, bridge dentro de parser, propriedade dentro de parser). **Não somar colunas.** Pilhas podem truncar na fronteira coroutine/musl; PHP interpretado não fornece aqui nome de cada método PHP. Os valores não são uma decomposição exaustiva.

| Perfil | Amostras | Kernel no topo | Parser nativo incl. | Bridge incl. | Recepção UDP incl. | Propriedades Zend incl. | Alocação/cópia incl. |
|---|---:|---:|---:|---:|---:|---:|---:|
"""
for k,v in profiles.items():
 g=v['groups'];txt+=f"| {k} | {v['samples']} | {100*v['kernel_leaf']/v['samples']:.2f}% | {g['native_parser_inclusive']['percent']:.2f}% | {g['buffer_bridge_inclusive']['percent']:.2f}% | {g['udp_receive_inclusive']['percent']:.2f}% | {g['zend_property_inclusive']['percent']:.2f}% | {g['allocation_copy_inclusive']['percent']:.2f}% |\n"
txt+="""
Critérios reproduzíveis em [analyze-profiles.py](analyze-profiles.py); contagens em [profile-summary.json](profile-summary.json). Percentuais de perfil não são intervalos estatísticos nem diferenças absolutas de consumo.

### Funções de maior custo próprio
"""
for k in profiles:
 txt+=f"\n**{k}:**\n\n| Self | DSO | Símbolo |\n|---:|---|---|\n"
 n=0
 for line in (P/k/'report-self.txt').read_text().splitlines():
  m=re.match(r'^\s*(\d+\.\d+)%\s+(\S+)\s+\[.\]\s+(.*?)\s{2,}',line)
  if m:
   txt+=f"| {m[1]}% | {m[2]} | {m[3].replace('|','/')} |\n";n+=1
   if n==10:break
txt+="""
## Série suplementar com dois membros

Mesmos caminhos A/B, mesma fonte PCMA, acrescido destino PCMU e sink externo ao PID medido. Um ensaio por cenário; não interpretar como cinco repetições ou conclusão estatística. O sink compara bytes PCMU FE x160 com o resultado esperado da conversão de D5 x160, validado também pela API de codec PHP em preflight/pcmu-expected.txt.

| Cenário | Task-clock ms | CPU% | µs/RTP | RTP | PCMU validado no sink | DTMF encaminhado |
|---|---:|---:|---:|---:|---:|---:|
"""
for r in rows:
 if r['two_members']:
  m=json.loads((P/r['run']/'manifest.json').read_text())
  txt+=f"| {names[r['scenario']]} | {r['task_clock_ms']:.2f} | {r['cpu_percent']:.4f} | {r['cpu_us_per_rtp']:.3f} | {r['rtp']} | {r['sink_pcmu_valid']} | {m['counts']['sender']['sink_dtmf']} |\n"
txt+="""
A validação comprova processamento de mídia, não só envelope RTP. O cenário principal tem um único membro e seu loop de relay exclui a própria origem: não foi tratado como transcodificação por pacote. Custos adicionais do suplemento incluem codec, fila de relay, packetização e sendto; não atribuir a diferença apenas ao codec.

A chave options.transcodingEnabled permaneceu false como no receptor original; o ramo real de relay inspecionado decide por codec/frequência/canais/ptime, sem consultar essa chave. A prova de transcodificação é a saída PCMU validada, não o rótulo dessa opção. Contagem do sink pode diferir em um pacote pela janela ligeiramente mais ampla das consultas externas.

## Integridade, limitações e reprodução

Antes de cada janela, o runner verifica caminho absoluto e hash do executável, símbolo/API, fábrica real, configuração do objeto, PID e /proc/PID/exe. O primeiro pacote recebido também comprova lazy/native versus eager/slice. O audit final confere contadores e hashes de todas as fontes monitoradas. Rótulos sozinhos não foram usados como prova.

Nenhum build foi necessário nesta etapa. psampler, Swoole, Zend, libspech local de produção e libspech externa não foram alterados. mc.php e lsend.php foram preservados. receiver.php recebeu apenas instrumentação de laboratório; a versão anterior está arquivada. Não houve commit, push, publicação ou carga massiva.

Acesso inicialmente negado foi documentado em preflight. Usuário liberou temporariamente paranoid=1 e kptr_restrict=0; a política é explicada pela [documentação do kernel](https://www.kernel.org/doc/html/latest/admin-guide/perf-security.html). Os valores originais eram 4 e 1. A tentativa automática de restauração falhou porque sudo exige senha (preflight/restore.txt). Os valores permaneceram 1 e 0 ao concluir. Para restaurar: sudo sysctl -w kernel.perf_event_paranoid=4 kernel.kptr_restrict=1.

Pilotos e falhas foram preservados. O piloto inicial marcou SIGINT do perf como falha; counters tinham sido finalizados corretamente. O runner passou a aceitar o encerramento deliberado -2 após disable+ack, sem aceitar outros erros. Não houve substituição de resultados principais.

Limites: um fluxo a 50 PPS, CPU híbrida com DVFS, host compartilhado, cinco repetições, pequena incerteza das bordas e overhead do observador. Perf mostra CPU ativa, não os 60 segundos de parede passados majoritariamente aguardando datagramas. Não é estimativa de capacidade máxima nem validação de milhares de chamadas.

Principais dados: [individual.csv](individual.csv), [aggregates.json](aggregates.json), [comparisons.json](comparisons.json), [profile-summary.json](profile-summary.json), [audit.json](audit.json). Comandos reais e arquivos por execução tornam possível auditar ou repetir sem confundir binários.
"""
interpretation=(P/'interpretacao.md').read_text()
txt=txt.replace('## Série suplementar com dois membros',interpretation+'\n## Série suplementar com dois membros')
(P/'resultado-final.md').write_text(txt)
