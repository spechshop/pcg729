# Validação do runtime contra libspech Optimizer — 2026-11-14

Identidade conferida antes dos testes e novamente após sua conclusão:

- Repositório: `https://github.com/spechshop/libspech`
- Caminho: `/home/lotus/projetos/sipswoole/libspech/`
- Branch: `Optimizer`
- Commit: `0c79736a23f538061a8ea613b69936513523b99d`, igual ao HEAD remoto consultado.
- Árvore da biblioteca limpa antes e depois dos testes.
- Executável: `/home/lotus/CLionProjects/pcg729/buildroot/bin/php`
- SHA-256: `50d874e9b06959973ec2873019acf0b09c3a350a8e60adce7e6f7f05ea59cd7f`
- PHP 8.5.12 ZTS, Swoole 6.3.0RC1, Linux x86_64, estático e sem strip.

## Resultados

Executado o runner completo usado pelo workflow MediaChannel dessa branch,
sem modificar os testes nem o código da biblioteca:

```bash
python3 /home/lotus/projetos/sipswoole/libspech/tests/mediachannel/run.py /home/lotus/CLionProjects/pcg729/buildroot/bin/php /home/lotus/projetos/sipswoole/libspech /tmp/pcg729-optimizer-0c79736-Gtp9dZ/functional.json
```

- Suíte funcional completa: **17/17 PASS**, código de saída 0, nenhum SKIP.
- Controle independente de Socket puro: **2/2**, incluindo leitura bloqueada.
- Lifecycle estrito de FD tradicional: **24/24**, nenhuma falha.
- Lifecycle estrito de FD nativo: **24/24**, nenhuma falha.
- Fechamento antes de start: contagens 1, 4, 16 e 50 aprovadas em ambos os modos.
- Sintaxe dos 48 arquivos PHP selecionados pelo mesmo padrão do workflow: OK.

Os testes FD estão incluídos nos 17 comandos; não são 50 comandos adicionais.
Logs de TIMEOUT são esperados no cenário que força timeout; os critérios de
aprovação originais foram mantidos e não houve falha nem SKIP nos resultados.

Os resultados anteriores contra `/home/lotus/projetos/libspech/`, commit
`f7c2695`, são históricos e não fundamentam reprovação deste runtime contra a
Optimizer atual. Nenhum código daquela cópia foi incorporado ao pcg729.

## Auditoria de escopo

Revisados o diff rastreado completo e os novos arquivos desta integração:
`src/SPC/builder/extension/swoole.php`, `src/SPC/store/SwooleBufferPatcher.php`,
patch/manifesto FD, `tests/swoole_patches/compatibility.php` e documentação.
As mudanças de implementação contêm somente coordenação/validação dos patches
SPC e o patch C++ de Swoole. Não incluem fontes, classes ou testes copiados da
libspech antiga. A base experimental do patch vem das evidências da cópia
correta de sipswoole; as referências à biblioteca são documentação.

`.gitignore`, `comandos.txt` e os demais artefatos não rastreados já estavam
presentes antes desta integração e não foram incorporados, movidos ou alterados
por esta validação. Nenhum arquivo foi colocado em staging. A auditoria do
escopo desta integração não aprova automaticamente os artefatos preexistentes
para uma release; revisar novamente o diff exato selecionado antes de publicar.

Evidências locais desta execução em
`/tmp/pcg729-optimizer-0c79736-Gtp9dZ/`: `identity.json`, `functional.json`,
`socket-control.json`, `traditional.json`, `native.json`, `lint.log`,
`pcg729.diff` e `pcg729-status.txt`. O diretório temporário não é parte da release.

Esta execução aprova a suíte funcional/FD disponível nessa branch. Não substitui
uma matriz específica de SSL/TLS, streams importados, TCP/UNIX, concorrência
entre threads ou os PHPTs da fixture C de Buffer. Não houve compilação,
substituição de runtime, commit, push, release ou alteração de CI. Publicação
permanece dependente de autorização explícita do usuário.
