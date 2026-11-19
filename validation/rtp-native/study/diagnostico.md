# Diagnóstico da recepção RTP e prioridades

A arquitetura recomendada mantém buffer UDP reutilizável, empréstimo síncrono pela ABI pública e objeto PHP comum. A alteração selecionada reutiliza nomes internados nas escritas de propriedades. Não substitui rtpc por um objeto C. O ganho da fábrica foi medido; o ganho do sistema com muitas chamadas depende da avaliação final da MediaChannel.

Este documento complementa relatorio-rtp-nativo.md. Caminhos abaixo são relativos ao projeto. Fonte significa inspeção direta; medido remete aos experimentos; estimativa significa contagem estática ou extrapolação; hipótese não constitui ganho demonstrado.

## Caminho crítico

| Camada | Fonte inspecionado | Custos |
|---|---|---|
| Kernel/socket | source/php-src/ext/swoole/src/coroutine/socket.cc, Socket::recvfrom | Syscall, cópia kernel→usuário, EINTR, readiness quando necessário. Não há suspensão obrigatória se os dados estão disponíveis. |
| Recepção | ext/swoole/ext-src/swoole_socket_coro.cc, recvfrom/recvfromInto | Ambos sincronizam propriedades do socket e criam array de peer/string de endereço. Into mantém guards e referências; recvfrom aloca string grande e reduz seu comprimento lógico. |
| Buffer | ext/swoole/ext-src/swoole_buffer.cc, buffer_read_acquire/release | Validação de estado, pin, token, identidade TLS e bloqueio de troca de fibra durante borrow. |
| Dispatcher | ext/psampler/binary_buffer.c, psampler_binary_with_view | Lookup por classe, pin adicional, acquire, validação dos spans e release. Bridge resolvida no startup. |
| Classificação | libspech/plugins/Utils/sip/MediaChannel.php, isRtcpPacket/start | Slice de até quatro bytes, travessia, borrow e string. Versão 2 e segundo byte 200–207, mínimo quatro bytes. Não valida compound RTCP. |
| Parser | binary_buffer.c, rtp_parse_view | Contexto de pilha, leitura big endian segura para desalinhamento/ring, bounds, CSRC/extensão/padding e cópias próprias. |
| Hidratação | binary_buffer.c, psampler_rtp_parse | Nove inteiros e payload via handlers Zend. Antes: dez nomes temporários; depois: nomes permanentes internados. |
| Fábrica | libspech/plugins/Utils/sip/rtpc.php | new self(null), slots, chamada C, snapshots e unset(rawPacket). Construção/destruição por pacote permanece. |
| Canal | MediaChannel.php, start/relay/processDtmf | Relógios, endpoint, membros, codec, SSRC, callbacks, arrays, relay por destino, DSP, codecs, timers e probes. Não perfilado com perf. |

## Alocações, cópias e escala

Estimativa estática para pacote válido não vazio: H bytes de cabeçalho, L de payload, N=H+L. O parser anterior faz duas strings de dados e dez nomes transitórios; o novo faz as mesmas duas strings e usa onze nomes compartilhados, inicializados uma vez. Isso elimina solicitações ao Zend MM, não necessariamente chamadas a malloc.

O parser copia N bytes; ring pode dividir cada cópia em dois spans. O pré-filtro acrescenta até quatro bytes. originalPayload compartilha a string por refcount, sem duplicar L. A primeira leitura de raw normalmente concatena/aloca N. Mutação de payload pode disparar COW.

recvfrom tradicional já recebe dentro da zend_string. Não há cópia integral adicional obrigatória antes do construtor. O legado compartilha raw e copia L por substr. Portanto nativo pode copiar MAIS bytes e ainda ganhar ao evitar unpack, arrays e nomes transitórios.

Buffer reutilizável economiza churn de strings grandes, mas mantém storage por canal: 10.000 receive loops com 65536 bytes equivalem, aritmeticamente, a 625 MiB só desse storage. Não é memória residente medida, nem comparação completa com buffers internos de sockets. Reduzir capacidade para áudio comum pode rejeitar pacotes antes aceitos.

Retenção de objetos em callbacks exige strings próprias. Pool de objetos ou storage sobrescrito quebraria snapshots/referências. Com muitos destinos, relay cresce por membro; codecs/resampling/filas podem dominar. Muitos sockets ociosos introduzem timers/wakeups que os testes de socket pronto não medem.

## Zend, ownership e ZTS

Zend/zend_API.c implementa zend_update_property criando/liberando nome a cada chamada. zend_update_property_ex usa nome pronto, conserva EG(fake_scope) e chama o mesmo write_property. A alteração monta zval long de pilha e troca apenas essa API.

Zend/zend_object_handlers.c, zend_std_write_property, resolve propriedade, visibilidade, readonly, hooks e tipos, e usa zend_assign_to_variable_ex. Essa atribuição preserva referências PHP e suas fontes de tipo. Acesso direto a OBJ_PROP foi rejeitado porque ignoraria garantias.

Zend/zend_string.c, zend_string_init_interned_permanent, calcula hash e registra strings permanentes no startup. Flags internados evitam alterações comuns de refcount. O array estático contém ponteiros imutáveis durante requests, sem classes ou zvals de request. Zend libera sua tabela no shutdown; não há free manual desses nomes. A documentação oficial descreve [zend_string e interning](https://php.github.io/php-src/core/data-structures/zend_string.html); o fonte local 8.5.12 determinou a implementação.

Borrow termina antes dos handlers. Setter pode limpar buffer, reentrar no parser ou lançar exceção; os dados já são próprios. O tratamento preexistente de bailout fatal não foi modificado. Não houve prova completa de caminhos OOM.

Cache global de zend_property_info seria incorreto entre requests ZTS. Cache por request exige lifetime, invalidação, isolamento de reentrância e hooks. Mesmo com cache correto, os handlers continuam necessários. Nomes internados evitam essa complexidade.

## Alternativas e critérios de decisão

| Alternativa | Redução e custo introduzido | Risco/complexidade | Comprovação e decisão |
|---|---|---|---|
| Nomes internados + update_property_ex | Retira criação/liberação/hash repetido; onze interns no startup | Baixa; lifetime permanente obrigatório | A/B, tipos, hooks, referências e threads. Implementada. |
| Slice integral + construtor legado | Evita hidratação C; adiciona cópia integral e mantém unpack/arrays | Baixa; raw passa a ser eager | Medida: vence baseline em casos, perde para nativo otimizado sem raw. Protótipo apenas, não preserva estado lazy. |
| raw imediato | Evita acesso mágico futuro; paga concatenação sempre | Baixa, mas modifica lazy | Medido custo de leitura. Não aplicar eager. |
| Classificação RTCP + parsing | Retira slice, aquisição e travessia | Média; curtos, keepalive, contadores, ordem dos efeitos | Próximo candidato: API separada com status explícito e mesma regra RTCP. Medir criação evitada de objeto para RTCP. Não implementada. |
| Cache de propriedade | Retira lookup | Média/alta: request, classe dinâmica, hooks, reentrância | Cache compatível com handler e fallback; comparar contra nomes internados. Adiada. |
| Fast path contíguo | Retira seleção de span em cada byte | Média: ring, endian, desalinhamento | memcpy para inteiros e conversão, nunca cast desprotegido. Ganho ainda não demonstrado. |
| Objeto interno/header compacto | Pode retirar string de header e slots | Alta: classe, referências, clone, serialize, Reflection | Descritor opt-in para outro consumidor, sem substituir rtpc. Não integrado. |
| Array de campos | Simplifica interface, mas cria array e segunda atribuição | Desnecessária no desenho atual | Rejeitado por análise; não foi medido. |
| Recepção+parser em chamada única | Reduz fronteiras/metadata | Alta: scheduler, cancelamento, socket e erros | Somente se perf demonstrar relevância. Não modificar Swoole agora. |
| Cache de membros/codec/SSRC | Reduz buscas, strtoupper e array_keys | Média: callbacks podem alterar membros; array_keys dá snapshot | Exige geração/invalidação explícita e perfil. Não integrado. |
| DTMF nativo | Evita unpack de quatro bytes | Travessia adicional pode custar mais | Medir cadeia completa, incluindo coroutine/cache/deduplicação. Adiado. |

## Contratos e oportunidades restantes

CSRC/extensões continuam opacos e preservados no snapshot. A fábrica usa modo compatível: conserva padding e versões inválidas como o legado. build mantém limitações existentes, inclusive não reemitir automaticamente CSRC/extensões. Snapshot não acompanha mutações posteriores de payload/campos.

A seleção automática da MediaChannel ainda testa slice/bridge sem exigir explicitamente psampler_rtp_parse. requireNativeRtpParser garante a API no laboratório; a política automática não foi alterada.

O callback de áudio é inline, antes do relay. Isso evita uma coroutine por áudio, mas permite que um callback lento monopolize a coroutine receptora. DTMF segue outro caminho, com coroutine, unpack e caches; a subsequência de callbacks de áudio não mede perda RTP total.

O próximo passo deve confrontar custos próprios/inclusivos de hidratação, allocator, dispatcher, reactor, callbacks e codecs no perf do usuário. Os resultados não demonstram ótimo global.
