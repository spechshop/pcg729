# Lifecycle nativo de FDs do Swoole no pcg729

Integração restrita ao Swoole `v6.3.0-rc1` (`6.3.0RC1`) em **Linux**.
A preparação dos patches foi validada sem compilar o runtime. ZTS, Swoole Thread,
psampler e as opções de configuração existentes são preservados; o comportamento
nativo ainda exige homologação após o build. Windows, macOS e BSD não estão
habilitados por esta integração: o patcher aborta nessas plataformas.

## Origem auditável

- PR: https://github.com/swoole/swoole-src/pull/6274
- Head consultado: `861a0dffbf5f97671b2d8772078e07d4db120cde` (PR aberta).
- Base da PR: `93efea44e57d9e1af3215918060d34dcc29ca4e1`, branch 6.1.
- Diff: https://github.com/swoole/swoole-src/compare/93efea44e57d9e1af3215918060d34dcc29ca4e1...861a0dffbf5f97671b2d8772078e07d4db120cde
- Investigação local: `/home/lotus/projetos/sipswoole/libspech/docs/Optimizer/09-socket-fd-lifecycle.md`.
- Base experimental: `evidence/fd-lifecycle/native-evaluation.patch` naquele diretório.
- Fonte original: URL da tag em `config/source.json`, sem alteração nesta integração.

O manifesto `src/globals/patch/swoole_socket_fd_lifecycle_v630rc1.json` registra
SHA-256 do arquivo experimental, do arquivo compactado usado na reprodução,
do novo patch e dos cinco arquivos nativos completos antes/depois. O hash do
arquivo compactado documenta a reprodução; a aceitação durante o build usa os
hashes individuais dos arquivos, inclusive os guardas do manifesto de Buffer.

## Adaptação local e semântica

O patch versionado é `src/globals/patch/swoole_socket_fd_lifecycle_v630rc1.patch`.
Modifica somente:

- `ext-src/swoole_socket_coro.cc`: finaliza `close()` após cancelamento quando não
  existem mais coroutines vinculadas e libera o FD apenas se fechado.
- `include/swoole_coroutine_socket.h`: declara `coroutine::Socket::close_fd()`.
- `include/swoole_socket.h`: declara `network::Socket::close_fd()`.
- `src/coroutine/socket.cc`: encerra SSL, remove o socket do reactor e delega o
  fechamento físico; o destrutor antecipa a liberação apenas após fechamento
  lógico ou `close_wait`.
- `src/network/socket.cc`: concentra unlink do endereço UNIX e close físico,
  invalida `fd` com `SW_BAD_SOCKET`; o cleanup diferido chama o mesmo método.

A lógica acompanha a PR, sem transplantar fontes 6.1. Em 6.3.0RC1,
`ssl_close()` substitui o `ssl_shutdown()` protegido por `SW_USE_OPENSSL` da PR:
é a API já utilizada pelo destrutor desta versão; libera SSL e zera o ponteiro.
O teste de `socket->ssl` impede a segunda liberação. A declaração virtual de
`cancel()` da versão 6.3 é preservada. A base experimental foi reformatada para
blocos com chaves, acompanhando o estilo upstream, sem alterar sua lógica.

A extração de `close_fd()` remove os ramos Windows/IOCP do cleanup desse arquivo,
como no experimento Linux. Isso não constitui um port Windows: o guard de
plataforma impede sua aplicação ali. Os testes e as alterações em expectativas
de testes da PR não foram importados; os testes existentes não foram relaxados.

Sockets destruídos ainda abertos continuam com fechamento físico diferido,
preservando a entrega da resposta final. A estrutura de socket continua sendo
liberada de forma diferida, porque eventos pendentes podem referenciá-la. Remover
o registro do reactor precede invalidar o FD. O cleanup posterior encontra FD
inválido, sem fechar um número reutilizado nem remover o caminho UNIX de uma
substituição. `move_fd()` continua somente transferindo ownership.

O fechamento físico não foi colocado em `coroutine::Socket::close()`: o hook
`swoole_coroutine_close()` só remove o FD do mapa compartilhado após esse método
retornar. `src/coroutine/hook.cc` também é fixado por hash para proteger essa
premissa. O ramo de streams importados (`zstream`, `php_stream_free()` e
`move_fd()`) permanece intacto, assim como a proteção de recursos referenciados.
Não há garantia de que um socket importado continue utilizável após fechar seu
stream: eles compartilham o FD. Esse comportamento deve ser testado no runtime.

`recvfromInto()` não é alterado. Buffer, ABI de leitura, lifecycle dos buffers,
integração psampler, seleção de extensões e flags permanecem como estavam.

## Ordem, compatibilidade e idempotência

`SourcePatcher::patchSwoole()` continua preparando a extração (inclusive o ajuste
em `config.m4`). `swoole::patchBeforeBuildconf()` continua chamando
`SwooleBufferPatcher::apply()`. Essa classe coordena os dois manifestos:

1. Valida plataforma, hashes dos patches e o estado completo dos arquivos
   monitorados, antes de escrever qualquer fonte.
2. Aplica Buffer ou sua atualização histórica, quando necessário.
3. Confere o estado completo Buffer atual, pré-requisito da correção FD.
4. Aplica o patch FD.
5. Confere o estado completo Buffer + FD, antes de continuar o build.

Não há segundo mecanismo de classificação nem chamada independente ao patcher
FD. O manifesto original de Buffer e seus patches não foram modificados.
Seus hashes `unchanged` continuam obrigatórios nos estados anteriores a FD.
Somente o estado combinado completo usa os hashes finais FD nesses arquivos e
em `ext-src/swoole_socket_coro.cc`. Os arquivos compartilhados precisam ter
hashes Buffer-final e FD-inicial iguais nos manifestos.

Estados aceitos: original, Buffer atual, Buffer v1.1.3 completo, Buffer revisão 2
completo e Buffer + FD completo. Original e revisões históricas convergem para
o mesmo resultado; Buffer atual recebe apenas FD. O estado combinado retorna
`false` e não escreve arquivos. Uma mistura de hashes conhecidos aborta como
patch parcial; hash desconhecido aborta como incompatibilidade, identificando
o arquivo. Arquivo ausente só é aceito quando explicitamente previsto como
`null`, por exemplo antes da criação dos arquivos Buffer.

Cada patch passa por dry-run e aplicação com `--batch --forward --fuzz=0`.
O código de saída real é obrigatório, sem depender de
`SourcePatcher::patchFile()`. Um arquivo temporário permite usar o mesmo caminho
em execução via PHAR. `LC_ALL=C` torna os diagnósticos previsíveis. Offsets de
linha são permitidos: o hook original acrescenta uma linha em `config.m4` e as
duas variantes têm hashes completos previstos. Fuzz não é permitido; os hashes
finais ainda precisam ser exatos. Uma falha real de escrita pode deixar uma
etapa incompleta, mas aborta o build e a próxima execução rejeita essa mistura;
não há recuperação silenciosa nem rollback que sobrescreva fontes do usuário.

## Verificação reproduzível sem build

```bash
php tests/swoole_patches/compatibility.php
# Opcional: informar outro caminho para o MESMO arquivo da tag:
php tests/swoole_patches/compatibility.php /caminho/swoole-v6.3.0-rc1.tar.gz
```

O teste usa diretório temporário exclusivo e apenas os arquivos monitorados do
arquivo compactado; não escreve em `source/` nem `buildroot/`. Confere os hashes
originais de Buffer independentemente, cria as revisões antigas por reversão
dos patches de upgrade e exige seus hashes antes de testar a atualização.
Confere igualdade byte a byte do resultado entre os caminhos de atualização.

Resultado da preparação: **78 casos passaram**, incluindo ambas as variantes de
`config.m4`, primeira aplicação, Buffer-only, repetição sem alterações, ambas as
revisões anteriores, todas as 30 misturas parciais dos cinco arquivos FD,
Buffer parcial e fonte incompatível nos três estados principais. Um teste
adicional com hunk rejeitado confirma o aborto no dry-run sem aplicar um hunk
anterior válido. Sintaxe PHP, manifestos JSON e `git diff --check` também
verificados. Nenhum teste nativo foi executado com runtime corrigido nesta etapa.

## Build e homologação pendente

O comando continua válido no projeto atual, preservando seleção e ZTS:

```bash
bin/spc build --build-cli "bcg729,psampler,opus,swoole,sockets,filter,standard" --enable-zts --no-strip
```

Não foi executado durante a preparação. Após o build, homologar: reprodução de
Socket puro; objeto fechado ainda referenciado; MediaChannel; `recvfrom()` e
`recvfromInto()`; loops no reactor persistente; reutilização de FDs; concorrência
e cancelamento; TCP/UDP/UNIX; streams importados; SSL/TLS; Buffer nativo e
regressões Optimizer da libspech. Atenção particular a ZTS/Thread, entrega final
em sockets destruídos abertos e cleanup de caminhos UNIX. Não alterar testes
estritos nem publicar runtime/CI antes da homologação.

## Atualização futura do Swoole

1. Fixar a nova tag e revisar mudanças upstream nos cinco arquivos, no hook de
   close, reactor, SSL, streams e ownership; verificar se a correção já entrou.
2. Reproduzir a preparação original em cópia limpa, aplicar/portar Buffer e só
   então FD. Nunca aceitar novos hashes apenas para contornar rejeições.
3. Regenerar o diff FD entre Buffer completo e resultado revisado; atualizar
   manifesto próprio com hashes completos, proveniência e plataforma validada.
4. Se Buffer mudar, atualizar seus estados e upgrades explicitamente; manter a
   igualdade dos hashes compartilhados entre Buffer-final e FD-inicial.
5. Reexecutar testes de estados, hashes e idempotência, atualizar a documentação
   e homologar o novo runtime antes de distribuí-lo. Remover o patch local se o
   upstream já resolver integralmente o problema, ajustando os estados de forma
   explícita, sem dupla aplicação.
