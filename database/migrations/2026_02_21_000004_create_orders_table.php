<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('proposta_id')
                ->constrained('propostas')
                ->restrictOnDelete();

            $table->string('status', 30)->default('pending');
            $table->decimal('valor_total', 10, 2);
            $table->text('observacoes')->nullable();
            $table->timestamps();

            $table->index('status', 'idx_orders_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
