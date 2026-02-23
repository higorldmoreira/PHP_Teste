# PHP Teste — API de Propostas

API REST desenvolvida em **Laravel 12** como teste técnico PHP. Implementa gestão de clientes, propostas financeiras e pedidos com **máquina de estados**, **optimistic locking**, **idempotência**, **auditoria automática** e documentação interativa (Swagger / OpenAPI 3.0).

---

## Tecnologias

| Camada | Tecnologia |
|---|---|
| Framework | Laravel 12 / PHP 8.2+ |
| Banco de dados | MySQL 8.0 |
| Cache / Filas | Redis 7 |
| Documentação | L5-Swagger / OpenAPI 3.0 |
| Testes | PHPUnit — SQLite in-memory |
| Infraestrutura | Docker / Docker Compose |

---

## Requisitos

- Docker e Docker Compose instalados
- Portas `8050` (app), `3306` (MySQL) e `6379` (Redis) livres

---

## Instalação

```bash
# 1. Clone o repositório
git clone https://github.com/higorldmoreira/PHP_Teste.git
cd PHP_Teste

# 2. Copie o arquivo de ambiente
cp .env.example .env

# 3. Suba os containers
docker compose up -d

# 4. Instale as dependências
docker exec php_teste-laravel.test-1 composer install

# 5. Gere a chave da aplicação
docker exec php_teste-laravel.test-1 php artisan key:generate

# 6. Execute as migrations e seeders
docker exec php_teste-laravel.test-1 php artisan migrate --seed
```

A API ficará disponível em `http://localhost:8050/api/v1`.

---

## Documentação Swagger

Acesse a UI interativa em:

```
http://localhost:8050/api/documentation
```

Para regenerar o JSON da documentação:

```bash
docker exec php_teste-laravel.test-1 php artisan l5-swagger:generate
```

---

## Endpoints

### Clientes

| Método | Rota | Descrição | Idempotência |
|---|---|---|---|
| `POST` | `/api/v1/clientes` | Cria um novo cliente | ✓ |
| `GET` | `/api/v1/clientes/{id}` | Detalha um cliente | — |

### Propostas

| Método | Rota | Descrição | Idempotência |
|---|---|---|---|
| `GET` | `/api/v1/propostas` | Lista propostas (filtros + paginação) | — |
| `POST` | `/api/v1/propostas` | Cria proposta (status inicial: DRAFT) | ✓ |
| `GET` | `/api/v1/propostas/{id}` | Detalha uma proposta | — |
| `PATCH` | `/api/v1/propostas/{id}` | Atualiza campos livres (optimistic lock) | — |
| `POST` | `/api/v1/propostas/{id}/submit` | DRAFT → SUBMITTED | — |
| `POST` | `/api/v1/propostas/{id}/approve` | SUBMITTED → APPROVED | — |
| `POST` | `/api/v1/propostas/{id}/reject` | SUBMITTED → REJECTED | — |
| `POST` | `/api/v1/propostas/{id}/cancel` | DRAFT / SUBMITTED → CANCELED | — |
| `GET` | `/api/v1/propostas/{id}/auditoria` | Histórico de auditoria da proposta | — |

### Pedidos (Orders)

| Método | Rota | Descrição |
|---|---|---|
| `GET` | `/api/v1/orders` | Lista pedidos (filtro por status + paginação) |
| `POST` | `/api/v1/propostas/{id}/orders` | Cria pedido a partir de proposta APPROVED |
| `GET` | `/api/v1/orders/{id}` | Detalha um pedido |
| `POST` | `/api/v1/orders/{id}/cancel` | Cancela um pedido pendente |

---

## Máquina de estados — Proposta

```
                  ┌─────────────┐
             ┌───►│  SUBMITTED  ├───► APPROVED  ──► (terminal)
             │    └──────┬──────┘
 DRAFT ──────┤           └────────────► REJECTED ──► (terminal)
             │
             └──────────────────────────────────────► CANCELED ──► (terminal)
```

Qualquer estado não-terminal pode ir para **CANCELED**.

---

## Máquina de estados — Order (Pedido)

```
PENDING ──► APPROVED ──► SHIPPED ──► DELIVERED  (terminal)
    │
    └──────────────────────────────► CANCELLED   (terminal)
    └──────────────────────────────► REJECTED     (terminal)
```

Apenas pedidos em estado **PENDING** podem ser cancelados via API.

---

## Idempotência

Endpoints marcados com **Idempotência ✓** aceitam o header:

```
Idempotency-Key: <uuid-v4>
```

Respostas com status 2xx ficam em cache por **24 horas**. Requisições repetidas retornam a mesma resposta com o header `X-Idempotency-Replayed: true`.

---

## Optimistic Locking

Ao atualizar uma proposta via `PATCH /api/v1/propostas/{id}`, o corpo deve incluir o campo `versao` com o valor atual do registro:

```json
{
  "versao": 3,
  "produto": "Crédito Pessoal",
  "valor_mensal": 1500.00
}
```

Se o valor de `versao` estiver desatualizado, a API retorna `HTTP 409 Conflict`.

---

## Auditoria Automática

Toda alteração em uma `Proposta` é registrada automaticamente via `PropostaObserver`. Eventos capturados:

| Evento | Gatilho |
|---|---|
| `created` | Criação da proposta |
| `updated_fields` | Atualização de campos livres |
| `status_changed` | Transição de status |
| `deleted_logical` | Exclusão lógica |

Consulta: `GET /api/v1/propostas/{id}/auditoria` — retorna o histórico em ordem decrescente.

---

## Filtros e Paginação

`GET /api/v1/propostas` aceita os seguintes query params:

| Parâmetro | Tipo | Descrição | Padrão |
|---|---|---|---|
| `status` | string | `draft`, `submitted`, `approved`, `rejected`, `canceled` | — |
| `cliente_id` | integer | Filtra por cliente | — |
| `sort` | string | Campo de ordenação | `created_at` |
| `direction` | string | `asc` / `desc` | `desc` |
| `per_page` | integer | Itens por página (máx. 100) | `15` |

`GET /api/v1/orders` aceita:

| Parâmetro | Tipo | Descrição |
|---|---|---|
| `status` | string | Filtra por status do pedido |
| `per_page` | integer | Itens por página (máx. 100) |

---

## Executando os testes

Os testes utilizam **SQLite in-memory** e rodam dentro do container:

```bash
docker exec php_teste-laravel.test-1 php artisan test
```

**Resultado esperado:**

```
Tests: 21 passed (117 assertions)
```

| Suite | Arquivo | Testes |
|---|---|---|
| Orders | `tests/Feature/OrderTest.php` | 6 |
| Transições de status | `tests/Feature/PropostaStatusTransitionTest.php` | 5 |
| Busca de propostas | `tests/Feature/PropostaSearchTest.php` | 3 |
| Idempotência | `tests/Feature/IdempotencyTest.php` | 2 |
| Optimistic Lock | `tests/Feature/OptimisticLockTest.php` | 2 |
| Auditoria | `tests/Feature/AuditoriaTest.php` | 1 |
| Exemplo (Feature) | `tests/Feature/ExampleTest.php` | 1 |
| Exemplo (Unit) | `tests/Unit/ExampleTest.php` | 1 |

---

## Arquitetura

```
app/
├── Enums/           # Backed enums do domínio (PropostaStatusEnum, OrderStatus, …)
├── Exceptions/      # BusinessException (422) e ConcurrencyException (409)
├── Http/
│   ├── Controllers/ # Api/V1 — controllers finos, delegam para Services
│   ├── Middleware/  # IdempotencyMiddleware
│   ├── Requests/    # Form Requests (validação + autorização)
│   └── Resources/   # API Resources (transformação de saída)
├── Models/          # Eloquent models com casts, scopes e relacionamentos
├── Observers/       # PropostaObserver — auditoria automática via Eloquent events
├── Providers/       # AppServiceProvider — bindings de serviços
└── Services/        # Camada de negócio (PropostaService, OrderService)
```

---

## Autor

**Higor Moreira** — [github.com/higorldmoreira](https://github.com/higorldmoreira)
