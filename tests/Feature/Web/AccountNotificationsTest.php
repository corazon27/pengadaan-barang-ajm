<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\User;
use App\Notifications\RfqSubmittedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AccountNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private function createNotification(User $user, array $overrides = []): mixed
    {
        return $user->notifications()->create(array_merge([
            'id' => (string) Str::uuid(),
            'type' => RfqSubmittedNotification::class,
            'data' => [
                'title' => 'RFQ Baru Diajukan',
                'message' => 'Sebuah RFQ baru menunggu penawaran harga.',
                'action_url' => '/akun/rfq',
            ],
            'read_at' => null,
        ], $overrides));
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/akun/notifikasi')->assertRedirect(route('login'));
    }

    public function test_index_lists_only_own_notifications_with_safe_fields(): void
    {
        $user = User::factory()->buyerB2b()->create();
        $other = User::factory()->buyerB2b()->create();

        $this->createNotification($user, ['created_at' => now()->subDay()]);
        $this->createNotification($user, [
            'created_at' => now(),
            'read_at' => now(),
        ]);
        $this->createNotification($other);

        $this->actingAs($user)
            ->get('/akun/notifikasi')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Account/Notifications/Index')
                ->has('notifications', 2)
                ->where('pagination.total', 2)
                ->where('notifications.0.title', 'RFQ Baru Diajukan')
                ->where('notifications.0.type', 'RfqSubmittedNotification')
                ->where('notifications.0.is_read', true)
                ->missing('notifications.0.data'));
    }
}
