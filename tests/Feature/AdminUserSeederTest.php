<?php

use App\Enums\Rol;
use App\Models\Negocio;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\NegocioDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'app.admin.name' => 'Administrador',
        'app.admin.email' => 'admin@miventa.test',
        'app.admin.password' => 'secret-password',
    ]);
});

test('it creates the admin user of the demo business that can log in', function () {
    $this->seed(AdminUserSeeder::class);

    $admin = User::where('username', 'admin')->sole();

    expect($admin->name)->toBe('Administrador')
        ->and($admin->email)->toBe('admin@miventa.test')
        ->and(Hash::check('secret-password', $admin->password))->toBeTrue();

    $this->postJson('/login', [
        'codigo_negocio' => NegocioDemoSeeder::CODIGO,
        'username' => 'admin',
        'password' => 'secret-password',
    ])->assertOk();

    $this->assertAuthenticatedAs($admin);
});

test('the admin user has the admin role and the main branch of the demo business', function () {
    $this->seed(AdminUserSeeder::class);

    $admin = User::where('username', 'admin')->sole();

    setPermissionsTeamId($admin->negocio_id);

    expect($admin->negocio->codigo)->toBe(NegocioDemoSeeder::CODIGO)
        ->and($admin->hasRole(Rol::Admin))->toBeTrue()
        ->and($admin->sucursalesAccesibles()->pluck('nombre')->all())->toBe(['Casa Matriz']);
});

test('the email is optional', function () {
    config(['app.admin.email' => null]);

    $this->seed(AdminUserSeeder::class);

    expect(User::where('username', 'admin')->sole()->email)->toBeNull();
});

test('running the seeder twice does not duplicate the business or the admin user', function () {
    $this->seed(AdminUserSeeder::class);
    $this->seed(AdminUserSeeder::class);

    expect(Negocio::count())->toBe(1)
        ->and(User::where('username', 'admin')->count())->toBe(1);
});

test('it fails when the admin password is not configured', function () {
    config(['app.admin.password' => null]);

    $this->seed(AdminUserSeeder::class);
})->throws(RuntimeException::class);
