<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Charset e collation explícitos garantem que esta tabela se comporte
     * de forma idêntica em qualquer ambiente (local, staging, produção),
     * independentemente do charset padrão configurado no servidor MySQL.
     */
    protected $connection = 'mysql';

    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $table) {

            $table->id(); // BIGINT UNSIGNED AUTO_INCREMENT — PK padrão Laravel

            $table->string('nome', 150);

            // Unique em nível de banco: mais performático e confiável do
            // que validar apenas na camada de aplicação.
            $table->string('email', 255)->unique();

            // documento: armazena CPF (11 dígitos) ou CNPJ (14 dígitos)
            // sem formatação (apenas dígitos). Tamanho 14 é o maior caso.
            // Index simples (não unique) porque CPF/CNPJ pode ser
            // reutilizado por clientes distintos em sistemas multiempresa.
            $table->string('documento', 14);

            $table->timestamps(); // created_at + updated_at nullable TIMESTAMP

            // ── Índices ──────────────────────────────────────────────────
            // Buscas por documento são frequentes (login, dedup, relatórios).
            // Índice separado do unique de email para não onerar inserts.
            $table->index('documento', 'idx_clientes_documento');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
