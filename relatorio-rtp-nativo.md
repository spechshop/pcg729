# Relatório técnico — parser RTP nativo experimental no psampler

## 1. Escopo e grau de evidência

Este documento descreve o estado local de `/home/lotus/CLionProjects/pcg729`, incluindo sua cópia de `libspech`. A implementação é experimental. A investigação deste relatório fez somente leituras de fontes, Git, hashes, logs e metadados do executável, além da escrita deste documento. Não houve alteração de código, compilação, execução de testes funcionais, benchmarks, `perf`, commits ou publicação nesta etapa.

As referências indicam arquivos relativos à raiz e linhas da versão inspecionada. Os resultados de testes pertencem à etapa anterior de implementação, registrada na conversa e em `validation/rtp-native/`. A execução de PHP nesta investigação limitou-se a consultar versões e `ReflectionFunction`; não processou tráfego.

Classificação usada:

- **Comprovado no fonte/binário:** comportamento verificável diretamente na implementação ou nos metadados consultados.
- **Resultado funcional registrado:** teste executado na implementação, sem repetição nesta investigação.
- **Observação informada pelo usuário:** recepção contínua próxima de 50 PPS, PCMA e DTMF; não é uma medição reproduzida aqui.
- **Estimativa estática:** contagem de operações para um caminho definido, sem medir tempo ou chamadas ao sistema de alocação.
- **Hipótese:** possível efeito sobre desempenho que precisa de medição controlada.

O parser elimina o `unpack()` do cabeçalho RTP no caminho nativo, mas não elimina objetos PHP, strings de payload, escritas Zend, arrays de peer, processamento PHP da MediaChannel ou `unpack()` de DTMF. Não há evidência suficiente para afirmar ganho de velocidade.

## 2. Inventário da implementação

| Arquivo | Alteração do experimento |
|---|---|
| `source/php-src/ext/psampler/binary_buffer.c:274` | Contexto de parsing, leitores de bytes/inteiros, cópia de ranges, interpretação RTP e função PHP `psampler_rtp_parse` |
| `source/php-src/ext/psampler/binary_buffer.h:7` | Declaração `PHP_FUNCTION(psampler_rtp_parse)` |
| `source/php-src/ext/psampler/psampler.c:940` e `:955` | Arginfo e registro na tabela de funções |
| `source/php-src/ext/psampler/psampler.stub.php:8` | Assinatura pública experimental |
| `source/php-src/ext/psampler/tests/rtp_parse.phpt:1` | Novo teste de ownership, limites e exceção em setter |
| `downloads/psampler/` | Mesmos cinco arquivos sincronizados na cópia local usada como origem de fontes |
| `libspech/plugins/Utils/sip/rtpc.php:79` | Fábrica `fromNativeBuffer()` passa a chamar o parser nativo |
| `libspech/plugins/Utils/sip/MediaChannel.php:51`, `:70`, `:646` | Flag/método para exigir parser nativo e verificação síncrona em `start()` |
| `libspech/test_rtpc_native.php:8` e `:119` | Exige a nova API; acrescenta 31 divisões de buffer circular |
| `mc-native.php:1` | Novo receptor experimental |
| `validation/rtp-native/functional.php:1`, `media.php:1` | Novos testes funcionais UDP/Swoole e MediaChannel |
| `validation/rtp-native/` | README, log de build, resultado PHPT, smoke test e manifests de hashes |

Não foi criado um novo arquivo C/C++ de parser: ele foi acrescentado a `binary_buffer.c`. Nenhum C++ do Swoole foi alterado **neste experimento de parsing**. A bridge, o produtor `Swoole\Buffer`, `recvfromInto()`, o dispatcher binário e o mecanismo de snapshot tardio de `rtpc` já existiam. Não atribuir toda a infraestrutura local de buffers a esta mudança.

## 3. API e interpretação RTP

### 3.1 Contrato público

```php
function psampler_rtp_parse(
    object $source,
    int $packetLength,
    object $target,
    bool $strict = true
): ?string;
```

Implementação: `binary_buffer.c:357`. A reflexão do binário confirmou exatamente esta assinatura.

- `source`: objeto de uma classe interna registrada no dispatcher binário. Aceita `Swoole\Buffer` pela bridge e `ByteBuffer`, incluindo dois spans de um ring. Não aceita um objeto arbitrário só por possuir métodos semelhantes.
- `packetLength`: número de bytes válidos do datagrama, normalmente retorno de `recvfromInto()`. Deve ser não negativo e não exceder o comprimento lógico atual do produtor. A API permite um prefixo menor que o comprimento do produtor; a responsabilidade por informar o tamanho correto é do chamador.
- `target`: objeto cujas propriedades serão preenchidas. A fábrica usa um `rtpc` novo. A assinatura não exige essa classe e não valida previamente um schema completo; `stdClass` também funciona. Tipos, hooks e setters do destino podem produzir erros e efeitos colaterais.
- `strict`: habilita validações de versão e padding e a rejeição de RTP estruturalmente inválido. **A integração com libspech passa `false`**, por compatibilidade.
- Retorno válido: string própria contendo o cabeçalho completo, incluindo CSRC, cabeçalho de extensão e dados de extensão. Seu comprimento é o offset do payload. O comprimento de `target->payloadRaw` é o tamanho do payload disponibilizado.
- Retorno `null`: pacote estruturalmente truncado no modo de compatibilidade. Não significa falha de acesso à memória; essas falhas lançam exceções.

Não há objeto RTP C persistente, array de campos retornado, lista de CSRC decodificada ou objeto de extensão. `rtp_parse_context` é uma estrutura automática na pilha com nove `zend_long`, tamanho, flag e três `zval`. O conteúdo de CSRC e da extensão fica opaco no cabeçalho copiado. O profile de extensão é preservado em bytes, não exposto como inteiro; somente seu comprimento é usado no parsing.

### 3.2 Algoritmo

Em `binary_buffer.c:305`:

1. Confere `packetLength` contra a soma dos spans já validados pelo dispatcher.
2. Exige pelo menos 12 bytes para ler o cabeçalho fixo.
3. Extrai versão, P, X, CC, M, PT, sequência, timestamp e SSRC.
4. Lê inteiros big-endian byte a byte (`rtp_uint`, linha 289), sem cast desalinhado e sem `unpack()`.
5. Calcula `offset = 12 + 4 * CC`; verifica se cabe no pacote.
6. Se X=1, exige mais quatro bytes; lê a quantidade de palavras de 32 bits em `offset+2`, avança quatro bytes e valida o comprimento da extensão contra o restante.
7. Calcula `payload_length = packetLength - offset`.
8. Em modo estrito, exige versão 2. Com P=1, exige payload não vazio, último byte diferente de zero e padding menor ou igual ao comprimento disponível; subtrai esse padding.
9. Copia o cabeçalho e o payload para strings próprias.
10. Libera a view e só então escreve as propriedades do destino.

Limites são verificados antes das leituras correspondentes. CC tem quatro bits; o comprimento da extensão tem 16 bits e é multiplicado após conversão para `size_t`. O dispatcher impede overflow da soma dos spans mediante limites de capacidade. Timestamp e SSRC são lidos como `uint32_t` e convertidos a `zend_long`; o binário validado é x86-64, com inteiros PHP de 64 bits. Não houve validação de portabilidade para PHP de 32 bits.

### 3.3 Propriedades e erros

As nove escritas inteiras são `version`, `padding`, `extension`, `cc`, `marker`, `payloadType`, `sequence`, `timestamp` e `ssrc`; depois vem `payloadRaw`. Em sucesso, a API não modifica `rawPacket`: devolve o cabeçalho para a fábrica cuidar do snapshot.

| Situação | Modo estrito | Modo de compatibilidade usado por `rtpc` |
|---|---|---|
| Tamanho negativo ou maior que o produtor | `ValueError` | `ValueError` |
| Produtor não registrado | `ValueError` do dispatcher | Igual |
| Produtor ocupado, não construído ou view inválida | Exceção do produtor/dispatcher | Igual |
| Menos de 12 bytes | `ValueError` | Não escreve campos inteiros; escreve payload/raw vazios; retorna `null` |
| CSRC ou extensão incompletos | `ValueError` | Escreve campos fixos, payload vazio e cópia integral em raw; retorna `null` |
| Versão diferente de 2 | `ValueError` | Preserva a versão e continua se os limites permitirem |
| Padding inválido | `ValueError` | Preserva os bytes, sem validar/remover padding |
| Padding válido | Remove padding de `payloadRaw` | Inclui padding em `payloadRaw`, como o construtor legado |
| Falha de tipo/setter no destino | Propaga erro; destino pode estar parcialmente escrito | Igual |

Fontes: `binary_buffer.c:310`, `:338`, `:346`, `:372`; dispatcher em `:112`. Erros de parsing estrito ocorrem antes das escritas no destino. A API não oferece transação para erros durante as escritas. Destinos reutilizados não são totalmente resetados: com pacote curto, os inteiros antigos permaneceriam. O contrato de uso é um destino novo, como faz a fábrica.

Em modo estrito com padding, `header . payloadRaw` não recompõe o datagrama original, porque o padding foi removido. A fábrica evita esse problema usando o modo de compatibilidade. A nova API não é por si só um serializador RTP completo.

## 4. Memória, cópias, alocações e ZTS

### 4.1 Como a bridge acessa o buffer

`psampler_binary_with_view()` (`binary_buffer.c:112`) procura o provider pela classe exata, incrementa a referência do objeto e chama acquire. Valida identidade, capacidade, comprimentos e ponteiros não nulos dos spans não vazios. Há proteção de bailout para liberar empréstimos mesmo em falhas fatais de alocação.

`swoole_bridge.c:32` adapta a view pública do Swoole para o primeiro span; o segundo fica vazio. Não usa `toString()`, não acessa por suposição o layout privado do produtor e não copia o datagrama nessa adaptação. A ABI é resolvida/validada no startup (`swoole_bridge.c:92`), e não por pacote.

No produtor, `swoole_buffer.cc:182` verifica classe e handlers, storage construído, tamanho, ausência de recepção pendente e leitura ativa. Mantém uma referência adicional, token e identidade da thread, e chama `zend_fiber_switch_block()`. O release (`:225`) desfaz o bloqueio e as referências. As variáveis de token/identidade são `thread_local` (`:38`). São duas referências temporárias ao source: dispatcher e produtor; não são cópias dos bytes.

A leitura termina e o empréstimo é liberado **antes** de executar `zend_update_property*`. Assim, setters podem executar PHP, lançar exceções ou reutilizar o source sem invalidar bytes ainda usados pelo parser. O teste PHPT cobre especificamente essa liberação antes de um setter que limpa o buffer e lança uma exceção.

### 4.2 Contagem do caminho comum

**Estimativa estática delimitada:** pacote RTP estruturalmente válido, payload não vazio, source Swoole contíguo, destino `rtpc` novo, sem hooks adicionais e sem leitura de `rawPacket`. Defina N=tamanho do datagrama, H=tamanho do cabeçalho, L=N−H no modo de compatibilidade.

| Item dentro de `psampler_rtp_parse()` | Contagem |
|---|---:|
| Entrada PHP→função C | 1 |
| Acquire/release da view | 1 par |
| `zend_string_alloc` para bytes RTP | 2: H e L |
| Cópias `memcpy` de bytes RTP, Swoole contíguo | 2, total H+L=N bytes |
| `zend_update_property_long` explícitas | 9 |
| `zend_update_property` explícita adicional | 1 para payload |
| Chamadas efetivas a `zend_update_property`, incluindo wrappers long | 10 |
| Chamadas de handler `write_property` | 10 |
| Strings temporárias de nomes de propriedades | 10 alocações e liberações |
| Arrays PHP de resultado | 0 |
| Objetos PHP criados pela função C | 0 |

O ponto dos nomes está comprovado no Zend local: `Zend/zend_API.c:5043` faz `zend_update_property_long` chamar `zend_update_property`; em `:4995`, esta última usa `zend_string_init(name, name_length, 0)`, chama `write_property(..., NULL)` e libera a string. Não passa cache de propriedade. Os dez nomes somam 73 bytes de conteúdo copiado, além dos terminadores, headers de `zend_string` e arredondamento do alocador.

Portanto, dizer “duas alocações por pacote” seria incompleto: há **12 solicitações de alocação de strings no caminho C descrito**, sendo dez transitórias para nomes e duas para dados. A fábrica acrescenta a criação de um objeto `rtpc`, com seus slots de propriedades. Isso não equivale a 13 chamadas a `malloc`: Zend MM usa arenas, caches e classes de tamanho. O número exato de operações do alocador subjacente, bytes reservados e ciclos não foi medido.

As nove chamadas de wrapper mais as dez chamadas internas não representam 19 escritas: são dez escritas, com nove wrappers. Outras operações Zend incluem validação de argumentos, hash lookup do provider, refcounts do source, bloqueio/desbloqueio de fibras, cópia de zvals/refcounts para as propriedades e destruição dos temporários. Não se deve confundir esse inventário com a contagem de todas as funções/instruções produzidas pelo compilador.

`binary_slice_view()` (`binary_buffer.c:187`) percorre até dois spans. Para source circular, um intervalo pode requerer duas cópias; a implementação permite até duas por range. No caminho Swoole sempre há um span. Payload vazio usa a string vazia compartilhada, sem alocação própria de dados.

### 4.3 Casos excepcionais e materialização

| Caminho | Strings de dados | Escritas de propriedades / nomes temporários |
|---|---|---:|
| Válido, payload vazio | 1 cabeçalho | 10 |
| CSRC/extensão truncados, compatibilidade | 1 raw integral | 11: nove inteiros + payload + raw |
| Menos de 12 bytes, compatibilidade | 0 strings de dados não vazias | 2: payload e raw |
| Erro estrito de estrutura/versão/padding | Nenhuma cópia de dados antes da rejeição | 0; alocações da exceção à parte |
| Tamanho negativo/produtor inválido | Nenhuma cópia RTP | 0; diagnóstico/exceção à parte |
| Erro em setter | Strings já preparadas, liberadas no cleanup normal | Parcial, até a primeira exceção |
| Primeira leitura tardia de raw válido | Normalmente nova string de N bytes, concatenando H+L | Trabalho adicional em PHP/Zend |

A contagem de raw tardio é o caminho habitual com duas strings não vazias. Otimizações internas da concatenação para operandos vazios podem alterar alocações. Alterar o payload enquanto o snapshot ainda o compartilha pode causar copy-on-write de L bytes. Clonar compartilha strings por refcount até mutação; não realiza obrigatoriamente uma cópia dos bytes naquele instante.

### 4.4 Recepção e comparação correta de cópias

`recvfromInto()` (`ext/swoole/ext-src/swoole_socket_coro.cc:1826`) recebe diretamente em storage reutilizável. A MediaChannel aloca capacidade de 65536 bytes uma vez por início do receive loop. O produtor possui byte extra para detectar datagrama maior que a capacidade, retorna erro `EMSGSIZE` e não publica um pacote truncado como válido.

A syscall ainda copia dados do kernel para user space. O caminho não é zero-copy de rede. Depois, o parser copia H+L para strings PHP. Antes dele a MediaChannel ainda copia até quatro bytes para classificar RTCP.

**Comparação relevante:** o `recvfrom()` tradicional local (`swoole_socket_coro.cc`, imediatamente antes de `:1826`) já recebe diretamente em uma `zend_string_alloc(SW_BUFFER_SIZE_BIG)`, reduzindo apenas seu comprimento lógico depois. Não há necessariamente uma cópia user-space adicional de N bytes para converter um buffer em string. O construtor tradicional compartilha a string recebida em raw e copia o payload com `substr()`.

Assim, o experimento evita a alocação de uma grande string de recepção por chamada e evita `unpack()`/arrays de cabeçalho; não prova redução universal de bytes copiados contra esse `recvfrom()` específico. Em cópias user-space de dados, o tradicional tipicamente copia L no `substr`, enquanto o nativo copia H+L e mais quatro bytes no pré-filtro. Se raw for lido, o nativo ainda concatena N. O benefício potencial é uma combinação de tamanho/lifetime das alocações, menos trabalho PHP e ausência de arrays de parsing, com outros custos introduzidos.

### 4.5 Segurança e limites das garantias

Não há ponteiro de source armazenado em `rtpc`. Snapshot e payload são strings próprias; reutilização do Swoole Buffer não os sobrescreve. O registro global de providers é escrito somente no startup/shutdown e lido nos requests. O contexto de parsing é local à chamada; `EG(exception)` e `EG(fake_scope)` seguem o contexto Zend ZTS. Os nomes estáticos das propriedades são somente leitura neste código.

Isso não torna objetos Zend ou buffers compartilháveis entre threads arbitrárias. O contrato pressupõe ownership de request/thread e producer ABI correto. ZTS foi confirmado no binário; não houve teste de stress concorrente específico deste parser. Tampouco houve ASan, fuzzing ou Valgrind específico do novo parser nos testes aqui registrados. Os testes funcionais não constituem prova formal de ausência de falhas de memória.

## 5. Integração com rtpc e MediaChannel

### 5.1 A fábrica e a compatibilidade

Em `rtpc.php:79`:

```php
$packet = new self(null);
$packet->originalHeader = \psampler_rtp_parse($source, $packetLength, $packet, false);
if ($packet->originalHeader === null) {
    return $packet;
}
$packet->originalPayload = $packet->payloadRaw;
unset($packet->rawPacket);
return $packet;
```

O construtor com `null` retorna imediatamente, mas a criação/inicialização do objeto ainda acontece. A fábrica mantém atribuições, comparação de null e `unset` em PHP. Já não chama `parseFixedHeader`, `unpack()` ou múltiplos slices para interpretar CSRC/extensão.

`originalPayload` recebe a mesma string por refcount. `__get` por referência (`:94`) concatena o header original com o payload original quando raw é lido pela primeira vez e depois limpa os snapshots. O snapshot não acompanha alterações posteriores de `payloadRaw` ou dos campos fixos. Uma escrita explícita em raw antes da leitura prevalece; ela pode deixar snapshots retidos até serialização ou destruição. `isset` não concatena. Referências à propriedade continuam sujeitas ao tipo string.

`__serialize()` (`:119`) materializa raw se necessário, faz cast do objeto para array e retira as duas propriedades privadas de snapshot. O custo de serialização inclui esse array e a serialização normal; não faz parte do caminho comum de recepção. Não foi adicionado `__unserialize` nem `__clone`; usa-se o comportamento normal de restauração/clonagem. Os testes cobrem serialização, clone, escrita direta, referências e reconstrução em casos concretos, não todas as possibilidades de Reflection/hooks.

`getCodec()` (`:155`) continua retornando `payloadType & 0x7F`. `build()` (`:196`) continua usando `pack('CCnNN', ...)` e concatenação em PHP. Sua implementação preexistente reconstrói somente 12 bytes fixos e payload: **não reemite automaticamente a lista CSRC nem os dados de extensão preservados em raw**. Também mantém a particularidade de retornar `false` para payload vazio apesar da assinatura `: string`, sujeita às regras de coerção do PHP. Preservar `build()` não significa corrigir essas limitações legadas; o teste de reconstrução cobre o caso simples com payload não vazio.

### 5.2 Fluxo completo

```text
SocketMutable / Swoole Coroutine Socket
  -> recvfromInto(buffer reutilizado, peer, 0.2)
  -> tamanho/erros/timeout/keepalive "0"
  -> endpoint address:port e filtros
  -> psampler_binary_slice(..., 0, min(4, tamanho))
  -> isRtcpPacket() em PHP
  -> rtpc::fromNativeBuffer()
       -> new rtpc(null)
       -> psampler_rtp_parse(..., false)
       -> snapshot e raw tardio
  -> getCodec / mapeamento por membro / SSRC interno / métricas opcionais
  -> criação inicial de rtpChannel e associação de membro
  -> telephone-event: forwardDtmfToMembers + processDtmf + continue
  -> áudio: onReceive inline
  -> processamento normal: relay direto ou decode/conversão/fila de PCM
  -> acoustic tap opcional / diagnóstico
```

Referências principais: `MediaChannel.php:644` (start), `:710` (recepção), `:802` (peer), `:806` (RTCP), `:817` (fábrica), `:829` (SSRC interno), `:901` (canal), `:933` (DTMF), `:945` (callback), `:957` (receive-only), `:980` (relay), `:1005` (decode).

O identificador principal de membro é `address:port`. `generateDeterministicSsrc()` (`:467`) possui cache por endpoint; não recalcula SHA-1 a cada pacote após o primeiro. O SSRC original continua no objeto RTP, inclusive para DTMF e estatísticas. Não confundir a chave interna de canal com o SSRC lido do datagrama.

### 5.3 Seleção e fallbacks

A flag `nativeRtpReceive` já tinha default `true`. `setNativeRtpReceive(false)` força `recvfrom()` e `new rtpc($packet)`. Se a seleção automática não encontra Buffer, método de recepção, função slice ou bridge suportada, mantém `nativeBuffer=null` e usa o caminho tradicional.

`requireNativeRtpParser()` (`MediaChannel.php:70`) ativa a recepção nativa e a exigência. `start()` valida sincronamente nova API, slice, classe, método e suporte da bridge antes de criar a coroutine. A sondagem usa um buffer temporário de 16 bytes; o loop cria outro de 65536. São custos de startup, não por pacote. `mc-native.php:20` também verifica a nova função antes do socket.

**Limitação identificada:** o bloco de seleção automática em `:661` ainda não testa `function_exists('psampler_rtp_parse')`. Um consumidor que não usa `requireNativeRtpParser()`, mas roda em um binário antigo com a bridge/slice disponíveis, pode selecionar o buffer e falhar na fábrica por função ausente; não há fallback de parser dentro de `fromNativeBuffer()`. O receptor experimental inspecionado evita essa ambiguidade mediante preflight. Não há `try/catch` que troque silenciosamente o parser após erro de parsing.

### 5.4 Custos que continuam em PHP

Permanecem a orquestração da recepção, controle de timeout, montagem da chave de endpoint, classificação RTCP com `ord()`, criação do objeto, snapshots, consultas a arrays, resolução de codec, cache de canais, callbacks, contadores, métricas opcionais, decisões de relay, filas, timers e lógica de DTMF. As funções de codec podem executar em C, mas sua seleção e encadeamento continuam em PHP.

Há ao menos três entradas PHP→nativo relevantes no trecho de dados do caminho comum: `recvfromInto`, `psampler_binary_slice` do pré-filtro e `psampler_rtp_parse`. Isso não é o total de chamadas nativas da iteração: existem também relógios, métodos de socket, `ord`, funções de codec etc., conforme o ramo e a otimização do VM.

O `$peer` é inicializado em PHP e substituído por um novo array do Swoole. Um literal PHP pode ser compartilhado por copy-on-write; não se deve automaticamente contar uma nova HashTable só pela atribuição literal. A nova HashTable de peer, string de endereço e entradas criadas em `recvfromInto:1897` são explícitas. `socket_coro_sync_properties:779` também escreve `errCode` e `errMsg` via Zend em cada recepção, trazendo custos próprios de nomes/strings. O parser não elimina esses arrays ou essas escritas.

### 5.5 Codecs, DTMF e pacotes inválidos

O parser não interpreta PCMA/PCMU/Opus/G.729/GSM/L16: entrega bytes e PT. O processamento de codec permanece nas rotas existentes. A transcodificação real validada pelo teste foi PCMA→PCMU; compatibilidade com os demais codecs é sustentada pela preservação da interface e do fluxo, não por uma bateria end-to-end nova de todos eles.

`forwardDtmfToMembers()` (`:514`) interpreta os quatro bytes de telephone-event com `ord`; `processDtmf()` (`:2372`) cria uma coroutine e usa `unpack('Cevent/Cflags/nduration', ...)`, arrays de cache, relógios e debounce. Portanto ainda há arrays temporários e parsing PHP em eventos DTMF; o parser nativo só interpreta o envelope RTP. O teste confirma evento e encaminhamento, mas não garante todas as condições de deduplicação, reordenação e wrap de sequência. O ramo de retransmissão em `processDtmf` possui disparo próprio de callback e merece avaliação separada se duplicações aparecerem.

CSRC/extensões são aceitos para cálculo de offset e snapshot, sem interpretação semântica dos dados. Pacotes malformados continuam com o comportamento parcial legado. A checagem de versão/tamanho da MediaChannel em `:821` depende de `audioMetricsEnabled`, cujo default é `false` (`:255`): este caminho de compatibilidade não equivale a uma barreira estrita contra RTP inválido.

## 6. Evidências funcionais e histórico de comandos

Nenhum teste desta seção foi executado novamente para escrever o relatório.

| Comando executado na implementação | Resultado registrado e abrangência |
|---|---|
| `buildroot/bin/php libspech/test_rtpc_native.php` | PASS: 773 casos equivalentes ao construtor; snapshots, referências, clone, serialização e build; mais 31 divisões em ByteBuffer circular |
| `buildroot/bin/php validation/rtp-native/functional.php` | PASS: 517 casos com Swoole Buffer via UDP, reutilização, binários, CSRC/extensão e erros estritos de limites/versão/padding |
| `buildroot/bin/php validation/rtp-native/media.php` | PASS: sockets reais, associação de peer, caminho nativo demonstrado pelo raw não materializado, três callbacks de áudio, PCMA→PCMU, evento DTMF e forwarding |
| `(cd libspech && ../buildroot/bin/php test_media_channel_member_ptime.php)` | FAIL no cleanup, linha 485: socket simulado com construtor vazio; `MediaChannel::close()` chama `cancel()` e Swoole lança “must call constructor first” |
| `buildroot/bin/php -l mc-native.php` | Sintaxe válida; houve também lint dos dois testes novos |

Comando PHPT executado na raiz:

```bash
buildroot/bin/php source/php-src/run-tests.php -q -p "$PWD/buildroot/bin/php" \
  source/php-src/ext/psampler/tests/rtp_parse.phpt \
  source/php-src/ext/psampler/tests/binary_slice.phpt \
  source/php-src/ext/psampler/tests/binary_slice_signature.phpt \
  source/php-src/ext/psampler/tests/binary_slice_swoole.phpt
```

Resultado: quatro aprovados, zero falhas/skips; registro em `validation/rtp-native/phpt.txt`. O runner informa que Valgrind não foi usado. Esses testes incluem preservação de ordem em ring, assinatura, reutilização/timeout/cancelamento Swoole e exceção em setter depois da liberação do borrow.

O smoke test iniciou `buildroot/bin/php mc-native.php` por `subprocess.Popen` em um script Python inline, enviou três pacotes PCMA e quatro pacotes de um evento DTMF pela origem `127.0.0.1:50000`, verificou a saída e terminou o processo. Não existe um arquivo dedicado com esse launcher para invocar novamente. `receiver-smoke.txt` registra PT 8, SSRC 456, evento 5 e `total=3`. Esse tráfego finito **não foi** o emissor de 11 pacotes/evento nem a observação contínua de 50 PPS.

A falha do teste antigo de ptime foi localizada no fixture e no trecho de close não alterado; não foi corrigida fora de escopo. Não há registro de uma execução baseline anterior desse teste demonstrando o mesmo resultado, portanto a atribuição a uma limitação preexistente é sustentada pela inspeção, não por comparação A/B daquele teste.

### 6.1 Recepção contínua e significado de seqGaps

O usuário informou recepção contínua próxima de 50 PPS, processamento PCMA e identificação de DTMF. Isso é evidência operacional adicional, sem duração/captura bruta anexada a este relatório. Não demonstra capacidade máxima, custo por pacote nem ganho relativo.

`lsend.php:28` define oito frames de progressão e três retransmissões finais: **11 pacotes RFC 4733 por evento**. Eles compartilham a sequência RTP com áudio. A MediaChannel trata telephone-event antes do callback de áudio e executa `continue` (`:933`). O callback de `mc-native.php:78` incrementa total/bytes e atualiza `prevSeq` somente para áudio.

Exemplo: último áudio com sequência S; DTMF usa S+1…S+11; próximo áudio tem S+12. O callback calcula `delta=12` e soma `delta−1=11` a `seqGaps`, mesmo com todos os datagramas recebidos. **Não classificar esse aumento automaticamente como perda de rede.** O contador registra lacunas na subsequência observada pelo callback. Também não representa sozinho duplicação, reordenação ou estatística RTCP de perda.

A taxa impressa por RX é de callbacks de áudio e os KiB/s são de `payloadRaw` desses callbacks, não de todos os bytes RTP recebidos. Nos intervalos com DTMF, PPS de áudio pode cair enquanto o total de datagramas mantém o ritmo de 20 ms. As métricas internas opcionais da MediaChannel contam em outra posição do fluxo; não são o mesmo contador.

## 7. Análise estática para interpretar perf

### 7.1 Custos candidatos

| Componente | Trabalho verificável | Hipótese a confrontar com perf |
|---|---|---|
| Swoole/UDP | Syscall, retries por EINTR, espera de readiness, timers, guards, peer e sync de propriedades | Em tráfego esparso, scheduling e recepção podem pesar mais que ler 12 bytes |
| Dispatcher/bridge | Hash lookup, dois pins, validações, token, bloqueio de fibra e callbacks | Pode ser relevante para payloads curtos; ocorre também no slice RTCP |
| Parser | Máscaras, leitura big-endian, limites, duas cópias e dez escritas Zend | Hidratação/nomes podem custar mais que a aritmética RTP |
| Objeto rtpc | Alocação, defaults, construtor, propriedades tipadas e destruição | Reduzir parsing não elimina o custo de um objeto por datagrama |
| Strings | H e L, prefixo RTCP, nomes; raw eventual e copy-on-write | Zend MM pode concentrar parte das amostras do ganho ou regressão |
| MediaChannel | Arrays, chaves, callbacks, métricas opcionais, relay, codec e filas | Com mais membros/transcoding, parsing tende a ser parcela menor |
| DTMF | Coroutine, unpack, caches, forwarding e impressão de callback | Rajadas de eventos mudam o perfil e devem ser separadas do áudio |
| Receptor de laboratório | Relógio, contadores e printf periódico | Saída de terminal e janela curta podem distorcer comparações |

Fonte da espera UDP: `ext/swoole/src/coroutine/socket.cc:1327`, especialmente syscall em `:1342` e `wait_event` em `:1345`. Não existe necessariamente uma suspensão/retomada por pacote: com dados já prontos, a syscall pode retornar imediatamente.

### 7.2 O que foi removido e o que apenas mudou de lugar

Foi removida a interpretação do cabeçalho RTP em opcodes PHP no caminho nativo: array de `unpack`, extrações/máscaras/offsets e, na fábrica anterior baseada em slices, múltiplas entradas C e empréstimos do buffer para cabeçalho/extensão/payload. O parser atual interpreta tudo sob um único acquire, embora a MediaChannel ainda faça outro acquire para RTCP.

A escrita dos mesmos nove campos não desapareceu: agora usa API Zend genérica, com nomes transitórios e handlers sem cache fornecido. A posse do payload continua exigindo uma string PHP para manter compatibilidade e sobreviver à reutilização do buffer. A string do cabeçalho é retida mesmo se raw nunca for lido. Não há um segundo array de resultado para converter em `rtpc`, mas tampouco há um objeto nativo que dispense as escritas nas propriedades PHP.

Conclusão comprovada: há menos trabalho de interpretação RTP em PHP. Conclusão ainda não demonstrada: o custo total do pacote diminuiu. A soma de alocações pequenas, escritas genéricas, runtime ZTS e custos da MediaChannel pode compensar parte ou todo o benefício.

### 7.3 Comparação dos receptores atuais

| Aspecto | `mc.php` original | `mc-native.php` experimental |
|---|---|---|
| `receiveOnlyMode` | `true` (`:54`) | `false` (`:51`) |
| Recepção escolhida de fato | `setNativeRtpReceive(false)` (`:56`) | `requireNativeRtpParser()` (`:53`) |
| `--native` | Lido e usado no texto de modo, mas não muda a chamada que força `false` | Não determina a seleção; modo nativo é obrigatório |
| Parser efetivo | Construtor de string | Fábrica + função C, modo compatível |
| Disponibilidade da nova API | Não exigida | Verificada antes de iniciar e em `start()` |
| Timeout de recepção no fluxo | `recvfrom(..., -1)` | `recvfromInto(..., 0.2)` |
| Probes | Configurados como true, mas ramo depende de receive-only | Configurados true e ramo normal habilitado |
| Rede/codecs/peer/callbacks | Mesmo laboratório PCMA 8/telephone-event 101 | Preservados |

**A comparação direta desses dois scripts não isola a troca de parser.** Além do transporte, há diferenças de processamento e idle/timeout. O texto exibido por `mc.php --native` não é prova de caminho nativo, pois o código força `false`. Para atribuição causal, um benchmark futuro precisa manter processamento, peers, tráfego, callbacks, métricas, aquecimento e duração equivalentes; isso exige preparação separada autorizada, não realizada aqui, e não implica alterar o original nesta tarefa.

`mc-native.php` mantém `options['transcodingEnabled']=false` como no laboratório, mas o trecho de relay inspecionado decide por codec/frequência/canais/ptime e não consulta essa chave naquele loop. O receptor tem um peer. Com apenas o emissor como membro, o loop exclui o próprio source e normalmente não precisa decodificar/transcodificar para outro destino. Portanto `receiveOnlyMode(false)` preserva a possibilidade de processamento completo, mas **não prova execução de decodificação PCM a cada pacote nesse cenário**. O teste `media.php` usa dois membros com codecs distintos e verificou a conversão real.

### 7.4 Como ler resultados futuros

Não foram abertos ou interpretados dados de perf nesta investigação. Para uma análise posterior:

- Identificar o hash do executável e as configurações efetivas, não apenas o nome do script ou o rótulo da saída.
- Separar amostras próprias de `zif_psampler_rtp_parse` do custo inclusivo em seus descendentes: `zend_update_property*`, handlers, allocator, memcpy e dispatcher.
- Procurar também `Socket::recvfrom`, reactor/scheduler, `zend_std_write_property`, `zend_string*`/`_emalloc*`, execução do VM, `php_unpack`, codec e callbacks. Os nomes podem mudar por inlining e símbolos disponíveis.
- Não somar percentuais inclusivos como se fossem custos independentes. Uma função pode subir em participação porque outra ficou mais barata, sem ter aumentado em tempo absoluto.
- Comparar trabalho útil e CPU por conjunto equivalente de datagramas, distinguindo áudio, DTMF, RTCP, timeouts e callbacks. Não normalizar todos os custos pelo contador de áudio como se contasse DTMF.
- Distinguir tempo de parede aguardando pacotes de CPU amostrada. Aproximadamente 50 PPS é o ritmo do emissor de 20 ms, não um teste de saturação.
- O build tem `-Os` e `-g` no comando registrado, além de `--no-strip`. Símbolos preservados não garantem que todos os helpers tenham frames separados nem garantem unwind perfeito. Não foi inferida presença de frame pointers.

## 8. Oportunidades de otimização — sem implementação

| Prioridade | Proposta | Impacto potencial | Complexidade / risco |
|---|---|---|---|
| 1 | Reutilizar `zend_string` de nomes e usar `zend_update_property_ex` preservando handlers | Elimina dez alocações/liberações de nomes por parsing comum; benefício local diretamente ligado ao código | Baixa a média; gerenciar lifetime MINIT/MSHUTDOWN, strings persistentes e ZTS corretamente; medir antes de afirmar ganho |
| 2 | Unificar pré-classificação RTCP e parsing sob uma aquisição | Pode retirar slice de quatro bytes, uma string, uma travessia PHP/C e um acquire/release por pacote | Média; conservar exatamente regras RTCP, keepalive, curtos e fallback; não transformar RTCP em erro RTP |
| 3 | Cachear informações de propriedades por classe validada, ou fast path específico com fallback genérico | Reduz lookups/escritas genéricas | Média a alta; referências tipadas, hooks PHP 8.5, herança, visibilidade e escopo não podem ser contornados silenciosamente |
| 4 | Rever alocações de peer/metadata e sync de propriedades no receive | Pode reduzir custo por datagrama fora do parser | Alta; envolve Swoole, ABI e semântica de referências/erros; só após demonstrar relevância no perfil |
| 5 | Revisar operações repetidas da MediaChannel, como consulta de codec e array_keys dos membros | Impacto dependente de membros, métricas e relay | Média; callbacks podem alterar estado, invalidando caches; exige preservar funcionalidade |
| 6 | Postergar cópia do header mantendo uma representação própria compacta | Pode evitar uma string pequena para cabeçalho fixo | Média/alta; raw deve reproduzir os bytes originais após mudanças em campos; CSRC/extensão requerem armazenamento próprio |
| 7 | Estrutura/objeto RTP nativo persistente com propriedades compatíveis | Pode reduzir hidratação e centralizar lazy materialization | Alta; altera ownership, handlers, clone, serialização, referências e interoperabilidade com rtpc; benefício precisa justificar uma mudança de arquitetura |
| 8 | Integrar recepção e parsing numa única chamada | Menos travessias e possibilidade de metadata conjunta | Alta; acopla parser ao I/O e scheduler Swoole, timeouts/cancelamento, ciclo de vida e testes |

A prioridade 1 preserva o desenho atual e tem alvo concreto: `zend_update_property_ex` existe em `Zend/zend_API.c:4983` e recebe um nome já existente. Isso não remove validação de tipo nem a escrita do handler; não é garantia de eliminar o gargalo total.

Evitar arrays temporários continua sendo desejável, mas **a função de parsing já não retorna array**. Substituí-la por um array associativo de campos seria provavelmente um recuo no desenho, sujeito a medição. Restam arrays reais na recepção, DTMF, métricas e serialização; cada um tem função distinta.

Preencher diretamente uma estrutura C temporária já acontece. Torná-la persistente não permite manter `payloadRaw` como string PHP independente sem alguma materialização/cópia. Uma string que simplesmente aponte para o Swoole Buffer reutilizado quebraria a segurança do objeto retido por callbacks. Um pool ou protocolo de empréstimo com retenção de storage exigiria novos contratos de ownership e compatibilidade; não é uma troca local segura.

## 9. Estado final verificável

### 9.1 Binário e compilação registrada

Caminho: `/home/lotus/CLionProjects/pcg729/buildroot/bin/php`.

| Item consultado nesta investigação | Valor |
|---|---|
| PHP | 8.5.12, ZTS=1 |
| psampler | 0.7.0, API experimental presente |
| Swoole | 6.3.0RC1 |
| bcg729 / opus | 1.0.3 / 1.1.1 |
| sockets / filter / standard | 8.5.12 |
| Formato | ELF 64-bit LSB x86-64, estático, com debug_info, não stripped |
| SHA-256 | `352186d2e57558321df65b5ae32a9c21778f17f9087913542a29933ebf2a7e79` |

A versão 0.7.0 sozinha não identifica este experimento; registrar também hash/API/fontes. A etapa anterior confirmou os símbolos `zif_psampler_rtp_parse` e `rtp_parse_view` com `nm`. Nesta etapa a presença foi reconfirmada via reflexão da função.

Comando de build **historicamente executado**, não repetido:

```bash
bin/spc build --build-cli "bcg729,psampler,opus,swoole,sockets,filter,standard" --enable-zts --no-strip
```

`validation/rtp-native/build.log` registra conclusão em 101,483 s e saída no caminho esperado. O comando de compilação registrado contém `-Os -g`; dependências adicionais como curl/OpenSSL/zlib vieram da resolução SPC. Não foi usado o `php-config` PHP 8.4 do sistema. Os oito hashes de quatro fontes nas duas cópias passaram na conferência atual contra `source.sha256`; isso e a nova API no binário sustentam que a build incluiu o código local. Não se pretende afirmar reprodutibilidade bit a bit apenas com essa conferência.

### 9.2 Git

Estado observado na raiz antes da criação deste relatório:

```text
 M .gitignore
 M comandos.txt
?? contexto-pcg729-20261113-195543.txt
?? lsend.php
?? lsim.php
?? mc-native.php
?? mc.php
?? perf
?? validation/rtp-native/
```

HEAD da raiz: `0b0be527` — `Add PHP stubs for Swoole 6.3.0RC1 classes and constants`.

As alterações em `.gitignore`, `comandos.txt` e os arquivos já existentes não devem ser atribuídos ao parser apenas por aparecerem no status: eram preexistentes no início da implementação. O arquivo chamado `perf` é um arquivo não rastreado já presente; seu nome não significa que tenha sido executado nesta tarefa.

`source/`, `downloads/`, `buildroot/` e `libspech/` são ignorados pelo repositório principal. Assim, `git diff` da raiz não mostra a implementação completa. A cópia local da libspech inspecionada não possui `.git` próprio na sua raiz.

Nos dois repositórios internos `source/php-src/ext/psampler` e `downloads/psampler`, HEAD é `538ced9` — `Expose frame_signal in AcousticStreamAnalyzer state for per-frame classification`, e o status observado é:

```text
 M binary_buffer.c
 M binary_buffer.h
 M psampler.c
 M psampler.stub.php
?? tests/rtp_parse.phpt
```

Este relatório acrescenta apenas `?? relatorio-rtp-nativo.md` ao status da raiz. Não houve staging ou commit.

### 9.3 Originais preservados

A conferência atual reproduziu exatamente os hashes registrados ao final da implementação:

```text
eefd7ed6949ca807d49155c199f360bef33779cb9d091993b260ccb5ddaf5aef  mc.php
f7e93cc6de6db1e8ea6b3c8131e2371eb96ab7fbc5558d968ccb2d6ec0d2ba51  lsend.php
```

### 9.4 Limitações e pendências

- Não há medição de ganho de desempenho atribuível apenas ao parser.
- Os scripts atuais diferem em receive-only e timeout, além de transporte/parser; exigem controle experimental para comparação causal.
- O caminho libspech usa modo compatível permissivo, incluindo padding e versões inválidas. O modo estrito da API não está ligado nesse fluxo.
- A seleção automática não obrigatória não verifica a nova API antes de escolher a bridge.
- Extensões/CSRC são preservados e delimitados, mas não decodificados semanticamente; `build()` mantém suas limitações legadas.
- Há dez nomes temporários de propriedade, duas strings RTP comuns e uma cópia adicional para RTCP; o parser não é allocation-free nem zero-copy.
- Testes funcionais passaram no cenário nativo, mas o teste adicional antigo de ptime falhou no fixture de socket. Não houve validação nova completa de todos os codecs, fuzzing ou concorrência ZTS.
- A observação de 50 PPS não mede saturação. `seqGaps += 11` por evento do emissor não é evidência automática de perda real.
- Nenhuma das otimizações sugeridas neste documento foi aplicada.

Para operar o receptor existente, sem alterar a implementação:

```bash
cd /home/lotus/CLionProjects/pcg729
./buildroot/bin/php mc-native.php
```

O emissor continua sendo o `lsend.php` preservado. O parser e o receptor estão prontos para investigação de desempenho, com as diferenças de configuração e os custos acima explicitados para análise independente.
