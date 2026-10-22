<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * Identificador público (ULID) para exponer el modelo fuera del servidor.
 *
 * El `id` autoincremental sigue siendo la llave primaria y de las foráneas,
 * pero nunca sale al navegador: URLs, formularios y props usan `public_id`.
 *
 * @mixin Model
 */
trait TienePublicId
{
    use HasUlids;

    /**
     * Solo `public_id` es un ULID; la llave primaria sigue siendo numérica.
     *
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /**
     * Atributos seguros para el frontend, con el `public_id` expuesto como `id`.
     *
     * @return array<string, mixed>
     */
    public function datosPublicos(string ...$atributos): array
    {
        return ['id' => $this->public_id, ...$this->only($atributos)];
    }
}
