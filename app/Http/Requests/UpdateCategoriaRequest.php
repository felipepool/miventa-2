<?php

namespace App\Http\Requests;

use App\Models\Categoria;

class UpdateCategoriaRequest extends StoreCategoriaRequest
{
    protected function categoriaEditada(): Categoria
    {
        return $this->route('categoria');
    }
}
