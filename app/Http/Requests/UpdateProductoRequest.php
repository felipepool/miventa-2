<?php

namespace App\Http\Requests;

use App\Models\Producto;

class UpdateProductoRequest extends StoreProductoRequest
{
    protected function productoEditado(): Producto
    {
        return $this->route('producto');
    }
}
