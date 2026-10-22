<?php

namespace App\Http\Requests;

use App\Models\Sucursal;

class UpdateSucursalRequest extends StoreSucursalRequest
{
    protected function sucursalEditada(): Sucursal
    {
        return $this->route('sucursal');
    }
}
