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




## Instalar ou atualizar o PHP do ambiente

Para baixar a versão pública estável mais recente e instalar ou atualizar o PHP de forma interativa:

```bash
curl -fsSL https://raw.githubusercontent.com/spechshop/pcg729/refs/heads/fix-files/bin/update-php | bash
```

O instalador detecta o binário retornado por `command -v php`, identifica se o ambiente atual usa ZTS ou NTS e mostra um menu navegável com as setas ↑/↓ e Enter.

Se não houver PHP no `PATH`, o mesmo comando instala em `/usr/local/bin/php`. Nesse caso, é necessário ter Python 3 para consultar os dados da release. O script requer Bash, `curl`, `sha256sum` e `install` (GNU coreutils); usa `sudo` quando precisa de permissão para gravar no destino. Certifique-se de que `/usr/local/bin` esteja no `PATH` após a instalação.

Todos os arquivos instaláveis disponíveis na última release pública estável são exibidos no menu, preservando variantes antigas e builds com extensões adicionais. Arquivos de checksum e assinatura são ignorados. Quando o GitHub publica o digest SHA-256 do asset, o instalador valida a integridade antes de instalar o binário.

## Exemplo de compilação

Para compilar um PHP com a extensão `bcg729` e suporte a ZTS:

```bash
git clone https://github.com/spechshop/pcg729
cd pcg729
bin/setup-runtime
bin/php bin/composer install
bin/php bin/spc download -A --shallow-clone --debug
bin/php bin/spc doctor --auto-fix
bin/php bin/spc build --build-cli "bcg729,psampler" --debug
buildroot/bin/php -v
```

O `-A` baixa todas as fontes. As fontes do GitHub usam URLs públicas de versões fixas em `config/source.json`, sem depender da API de releases e tags.

O `bin/setup-runtime` instala o PHP para o sistema e a arquitetura atuais em `bin/php`. Ao executar `bin/spc` diretamente, o PHP vem do `PATH`.

Para verificar o ambiente Linux ARM64 com Docker:

```bash
SPC_USE_ARCH=arm64 bin/spc-alpine-docker doctor
```

Em um host x86-64, habilite a emulação ARM64 no Docker antes de rodar o comando. Em um host ARM64, o script seleciona a arquitetura automaticamente. Os arquivos de build ARM64 ficam em `.spc-docker/aarch64/`. [Documentação do Docker sobre emulação com QEMU](https://docs.docker.com/build/building/multi-platform/#qemu)

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
