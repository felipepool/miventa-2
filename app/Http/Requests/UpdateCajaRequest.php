<?php

namespace App\Http\Requests;

use App\Models\Caja;

class UpdateCajaRequest extends StoreCajaRequest
{
    protected function cajaEditada(): Caja
    {
        return $this->route('caja');
    }
}
