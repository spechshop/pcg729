# PCG729



OBS: in progress setting

## Descrição
Este repositório contém o projeto `pcg729`, desenvolvido e mantido por `berzersks`. Ele inclui uma estrutura robusta para desenvolvimento em PHP, com diversas dependências e ferramentas configuradas para facilitar o desenvolvimento e a manutenção do código.

## Estrutura do Projeto
Abaixo está uma visão geral da estrutura do projeto:

- **bin/**: Scripts utilitários para construção, configuração e execução.
- **config/**: Arquivos de configuração, incluindo definições de ambiente e extensões.
- **src/**: Código-fonte principal do projeto, organizado em subdiretórios para diferentes funcionalidades.
- **vendor/**: Dependências gerenciadas pelo Composer.

## Dependências
As dependências do projeto são gerenciadas pelo Composer incluído em `bin/composer`.




## Exemplo de compilação

Para compilar um PHP com a extensão `bcg729` e suporte a ZTS:

```bash
git clone https://github.com/spechshop/pcg729
cd pcg729
bin/php bin/composer install
bin/php bin/spc download -A --shallow-clone --debug
bin/php bin/spc build --build-cli "bcg729" --enable-zts --debug
buildroot/bin/php -v
```

## Contribuição
Contribuições são bem-vindas! Siga as etapas abaixo para contribuir:

1. Faça um fork do repositório.
2. Crie uma branch para sua feature ou correção de bug:
   ```bash
   git checkout -b minha-feature
   ```
3. Faça commit das suas alterações:
   ```bash
   git commit -m "Descrição das alterações"
   ```
4. Envie suas alterações:
   ```bash
   git push origin minha-feature
   ```
5. Abra um Pull Request.

## Licença
Este projeto está licenciado sob a licença MIT. Veja o arquivo LICENSE para mais detalhes.
