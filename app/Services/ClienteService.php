<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Cliente;

/**
 * ClienteService
 *
 * Camada de serviço responsável pela criação e consulta de clientes.
 */
class ClienteService
{
    /**
     * Persiste um novo cliente com os dados validados.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Cliente
    {
        /** @var Cliente $cliente */
        $cliente = Cliente::create($data);

        return $cliente;
    }
}
