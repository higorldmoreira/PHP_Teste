<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\CriarClienteDTO;
use App\Models\Cliente;
use Illuminate\Support\Facades\Log;

/**
 * ClienteService
 *
 * Camada de serviço responsável pela criação e consulta de clientes.
 */
class ClienteService
{
    /**
     * Persiste um novo cliente com os dados validados.
     */
    public function create(CriarClienteDTO $dto): Cliente
    {
        /** @var Cliente $cliente */
        $cliente = Cliente::create($dto->toArray());

        Log::info('cliente.created', [
            'cliente_id' => $cliente->id,
            'email'      => $cliente->email,
        ]);

        return $cliente;
    }
}
