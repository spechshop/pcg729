Comece por final.md; diagnóstico e experimentos contêm os detalhes.
Tudo relativo à raiz /home/lotus/CLionProjects/pcg729.

Reprodução:
  python3 validation/rtp-native/study/run-bench.py
  python3 validation/rtp-native/study/run-udp.py
  python3 validation/rtp-native/study/run-tests.py

Receptor para avaliação manual:
  RTP_STUDY_MODE=native buildroot/bin/php validation/rtp-native/study/receiver.php
  RTP_STUDY_MODE=slice buildroot/bin/php validation/rtp-native/study/receiver.php

A/B da otimização: mesmo modo native com php-baseline e binário final.
Receptores usam porta 40000 e esperam origem 127.0.0.1:50000 como mc-native.
Não execute ambos simultaneamente. O emissor original não foi alterado.
media-compare.php é a variante finita funcional, com portas efêmeras.

control-aa/ contém a rodada inicial com binários idênticos (instalação SPC falhou).
Não interpretar o rótulo interned dessa pasta como implementação otimizada.
tests.json registra duas falhas conhecidas de ptime; coletor não as oculta.
phpt.txt registra skips de fixtures ausentes.
memcheck-exit.txt=127: Valgrind indisponível, não houve memcheck efetivo.

optimization.patch descreve só a mudança desta investigação.
php-baseline e binary_buffer.baseline.c preservam o estado inicial experimental.
Não aplicar reversão sobre alterações posteriores sem revisar o diff.
