<?php

namespace App\Http\Requests;

use App\Models\Marca;

class UpdateMarcaRequest extends StoreMarcaRequest
{
    protected function marcaEditada(): Marca
    {
        return $this->route('marca');
    }
}
