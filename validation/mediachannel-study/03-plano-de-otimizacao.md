# Plano antes da implementação

1. Baixo risco: reproduzir DTMF END isolado e deduplicação; corrigir somente em candidate com assinatura de callback preservada. Testar paridade rtpc nativo/tradicional, codecs e lifecycle.
2. Baixo risco funcional: timeout fallback 200ms e interrupção após callback que fecha canal, apenas se reproduzidos. Medir wakeups e considerar custo em ociosidade.
3. Experimento de CPU independente: fast path de PCM 320B no monitor, sem desligar acústica e sem mudança de análise. Variante optimization sem correções de bugs para evitar atribuição falsa de ganho. Descartar se inconclusivo.
4. Intermediárias documentadas: validação completa RTP no transporte, cache de resolução negociada com invalidação para arrays públicos, limite/expiração DTMF, cleanup único finally, rebind seguro. Não integrar sem reproduções e contratos definidos.
5. Arquiteturais: resampler contínuo no relay, pacer por worker, seleção autorizada de peer em vez de autojoin, duração real por codec e controle de fila por geração. Exigem compatibilidade SIP e endpoints reais; manter propostas.

Prioridade: eventos perdidos e terminação > validação e limites de memória > trabalho DSP redundante > microoperações Zend. Não trocar codecs stateful por caches de payload. Não usar 1 membro para inferir custo de transcode. Não alterar Swoole/psampler/PHP. Benchmarks em loopback e carga limitada, múltiplas repetições intercaladas. Perf sem mudar sysctl. Baseline e optimization usam o mesmo binário explicitamente hasheado, com Reflection para arquivo efetivamente carregado.
