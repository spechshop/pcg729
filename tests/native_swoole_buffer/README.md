# Fixture nativa do Swoole Buffer

Código e nove PHPTs preparados, **não compilados nem executados** nesta tarefa.
A fixture é separada do build de produção; não a adicione à lista de extensões
do pcg729 distribuído. Ela não devolve endereços ao PHP: os resultados são
comprimento, capacidade, soma dos bytes e estado do bloqueio de Fibers.

Depois de compilar PHP/Swoole com o novo patch e instalar os headers, você pode
usar phpize/php-config desse mesmo build para compilar esta fixture com
`--enable-swoole-buffer-test`. O include público esperado é
`ext/swoole/swoole_buffer_c_api.h`. Carregue Swoole antes da fixture; as
dependências Zend também estão declaradas no módulo. A função de leitura tem
origem por referência para o teste de remoção do último dono PHP.

Para a integração de contrato, instale os headers da psampler preparada e
habilite também `--enable-swoole-buffer-test-psampler`. Essa configuração depende
de psampler e registra um adaptador de teste no MINIT, removido no MSHUTDOWN.
Não carregue `psampler_swoole` junto com esse adaptador: providers duplicados
devem falhar. O PHPT de integração pula se a fixture não registrar o produtor.
A fixture usa diretamente psampler_binary.h, sem cópia local desse header.

O runner PHPT do PHP deverá usar um executável recém-compilado e as extensões
correspondentes; não substitua o PHP existente. Esta fixture tem config.m4;
para executá-la em Windows, preparar o build separado equivalente. O patch de
produção já atualiza config.w32 e a instalação do header público.

| Arquivo | Cobertura |
| --- | --- |
| read.phpt | API/versão/tamanho, classe/handlers inválidos, vazio, UDP com NUL, 160/1024/2048 bytes, slices, ajuste exato, overflow/EMSGSIZE e reutilização; requer sockets para SOCKET_EMSGSIZE |
| errors.phpt | Objeto errado, objeto criado nativamente sem construtor, negativos, offsets/comprimentos inválidos e extremos, callback falhando/lançando, guard liberado |
| guards.phpt | clear, reconstrução, read recursivo, recvfromInto com pacote já enfileirado, estado/peer intactos, outro buffer e bloqueio de Fiber |
| pending_receive.phpt | Leitura/clear durante receive pendente, cancelamento, timeout e reutilização |
| lifetime.phpt | Remoção do último dono PHP durante callback, WeakReference, exceção, referências balanceadas, tokens inválidos/antigos/duplicados e Fiber desbloqueada |
| bailout.phpt | Bailout real no callback; shutdown verifica liberação de guard/referências e desbloqueio de Fiber |
| coroutine_suspend.phpt | Tentativa de suspensão da corrotina, fatal antes da troca, cleanup no bailout |
| coroutine_create.phpt | Entrada em nova corrotina recusada durante empréstimo na thread principal |
| psampler_contract.phpt | Produtor compatível com psampler_binary.h, dois spans com segundo vazio, callback real via psampler_binary_with_view, exclusão, lifetime e appendBuffer com slices |

Os três testes de fatal/bailout precisam de processos isolados, como os do
runner PHPT. Não capture bailout para continuar o request. A função C de
criação sem construtor existe somente aqui porque Reflection pode impedir a
criação de classes internas finais sem construtor. Os tokens e contadores de
referências só são inspecionados em C, sem acessar o layout privado do Swoole.

A execução futura deve incluir os testes/regressões anteriores da psampler e
da recepção UDP, além de builds ZTS com objetos independentes por request,
ASan/UBSan, carregamento estático/compartilhado e shutdown. Os casos não provam
a ausência de alocações por instrumentação; esse caminho foi revisado apenas
estaticamente. Consulte [o contrato público](../../docs/swoole-buffer-c-api.md).
