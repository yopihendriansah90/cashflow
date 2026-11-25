<?php

use App\Models\Household;
use App\Models\HouseholdJoinCode;
use App\Models\HouseholdMembership;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

beforeEach(function () {
    if (getenv('CASHFLOW_VERIFY_MYSQL') !== '1') {
        $this->markTestSkipped('Dedicated MySQL test schema required.');
    }

    $this->assertTrue(app()->environment('testing'));
    $this->assertSame('mysql', DB::getDriverName());
    $this->assertMatchesRegularExpression('/\Acashflow_stage1_test_[a-z0-9_]+\z/', DB::connection()->getDatabaseName());
    $this->artisan('migrate')->assertSuccessful();
});

test('database enforces active membership uniqueness and preserves history', function () {
    DB::beginTransaction();

    try {
        $owner = User::factory()->create();
        $partner = User::factory()->create();
        $otherUser = User::factory()->create();
        $household = Household::create(['name' => 'Batas anggota']);
        $other = Household::create(['name' => 'Rumah lain']);
        $household->memberships()->create(['user_id' => $owner->id, 'role' => HouseholdMembership::OWNER]);
        $membership = $household->memberships()->create(['user_id' => $partner->id, 'role' => HouseholdMembership::PARTNER]);

        foreach ([
            [$other, $partner, HouseholdMembership::OWNER],
            [$household, $otherUser, HouseholdMembership::OWNER],
            [$household, $otherUser, HouseholdMembership::PARTNER],
        ] as [$target, $user, $role]) {
            try {
                $target->memberships()->create(['user_id' => $user->id, 'role' => $role]);
                $this->fail('Expected duplicate active membership to fail.');
            } catch (QueryException $exception) {
                $this->assertSame(1062, $exception->errorInfo[1]);
            }
        }

        $membership->update(['ended_at' => now()]);
        $other->memberships()->create(['user_id' => $partner->id, 'role' => HouseholdMembership::OWNER]);
        $household->memberships()->create(['user_id' => $otherUser->id, 'role' => HouseholdMembership::PARTNER]);
        try {
            $partner->delete();
            $this->fail('Expected membership history to prevent user deletion.');
        } catch (QueryException $exception) {
            $this->assertSame(1451, $exception->errorInfo[1]);
        }
    } finally {
        DB::rollBack();
    }
});

test('two concurrent redemptions create exactly one partner', function () {
    $owner = User::factory()->create();
    $users = User::factory()->count(2)->create();
    $household = Household::create(['name' => 'Uji penukaran serentak']);
    $membership = $household->memberships()->create(['user_id' => $owner->id, 'role' => HouseholdMembership::OWNER]);
    $code = bin2hex(random_bytes(16));
    $joinCode = $household->joinCodes()->create([
        'created_by_membership_id' => $membership->id,
        'code_hash' => hash('sha256', $code),
        'expires_at' => now()->addDay(),
    ]);

    $processes = $users->map(fn (User $user) => new Process(
        [PHP_BINARY, base_path('tests/Support/redeem-household-code.php'), (string) $user->id],
        base_path(),
        ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => DB::connection()->getDatabaseName(), 'SESSION_DRIVER' => 'array'],
        $code,
        30,
    ));

    try {
        DB::beginTransaction();
        Household::lockForUpdate()->findOrFail($household->id);

        foreach ($processes as $process) {
            $process->start();
        }

        foreach ($processes as $process) {
            $this->assertTrue($process->waitUntil(fn ($type, $output) => str_contains($output, 'ready')), $process->getErrorOutput());
        }

        DB::commit();

        $results = [];
        foreach ($processes as $process) {
            $this->assertSame(0, $process->wait(), $process->getErrorOutput());
            $results[] = trim(str_replace('ready', '', $process->getOutput()));
        }

        $this->assertEqualsCanonicalizing(['joined', 'rejected'], $results);
        $this->assertSame(1, $household->memberships()->where('active_role', HouseholdMembership::PARTNER)->count());
        $this->assertNotNull($joinCode->fresh()->used_at);
    } finally {
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }

        foreach ($processes as $process) {
            $process->stop();
        }

        HouseholdJoinCode::whereKey($joinCode->id)->delete();
        $household->memberships()->delete();
        $household->delete();
        User::whereKey([$owner->id, ...$users->modelKeys()])->delete();
    }
});
