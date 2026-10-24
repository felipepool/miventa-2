<?php

namespace Database\Factories;

use App\Enums\Modulo;
use App\Enums\Plan;
use App\Models\Negocio;
use App\Models\Sucursal;
use App\Models\Suscripcion;
use App\Models\Tarifa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Negocio>
 */
class NegocioFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => fake()->unique()->bothify('negocio-####??'),
            'nombre' => fake()->company(),
            'plan' => Plan::Mixto,
            'modulos' => Plan::Mixto->modulos(),
            'rut' => null,
            'direccion' => fake()->streetAddress(),
            'telefono' => fake()->phoneNumber(),
            'email' => fake()->unique()->companyEmail(),
            'activo' => true,
        ];
    }

    /**
     * Negocio con un plan y todos los módulos que incluye.
     */
    public function plan(Plan $plan): static
    {
        return $this->state(fn (array $attributes) => [
            'plan' => $plan,
            'modulos' => $plan->modulos(),
        ]);
    }

    /**
     * @param  list<Modulo>  $modulos
     */
    public function conModulos(array $modulos): static
    {
        return $this->state(fn (array $attributes) => [
            'modulos' => $modulos,
        ]);
    }

    public function inactivo(): static
    {
        return $this->state(fn (array $attributes) => [
            'activo' => false,
        ]);
    }

    /** Negocio fuera de prueba, con un contrato de precio conocido para pruebas de cobro. */
    public function conPlanContratado(): static
    {
        return $this->afterCreating(function (Negocio $negocio): void {
            $negocio->forceFill(['prueba_desde' => now()->subDays(30), 'prueba_hasta' => now()->subDays(16)])->save();
            $tarifa = Tarifa::where('concepto', 'plan')->where('plan', $negocio->plan)->sole();
            if ($tarifa->precio_uf === null) {
                $tarifa->update(['precio_uf' => '1.0000']);
            }
            Suscripcion::factory()->for($negocio)->create();
        });
    }

    /**
     * Crea el negocio junto a su sucursal principal, como en el alta real.
     */
    public function conSucursalPrincipal(): static
    {
        return $this->has(Sucursal::factory()->principal(), 'sucursales');
    }
}
