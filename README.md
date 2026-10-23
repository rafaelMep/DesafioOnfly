# Onfly Travel

Gestão de pedidos de viagem corporativa. **Laravel 12 / PHP 8.4 + Vue 3 / TypeScript**,
com PostgreSQL, autenticação JWT e notificações assíncronas por e-mail.

> Projeto desenvolvido para o case técnico Full Stack PHP e Vue.js da Onfly.
> O ambiente fornecido é de avaliação local, não uma configuração de produção.

## Começar em um comando

**Pré-requisitos:** Docker com engine Linux e Docker Compose v2 ou superior.
No Windows, use Docker Desktop com WSL 2, aberto e com `Engine running`.
Não é necessário instalar PHP, Composer, Node ou PostgreSQL no computador.

Clone o repositório e entre na pasta (requer Git):

```bash
git clone https://github.com/rafaelMep/DesafioOnfly.git
cd DesafioOnfly
```

Na raiz do projeto, instale as dependências e inicie todos os serviços com Docker:

```bash
docker compose up -d --build --wait --wait-timeout 180
```

O primeiro build baixa as imagens e dependências e pode levar alguns minutos.
Os Dockerfiles executam `composer install` e `npm ci`; não é necessário
instalar as dependências manualmente no computador para executar a aplicação.
O serviço `init` aguarda o PostgreSQL, gera chaves, executa migrations e cria as
contas e os pedidos de demonstração. Ele terminar com `Exited (0)` é esperado.

| Recurso | Endereço |
| --- | --- |
| Aplicação | **http://localhost:5173** |
| API | http://localhost:8000/api |
| Health check | http://localhost:8000/up |
| Caixa de e-mails (Mailpit) | **http://localhost:8025** |

### Contas de demonstração

Senha para os três perfis: **`Onfly@123`**.

| E-mail | Perfil | Finalidade |
| --- | --- | --- |
| `rafael@onfly.test` | Viajante | Criar, consultar e filtrar pedidos próprios |
| `ana@onfly.test` | Viajante | Verificar o isolamento entre usuários |
| `admin@onfly.test` | Administrador | Analisar pedidos da equipe |

Os atalhos do login apenas preenchem credenciais dessas contas locais.
O seeder é idempotente e só roda em ambientes `local` ou `testing`; executá-lo
novamente não duplica os pedidos nem restaura seus status.

## Roteiro rápido de avaliação

1. Entre como **viajante**, crie um pedido e observe o status **Solicitado**.
2. Use destino, status, período e paginação. Abra os detalhes do pedido.
3. Entre como **Ana**: os pedidos do Rafael não devem aparecer.
4. Entre como **administrador**, aprove ou cancele um pedido de outro usuário.
5. Confira o e-mail correspondente em **http://localhost:8025**.
6. Abra um pedido aprovado: ele não pode ser cancelado.
7. Observe os próprios pedidos do administrador: ele não pode aprová-los.
8. Experimente formulários inválidos, busca sem resultados e a versão mobile.
9. Crie outro pedido como viajante e entre como administrador: o sininho no topo
   mostra o aviso não lido. Clique para abrir o pedido e marcar o aviso como lido.
   Com o administrador já conectado em outra aba, o contador atualiza em até 30 segundos.
10. Volte ao viajante após uma aprovação/cancelamento: o sininho mostra a decisão
    e abre os detalhes do pedido. O e-mail também permanece disponível no Mailpit.

## Regras e interpretação do enunciado

| Operação | Viajante | Administrador |
| --- | --- | --- |
| Criar pedido para si | Sim | Sim |
| Listar/consultar os próprios pedidos | Sim | Sim |
| Listar/consultar pedidos de outros usuários | Não | Sim, para gestão |
| Alterar status do próprio pedido | Não | **Não** |
| Aprovar/cancelar pedido solicitado de outra pessoa | Não | Sim |
| Alterar pedido aprovado/cancelado | Não | Não |

Transições permitidas:

```text
solicitado ──> aprovado   (terminal)
           └─> cancelado (terminal)
```

- O título “cancelar pedido após aprovação” é contraditório com a regra escrita
  no desafio. **Foi priorizada a regra: pedido aprovado não pode ser cancelado.**
- O acesso administrativo aos pedidos da equipe é a exceção necessária para a
  aprovação. Usuários comuns não conseguem acessar pedidos alheios nem por ID.
- Não há cancelamento pelo solicitante: o enunciado reserva mudanças de status
  ao administrador e proíbe modificar o próprio pedido.
- Solicitante e proprietário vêm do usuário autenticado. `user_id` e `status`
  são proibidos no corpo da criação.
- Ida não pode ser anterior a hoje; volta pode ser no mesmo dia da ida.
- Pedidos não são editados nem excluídos: essas operações não foram solicitadas.

### Filtros

Os filtros são combináveis e sempre respeitam o escopo de autorização:

- **Destino:** busca parcial sem diferenciação entre maiúsculas e minúsculas.
- **Status:** solicitado, aprovado ou cancelado.
- **Data do pedido:** intervalo inclusivo sobre `created_at` (UTC).
- **Período da viagem:** inclui viagens que **intersectam** o intervalo;
  não exclui uma viagem que começou antes e termina dentro/depois do período.
- Datas inicial/final podem ser utilizadas separadamente.
- Paginação por ID decrescente, 10 por página; máximo de 100 via API.
- O resumo conta todos os pedidos visíveis, **independente dos filtros**.

## Arquitetura e organização

**Monólito modular, não microsserviços.** A API mantém regras coesas, com
separação por responsabilidade e sem camadas genéricas de repositórios que
apenas duplicariam o Eloquent. Front-end e worker separados não são microsserviços.

```text
backend/
  app/Actions/          criação e mudança de status com transações
  app/Enums/           transições válidas
  app/Http/Controllers/ orquestração HTTP
  app/Http/Requests/    validação dos dados de entrada
  app/Http/Resources/   contrato de saída, sem expor dados sensíveis
  app/Models/          persistência e relacionamentos
  app/Policies/        autorização de acesso e alteração
  app/Notifications/   avisos persistentes e e-mail em fila
  tests/               testes unitários e de integração da API
frontend/
  src/components/      componentes de apresentação e formulários
  src/composables/     ciclo de consulta, cancelamento e paginação
  src/services/        cliente HTTP e contrato de acesso à API
  src/stores/          autenticação e feedback global
  src/types/           contratos TypeScript
  src/views/           composição das páginas
  e2e/                 testes reais no navegador
docker/                configuração do Nginx da API
docs/                  contrato da API e ambiente Windows
```

**Concorrência:** a mudança de status utiliza transação e `SELECT ... FOR UPDATE`
no PostgreSQL. A regra é reavaliada após adquirir o lock; duas decisões simultâneas
não podem sobrescrever uma decisão terminal. Uma transição inválida retorna 409.

**Performance:** paginação no servidor, eager loading do solicitante (evitando
N+1), índices de proprietário/status/datas, resumo agregado em SQL e assets
versionados/cacheados. A busca parcial por destino usa `LIKE`: para volumes
elevados, um índice trigram no PostgreSQL é a evolução apropriada.

**Notificações:** fila no próprio PostgreSQL, worker separado, até três
tentativas. O job é despachado **após o commit** da mudança, evitando notificar
uma transação revertida. O Mailpit captura e-mails localmente; não envia para a
internet. Reinicie o worker após alterações de código.

### Caixa de notificações de administradores e viajantes

Como melhoria além do requisito de e-mail, um pedido novo gera um aviso interno
para **cada administrador existente, exceto o próprio solicitante**. Os avisos
são gravados sincronamente no banco na mesma transação do pedido: não dependem
de o administrador estar online nem da execução da fila. Se a gravação falhar,
o pedido e os avisos são revertidos juntos. O seeder não gera avisos retroativos;
eles são criados pelas novas solicitações feitas pela API.

Ao aprovar ou cancelar um pedido, o solicitante recebe um aviso pessoal no
mesmo sininho, inclusive se também for administrador. A decisão e o aviso são
persistidos na mesma transação; o e-mail obrigatório continua na fila após o
commit. Não há criação retroativa de avisos para decisões anteriores à funcionalidade.

No sininho há contador de não lidas, filtros Todas/Não lidas, histórico paginado,
marcação individual ao abrir o pedido e opção de marcar todas como lidas.
Cada destinatário tem seu próprio estado de leitura e não pode listar ou marcar
avisos de outro usuário. Viajantes veem apenas decisões sobre seus próprios pedidos;
administradores também veem os novos pedidos da equipe recebidos por eles.
Quem deixa de ser administrador perde acesso aos avisos administrativos antigos,
mas mantém suas decisões pessoais.
O conteúdo registra o solicitante/destino no momento do pedido; os detalhes
abertos pela notificação consultam a situação atual do pedido na API.

A atualização é por **polling, não webhook nem WebSocket**: uma consulta leve ao
contador a cada 30 segundos, sem sobrepor requisições. A consulta pausa com a aba
oculta ou navegador offline, retoma ao voltar e é encerrada ao sair da tela/conta.
Falhas usam intervalo de 60 segundos e não bloqueiam o dashboard. Novos avisos
com a lista aberta mostram uma opção de atualizar, sem mover o conteúdo durante
a leitura. Aprovação/cancelamento continuam enviando o e-mail obrigatório ao viajante.

## UI e experiência do usuário

- Layout responsivo, com tabela no desktop e cards no celular, mantendo destino,
  datas, status e ações visíveis sem rolagem horizontal.
- Filtros explícitos, resumo clicável, detalhes e confirmação de ações definitivas.
- Loading skeletons, estados de erro recuperáveis e estados vazios contextualizados.
- Validações próximas dos campos, preservação do formulário após falha e
  proteção contra envio duplicado e descarte acidental.
- Modal com foco inicial, contenção de foco, Escape e restauração do foco.
- Mensagens anunciadas por leitores de tela, labels, status com texto e ícone,
  link para pular navegação e respeito a preferência por movimento reduzido.
- Fontes servidas pela própria aplicação, sem chamadas a Google Fonts.

### Autocomplete de destinos

O formulário sugere cidades **brasileiras e internacionais** usando
[Open-Meteo Geocoding](https://open-meteo.com/en/docs/geocoding-api), com dados
do [GeoNames](https://www.geonames.org/), sem chave ou cadastro na modalidade
gratuita não comercial. Os nomes são solicitados em português: por exemplo,
`Londres, Inglaterra, Reino Unido`. País e região distinguem cidades homônimas.
O destino continua sendo texto livre: selecionar uma sugestão não é obrigatório.

**Fallback brasileiro:** se o Open-Meteo não retornar cidades, estiver indisponível,
em cooldown ou com orçamento de chamadas esgotado, a aplicação consulta o catálogo
de municípios do [IBGE](https://servicodados.ibge.gov.br/api/docs/localidades).
O catálogo é baixado completo e cacheado por 24 horas (cópia de contingência por
sete dias); os termos pesquisados não são enviados ao IBGE. A pesquisa local
ignora acentos/caixa, aceita UF e prioriza o nome exato. Ex.: **Ouro Preto, MG, Brasil**.
O fallback tem timeout e cooldown independentes do provedor mundial. A interface
identifica a fonte de cada resposta e não mistura códigos GeoNames e IBGE.
Uma linha incompleta no provedor mundial é ignorada sem descartar outras cidades válidas.

A aplicação consulta seu próprio endpoint autenticado, com debounce de 300 ms,
cancelamento de buscas anteriores e seleção por mouse/teclado (↑, ↓, Enter e Escape).
A busca retorna até oito cidades. O provedor faz busca exata com dois caracteres
e por prefixo normalizado a partir de três; traduções dependem da cobertura do GeoNames.
O back-end envia **somente o termo pesquisado** ao provedor (não o JWT nem o
perfil do usuário) e mantém cada consulta em cache por 24 horas. Em falha, usa a
última resposta daquela consulta por até sete dias; sem cache, informa a
indisponibilidade caso o fallback também não consiga cobrir a busca, sem bloquear o formulário. Um cooldown global de um minuto
evita insistir no provedor durante falhas. Há orçamento compartilhado de
30 chamadas externas/minuto e 1.000/dia; consultas em cache não consomem esse orçamento.

**Licença e limites:** a API hospedada gratuita do Open-Meteo é destinada a
[uso não comercial](https://open-meteo.com/en/terms), com limites publicados de
menos de 600 chamadas/minuto, 5.000/hora e 10.000/dia. Esta integração é para a
demonstração local do case, não para implantação comercial gratuita na Onfly.
Uso comercial exige revisar a licença/plano ou hospedar a solução com dados
abertos do GeoNames. Atribuição Open-Meteo/GeoNames é exibida no autocomplete.
Dados disponibilizados sob CC BY 4.0; a interface adapta/filtra os resultados.

## Autenticação e segurança

- JWT com validade padrão de 60 minutos, em `Authorization: Bearer`.
- O token fica em **sessionStorage**: sobrevive ao refresh, mas não persiste
  após fechar a aba. Nunca é passado pela URL. Dados do perfil vêm de `/auth/me`.
- O logout invalida o token na blacklist, usando cache compartilhado no banco.
- Respostas 401 limpam a sessão e redirecionam ao login. Não há refresh token;
  após expirar, é necessário autenticar novamente.
- Rate limits: 5 tentativas de login/minuto por IP e 60 requisições autenticadas/minuto.
- Validação e autorização sempre na API; ocultar botões no Vue não substitui isso.
- Senhas com hash, campos permitidos explicitamente e queries parametrizadas.
- Nginx do front-end aplica CSP, bloqueio de framing e `nosniff`.
- Serviços HTTP expostos apenas em `127.0.0.1`; PostgreSQL sem porta publicada.
- Chaves geradas na primeira inicialização e armazenadas no volume `secrets`,
  sem segredos fixos no código ou na imagem.

**Trade-off:** sessionStorage ainda é acessível a JavaScript. Para produção,
considere cookies HttpOnly com fluxo de CSRF apropriado, TLS, gestão externa de
segredos, imagem sem dependências de desenvolvimento, backups, monitoramento
e contas provisionadas sem atalhos de demonstração.

## Configuração

O Compose contém as configurações locais de banco, e-mail e URLs. Não é
necessário criar `.env` manualmente para usar Docker. Para personalizar a senha
do PostgreSQL, copie o `.env.example` **da raiz** para `.env` antes da primeira
inicialização e altere `DB_PASSWORD`. Alterar a variável depois que o volume
existe não altera automaticamente a senha do PostgreSQL.

O arquivo `backend/.env.example` documenta o modo de desenvolvimento fora do
Docker. Dentro dos contêineres, o `.env` é um link para o arquivo compartilhado
no volume de segredos; variáveis de ambiente do Compose têm precedência.

```bash
docker compose ps                  # estado dos serviços
docker compose logs -f init        # preparação do banco
docker compose logs -f worker      # processamento de e-mails
docker compose exec backend php artisan queue:failed
docker compose exec backend php artisan queue:retry all
docker compose down               # para sem remover dados
docker compose up -d              # inicia novamente
```

**Não use `docker compose down -v` para apenas parar a aplicação:** essa opção
apaga banco, chaves e dados persistidos.

### Desenvolvimento sem Docker

Requer PHP **8.4**, Composer 2 e Node **22 LTS atualizado** (o Docker usa Node 22).
As extensões PHP necessárias incluem Mbstring, OpenSSL, PDO, Fileinfo, DOM,
XML, Sodium, Zip e PDO SQLite (ou PDO PostgreSQL).

Na pasta `backend`:

```bash
composer install
```

Copie `backend/.env.example` para `backend/.env` se não existir (PowerShell:
`Copy-Item .env.example .env`; Bash: `cp .env.example .env`). O padrão usa SQLite.

```bash
php artisan key:generate
php artisan jwt:secret --force
php artisan migrate --seed
php artisan serve --host=127.0.0.1 --port=8000
```

Em outro terminal, na pasta `backend`, execute `php artisan queue:work`.
No padrão local, os e-mails são escritos em `storage/logs/laravel.log`.
Para visualizá-los no Mailpit via Docker, configure `MAIL_MAILER=smtp`,
`MAIL_HOST=127.0.0.1` e publique a porta SMTP 1025 do serviço `mailpit`.

Na pasta `frontend`, em outro terminal:

```bash
npm ci
npm run dev
```

O Vite encaminha `/api` para `http://localhost:8000`. Não é necessário abrir CORS.
Pare os serviços Docker que ocupem as mesmas portas antes de usar esse modo.

## Testes e verificações

### Back-end

```bash
docker compose exec -T backend php artisan test
docker compose exec -T backend php vendor/bin/pint --test
docker compose exec -T backend composer audit
```

Ou, em `backend`: `php artisan test`.

O PHPUnit **força SQLite em memória**, cache em array, e-mail em array e fila
síncrona. Os testes não migram/apagam o PostgreSQL usado pela demonstração.
As variáveis são sobrescritas em `$_ENV` e `$_SERVER` (necessário no Docker),
e a classe base aborta antes de qualquer migration caso a conexão não seja
SQLite em memória.
Cobrem transições, autorização, isolamento, validação, filtros, paginação, JWT,
logout, rate limiting e construção/despacho de notificações. Locks de linha
são uma garantia do PostgreSQL; SQLite não exercita esse comportamento.

### Front-end

Na pasta `frontend`:

```bash
npm ci
npm test
npm run build
npm run format:check
npm audit
```

### Ponta a ponta (Playwright)

Com a aplicação e o Mailpit em execução, na pasta `frontend` (após `npm ci`):

```bash
npx playwright install chromium
npm run test:e2e
```

Exercita autenticação, criação, aprovação por outro perfil, e-mail real no Mailpit,
busca vazia, falha de rede/retry, token inválido, layout mobile e foco de modal.
Também dispara decisões concorrentes contra o PostgreSQL e verifica que apenas
uma vence (200), enquanto a outra recebe 409, sem sobrescrever o estado final.
Os testes E2E criam pedidos com destinos `Recife · E2E ...`, `Pedido próprio E2E ...`
e `Concorrência E2E ...` no banco local. Cada cenário cria seus próprios registros,
sem depender de um pedido específico do seed estar na primeira página.
Não excluem pedidos existentes. Não rode contra um ambiente de produção.
O limite de login continua ativo: se executar repetidamente em menos de um minuto,
aguarde a janela do rate limit terminar antes de repetir.

Há uma pipeline em `.github/workflows/ci.yml` para testes, formatação, build e E2E.

## Documentação da API

Contrato, exemplos e códigos HTTP em **[docs/api.md](docs/api.md)**.

## Limitações e próximas evoluções

- Sem cadastro público, recuperação de senha, refresh token ou edição de pedidos:
  fora do escopo solicitado; contas locais vêm do seeder.
- Sem WebSocket: pedidos são atualizados por ação explícita/recarga; o contador
   de avisos de administradores e viajantes usa polling a cada 30 segundos com a aba visível.
- A fila após commit evita e-mails de transações revertidas, mas não implementa
  **transactional outbox**. Em uma falha excepcional entre commit e enfileiramento,
   a mudança e o aviso interno podem persistir sem enfileirar o e-mail. Uma outbox com retry/idempotência
  seria a evolução para garantia forte de entrega.
- E-mail pode ser entregue novamente em retries; não há promessa de exactly-once.
- Dados de datas são validados no servidor, com relógio de negócio em UTC.
- Dados de demonstração e credenciais públicas são exclusivos da avaliação local.

As escolhas priorizam clareza, segurança das regras centrais e execução reproduzível,
sem introduzir infraestrutura distribuída desnecessária ao tamanho do problema.
