<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auditoria_propostas', function (Blueprint $table) {

            $table->id();

            // ── Foreign key ───────────────────────────────────────────────
            // cascadeOnDelete(): ao contrário de propostas, registros de
            // auditoria seguem o ciclo de vida da proposta. Se a proposta
            // for hard-deleted (limpeza administrativa), a auditoria vai junto.
            // Propostas usam SoftDeletes no dia a dia, então cascade é seguro.
            $table->foreignId('proposta_id')
                  ->constrained('propostas')
                  ->cascadeOnDelete();

            // actor: identificador de quem gerou o evento.
            // Pode ser "user:{id}", "system", "api:{client_id}", etc.
            // String livre para não exigir FK e permitir atores externos.
            $table->string('actor', 100);

            // evento: nome do evento de domínio ocorrido.
            // Ex: "proposta.criada", "status.alterado", "versao.incrementada"
            $table->string('evento', 80);

            // payload: snapshot do estado anterior/posterior ou dados do evento.
            // JSON nativo do MySQL 8 — suporta JSON_EXTRACT para queries analíticas
            // e valida estrutura automaticamente no INSERT.
            $table->json('payload');

            // ── Sem updated_at ────────────────────────────────────────────
            // Registros de auditoria são IMUTÁVEIS por definição.
            // Usar apenas created_at comunica essa intenção explicitamente
            // e evita que o Eloquent tente atualizar updated_at.
            // No Model: public $timestamps = false; + const CREATED_AT = 'created_at';
            $table->timestamp('created_at')->useCurrent();

            // ── Índices ───────────────────────────────────────────────────

            // Consulta mais comum: "histórico completo de uma proposta",
            // ordenado por data. O índice composto (proposta_id, created_at)
            // atende esse padrão sem full-table-scan mesmo em tabelas grandes.
            // A FK já indexa proposta_id isoladamente; o composto é adicional
            // para cobrir o ORDER BY created_at sem filesort.
            $table->index(['proposta_id', 'created_at'], 'idx_auditoria_proposta_data');

            // Índice em evento permite filtrar o log por tipo de ocorrência
            // (ex: "todos os status.alterado nos últimos 30 dias" para alertas).
            $table->index('evento', 'idx_auditoria_evento');

            // Índice em actor é útil para investigações de segurança:
            // "tudo que este usuário/sistema fez" em qualquer proposta.
            $table->index('actor', 'idx_auditoria_actor');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auditoria_propostas');
    }
};
