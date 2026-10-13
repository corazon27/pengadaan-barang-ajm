<?php

declare(strict_types=1);

namespace Tests\Feature\Middleware;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountActiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_token_can_access_protected_api(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/auth/me')->assertOk();
    }

    public function test_non_active_token_is_denied(): void
    {
        foreach (['pending', 'rejected', 'suspended'] as $state) {
            $user = User::factory()->{$state}()->create();
            Sanctum::actingAs($user);

            $this->getJson('/api/v1/auth/me')->assertStatus(403);
        }
    }

    public function test_non_active_web_user_is_redirected_to_login(): void
    {
        $user = User::factory()->pending()->create();

        $this->actingAs($user)->get('/akun')->assertRedirect(route('login'));
    }

    public function test_suspended_user_with_remember_token_is_blocked(): void
    {
        $user = User::factory()->suspended()->create(['remember_token' => 'legacy-token']);

        $this->actingAs($user)->get('/akun')->assertRedirect(route('login'));
    }
}
