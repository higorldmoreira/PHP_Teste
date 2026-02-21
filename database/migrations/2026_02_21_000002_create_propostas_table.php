<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('propostas', function (Blueprint $table) {

            $table->id();

            // ── Foreign key ───────────────────────────────────────────────
            // constrained() resolve automaticamente para clientes.id.
            // restrictOnDelete(): impede exclusão de cliente com propostas
            // vinculadas — intenção explícita de negócio (dados financeiros
            // não podem ser orfanados silenciosamente).
            $table->foreignId('cliente_id')
                  ->constrained('clientes')
                  ->restrictOnDelete();

            $table->string('produto', 100);

            // decimal(10, 2): até R$ 99.999.999,99 — evite float/double
            // para valores monetários (problema de precisão binária).
            $table->decimal('valor_mensal', 10, 2);

            // status persiste o value do Enum (string) — validação acontece
            // na camada de aplicação (OrderStatus::values()), não via CHECK
            // constraint, para facilitar adição de novos status sem migração.
            $table->string('status', 30)->default('draft');

            // origem: canal de captura (web, app, parceiro, api, etc.)
            $table->string('origem', 50);

            // versao: controle de revisão otimista da proposta.
            // Incrementado a cada aceite de alteração de valores/condições.
            $table->unsignedInteger('versao')->default(1);

            $table->timestamps();

            // deleted_at: habilita SoftDeletes — propostas nunca são
            // removidas fisicamente do banco (requisito de auditoria/fiscal).
            $table->softDeletes();

            // ── Índices ───────────────────────────────────────────────────

            // The FK cliente_id já cria índice implicitamente no MySQL,
            // mas nomeá-lo explicitamente facilita monitoramento via EXPLAIN.
            // (O constrained() acima já cuida disso — comentário apenas informativo.)

            // Consultas de listagem filtram por status com frequência
            // (ex: "todas as propostas aprovadas"). Índice simples é suficiente
            // pois a cardinalidade de status é baixa — se tornar seletivo,
            // considere index composto (status, created_at).
            $table->index('status', 'idx_propostas_status');

            // Relatórios e dashboards filtram por origem para métricas de canal.
            $table->index('origem', 'idx_propostas_origem');

            // Índice composto para o padrão mais comum de consulta:
            // "todas as propostas de um cliente em determinado status".
            // A ordem (cliente_id primeiro) maximiza o reuso deste índice
            // também para queries que filtram apenas por cliente_id.
            $table->index(['cliente_id', 'status'], 'idx_propostas_cliente_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('propostas');
    }
};
