# Contrato HTTP — Onfly Travel

Base: `http://localhost:8000/api`. No navegador, o front-end usa `/api` na mesma
origem por meio do proxy Nginx/Vite.

Envie `Accept: application/json` e `Content-Type: application/json`.
Exceto login, todas as rotas exigem `Authorization: Bearer <access_token>`.

## Autenticação

### `POST /auth/login`

```json
{"email":"rafael@onfly.test","password":"Onfly@123"}
```

Resposta 200:

```json
{
  "access_token": "<JWT>",
  "token_type": "bearer",
  "expires_in": 3600,
  "user": {"id":2,"name":"Rafael Silva","email":"rafael@onfly.test","is_admin":false}
}
```

O perfil também pode conter `created_at`, `updated_at` e `email_verified_at`.
Senha e `remember_token` nunca são serializados.

### `GET /auth/me`

Retorna `{"user": {...}}` com o perfil autenticado.

### `POST /auth/logout`

Invalida o JWT. Retorna 200 com `{"message":"Sessão encerrada."}`.

## Pedidos

### Sugestões — `GET /destinations?q=Belo`

Exige autenticação. `q`: entre 2 e 150 caracteres. Retorna até oito cidades no
Brasil ou exterior, com nomes em português quando disponíveis. Com dois
caracteres, o provedor busca nomes exatos; com três ou mais, usa prefixos normalizados.

```json
{"data":[{"id":2643743,"name":"Londres","state":"Inglaterra","country":"Reino Unido","country_code":"GB","label":"Londres, Inglaterra, Reino Unido"}],"available":true,"source":"open-meteo"}
```

`source` é `open-meteo`, `ibge` ou `null` quando não há sugestões. Quando a busca
mundial falha ou não tem resultados, o IBGE é consultado como fallback brasileiro.
Exemplo: `Ouro Preto, MG, Brasil`, com `source: "ibge"` e `id: 3146107`.
IDs pertencem ao GeoNames ou IBGE conforme `source`; não devem ser comparados entre fontes.
`state` representa a
região administrativa e pode ser uma string vazia. A mudança de provedor não
altera pedidos já salvos, cujo destino continua sendo texto.

Se uma fonte está indisponível e nenhuma sugestão/cache foi encontrada na outra, retorna 200 com
`{"data":[],"available":false,"source":null}`. `available:true` com lista vazia significa
que não foram encontradas cidades. Sugestões são opcionais; o destino de
um pedido nunca é validado contra esse catálogo.

### `POST /travel-orders`

```json
{"destination":"Belo Horizonte, MG","departure_date":"2099-01-10","return_date":"2099-01-15"}
```

Resposta **201** com o pedido em `data`. Proprietário e nome vêm da autenticação;
enviar `user_id` ou `status` resulta em 422. O nome do solicitante é o nome atual
do usuário relacionado, não uma cópia histórica.

### `GET /travel-orders/{id}`

Resposta 200:

```json
{
  "data": {
    "id":25,
    "user_id":2,
    "requester_name":"Rafael Silva",
    "destination":"Belo Horizonte, MG",
    "departure_date":"2099-01-10",
    "return_date":"2099-01-15",
    "status":"solicitado",
    "created_at":"2026-10-23T12:00:00+00:00"
  }
}
```

### `GET /travel-orders`

Parâmetros opcionais:

| Parâmetro | Valores |
| --- | --- |
| `status` | `solicitado`, `aprovado`, `cancelado` |
| `destination` | trecho do destino, até 150 caracteres |
| `date_type` | `created` (padrão) ou `travel` |
| `date_from`, `date_to` | `YYYY-MM-DD`, intervalo inclusivo |
| `page` | inteiro a partir de 1 |
| `per_page` | de 1 a 100; padrão 10 |

Exemplo:

```text
/travel-orders?status=solicitado&destination=Belo&date_type=travel&date_from=2099-01-01&date_to=2099-01-31
```

Retorna `data` (lista), `links` e `meta` (inclui `current_page`, `last_page`,
`per_page`, `from`, `to`, `total`). A lista é ordenada por ID decrescente.

### `GET /travel-orders/summary`

Contadores agregados no mesmo escopo de autorização da lista, sem filtros:

```json
{"data":{"total":8,"solicitado":4,"aprovado":2,"cancelado":2}}
```

### `PATCH /travel-orders/{id}/status`

```json
{"status":"aprovado"}
```

Ou `{"status":"cancelado"}`. Apenas administrador, pedido de outra pessoa,
status atual `solicitado`. Retorna 200 com `data` atualizado; enfileira e-mail
após o commit. Pedido já aprovado/cancelado retorna **409**.

## Notificações

Todas as rotas abaixo exigem JWT válido. O escopo é **sempre o destinatário
autenticado**; um ID pertencente a outro usuário retorna 404. Viajantes recebem
decisões sobre seus pedidos; administradores também recebem novos pedidos da equipe.
Avisos administrativos antigos ficam inacessíveis se o destinatário perder esse perfil.

### `GET /notifications/unread-count`

Resposta leve para polling: `{"unread_count":2}`. Não altera o estado de leitura.

### `GET /notifications?page=1&unread_only=0`

Lista paginada (20 por página, mais recentes primeiro) com `data`, `links`, `meta`
e `unread_count` no nível raiz. `unread_only=1` filtra apenas não lidas; o contador
sempre representa todas as não lidas do destinatário, não somente a página.

```json
{
  "data": [{
    "id": "f518df33-b189-4cee-afde-b9ef5fafc2c3",
    "kind": "travel_order_requested",
    "status": null,
    "travel_order_id": 42,
    "title": "Novo pedido de viagem",
    "requester_name": "Rafael Silva",
    "destination": "Ouro Preto, MG, Brasil",
    "read_at": null,
    "created_at": "2026-10-23T12:00:00+00:00"
  }],
  "unread_count": 2
}
```

O exemplo omite `meta` e `links` por brevidade. Avisos são gerados para os
administradores existentes quando um pedido é criado pela API, excluindo o solicitante.
Decisões geram avisos pessoais com `kind=travel_order_status_changed`,
`status=aprovado|cancelado` e título `Pedido aprovado` ou `Pedido cancelado`.
O aviso é persistido na transação da decisão e o e-mail continua enfileirado após
o commit. Não há avisos retroativos; payloads antigos de novos pedidos permanecem compatíveis.

### `PATCH /notifications/{uuid}/read`

Marca um aviso próprio como lido. Retorna `data` do aviso e `unread_count`.
É idempotente: chamadas repetidas preservam o primeiro `read_at`.

### `PATCH /notifications/read-all`

Marca todos os avisos próprios como lidos, sem alterar os de outros usuários.
Retorna `{"unread_count":0}` (ou o contador atualizado caso chegue outro aviso
concorrentemente). A marcação de leitura não altera o pedido de viagem.

## Erros

| HTTP | Significado |
| --- | --- |
| 401 | credenciais incorretas, token ausente/inválido/expirado/revogado |
| 403 | perfil sem autorização, inclusive administrador no próprio pedido |
| 404 | pedido não encontrado |
| 409 | transição não permitida pelo estado atual |
| 422 | dados inválidos; `errors` contém mensagens por campo |
| 429 | limite de requisições atingido; respeite `Retry-After` |
| 500 | falha interna; detalhes ficam nos logs, não na resposta local do Docker |

Exemplo de validação:

```json
{"message":"...","errors":{"destination":["O campo destino é obrigatório."]}}
```

## Exemplo em PowerShell

```powershell
$login = Invoke-RestMethod http://localhost:8000/api/auth/login `
  -Method Post -ContentType application/json `
  -Body '{"email":"rafael@onfly.test","password":"Onfly@123"}'
$headers = @{ Authorization = "Bearer $($login.access_token)" }
Invoke-RestMethod http://localhost:8000/api/travel-orders -Headers $headers
```
