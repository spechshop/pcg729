# API C de leitura do Swoole\Buffer — v1

Implementação preparada e revisada apenas estaticamente. Não foi compilada,
executada ou medida. A versão do Swoole continua sendo 6.3.0RC1; esta é a
revisão 2 do patch `swoole_native_buffer_v630rc1`, com ABI de leitura v1.

O header público é `swoole_buffer_c_api.h`, na raiz do Swoole após aplicação
do patch, instalado como `ext/swoole/swoole_buffer_c_api.h`. Ele inclui somente
headers C/PHP e funciona com consumidores C e C++, com linkage `extern "C"`.
O layout privado de `SwooleBufferObject` continua em `ext-src/php_swoole_buffer.h`;
consumidores não devem incluí-lo nem calcular offsets de objetos.

## Funções e descritores

```c
uint32_t swoole_buffer_read_api_version(void);
const swoole_buffer_read_api_v1 *swoole_buffer_get_read_api(
    uint32_t api_version, size_t expected_struct_size);
zend_result swoole_buffer_with_read_view_v1(
    zval *source, size_t offset, size_t length,
    swoole_buffer_read_handler_v1 handler, void *context);
```

`SWOOLE_BUFFER_READ_API_VERSION` é 1. A função de versão informa essa constante;
o getter exige versão e `sizeof(swoole_buffer_read_api_v1)` exatos e retorna
NULL antes do MINIT do produtor e depois de seu MSHUTDOWN. A tabela publicada
permanece imutável durante a vida dos módulos dependentes, inclusive no
MSHUTDOWN deles. Ela contém, na ordem acordada com a psampler:

```c
uint32_t api_version;
size_t struct_size;
zend_class_entry *buffer_ce;
zend_result (*acquire)(zend_object *source, swoole_buffer_read_view_v1 *view);
void (*release)(zend_object *source, void *token);
```

A view contém `zend_object *source`, `const unsigned char *data`,
`size_t length`, `size_t capacity` e `void *token`, nessa ordem. `source` é
exatamente o objeto de origem; `buffer_ce` identifica a classe interna exata.
`length` inclui somente os bytes válidos. `capacity` é a capacidade pública,
sem o byte privado de detecção de datagramas grandes. Não há terminador;
NUL é conteúdo normal. Views vazias usam `data == NULL`.

A ABI depende da versão/arquitetura/opções de build de PHP/Zend do processo.
Versionar a API não permite misturar headers de PHP, ZTS/NTS ou arquiteturas
incompatíveis. Todos os produtores e consumidores devem compartilhar esse ABI.

## Leitura síncrona e slices

Prefira `swoole_buffer_with_read_view_v1()`. Ela fixa a origem com uma referência
Zend, adquire o guard, valida a região e chama uma vez:

```c
zend_result reader(const swoole_buffer_read_view_v1 *view, void *context);
```

O callback recebe somente a região selecionada em `data/length`; a capacidade
e a identidade continuam sendo as do buffer inteiro. Offset no fim e tamanho
zero são válidos. `length == SIZE_MAX` seleciona todos os bytes restantes.
A validação usa `offset <= valid_length` e `length <= valid_length - offset`
antes de calcular endereços. Não há aritmética sobre NULL em regiões vazias.
Valores negativos vindos de PHP precisam ser rejeitados pelo consumidor antes
de convertê-los para `size_t`.

Nenhum payload é copiado e nenhuma `zend_string` é criada por essa API.
O callback pode interpretar cabeçalhos ou processar amostras diretamente.
Retorna SUCCESS ou FAILURE com exceção. FAILURE sem exceção vira Error;
SUCCESS com exceção também resulta em FAILURE. Exceções C++ que escapem do
callback são contidas como Error, embora sejam proibidas pelo contrato C.

Na saída normal, em exceções e no `zend_catch` de bailout, o wrapper devolve
o empréstimo antes de soltar a referência da origem. Bailout é propagado;
não há tentativa de continuar o request. A aquisição tem seu próprio caminho
de bailout para liberar o pin se a criação de uma exceção falhar. Depois que
o guard é ativado, acquire não contém operações falíveis.

## Protocolo de baixo nível para adaptadores

As assinaturas/layout da tabela e da view são os da proposta examinada em
`/home/lotus/projetos/psampler/docs/swoole-buffer-c-api-v1.h` e em
`docs/native-buffers.md` do mesmo repositório. A tabela não recebeu campos
extras; versão e leitura por callback são símbolos adicionais independentes.

Para usar acquire/release diretamente, o consumidor deve:

1. Manter uma referência Zend própria antes de acquire, até depois de release.
2. Em FAILURE, propagar a exceção; não chamar release.
3. Em SUCCESS, usar os bytes apenas síncrona e localmente, na mesma thread.
4. Chamar release exatamente uma vez com a origem e o token devolvidos,
   inclusive ao tratar exceção, metadata inválida ou bailout Zend.
5. Somente então soltar sua referência Zend. No bailout, propagá-lo novamente.

Acquire verifica origem não NULL, classe e handlers exatos antes de converter
para o layout privado. Verifica construção, storage não NULL, capacidade
positiva, `length <= capacity <= ZEND_LONG_MAX`, ausência de receive pendente
e ausência de outro empréstimo do mesmo objeto. Falhas deixam a view zerada,
sem guard ou referência adquirida. Exceções preexistentes são preservadas.

Além do pin obrigatório do consumidor, cada aquisição guarda uma referência
Zend adicional do produtor. Release devolve essa referência usando GC_DELREF;
o pin externo garante que essa operação não destrua o objeto nem execute PHP.
Release não aloca, lança, suspende ou chama PHP. A última referência normal é
liberada pelo consumidor, fora do guard. Nunca omita o pin externo: ele também
faz parte do contrato original da psampler.

Tokens são identificadores opacos monotônicos por thread, representados em
`void *` sem dereference. Release valida origem, handlers, thread e token atual.
NULL, token errado, duplicado ou de aquisição anterior não removem um guard
novo. O contador não dá wrap: esgotamento recusa novas aquisições. Isso não
autoriza passar um `zend_object *` destruído; a origem tem de continuar fixada.

Release encerra a autorização de acesso. Uma API C com ponteiro cru não pode
revogar fisicamente um endereço que código nativo desobediente tenha copiado.
Reter, escrever, liberar ou acessar esse endereço depois do callback/release
é violação do contrato; para uso posterior, mantenha uma referência Zend
normal e adquira uma view nova. Nenhum endereço é exportado para PHP.

## Exclusão de escritores e suspensão

Durante o empréstimo, `clear()` e `__construct()` recusam a operação antes de
alterar comprimento/capacidade/storage. `recvfromInto()` verifica o guard antes
de criar ReceiveGuard, zerar o comprimento ou chamar o socket. Isso protege
também o byte privado de overflow. Uma tentativa recusada não consome um
datagrama já enfileirado, nem altera o comprimento anterior ou o peer.
Novos escritores deverão usar a mesma verificação antes de qualquer mutação.

Acquire recusa receive pendente durante toda a vida do ReceiveGuard, inclusive
na atualização do peer. O caminho de recepção continua usando
`SocketImpl::recvfrom(data, capacity + 1)`, com timeout, cancelamento e exclusão
de leituras concorrentes nativos. Datagramas de tamanho exato continuam válidos;
datagramas maiores retornam false/EMSGSIZE e comprimento zero. Os outros
métodos de socket não foram alterados.

Leituras normais `getCapacity()`, `getLength()` e `toString()` continuam
permitidas durante o empréstimo; `toString()` mantém sua cópia/string PHP
explícita anterior. Nova aquisição no mesmo objeto é recusada. Outros buffers
podem ser lidos com guards aninhados e contadores balanceados.

Nenhum empréstimo pode atravessar suspensão ou mudança de contexto. Cada
acquire chama `zend_fiber_switch_block()` e release desfaz exatamente esse
bloqueio. Swoole não utiliza esse bloqueio Zend para trocar suas próprias
stacks: por isso foram adicionadas verificações em PHPCoroutine::create,
on_yield e on_resume, antes de salvar/restaurar o contexto Zend. Tentativa
explícita de troca durante um empréstimo provoca E_ERROR/bailout antes da
troca. Um simples Error retornado de um hook void não impediria o swap do
núcleo. O consumidor de baixo nível precisa respeitar o zend_catch descrito
acima; `psampler_binary_with_view()` já tem esse caminho no código examinado.

A preempção automática do Swoole é adiada enquanto há empréstimos na thread,
sem enfileirar um resume desse contexto. O scheduler volta a operar normalmente
após release. O callback pode reentrar PHP de forma síncrona, mas não pode
criar/resumir corrotinas, executar operações que cedam ao reactor, suspender
Fibers, despachar trabalho assíncrono ou compartilhar ponteiros entre threads.
Uma API nativa que ignore os mecanismos Zend/Swoole também viola o contrato.

## ZTS e lifecycle

Storage e estado do guard ficam no objeto do request. Identidade de thread,
contador de tokens e número de empréstimos ativos usam thread_local; nenhum
objeto ou ponteiro de payload fica na tabela global. Ela é inicializada no
MINIT, lida sem escrita por pacote e despublicada no MSHUTDOWN do Swoole,
depois dos módulos dependentes. Uma reinicialização de módulos publica a
classe da nova inicialização.

As operações de leitura só podem ocorrer durante um request. O chamador só
pode fornecer objetos válidos de seu próprio request/thread.
O marcador de thread em release não transforma o guard em um mutex e não
permite acessar um objeto Zend estrangeiro. Não há suporte a compartilhamento
concorrente de objetos ou transferências do empréstimo para workers ZTS.
Requests independentes podem ler seus próprios objetos em paralelo.

## Integração independente com psampler

Não há include, link ou registro psampler no código de produção do Swoole.
Uma ponte `psampler_swoole` depende de ambos os módulos via
`ZEND_MOD_REQUIRED("swoole")` e `ZEND_MOD_REQUIRED("psampler")`.
No MINIT, obtém a tabela uma vez, valida versão/tamanho/callbacks/classe e
registra um `psampler_binary_provider` para a classe exata. No MSHUTDOWN,
remove seu próprio provider antes de soltar referências à tabela/código.
Não há lookup de símbolos, registro ou alteração de globals por pacote.

Acquire da ponte mapeia a view Swoole para `psampler_binary_view`: mesma
origem, data/length no primeiro span, segundo span NULL/zero, mesma capacidade
e token. Release repassa origem e token. A psampler fixa a origem durante
`psampler_binary_with_view()` e libera em todos os caminhos de seu callback.
A referência adicional do produtor é compatível com esse pin. `appendBuffer()`
continua fazendo a cópia necessária para o storage próprio do destino; a
leitura/processamento direto por callback não copia o payload.

Há um adaptador demonstrativo somente na fixture de testes, compilável de
forma opcional contra o `psampler_binary.h` real. Ele não é uma extensão de
produção nem deve ser carregado junto com outro provider da mesma classe.
A ponte `psampler_swoole` de produção continua sendo trabalho no consumidor.
Os arquivos da psampler não foram modificados nesta tarefa.

## Símbolos, builds e carregamento

Os três símbolos públicos usam linkage C e visibilidade default em GCC/Clang,
mesmo em builds com visibilidade oculta. Os callbacks de aquisição/liberação
são acessados pela tabela; não precisam ser símbolos globais. Unix instala o
header explicitamente por PHP_INSTALL_HEADERS; Windows também. A implementação
fica no mesmo `ext-src/swoole_buffer.cc` que os builds m4/w32 já incluem uma vez.

Em builds estáticos, linke produtor/consumidor no mesmo PHP, respeitando as
dependências Zend no MINIT e o shutdown inverso. Para uma ponte dinâmica sobre
PHP com Swoole estático, o executável precisa exportar os símbolos na sua tabela
dinâmica, manter a unidade do produtor no link e permitir a resolução pelo
loader (por exemplo, política de exportação equivalente a --export-dynamic
em ELF). Visibilidade default sozinha não garante exportação do executável.

Em builds compartilhados, carregue swoole e psampler antes da ponte nos INIs;
a ordem relativa entre os dois é livre. A ponte pode fazer link direto ou
resolver o getter uma vez no MINIT, com tratamento de ausência. As dependências
Zend ordenam MINIT, mas não resolvem símbolos que o loader não consegue
localizar. Sem dl/unload durante requests; os módulos ficam carregados até
MSHUTDOWN. Não use lookup por pacote nem nome de classe para acessar layout.

No Windows, o produtor define SWOOLE_BUFFER_C_API_BUILD no arquivo que
implementa a API e exporta com __declspec(dllexport). Consumidores de DLL usam
o import library correspondente e __declspec(dllimport). Consumidores ligados
diretamente de forma estática podem definir SWOOLE_BUFFER_C_API_STATIC para
suprimir dllimport. Um binário com Swoole estático que sirva uma ponte dinâmica
precisa publicar os exports/import library adequados. A versão-alvo contém
config.w32, mas Windows não foi compilado nem validado em runtime.

## Patch reproduzível

SwooleBufferPatcher continua sendo chamado em patchBeforeBuildconf do builder.
O patch principal contém todos os arquivos C/C++, stubs e mudanças de build.
O patch de upgrade contém somente o delta da implementação completa da v1.1.3
para o mesmo resultado final. Não foi editado Swoole em buildroot/source.

O manifesto fixa SHA-256 de ambos os patches, todos os arquivos afetados e
os helpers/receive/core de corrotinas mantidos sem alteração. São aceitos três
estados completos: upstream suportado, patch anterior completo e patch atual
completo. Os dois primeiros recebem o patch apropriado; o atual retorna false.
Árvores mistas, modificadas ou versões diferentes falham com mensagem explícita.
Os patches usam hunks sem linhas de contexto: a pré-condição é a igualdade
do arquivo inteiro por SHA-256, e todos os resultados também são conferidos.
Os dois estados conhecidos de config.m4 (antes/depois do hook de include SPC)
continuam enumerados por hashes exatos. Todos os outputs são revalidados após
aplicação, porque SourcePatcher não impõe o exit code do processo patch.

## Validação e pendências

Foi feita inspeção de fontes extraídas do tarball local, comparação textual
com os contratos psampler, revisão dos diffs e conferência dos hashes/hunks.
Não foram executados testes, scripts PHP, compiladores ou benchmarks.

Os nove PHPTs e a fixture C em `tests/native_swoole_buffer/` estão escritos e
não executados. O README nesse diretório descreve cobertura e uso posterior.
Após compilação, validar assinaturas/macros Zend, instalação do header e exports
nos builds estático/dinâmico, ordem de carga/shutdown, bailout isolado, scheduler,
timeout/cancelamento, ZTS com requests independentes e sanitizers. Validar
Windows/32 bits e versões PHP adicionais nos ambientes correspondentes.
A ausência de strings no caminho novo foi revisada no código; não medida.
