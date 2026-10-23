<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUsuarioRequest extends StoreUsuarioRequest
{
    /**
     * La contraseña es opcional: si se envía, el admin la restablece.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'password' => ['nullable', 'string', Password::default(), 'confirmed'],
            'sucursales.*' => [
                'string',
                'distinct',
                Rule::exists('sucursales', 'public_id')
                    ->where('negocio_id', $this->usuarioEditado()->negocio_id)
                    ->where(fn (Builder $query) => $query
                        ->where('activa', true)
                        ->orWhereIn('id', $this->usuarioEditado()->sucursales()->select('sucursales.id'))),
            ],
        ];
    }

    protected function usuarioEditado(): User
    {
        return $this->route('usuario');
    }
}
