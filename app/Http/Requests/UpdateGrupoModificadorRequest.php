<?php

namespace App\Http\Requests;

use App\Models\GrupoModificador;

class UpdateGrupoModificadorRequest extends StoreGrupoModificadorRequest
{
    protected function grupoEditado(): GrupoModificador
    {
        return $this->route('grupoModificador');
    }
}
