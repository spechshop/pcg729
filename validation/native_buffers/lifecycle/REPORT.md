**Correção do lifecycle de `recvfromInto()` — 28/10/2026**

O defeito específico foi corrigido nos patches versionados: bailout libera `receiving` e devolve exatamente os dois pins nativos. O bailout continua fatal. UDP e ABI v1 passaram, incluindo o contrato de teste da psampler. Não há aprovação irrestrita do ambiente inteiro: persistem perdas de teardown da coroutine e de globals ZTS, reproduzidas nos controles anteriores à correção, e o defeito PCM descrito no relatório anterior permanece fora deste trabalho.

**1. Causa raiz confirmada**

`ReceiveGuard` adquiria uma referência do buffer e uma do socket, ativava `receiving` e só desfazia essas operações no destrutor C++. `ZEND_TRY_ASSIGN_VALUE(peername, &peer)` podia chamar o destrutor PHP do valor anterior. `zend_bailout()` faz um salto não local que ignora esse destrutor C++ e também `TimeoutSetter`.

O teste original foi executado primeiro com `buildroot/bin/php`, preservado. Sua saída foi `GUARD LEAK`, `destroyed=no` e status 255: [reprodução anterior](results/reproduce-before.txt). A versão anterior também reprova os dois novos PHPTs de bailout: [PHPTs anteriores](results/phpt-before.txt).

O GDB da versão anterior recompilada com símbolos observou `receiving=1`, refcount do buffer 5 antes da atribuição e **zero execuções do destrutor do guard** após o fatal: [evidência anterior](results/before-gdb.txt). O CLion não tinha sessão/configuração de execução disponível; foi usado GDB batch.

**2. Arquivos alterados**

Arquivos de produção, relativos à raiz do repositório:

- `src/globals/patch/swoole_native_buffer_v630rc1.patch`.
- `src/globals/patch/swoole_native_buffer_v630rc1_read_api_v1_upgrade.patch`.
- Novo `src/globals/patch/swoole_native_buffer_v630rc1_lifecycle_upgrade.patch`.
- `src/globals/patch/swoole_native_buffer_v630rc1.json`: revisão do patch 3, novos hashes e estado anterior completo da revisão 2. A ABI continua v1.
- `src/SPC/store/SwooleBufferPatcher.php`: seleciona a atualização correspondente ao estado completo de origem.

Testes: `tests/native_swoole_buffer/swoole_buffer_test.c` ganhou somente uma função de fixture para observar refcounts por referência, sem usar o layout privado; `read.phpt` ganhou 172 bytes; foram adicionados `recvfrom_into_{bailout,typed_bailout,exception,typed_exception,lifecycle}.phpt` e `recvfrom_into_peer.inc`. O `README.md` dessa fixture foi atualizado com os testes e a validação executada.

Scripts, controles, relatório e evidências novos estão em `validation/native_buffers/lifecycle/`. O relatório anterior recebeu uma nota apontando para este complemento. A árvore local `source/php-src/ext/swoole/ext-src/swoole_socket_coro.cc` foi atualizada para compilar os objetos de teste; seu conteúdo corresponde exatamente ao hash final do manifesto.

`src/SPC/builder/extension/swoole.php` foi examinado e permanece inalterado: já aplica o patch antes de buildconf/configure. Não foram modificados psampler, libspech, sipswoole ou suas árvores extraídas. Nenhum commit, push, release, serviço ou instalação foi executado.

**3. Alterações no cleanup e propagação do bailout**

Há um único `zend_try` envolvendo recepção, sincronização de propriedades, criação de metadata e atribuição. Não há `return` dentro dele: os retornos usam `RETVAL_*` e só deixam o método depois de `zend_end_try()`, restaurando `EG(bailout)`.

No `zend_catch`, a ordem é:

1. Destruir somente o array de peer ainda pertencente ao método. Esse array não publicado contém apenas endereço e porta e não pode executar PHP.
2. Restaurar explicitamente o timeout pelo destrutor de `TimeoutSetter`, enquanto o socket continua pinado.
3. Desativar `receiving`.
4. Liberar o pin do buffer.
5. Liberar o pin do socket.
6. Chamar `zend_bailout()` novamente para propagar o mesmo encerramento.

A ordem buffer antes do socket evita que um destrutor PHP de uma subclasse do socket interrompa a devolução do pin do buffer. Buffer é final, sem destrutor PHP nem propriedades dinâmicas. Após a liberação do buffer, o código não volta a acessá-lo. Os pins e o estado do objeto guard são estabelecidos antes de setjmp e permanecem inalterados durante a região protegida. No retorno normal ou com exceção PHP recuperável, os destrutores C++ continuam fazendo o cleanup uma vez. No catch, o cleanup explícito é seguido por bailout, que pula esses destrutores; não há uma segunda liberação.

Não se converte fatal em `false`, exceção ou retorno normal. Os testes isolados continuam terminando com 255; os quatro workers ZTS também preservam 255.

**4. Ownership de `$peername` e referências Zend**

A macro Zend não tem a mesma sequência em todas as referências. Sem tipo, destrói o valor anterior **antes** de transferir o novo array; com tipo, o helper Zend valida e publica o array **antes** de destruir o anterior.

A correção mantém a macro no caso sem tipo. No caso tipado, usa a mesma validação `zend_verify_ref_assignable_zval` e a mesma atribuição `zend_safe_assign_to_variable_noref` do Zend, marcando a transferência antes da chamada que pode executar o destrutor antigo. Falha de validação mantém o array sob ownership local e preserva o valor anterior.

`zend_array *volatile owned_peer` é a única informação de ownership mutável lida após longjmp. É estabelecida como NULL antes de setjmp, recebe o array depois de alocação bem sucedida e volta a NULL no ponto correto de transferência. Não se consulta um zval automático modificado e indeterminado após longjmp. Valores publicados ficam sob responsabilidade do Zend e nunca são destruídos novamente pelo catch.

As duas referências adicionais são adquiridas exatamente uma vez. O GDB no reproducer original mostrou refs do buffer **6→5**, refs do socket **3→2**, `receiving=0`, uma chamada de cleanup e uma chamada final de `buffer_free_object`: [evidência corrigida](results/after-gdb.txt). Os dois cenários novos, tipado e sem tipo, mostraram respectivamente **4→3** e **3→2**, com um cleanup e uma liberação final: [sem tipo](results/gdb-untyped.txt), [tipado](results/gdb-typed.txt).

Há uma distinção necessária no relatório anterior: `destroyed=no` durante o callback de shutdown **também ocorre depois da correção**. O Zend ainda mantém referências do argumento, de `$this` e, quando habilitado, do trace fatal até o teardown do executor. Um método escrito em PHP, sem recepção nem pins nativos, reproduz o mesmo saldo: +2 buffer e +1 socket com trace/argumentos habilitados; sem trace, +1 e +1. O [controle PHP](frame_control.php) está incluído nas saídas de [Memcheck](results/memcheck.json). Portanto esse `destroyed=no` isolado não prova um pin nativo pendente. A liberação final foi verificada diretamente no GDB. Nos caminhos sem fatal, as WeakReferences devem ficar nulas imediatamente após remover os donos, e os testes fazem essa verificação.

Não foram forçados descartes de frames PHP para antecipar a destruição no shutdown. Isso alteraria o comportamento do Zend/Swoole e excederia o cleanup deste método.

**5. Regressão funcional, UDP e exceções**

[Suíte sem provider de teste](results/phpt-after.txt): 13 aprovados, zero falhas, um skip esperado para o contrato opcional. [Suíte com a fixture de contrato preexistente](results/phpt-contract.txt): **14 aprovados, zero falhas/skips**. Essa fixture já existia; não foi implementada a ponte de produção `psampler_swoole`.

Os testes verificam explicitamente guard e referências, além do status do processo:

- Bailout no destrutor do peer, tipado e sem tipo; guard liberado, refcounts iguais aos controles PHP, timeout restaurado, destrutor antigo executado uma vez e propagação fatal.
- Exceção recuperável nos dois caminhos, buffer com length zero, reutilização e destruição imediata após remoção do último dono.
- Falha de atribuição a propriedade `int`: TypeError, peer antigo preservado, metadata descartada e pins equilibrados.
- UDP de 0, 160, 172, 1024 e 2048 bytes, integridade binária, endereço/porta, capacidade fixa, overflow de 2049/4096/65507 com `EMSGSIZE`, length zero e reutilização.
- Timeout, cancelamento pela coroutine e pelo socket, erro de socket fechado, rejeição de socket TCP e refcounts iguais aos anteriores.
- Output referenciando o próprio buffer: a operação mantém o objeto válido e a WeakReference fica nula depois do retorno.
- Recepção suspensa depois de remover as variáveis PHP do socket/buffer: ambos permanecem válidos, a recepção termina e ambos são destruídos.
- Scheduler cooperativo, concorrência e 10.000 reutilizações com payload binário no harness anterior: [regressão funcional](results/memcheck.json), caso `functional`.

`testb.php` passou os três pacotes e os cinco ticks do scheduler, com timeout de aproximadamente 0,200 s: [saída](results/testb.txt). `testb2.php cancel` reutilizou o buffer: [saída](results/testb2-cancel.txt). `testb2.php concorrencia` terminou com 255 e a proteção original “already been bound to another coroutine”, resultado esperado para esse script: [saída](results/testb2-concorrencia.txt).

Também foi compilado somente o objeto corrigido com `-O2 -g -Wextra -Wclobbered` e relink isolado, para verificar o estado atravessando longjmp sob otimização: [resultado](results/phpt-optimized.txt).

**6. ABI C e ZTS**

O header público, `swoole_buffer.cc`, assinaturas, layout da tabela acquire/release, callbacks e versão da ABI não foram alterados. Seus hashes são exatamente os da revisão 2. Permanecem os três símbolos públicos, o pin externo obrigatório, os tokens e guards de leitura, exclusão de mutações e proteção contra suspensão. Os testes exercitam aquisição/liberação, token inválido/antigo/duplicado, lifetime no callback, exceção/bailout, guards de Fiber/coroutine, objeto/classe inválidos e compatibilidade de tamanho/versão da tabela.

O guard de leitura continua sendo verificado antes da criação do guard de receive, sem consumir datagramas ou alterar peer/buffer quando há empréstimo ativo. `guards.phpt`, `pending_receive.phpt` e o contrato da psampler passaram. O contrato também passou no Memcheck com zero erros e perdas: caso `contract` em [resumo](results/memcheck.json).

Quatro requests ZTS fizeram **8.000 ciclos UDP/ABI**, verificando pins e destruição, e quatro requests fizeram bailout real com peers tipados/sem tipo, verificando guard, ABI disponível no shutdown e status 255: [ZTS normal](results/zts.txt), [ZTS fatal](results/zts-bailout.txt). Não se adicionou estado global de recepção ou lock; o bailout usa o contexto Zend da thread/request.

**7. Análise de memória**

Valgrind 3.22, runtime musl dinâmico no executável de diagnóstico, `USE_ZEND_ALLOC=0`, `USE_TRACKED_ALLOC=1`, `--soname-synonyms=somalloc=*` e detecção de perdas sem supressões. A instrumentação observou centenas de milhares de alocações; não é o falso zero do PHP estático apontado na validação anterior.

[Resumo de 31 processos](results/memcheck.json): **17 com zero erros**, nenhum sinal, nenhum Invalid read/write/free, double free, uso de memória não inicializada ou acesso depois de liberar. Os 14 restantes acusam perdas reais e não recebem aprovação irrestrita:

| Cenário | Resultado de perdas | Comparação/implicação |
| --- | --- | --- |
| Bailout de peer sem coroutine, tipado e sem tipo | Zero | Cleanup e descarte de metadata aprovados |
| Exceções, UDP, ABI, timeout, cancelamento e lifetime | Zero | Asserções e Memcheck aprovados |
| Coroutine com fatal | 168 B diretos + 2.097.152 B indiretos | Igual antes/depois e ao fatal de controle sem empréstimo |
| ZTS, quatro requests normais | 8.320 B diretos | Igual antes/depois e a quatro threads vazias; globals de extensões, incluindo pgsql |
| ZTS, quatro requests fatais | 8.592 B diretos | Igual ao controle PHP fatal antes/depois; 272 B adicionais nas stacks de conversão numérica `zend_dtoa` ao formatar o timeout no trace |

Os avisos de troca de stack do Valgrind e os avisos de fd -1 nos casos fatais foram preservados nos logs. Nenhuma perda nova foi atribuída ao método corrigido: há comparações anteriores e controles, além das asserções de refcount. Isso não demonstra ausência universal de falhas. Não foi executada uma nova compilação completa com ASan/UBSan nem injeção sistemática de falhas de malloc; o caminho de ownership em falta de memória foi revisado, e as verificações dinâmicas foram feitas com Memcheck.

**8. Desempenho e alocações**

Comparação dos executáveis before/after com mesma fixture, toolchain, demais objetos e flags. Ambos recompilaram somente `swoole_socket_coro.cc` com as opções normais do Makefile e `-g`; os demais objetos vieram do build existente. São executáveis temporários com runtime musl dinâmico, diferentes da linkagem estática do PHP instalado. A verificação otimizada é separada deste benchmark.

Sete pares alternando a ordem, 300.000 datagramas por amostra, 5.000 de aquecimento, lotes de 32, receptor na CPU 2 e emissor na CPU 4, tamanhos 172 e 2048. Total medido 8,4 milhões de datagramas, zero perdas/erros. A rodada final ocorreu depois de terminar o Memcheck; a rodada inicial com diagnóstico concorrente foi preservada e não fundamenta a tabela.

| Payload | CPU antes (µs/pacote) | CPU depois | Variação | Tempo decorrido antes→depois (µs/pacote) |
| --- | --- | --- | --- | --- |
| 172 | 1,74018 | 1,76728 | +1,56%, aproximadamente 27 ns/pacote | 1,78634→1,81534 |
| 2048 | 1,90605 | 1,90825 | +0,12%, aproximadamente 2 ns/pacote | 1,98300→1,96711 |

São medianas; intervalos de CPU se sobrepõem (172: 1,70616–1,79192 antes; 1,72355–1,78143 depois). Há um pequeno custo possível de CPU e não se afirma overhead zero. A comparação curta não mostrou degradação grande ou nova alocação por pacote: o mesmo harness funcional de 10.000 reutilizações observou **273.442 alocações em cada versão**. Não foram introduzidos zend_string do payload, locks globais, syscalls adicionais por recepção ou bloqueio do reactor. O byte extra de detecção de truncamento e o receive nativo original permanecem.

Dados, comandos e hashes dos binários: [benchmark](results/benchmark.json), [resumo](results/benchmark-summary.json), [alocações](results/memcheck.json), casos `functional` before/after. A variação curta não substitui medições prolongadas de produção; não foram repetidos os benchmarks de 70 milhões.

**9. Reprodutibilidade e integridade**

[25 verificações aprovadas](results/patches.json), usando a classe PHP real de patching, sem substituí-la por mock:

- Aplicação no upstream Swoole 6.3.0-rc1 e na variante com hook de include do SPC.
- Upgrade direto de árvores completas v1.1.3/revisão 1 e read API v1/revisão 2 para a revisão 3.
- Segunda aplicação devolve `UNCHANGED`, com hashes finais idênticos.
- Rejeição antes de qualquer escrita de conteúdo desconhecido, estados parcialmente modificados e mistura de hashes conhecidos que não forma um estado completo suportado.
- Rejeição de cada um dos três patches com hash alterado e de mudanças nos arquivos upstream protegidos.
- Todos os hashes finais e os hashes upstream não modificados conferidos.

O manifesto mantém Swoole 6.3.0-rc1 e read API v1. A revisão 3 é somente a revisão do patch. O PHP continua 8.5.10 ZTS; não foram alteradas versões de dependências. Não foram executados builds Windows/NTS ou outros PHPs nesta validação.

**10. Reproduzir e revisar**

Com o build local existente e o tarball upstream já disponíveis, estes comandos não substituem PHP nem recompilam o PHP completo:

```bash
python3 validation/native_buffers/lifecycle/check_patches.py
python3 validation/native_buffers/lifecycle/build.py
REPORT_EXIT_STATUS=1 \
TEST_PHP_EXECUTABLE="$PWD/validation/native_buffers/lifecycle/php-contract" \
validation/native_buffers/lifecycle/php-contract -n source/php-src/run-tests.php \
  -n -q tests/native_swoole_buffer
python3 validation/native_buffers/lifecycle/check_optimized.py
python3 validation/native_buffers/lifecycle/memcheck.py
python3 validation/native_buffers/lifecycle/memcheck_extra.py
# Executar depois do diagnóstico de memória, sem concorrência com ele:
python3 validation/native_buffers/lifecycle/benchmark.py
validation/native_buffers/lifecycle/php-after -n validation/native_buffers/lifecycle/zts.php
validation/native_buffers/lifecycle/php-after -n -d error_reporting=24575 \
  validation/native_buffers/lifecycle/zts_bailout.php
sha256sum -c validation/native_buffers/lifecycle/results/original-php-before.sha256
```

O build de fixtures usa somente o consumidor Swoole de testes; o executável de contrato reutiliza a fixture opcional já preparada. `check_patches.py` extrai o baseline dos patches dos commits anteriores, verifica hashes e prepara a cópia de fonte antiga. Os scripts de Memcheck registram resultados com perdas; esses casos não são convertidos em aprovação pelo exit code do script agregador. Inspecionar `errors`, perdas, stdout/assertions e status de cada processo no JSON.

O hash de `buildroot/bin/php` permaneceu `17319dac0290c0ed7f01b54bc9164f2520733da397426b45000e6d046b59f71d`: [registro](results/original-php-before.sha256). Os executáveis temporários e objetos são ignorados pelo Git. `git diff --check` passou.

**11. Pendências e conclusão de escopo**

Não restou reprodução de guard ativo ou de pin adicional nativo pendente pelo bailout no destrutor de `$peername` nos cenários testados. O cleanup de metadata, timeout, buffer e socket é explícito em bailout, e a ABI/UDP existentes continuam funcionais.

O reproducer original ainda imprime `destroyed=no` dentro do shutdown por referências normais do executor/trace. Os controles PHP reproduzem esse comportamento; GDB confirma uma liberação final do buffer no teardown. Assim, exigir que toda WeakReference já esteja nula naquele callback, independentemente das referências do executor, continua sendo um critério não atendido pelo comportamento normal do Zend. Não se apresenta essa linha como `destroyed=yes` nem se confunde liberação forçada no teardown com retorno normal de todos os frames.

Continuam pendentes as perdas da coroutine fatal, dos globals ZTS e da formatação de trace fatal em threads, além do defeito PCM na psampler registrado na validação anterior. Não foram corrigidos fora do escopo nem ocultados. A correção específica do Swoole passou; uma declaração de que **toda a infraestrutura está pronta** para a ponte ainda depende do tratamento/aceitação explícita dessas pendências anteriores. A ponte não foi implementada.
