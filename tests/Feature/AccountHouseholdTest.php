<?php

use App\Models\Household;
use App\Models\HouseholdJoinCode;
use App\Models\HouseholdMembership;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
});

function createHouseholdOwner(): array
{
    $owner = User::factory()->create();
    $household = Household::create(['name' => 'Rumah Uji']);
    $household->memberships()->create(['user_id' => $owner->id, 'role' => HouseholdMembership::OWNER]);

    return [$owner, $household];
}

function issueTestJoinCode($test, User $owner, Household $household): string
{
    $test->actingAs($owner)->from(route('households.settings', $household))->post(route('households.codes.store', $household))
        ->assertRedirect(route('households.settings', $household))->assertSessionHasNoErrors();

    return decrypt(session('join_code'));
}

test('guests can register, create a household, log out, and log back in', function () {
    $this->get('/')->assertRedirect(route('login'));
    $this->get(route('signup'))->assertOk();
    $this->post(route('signup'), [
        'name' => 'Pemilik', 'email' => 'Owner@example.com',
        'password' => 'password123', 'password_confirmation' => 'password123',
    ])->assertRedirect(route('households.onboarding'));
    $this->assertAuthenticated();
    $this->assertDatabaseHas('users', ['email' => 'owner@example.com', 'email_verified_at' => null]);
    $this->get('/')->assertRedirect(route('households.onboarding'));
    $this->get(route('households.onboarding'))->assertOk();
    $this->post(route('households.store'), ['name' => 'Rumah Baru'])->assertRedirect('/');
    $this->get('/')->assertOk()->assertSee('Rumah Baru');
    $this->post(route('households.store'), ['name' => 'Rumah Kedua'])->assertSessionHasErrors('household');
    $this->assertDatabaseCount('households', 1);
    $this->post(route('logout'))->assertRedirect(route('login'));
    $this->assertGuest();
    $this->post(route('login'), ['email' => 'Owner@example.com', 'password' => 'password123'])->assertRedirect('/');
    $this->assertAuthenticated();
});

test('password broker sends a reset link and accepts its token only once', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->get(route('password.request'))->assertOk();
    $this->post(route('password.email'), ['email' => $user->email])->assertSessionHas('status');
    $notification = Notification::sent($user, ResetPassword::class)->sole();
    $this->get(route('password.reset', ['token' => $notification->token, 'email' => $user->email]))->assertOk();
    $data = ['email' => $user->email, 'token' => $notification->token, 'password' => 'newpassword123', 'password_confirmation' => 'newpassword123'];
    $this->post(route('password.update'), $data)->assertRedirect(route('login'));
    $this->post(route('password.update'), $data)->assertSessionHasErrors('email');
    $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
    $this->post(route('login'), ['email' => $user->email, 'password' => 'newpassword123'])->assertRedirect();
    $this->assertAuthenticatedAs($user);
});

test('household routes reject another household and partner cannot manage access', function () {
    [$owner, $household] = createHouseholdOwner();
    [$outsider, $other] = createHouseholdOwner();
    $partner = User::factory()->create();
    $code = issueTestJoinCode($this, $owner, $household);

    $this->get(route('households.settings', $household))->assertOk()->assertSee($code);
    $this->get(route('households.settings', $household))->assertOk()->assertDontSee($code);
    $this->actingAs($outsider)->get(route('households.settings', $household))->assertNotFound();
    $this->post(route('households.codes.store', $household))->assertNotFound();
    $this->post(route('households.join'), ['code' => $code])->assertSessionHasErrors('household');

    $this->actingAs($partner)->post(route('households.join'), ['code' => $code])->assertRedirect('/');
    $this->get(route('households.settings', $other))->assertNotFound();
    $this->get(route('households.settings', $household))->assertOk();
    $this->post(route('households.codes.store', $household))->assertForbidden();
    $this->delete(route('households.codes.destroy', $household))->assertForbidden();
    $membership = $partner->activeMembership()->firstOrFail();
    $this->delete(route('households.partner.destroy', [$household, $membership]))->assertForbidden();

    $this->actingAs($owner)->delete(route('households.leave', $household))->assertForbidden();
    $this->post(route('households.codes.store', $household))->assertSessionHasErrors('household');
    $this->delete(route('households.partner.destroy', [$household, $membership]))->assertSessionHas('status');

    // A still-authenticated partner loses access on the next request.
    $this->actingAs($partner)->get(route('households.settings', $household))->assertRedirect(route('households.onboarding'));
    $this->assertAuthenticatedAs($partner);
    expect($membership->fresh()->ended_at)->not->toBeNull();
});

test('replaced, revoked, expired, and spent codes cannot be redeemed', function () {
    [$owner, $household] = createHouseholdOwner();
    $partner = User::factory()->create();

    $replaced = issueTestJoinCode($this, $owner, $household);
    $revoked = issueTestJoinCode($this, $owner, $household);
    $this->delete(route('households.codes.destroy', $household));
    $expired = issueTestJoinCode($this, $owner, $household);
    $this->travel(24)->hours();
    $valid = issueTestJoinCode($this, $owner, $household);

    $this->assertDatabaseHas('household_join_codes', ['code_hash' => hash('sha256', $valid)]);
    $this->assertDatabaseMissing('household_join_codes', ['code_hash' => $valid]);
    $this->actingAs($partner);
    foreach ([$replaced, $revoked, $expired] as $invalid) {
        $this->post(route('households.join'), ['code' => $invalid])->assertSessionHasErrors('code')
            ->assertSessionMissing('_old_input.code');
    }

    $this->post(route('households.join'), ['code' => strtoupper($valid)])->assertRedirect('/');
    $this->delete(route('households.leave', $household))->assertRedirect(route('households.onboarding'));
    $this->post(route('households.join'), ['code' => $valid])->assertSessionHasErrors('code');
    expect(HouseholdJoinCode::where('code_hash', hash('sha256', $valid))->firstOrFail()->used_at)->not->toBeNull();
    $this->assertDatabaseCount('household_memberships', 2);
});

test('join attempts are throttled', function () {
    $this->actingAs(User::factory()->create());
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->post(route('households.join'), ['code' => str_repeat('a', 32)])->assertSessionHasErrors('code');
    }

    $this->post(route('households.join'), ['code' => str_repeat('a', 32)])->assertTooManyRequests();
});

test('guest throttling ignores client-supplied forwarding headers by default', function () {
    $attempt = ['email' => 'nobody@example.com', 'password' => 'password123'];
    for ($i = 1; $i <= 8; $i++) {
        $this->post(route('login'), $attempt, ['X-Forwarded-For' => "192.0.2.$i"])->assertSessionHasErrors('email');
    }
    $this->post(route('login'), $attempt, ['X-Forwarded-For' => '192.0.2.200'])->assertTooManyRequests();
});

test('revocation blocks a partner whose login session is still valid', function () {
    [$owner, $household] = createHouseholdOwner();
    $partner = User::factory()->create();
    $membership = $household->memberships()->create([
        'user_id' => $partner->id,
        'role' => HouseholdMembership::PARTNER,
    ]);

    $this->post(route('login'), ['email' => $partner->email, 'password' => 'password'])->assertRedirect('/');
    $this->get(route('households.settings', $household))->assertOk();
    $sessionKey = Auth::guard()->getName();
    $this->assertSame($partner->id, session($sessionKey));

    $this->actingAs($owner)->delete(route('households.partner.destroy', [$household, $membership]))->assertSessionHas('status');

    // Reload the partner from their original authenticated session.
    Auth::forgetGuards();
    $this->get(route('households.settings', $household))->assertRedirect(route('households.onboarding'));
    $this->assertAuthenticatedAs($partner);
    $this->assertSame($partner->id, session($sessionKey));
});
