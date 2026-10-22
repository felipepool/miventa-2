<?php

namespace Tests;

use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Seed roles and permissions whenever a test refreshes the database.
     */
    protected bool $seed = true;

    protected string $seeder = RolesYPermisosSeeder::class;

    protected function setUp(): void
    {
        parent::setUp();

        // Los tests de backend no dependen de los assets compilados.
        $this->withoutVite();

        // Las peticiones vienen de la SPA: Sanctum las trata como stateful (con sesión).
        $this->withHeader('Referer', config('app.url').'/');
    }
}
