Experimento automatizado RTP real via MediaChannel.
Relatório principal: resultado-final.md (gerado após conclusão).
Plano e condições: metodologia.md.

Execução a partir da raiz:
  python3 validation/rtp-native/study/perf/run.py --pilot
  python3 validation/rtp-native/study/perf/run.py
  python3 validation/rtp-native/study/perf/run.py --profiles
  python3 validation/rtp-native/study/perf/run.py --two
  python3 validation/rtp-native/study/perf/analyze.py
  python3 validation/rtp-native/study/perf/analyze-profiles.py
  python3 validation/rtp-native/study/perf/audit.py

Por segurança, o runner recusa sobrescrever diretórios de execuções existentes.
Para repetir, arquive os diretórios anteriores (não descarte resultados).
Manifestos contêm os comandos reais e hashes; para reexecutar uma janela individual:
  python3 -c "import sys; sys.path.insert(0,'validation/rtp-native/study/perf'); import run; run.run('repeticao-nova','optimized-native',60)"
Cenários: baseline-native, optimized-native, optimized-slice.
Amostragem: passar profile=True. Dois membros: two=True.
Portas do laboratório devem estar livres: 40000/40001, 50000/50001/50002.
Nenhum componente nativo é recompilado ou modificado por esses scripts.
O programa encerra apenas os processos que ele próprio iniciou.

stat-r*/: cinco repetições por cenário, principais.
profile-*/: amostragem separada, não usar para comparação de task-clock principal.
two-*/: suplemento funcional com transcodificação PCMA para PCMU.
pilot*/: testes de preparação; nunca misturar com principais.
preflight/: permissões, PMU, ambiente e prova do codec.
SIGINT é enviado ao perf após disable+ack; exit -2 é esperado nessa instalação,
com counters finalizados. Outros sinais/falhas abortam a janela.

Restauração dos sysctls temporariamente liberados pelo usuário:
  sudo sysctl -w kernel.perf_event_paranoid=4 kernel.kptr_restrict=1
