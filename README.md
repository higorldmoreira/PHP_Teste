# PHP Test — API Laravel 12

REST API desenvolvida em **Laravel 12** como teste técnico PHP. Implementa gestão de clientes e propostas com máquina de estados, lock otimista, idempotência, auditoria automática, autenticação OAuth2 (Passport) e documentação interativa (Swagger).

---

## Tecnologias

| Camada | Tecnologia |
|---|---|
| Framework | Laravel 12 / PHP 8.2+ |
| Banco de dados | MySQL 8.0 |
| Cache / Filas | Redis 7 |
| Autenticação | Laravel Passport (OAuth2) |
| Documentação | L5-Swagger / OpenAPI 3.0 |
| Testes | PHPUnit — SQLite in-memory |
| Infraestrutura | Docker / Docker Compose |

---

## Requisitos

- Docker e Docker Compose instalados
- Portas `3306` (MySQL), `6379` (Redis) e `80` disponíveis

---

## Instalação

```bash
# 1. Clone o repositório
git clone https://github.com/higorldmoreira/PHP_Teste.git
cd php-teste

# 2. Copie o arquivo de ambiente
cp .env.example .env

# 3. Suba os containers
docker compose -f docker-compose.yml up -d

# 4. Instale as dependências dentro do container
docker exec -it php_teste composer install

# 5. Gere a chave da aplicação
docker exec -it php_teste php artisan key:generate

# 6. Execute as migrations e seeders
docker exec -it php_teste php artisan migrate --seed

# 7. Publique as chaves do Passport
docker exec -it php_teste php artisan passport:keys --force
```

A API ficará disponível em `http://localhost/api/v1`.

---

## Credenciais padrão

Após executar `migrate --seed`, os seguintes dados estarão disponíveis:

| Campo | Valor |
|---|---|
| E-mail | `admin@teste.com` |
| Senha | `password` |

Use o endpoint `POST /api/v1/auth/login` para obter o Bearer Token.

---

## Documentação Swagger

Acesse a UI interativa em:

```
http://localhost/api/documentation
```

Para regenerar o JSON (`storage/api-docs/api-docs.json`):

```bash
php artisan l5-swagger:generate
```

---

## Endpoints

### Autenticação

| Método | Rota | Descrição | Auth |
|---|---|---|---|
| `POST` | `/api/v1/auth/register` | Cadastra novo usuário | — |
| `POST` | `/api/v1/auth/login` | Obtém Bearer Token | — |
| `POST` | `/api/v1/auth/logout` | Revoga o token atual | ✓ |
| `GET` | `/api/v1/auth/me` | Dados do usuário autenticado | ✓ |

### Clientes

| Método | Rota | Descrição | Auth | Idempotência |
|---|---|---|---|---|
| `POST` | `/api/v1/clientes` | Cria cliente | ✓ | ✓ |
| `GET` | `/api/v1/clientes/{id}` | Detalha cliente | ✓ | — |

### Propostas

| Método | Rota | Descrição | Auth | Idempotência |
|---|---|---|---|---|
| `GET` | `/api/v1/propostas` | Lista propostas (filtros + paginação) | ✓ | — |
| `POST` | `/api/v1/propostas` | Cria proposta (DRAFT) | ✓ | ✓ |
| `GET` | `/api/v1/propostas/{id}` | Detalha proposta | ✓ | — |
| `PATCH` | `/api/v1/propostas/{id}` | Atualiza proposta (optimistic lock) | ✓ | — |
| `POST` | `/api/v1/propostas/{id}/submit` | DRAFT → SUBMITTED | ✓ | — |
| `POST` | `/api/v1/propostas/{id}/approve` | SUBMITTED → APPROVED | ✓ | — |
| `POST` | `/api/v1/propostas/{id}/reject` | SUBMITTED → REJECTED | ✓ | — |
| `POST` | `/api/v1/propostas/{id}/cancel` | DRAFT/SUBMITTED → CANCELED | ✓ | — |
| `GET` | `/api/v1/propostas/{id}/auditoria` | Histórico de auditoria | ✓ | — |

### Orders (Pedidos)

| Método | Rota | Descrição | Auth |
|---|---|---|---|
| `GET` | `/api/v1/orders` | Lista pedidos do usuário | ✓ |
| `POST` | `/api/v1/propostas/{id}/orders` | Cria pedido a partir de proposta APPROVED | ✓ |
| `GET` | `/api/v1/orders/{id}` | Detalha pedido | ✓ |
| `POST` | `/api/v1/orders/{id}/cancel` | Cancela pedido | ✓ |

---

## Máquina de estados — Proposta

```
                  ┌─────────┐
             ┌───►│SUBMITTED├───► APPROVED ──► (terminal)
             │    └────┬────┘
 DRAFT ──────┤         └──────────► REJECTED ──► (terminal)
             │
             └──────────────────────────────────► CANCELED ──► (terminal)

Qualquer estado não-terminal pode ir para CANCELED.
```

---

## Idempotência

Endpoints de mutação marcados com **Idempotência ✓** aceitam o header:

```
Idempotency-Key: <uuid>
```

Respostas 2xx ficam em cache por **24 horas**. Replays retornam o header `X-Idempotency-Replayed: true`.

---

## Optimistic Lock

Ao atualizar uma proposta via `PATCH`, a requisição deve enviar o campo `versao` com o valor atual do registro. Divergência retorna `HTTP 409 Conflict`.

---

## Executando os testes

Os testes usam **SQLite in-memory** e não dependem do Docker.

```bash
php artisan test
# ou
./vendor/bin/phpunit
```

| Suite | Arquivo |
|---|---|
| Auth | `tests/Feature/AuthTest.php` |
| Orders | `tests/Feature/OrderTest.php` |
| Auditoria | `tests/Feature/AuditoriaTest.php` |
| Idempotência | `tests/Feature/IdempotencyTest.php` |
| Optimistic Lock | `tests/Feature/OptimisticLockTest.php` |
| Busca de propostas | `tests/Feature/PropostaSearchTest.php` |
| Transições de status | `tests/Feature/PropostaStatusTransitionTest.php` |

---

## Arquitetura

```
app/
├── Enums/           # Backed enums do domínio (PropostaStatusEnum, OrderStatus…)
├── Exceptions/      # BusinessException (422) e ConcurrencyException (409)
├── Http/
│   ├── Controllers/ # Api/V1 — thin controllers, delegam para Services
│   ├── Middleware/  # IdempotencyMiddleware
│   ├── Requests/    # Form Requests com validação e autorização
│   └── Resources/   # API Resources (transformação de saída)
├── Models/          # Eloquent models com casts, scopes e relacionamentos
├── Observers/       # PropostaObserver — auditoria automática via Eloquent events
├── Providers/       # AppServiceProvider — bindings e configuração Passport
└── Services/        # Camada de negócio (PropostaService, OrderService)
```
