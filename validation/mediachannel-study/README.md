# Estudo da MediaChannel

Auditoria de desempenho e confiabilidade na cópia local da libspech. Produção e originais preservados. Há quatro correções experimentais com reprodução antes/depois; otimização de CPU não integrada por falta de benefício consistente no canal completo. Validação funcional parcial: consulte lacunas antes de qualquer adoção.

1. [Auditoria integral](01-auditoria-completa.md)
2. [Referências, problemas e classificação](02-referencias-e-problemas.md)
3. [Plano registrado antes das alterações](03-plano-de-otimizacao.md)
4. [Correções e alterações](04-correcoes-e-alteracoes.md)
5. [Testes e pendências](05-testes-funcionais.md)
6. [Benchmarks e limitações](06-benchmarks.md)
7. [Escalabilidade](07-analise-de-escalabilidade.md)
8. [Conclusão e critérios ainda pendentes](08-conclusao-final.md)

`lab/baseline`: snapshot inicial de plugins e testes, .env vazio. `lab/candidate`: apenas correções experimentais da MediaChannel. `lab/optimization`: protótipo acústico congelado para reproduzir resultados, rejeitado para integração; não contém as correções. Nomes não substituem hashes em evidence/manifests.

`tests/`: harnesses e comandos reproduzíveis. `evidence/resultados/`: JSON/JSONL brutos. `evidence/perf/`: contadores reais em texto/CSV. `evidence/logs/`: saídas integrais das suites. `evidence/manifests/`: ambiente, hashes e fonte numerada.

Raiz do laboratório completo: `/home/lotus/CLionProjects/pcg729/validation/mediachannel-study/`. Binário usado fora do ZIP: `/home/lotus/CLionProjects/pcg729/buildroot/bin/php`, hash em environment.json. Não há perf.data ou binários no pacote. ZIP irmão `validation/mediachannel-study.zip` inclui relatórios, evidências textuais, testes e fontes PHP de laboratório; exclui .env e caches. Dependências de runtime nativas precisam ser fornecidas separadamente com hash equivalente.

Ao extrair o ZIP, criar `.env` vazio em cada `lab/baseline`, `lab/candidate` e `lab/optimization` antes de usar o autoloader (não usar credenciais):

```sh
python3 validation/mediachannel-study/tests/restore-env.py
```
