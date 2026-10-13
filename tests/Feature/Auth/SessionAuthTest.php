<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_user_can_login(): void
    {
        $user = User::factory()->create(['email' => 'active@example.com']);

        $this->post('/masuk', [
            'email' => 'active@example.com',
            'password' => 'password123',
        ])->assertRedirect(route('account.home'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_non_active_accounts_cannot_establish_a_session(): void
    {
        foreach ([UserStatus::PENDING, UserStatus::REJECTED, UserStatus::SUSPENDED] as $status) {
            $user = User::factory()->create(['email' => 'blocked-'.$status->value.'@example.com']);
            $user->forceFill(['status' => $status])->save();

            $this->from('/masuk')
                ->post('/masuk', ['email' => $user->email, 'password' => 'password123'])
                ->assertRedirect('/masuk')
                ->assertSessionHasErrors('email');

            $this->assertGuest();
        }
    }

    public function test_wrong_password_is_rejected(): void
    {
        User::factory()->create(['email' => 'active@example.com']);

        $this->from('/masuk')
            ->post('/masuk', ['email' => 'active@example.com', 'password' => 'wrong-password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_guest_cannot_reach_the_account_area(): void
    {
        $this->get('/akun')->assertRedirect(route('login'));
    }

    public function test_user_can_logout(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $this->actingAs($user)->post('/keluar')->assertRedirect('/');

        $this->assertGuest();
    }
}
