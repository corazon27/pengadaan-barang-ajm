<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class UserSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_demo_users_outside_production(): void
    {
        (new UserSeeder)->run();

        $this->assertDatabaseHas('users', ['email' => 'admin@ajm.co.id']);
        $this->assertDatabaseHas('users', ['email' => 'buyer.b2b@corporation.com']);
        $this->assertDatabaseHas('users', ['email' => 'ppk.b2g@dinas.go.id']);
    }

    public function test_seeder_fails_closed_in_production_without_demo_users_enabled(): void
    {
        $this->app->instance('env', 'production');
        config(['app.demo.seed_users' => false]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('SEED_DEMO_USERS');

        (new UserSeeder)->run();
    }

    public function test_seeder_fails_closed_in_production_when_password_missing(): void
    {
        $this->app->instance('env', 'production');
        config(['app.demo.seed_users' => true]);
        config([
            'app.demo.admin_password' => null,
            'app.demo.buyer_b2b_password' => null,
            'app.demo.buyer_b2g_password' => null,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('SEED_ADMIN_PASSWORD');

        (new UserSeeder)->run();
    }

    public function test_seeder_uses_environment_passwords_in_production_when_all_supplied(): void
    {
        $this->app->instance('env', 'production');
        config(['app.demo.seed_users' => true]);
        config([
            'app.demo.admin_password' => 'Very-Strong-Admin-1',
            'app.demo.buyer_b2b_password' => 'Very-Strong-B2B-2',
            'app.demo.buyer_b2g_password' => 'Very-Strong-B2G-3',
        ]);

        (new UserSeeder)->run();

        $this->assertDatabaseHas('users', ['email' => 'admin@ajm.co.id']);
        $this->assertTrue(Hash::check('Very-Strong-Admin-1', User::where('email', 'admin@ajm.co.id')->first()->password));
    }
}
