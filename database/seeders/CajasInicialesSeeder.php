<?php

namespace Database\Seeders;

use App\Models\Sucursal;
use Illuminate\Database\Seeder;

class CajasInicialesSeeder extends Seeder
{
    public function run(): void
    {
        Sucursal::withoutGlobalScopes()->orderBy('id')->each(fn (Sucursal $sucursal) => $sucursal->asegurarCajaInicial());
    }
}
