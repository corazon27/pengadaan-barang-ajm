<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Rfq;
use App\Models\User;
use App\Notifications\RfqSubmittedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationTest extends TestCase
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
                'action_url' => '/api/v1/rfqs/1',
            ],
            'read_at' => null,
        ], $overrides));
    }

    public function test_user_lists_own_notifications_newest_first_with_read_indicator(): void
    {
        $user = User::factory()->buyerB2b()->create();

        $old = $this->createNotification($user, ['created_at' => now()->subDays(2)]);
        $new = $this->createNotification($user, ['created_at' => now()->subDay()]);
        $read = $this->createNotification($user, [
            'created_at' => now()->subHours(2),
            'read_at' => now()->subHour(),
        ]);

        $this->actingAs($user);

        $response = $this->getJson('/api/v1/notifications');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'data.items')
            ->assertJsonPath('data.items.0.id', $read->id)
            ->assertJsonPath('data.items.1.id', $new->id)
            ->assertJsonPath('data.items.2.id', $old->id)
            ->assertJsonPath('data.items.0.is_read', true)
            ->assertJsonPath('data.items.0.read_at', $read->read_at->toISOString())
            ->assertJsonPath('data.items.1.is_read', false)
            ->assertJsonPath('data.items.1.read_at', null)
            ->assertJsonStructure([
                'data' => [
                    'items' => [
                        '*' => [
                            'id',
                            'type',
                            'title',
                            'message',
                            'action_url',
                            'read_at',
                            'is_read',
                            'created_at',
                        ],
                    ],
                ],
            ]);
    }

    public function test_notifications_list_is_paginated(): void
    {
        $user = User::factory()->buyerB2b()->create();

        for ($i = 0; $i < 20; $i++) {
            $this->createNotification($user);
        }

        $this->actingAs($user);

        $response = $this->getJson('/api/v1/notifications?per_page=5');

        $response->assertOk()
            ->assertJsonCount(5, 'data.items')
            ->assertJsonPath('data.pagination.total', 20)
            ->assertJsonPath('data.pagination.per_page', 5)
            ->assertJsonPath('data.pagination.current_page', 1)
            ->assertJsonPath('data.pagination.last_page', 4);
    }

    public function test_user_marks_single_notification_as_read(): void
    {
        $user = User::factory()->buyerB2b()->create();
        $notification = $this->createNotification($user);

        $this->actingAs($user);

        $response = $this->patchJson("/api/v1/notifications/{$notification->id}/read");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_read', true);

        $this->assertNotNull($notification->refresh()->read_at);
    }

    public function test_user_marks_all_notifications_as_read(): void
    {
        $user = User::factory()->buyerB2b()->create();
        $this->createNotification($user);
        $this->createNotification($user);
        $this->createNotification($user, ['read_at' => now()]);

        $this->actingAs($user);

        $response = $this->postJson('/api/v1/notifications/read-all');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.marked_as_read', 2);

        $this->assertSame(3, $user->notifications()->whereNotNull('read_at')->count());
        $this->assertSame(0, $user->unreadNotifications()->count());
    }

    public function test_user_cannot_mark_other_users_notification_as_read(): void
    {
        $owner = User::factory()->buyerB2b()->create();
        $other = User::factory()->buyerB2b()->create();
        $notification = $this->createNotification($owner);

        $this->actingAs($other);

        $this->patchJson("/api/v1/notifications/{$notification->id}/read")
            ->assertForbidden();

        $this->assertNull($notification->refresh()->read_at);
    }

    public function test_unauthenticated_user_cannot_access_notifications(): void
    {
        $this->getJson('/api/v1/notifications')->assertUnauthorized();
        $this->postJson('/api/v1/notifications/read-all')->assertUnauthorized();
    }

    public function test_dispatching_notification_creates_database_row(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $buyer = User::factory()->buyerB2b()->create();
        $rfq = Rfq::factory()->create(['user_id' => $buyer->id]);

        NotificationFacade::send($superadmin, new RfqSubmittedNotification($rfq));

        $this->assertDatabaseHas('notifications', [
            'type' => RfqSubmittedNotification::class,
            'notifiable_id' => $superadmin->id,
            'notifiable_type' => User::class,
        ]);

        $this->actingAs($superadmin);

        $this->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.type', class_basename(RfqSubmittedNotification::class))
            ->assertJsonPath('data.items.0.is_read', false);
    }

    public function test_buyer_receives_only_own_notifications(): void
    {
        $buyerA = User::factory()->buyerB2b()->create();
        $buyerB = User::factory()->buyerB2b()->create();

        $notificationA = $this->createNotification($buyerA);
        $this->createNotification($buyerB);

        $this->actingAs($buyerA);

        $response = $this->getJson('/api/v1/notifications');

        $response->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $notificationA->id);
    }
}
