# Ambiente de desenvolvimento no Windows

## Ferramentas instaladas

- PHP CLI 8.4.27 (NTS x64), separado do PHP 8.2 do XAMPP.
- Composer 2.10.3, utilizando o PHP CLI selecionado no `PATH`.
- Docker Desktop 4.96.2, com backend WSL 2.
- Docker CLI 29.9.2 e Docker Compose 5.6.1.
- WSL 2.7.13, com a versão 2 definida como padrão.

PHP e Docker foram instalados pelo WinGet, com verificação de hash dos pacotes.
O Composer também teve seu SHA-256 verificado.

## Validação concluída

Após a reinicialização do Windows e a conclusão das telas iniciais do Docker
Desktop, a configuração foi validada:

- `docker version` exibiu cliente e servidor Linux em funcionamento.
- `docker run --rm hello-world` baixou a imagem e executou com sucesso.
- O contexto ativo é `desktop-linux`, com backend WSL 2.
- O diagnóstico do Composer e os requisitos de plataforma passaram com PHP 8.4.

## Verificar o ambiente novamente

1. Abra o Docker Desktop.
2. Aguarde o engine iniciar.
3. Abra um terminal. Se acabou de instalar as ferramentas, abra um **novo
   terminal** para carregar as alterações do `PATH`.
4. Execute:

```powershell
php --version
php --ini
composer diagnose
wsl --version
docker version
docker compose version
docker run --rm hello-world
```

`php --version` deve informar PHP 8.4. `docker version` deve exibir tanto
`Client` quanto `Server`; exibir apenas o cliente não confirma o funcionamento
do engine. `hello-world` baixa e executa uma imagem de teste.

## PHP e XAMPP

O PHP novo está instalado em:

```text
%LOCALAPPDATA%\Microsoft\WinGet\Packages\PHP.PHP.NTS.8.4_Microsoft.Winget.Source_8wekyb3d8bbwe
```

Seu `php.ini` habilita cURL, Fileinfo, Intl, Mbstring, OpenSSL, PDO para
PostgreSQL/MySQL/SQLite, PostgreSQL, Sodium, SQLite e Zip.

O Composer está em `%LOCALAPPDATA%\Programs\Composer`.

A configuração HTTPS do PHP utiliza um arquivo PEM com as autoridades do
bundle público do cURL e as raízes já confiáveis do Windows. A verificação de
certificados **não foi desabilitada**. Após mudanças nas autoridades confiáveis
do Windows, esse arquivo precisará ser atualizado.

O XAMPP não foi alterado: seu Apache continua utilizando o PHP original.
Para verificar qual PHP o terminal utiliza:

```powershell
Get-Command php
where.exe php
```

## Docker

O Docker Desktop foi instalado com `--backend=wsl-2`. Sua configuração gerada
contém `EngineTypeWindows: wsl2`. Não é necessário instalar Ubuntu apenas para
executar os contêineres pelo Docker Desktop.

Não foram alteradas configurações de BIOS, limites de memória nem políticas
de segurança do Windows.
