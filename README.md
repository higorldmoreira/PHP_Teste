# Gestor de Propostas — Laravel 12 REST API

API RESTful construída em **Laravel 12 / PHP 8.2** sem autenticação, com foco em Clean Code, SOLID e boas práticas de engenharia de software.

---

## Stack

| Componente | Versão |
|------------|--------|
| PHP        | 8.2    |
| Laravel    | 12.x   |
| Banco (prod) | MySQL  |
| Banco (testes) | SQLite :memory: |
| Documentação | L5-Swagger (OpenAPI 3.0) |
| Docker     | Sail   |

---

## Arquitetura

```
app/
├── Enums/
│   ├── OrderStatus.php           # PENDING, APPROVED, REJECTED, SHIPPED, DELIVERED, CANCELED
│   ├── PropostaStatusEnum.php    # DRAFT, SUBMITTED, APPROVED, REJECTED, CANCELED
│   ├── PropostaOrigemEnum.php    # app, site, api
│   └── AuditoriaEventoEnum.php   # created, status_changed, updated
├── Exceptions/
│   ├── BusinessException.php     # HTTP 422 — regra de negócio violada
│   └── ConcurrencyException.php  # HTTP 409 — Optimistic Lock
├── Http/
│   ├── Controllers/Api/V1/       # ClienteController, PropostaController, OrderController
│   ├── Requests/                 # StoreClienteRequest, StorePropostaRequest, ...
│   └── Resources/                # ClienteResource, PropostaResource, OrderResource, ...
├── Models/
│   ├── Cliente.php
│   ├── Proposta.php              # SoftDeletes + PropostaObserver
│   ├── Order.php                 # scopePorStatus()
│   └── AuditoriaProposta.php
├── Observers/
│   └── PropostaObserver.php      # Auditoria automática via Eloquent events
├── Providers/
│   └── AppServiceProvider.php    # Singletons: ClienteService, PropostaService, OrderService
└── Services/
    ├── ClienteService.php        # create(array): Cliente
    ├── PropostaService.php       # create, update, search, submit, approve, reject, cancel
    └── OrderService.php          # placeOrder, cancel, paginate
```

### Padrões aplicados

- **Service Layer** — toda lógica de negócio fora dos controllers
- **Optimistic Locking** — campo `versao` incrementado a cada escrita; conflito → HTTP 409
- **Máquina de estados** — transições validadas em `PropostaService` e `OrderService`
- **Soft Deletes** — propostas canceladas usam `deleted_at`
- **Auditoria automática** — `PropostaObserver` registra `created` e `status_changed`
- **Idempotência** — header `Idempotency-Key` (UUID) em POST de clientes e propostas
- **Enums SCREAMING_SNAKE_CASE** — valores americanos, sem acentos, compatíveis com o banco

---

## Endpoints

### Clientes

| Método | URL                     | Descrição                  |
|--------|-------------------------|----------------------------|
| POST   | `/api/v1/clientes`      | Cria cliente (idempotente) |
| GET    | `/api/v1/clientes/{id}` | Exibe cliente              |

### Propostas

| Método | URL                                    | Descrição                         |
|--------|----------------------------------------|-----------------------------------|
| GET    | `/api/v1/propostas`                    | Lista com filtros e paginação     |
| POST   | `/api/v1/propostas`                    | Cria proposta DRAFT (idempotente) |
| GET    | `/api/v1/propostas/{id}`               | Exibe proposta                    |
| PATCH  | `/api/v1/propostas/{id}`               | Atualiza com Optimistic Lock      |
| POST   | `/api/v1/propostas/{id}/submit`        | DRAFT → SUBMITTED                 |
| POST   | `/api/v1/propostas/{id}/approve`       | SUBMITTED → APPROVED              |
| POST   | `/api/v1/propostas/{id}/reject`        | SUBMITTED → REJECTED              |
| POST   | `/api/v1/propostas/{id}/cancel`        | DRAFT|SUBMITTED → CANCELED        |
| GET    | `/api/v1/propostas/{id}/auditoria`     | Histórico de auditoria            |

**Parâmetros de filtro** (`GET /api/v1/propostas`):

| Parâmetro    | Tipo    | Padrão       | Descrição                                                    |
|--------------|---------|--------------|--------------------------------------------------------------|
| `status`     | string  | —            | `draft`, `submitted`, `approved`, `rejected`, `canceled`     |
| `cliente_id` | integer | —            | Filtra por cliente                                           |
| `sort`       | string  | `created_at` | Campo ordenável: `created_at`, `updated_at`, `valor_mensal`, `status`, `versao` |
| `direction`  | string  | `desc`       | `asc` ou `desc`                                              |
| `per_page`   | integer | `15`         | Máximo: 100                                                  |

### Pedidos (Orders)

| Método | URL                              | Descrição                                     |
|--------|----------------------------------|-----------------------------------------------|
| GET    | `/api/v1/orders`                 | Lista pedidos paginados (filtro `?status=`)   |
| GET    | `/api/v1/orders/{id}`            | Exibe pedido                                  |
| POST   | `/api/v1/propostas/{id}/orders`  | Cria pedido a partir de proposta APPROVED     |
| POST   | `/api/v1/orders/{id}/cancel`     | Cancela pedido PENDING                        |

---

## Máquinas de Estado

### Proposta

```
DRAFT ──submit──► SUBMITTED ──approve──► APPROVED (terminal)
  │                   │
  └──cancel──►        ├──reject──► REJECTED (terminal)
       ▼              └──cancel──►
  CANCELED (terminal)
```

### Order

```
PENDING ──► APPROVED ──► SHIPPED ──► DELIVERED (terminal)
PENDING ──cancel──► CANCELED (terminal)
                         REJECTED (terminal)
```

---

## Executando o projeto

### Pré-requisitos

| Ferramenta | Versão mínima | Download |
|------------|--------------|----------|
| [Docker Desktop](https://www.docker.com/products/docker-desktop/) | 24+ | obrigatório |
| [PHP](https://www.php.net/downloads) | 8.2+ | obrigatório (apenas para `composer install` e `key:generate` locais) |
| [Composer](https://getcomposer.org/download/) | 2+ | obrigatório |

> **Por que PHP local?** `composer install` e `php artisan key:generate` rodam na máquina host antes de subir os containers. Após isso, tudo acontece dentro do Docker. Se preferir não instalar PHP localmente, consulte a seção [Sem PHP local](#sem-php-local).

---

### 1. Clonar o repositório

```bash
git clone https://github.com/higorldmoreira/Gestor_Propostas.git
cd Gestor_Propostas
```

---

### 2. Instalar dependências e configurar o ambiente

```bash
# Instalar pacotes PHP
composer install

# Copiar arquivo de variáveis de ambiente
cp .env.example .env      # Linux / macOS / Git Bash
# copy .env.example .env  # PowerShell / CMD (Windows)

# Gerar a chave da aplicação
php artisan key:generate
```

---

### 3. Subir os containers

> **Linux / macOS** — use o script Sail:
> ```bash
> ./vendor/bin/sail up -d
> ```

> **Windows (PowerShell / CMD)** — use `docker compose` diretamente (não requer WSL):
> ```powershell
> docker compose up -d
> ```

Aguarde até todos os serviços estarem `healthy` (~30 s na primeira vez, pois o MySQL precisa inicializar).

---

### 4. Migrations + Seed

```bash
# Linux / macOS
./vendor/bin/sail artisan migrate:fresh --seed

# Windows PowerShell
docker compose exec laravel.test php artisan migrate:fresh --seed
```

---

### 5. Testes

```bash
# Linux / macOS
./vendor/bin/sail artisan test

# Windows PowerShell
docker compose exec laravel.test php artisan test
```

---

> **URL padrão (desenvolvimento):** `http://localhost:8050`

---

### Sem PHP local

Se não quiser instalar PHP na máquina host, use a imagem oficial do Sail para rodar o Composer dentro do Docker:

```bash
# Linux / macOS
docker run --rm \
    -u "$(id -u):$(id -g)" \
    -v "$(pwd):/var/www/html" \
    -w /var/www/html \
    laravelsail/php85-composer:latest \
    composer install --ignore-platform-reqs

# Windows PowerShell
docker run --rm `
    -v "${PWD}:/var/www/html" `
    -w /var/www/html `
    laravelsail/php85-composer:latest `
    composer install --ignore-platform-reqs
```

Depois gere a chave com:

```bash
# Linux / macOS
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate

# Windows PowerShell
docker compose up -d
docker compose exec laravel.test php artisan key:generate
docker compose exec laravel.test php artisan migrate:fresh --seed
```

---

### Aliases úteis (opcional)

**Linux / macOS** — adicione ao `~/.bashrc` ou `~/.zshrc`:
```bash
alias sail='./vendor/bin/sail'
```

**Windows PowerShell** — adicione ao seu `$PROFILE`:
```powershell
function sail { docker compose exec laravel.test php artisan $args }
```

---

## Documentação Swagger

Disponível em: [`http://localhost:8050/api/documentation`](http://localhost:8050/api/documentation)

Geração automática via `L5_SWAGGER_GENERATE_ALWAYS=true` (`.env`).

---

## Rate Limiting

Aplicado via `RateLimiter` do Laravel (`AppServiceProvider`) + middleware `throttle` nas rotas.

| Escopo | Limite | Aplicado em |
|--------|--------|-------------|
| `api` | 60 req/min por IP | Todas as rotas `v1/` |
| `api-write` | 20 req/min por IP | `POST` e `PATCH` |

Ao exceder o limite: **HTTP 429 Too Many Requests**.

> Em testes, `CACHE_STORE=array` é usado, garantindo que o contador de rate limit seja reiniciado a cada request e os testes não recebam 429.

---

## Testes

**62 testes — 211 assertions — todos passando.**

### Suíte

| Arquivo                        | Tipo    | Testes |
|--------------------------------|---------|--------|
| `OrderStatusTest`              | Unit    | 5      |
| `PropostaStatusEnumTest`       | Unit    | 10     |
| `OrderServiceTest`             | Unit    | 8      |
| `PropostaServiceTest`          | Unit    | 13     |
| `AuditoriaTest`                | Feature | 1      |
| `IdempotencyTest`              | Feature | 2      |
| `OptimisticLockTest`           | Feature | 2      |
| `OrderTest`                    | Feature | 8      |
| `PropostaSearchTest`           | Feature | 5      |
| `PropostaStatusTransitionTest` | Feature | 8      |

### Cobertura

- **Unit/Enum** — `isCancellable()`, `isTerminal()`, `isEditable()`, `isCancelable()`, `label()`, `values()` para cada case
- **Unit/Service** — todas as transições válidas e inválidas, lock otimista, duplicata de pedido
- **Feature/Status** — máquina de estados completa (DRAFT→SUBMITTED→APPROVED/REJECTED/CANCELED)
- **Feature/Search** — filtro por status, filtro por cliente, paginação, ordenação por campo, sort inválido
- **Feature/Orders** — criação, listagem, exibição, filtro por status, cancelamento, duplicata
- **Feature/Auditoria** — registro automático de `created` e `status_changed`
- **Feature/Idempotency** — cache por `Idempotency-Key`, header `X-Idempotency-Replayed`
- **Feature/OptimisticLock** — versão correta → 200, versão desatualizada → 409

---

## Seeders

| Seeder          | Dados gerados                                                          |
|-----------------|------------------------------------------------------------------------|
| `ClienteSeeder` | 50 clientes, cada um com 1–5 propostas aleatórias                      |
| `PropostaSeeder`| 1 proposta em cada estado por cliente (amostra de 10)                  |
| `OrderSeeder`   | 1 pedido por proposta APPROVED, distribuídos em PENDING/APPROVED/CANCELED |

---

## Variáveis de ambiente relevantes

```dotenv
APP_URL=http://localhost:8050
APP_PORT=8050
DB_CONNECTION=mysql
DB_DATABASE=laravel
L5_SWAGGER_GENERATE_ALWAYS=true
CACHE_DRIVER=redis   # array em testes (override automático no TestCase)
```
