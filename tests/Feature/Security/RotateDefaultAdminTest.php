<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

uses(DatabaseTransactions::class);

function makeRotateAdminRole(): Role
{
    return Role::query()->where('permission_type', 'all')->orderBy('id')->first()
        ?? Role::create([
            'name' => 'Rotate Admin Role '.bin2hex(random_bytes(6)),
            'description' => 'Created by the default-admin rotation tests.',
            'permission_type' => 'all',
        ]);
}

function seedDefaultAdmin(Role $role, string $password): User
{
    $admin = User::query()->where('email', 'admin@example.com')->first();

    if (! $admin) {
        $admin = new User;
        $admin->email = 'admin@example.com';
        $admin->view_permission = 'global';
    }

    $admin->name = 'Example Admin';
    $admin->password = bcrypt($password);
    $admin->status = 1;
    $admin->role_id = $role->id;
    $admin->save();

    return $admin;
}

function setOperatorEnv(string $email, string $password): void
{
    putenv('ADMIN_EMAIL='.$email);
    putenv('ADMIN_PASSWORD='.$password);
    $_ENV['ADMIN_EMAIL'] = $email;
    $_ENV['ADMIN_PASSWORD'] = $password;
    $_SERVER['ADMIN_EMAIL'] = $email;
    $_SERVER['ADMIN_PASSWORD'] = $password;
}

function clearOperatorEnv(): void
{
    putenv('ADMIN_EMAIL');
    putenv('ADMIN_PASSWORD');
    unset($_ENV['ADMIN_EMAIL'], $_ENV['ADMIN_PASSWORD'], $_SERVER['ADMIN_EMAIL'], $_SERVER['ADMIN_PASSWORD']);
}

beforeEach(function () {
    URL::forceRootUrl('http://localhost');
    Cache::flush();
    clearOperatorEnv();
});

afterEach(function () {
    clearOperatorEnv();
});

it('does not change the seeded admin when operator env is missing', function () {
    $role = makeRotateAdminRole();
    $password = 'seeded-login-'.bin2hex(random_bytes(4));
    $admin = seedDefaultAdmin($role, $password);

    test()->artisan('krayin:rotate-admin')->assertSuccessful();

    $admin->refresh();

    expect($admin->email)->toBe('admin@example.com')
        ->and((int) $admin->status)->toBe(1);

    test()->post('/admin/login', [
        'email' => 'admin@example.com',
        'password' => $password,
    ])->assertRedirect(route('admin.dashboard.index'));
});

it('stops the installer default admin from reaching the dashboard once operator env is set', function () {
    $role = makeRotateAdminRole();
    $defaultPassword = 'seeded-login-'.bin2hex(random_bytes(4));
    $operatorEmail = 'operator-'.bin2hex(random_bytes(4)).'@example.invalid';
    $operatorPassword = 'operator-login-'.bin2hex(random_bytes(8));

    seedDefaultAdmin($role, $defaultPassword);

    setOperatorEnv($operatorEmail, $operatorPassword);

    test()->artisan('krayin:rotate-admin')->assertSuccessful();

    expect(User::query()->where('email', 'admin@example.com')->exists())->toBeFalse();

    $defaultLogin = test()->post('/admin/login', [
        'email' => 'admin@example.com',
        'password' => $defaultPassword,
    ]);

    expect($defaultLogin->headers->get('Location') ?? '')->not->toContain('/admin/dashboard');
    $defaultLogin->assertRedirect();

    test()->post('/admin/login', [
        'email' => $operatorEmail,
        'password' => $operatorPassword,
    ])->assertRedirect(route('admin.dashboard.index'));

    test()->get(route('admin.dashboard.index'))->assertOk();
});

it('rejects using the installer default address as ADMIN_EMAIL', function () {
    $role = makeRotateAdminRole();

    seedDefaultAdmin($role, 'seeded-login');

    setOperatorEnv('admin@example.com', 'should-not-apply');

    test()->artisan('krayin:rotate-admin')->assertFailed();

    expect(User::query()->where('email', 'admin@example.com')->exists())->toBeTrue();
});
