<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Concurrency;

use App\Enums\ConsentStatus;
use App\Enums\StatutoryTimerStatus;
use App\Enums\StatutoryTimerType;
use App\Models\ConsentRecord;
use App\Models\User;
use App\Services\ConsentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ConsentWithdrawalConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected function connectionsToTransact(): array
    {
        return [];
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (Config::get('database.default') !== 'mysql') {
            $this->markTestSkipped('Requires MySQL for lockForUpdate concurrency testing');
        }
    }

    private function createActiveConsent(): ConsentRecord
    {
        $user = User::factory()->create();

        return ConsentRecord::create([
            'subject_user_id' => $user->id,
            'subject_type' => 'USER',
            'purpose' => 'marketing',
            'processing_lawful_basis' => 'CONSENT',
            'notice_version' => 'v1.0',
            'document_ref' => 'policy_2026',
            'source_channel' => 'WEB',
            'granted_at' => now(),
            'consent_status' => ConsentStatus::ACTIVE,
        ]);
    }

    private function createRawPdo(): \PDO
    {
        $config = Config::get('database.connections.mysql');

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $config['host'],
            $config['port'],
            $config['database'],
        );

        $pdo = new \PDO($dsn, $config['username'], $config['password'], [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        ]);

        $pdo->exec('SET SESSION innodb_lock_wait_timeout = 2');

        return $pdo;
    }

    private function destroyPdo(?\PDO &$pdo): void
    {
        if ($pdo === null) {
            return;
        }

        try {
            $pdo->exec('ROLLBACK');
        } catch (\PDOException $e) {
            // Connection may be in a broken state after lock timeout.
        }

        $pdo = null;
    }

    private function deleteFixture(string $userId, string $recordId): void
    {
        $timerIds = DB::connection('mysql')->table('statutory_timers')
            ->where('ref_type', ConsentRecord::class)
            ->where('ref_id', $recordId)
            ->pluck('id');

        DB::connection('mysql')->table('statutory_timers')
            ->where('ref_type', ConsentRecord::class)
            ->where('ref_id', $recordId)
            ->delete();
        DB::connection('mysql')->table('audit_logs')
            ->where('entity_type', 'ConsentRecord')
            ->where('entity_id', $recordId)
            ->delete();
        DB::connection('mysql')->table('audit_logs')
            ->where('entity_type', 'StatutoryTimer')
            ->whereIn('entity_id', $timerIds)
            ->delete();
        DB::connection('mysql')->table('audit_logs')
            ->where('user_id', $userId)
            ->delete();
        DB::connection('mysql')->table('consent_records')
            ->where('id', $recordId)
            ->delete();
        DB::connection('mysql')->table('users')
            ->where('id', $userId)
            ->delete();
    }

    public function test_mysql_concurrent_withdrawal_race(): void
    {
        $record = $this->createActiveConsent();
        $user = User::find($record->subject_user_id);
        $userId = $user->id;
        $recordId = $record->id;

        try {
            $service = app(ConsentService::class);

            // Phase 1+2: Prove lockForUpdate blocks with two independent MySQL sessions
            $pdo1 = $this->createRawPdo();
            $pdo1->exec('BEGIN');
            $stmt1 = $pdo1->prepare('SELECT * FROM consent_records WHERE id = ? FOR UPDATE');
            $stmt1->execute([$record->id]);

            $pdo2 = $this->createRawPdo();
            $pdo2->exec('BEGIN');
            $lockBlocked = false;
            try {
                $stmt2 = $pdo2->prepare('SELECT * FROM consent_records WHERE id = ? FOR UPDATE');
                $stmt2->execute([$record->id]);
            } catch (\PDOException $e) {
                $lockBlocked = true;
                $this->assertStringContainsString('Lock wait timeout exceeded', $e->getMessage());
            }
            $this->assertTrue($lockBlocked, 'TX2 must be blocked by TX1 lock');

            // Destroy both raw PDO connections — rollback and close to release all locks
            $this->destroyPdo($pdo1);
            $this->destroyPdo($pdo2);

            // Phase 4: Production service — first withdrawal succeeds
            // (no Event::fake — listener runs on sync queue, creating the timer)
            $result = $service->withdraw($record->fresh(), $user);
            $this->assertSame(ConsentStatus::WITHDRAWN, $result->consent_status);

            // Phase 5: Production service — second withdrawal rejected via state guard
            try {
                $service->withdraw($record->fresh(), $user);
                $this->fail('Expected InvalidArgumentException');
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString('WITHDRAWN', $e->getMessage());
            }

            // Phase 6: Fresh connection verifies final WITHDRAWN state
            $pdo3 = $this->createRawPdo();
            $pdo3->exec('BEGIN');
            $stmt3 = $pdo3->prepare('SELECT consent_status FROM consent_records WHERE id = ? FOR UPDATE');
            $stmt3->execute([$record->id]);
            $row = $stmt3->fetch(\PDO::FETCH_ASSOC);
            $pdo3->exec('COMMIT');
            $pdo3 = null;

            $this->assertSame('WITHDRAWN', $row['consent_status']);

            // Final assertions: exactly one RUNNING timer (proves exactly one event dispatched),
            // final status = WITHDRAWN, second attempt rejected (proven above)
            $this->assertDatabaseCount('statutory_timers', 1);
            $this->assertDatabaseHas('statutory_timers', [
                'timer_type' => StatutoryTimerType::CONSENT_WITHDRAWAL->value,
                'ref_type' => ConsentRecord::class,
                'ref_id' => $record->id,
                'status' => StatutoryTimerStatus::RUNNING->value,
            ]);
            $this->assertDatabaseHas('consent_records', [
                'id' => $record->id,
                'consent_status' => ConsentStatus::WITHDRAWN,
            ]);
        } finally {
            $this->deleteFixture($userId, $recordId);
        }
    }
}
