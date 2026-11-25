<?php

use App\Models\Household;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('identity uses the cashflow navigation only when the admin has an active household', function () {
    $admin = User::factory()->create();
    $this->artisan('cashflow:admin', ['email' => $admin->email])->assertSuccessful();
    $this->actingAs($admin->refresh())->get(route('identity.index'))
        ->assertOk()->assertDontSee('aria-label="Navigasi utama"', false);

    $household = Household::create(['name' => 'Rumah admin']);
    $household->memberships()->create(['user_id' => $admin->id, 'role' => 'owner']);
    $this->get(route('identity.index'))->assertOk()
        ->assertSee('aria-label="Navigasi utama"', false)
        ->assertSee('class="hidden xl:contents"', false)
        ->assertSee('href="/identitas"  aria-current="page"', false);
});

test('artisan grants identity only and the web form validates, renders and restores branding', function () {
    Storage::fake('local');
    $admin = User::factory()->create();
    $house = Household::create(['name' => 'Rumah privat']);
    $owner = User::factory()->create();
    $house->memberships()->create(['user_id' => $owner->id, 'role' => 'owner']);

    $this->get(route('identity.index'))->assertRedirect(route('login'));
    $this->get(route('login'))->assertOk()->assertSee('logo-icon.svg')->assertSee('favicon.ico');
    $this->actingAs($owner)->get(route('identity.index'))->assertForbidden();
    $this->post(route('identity.update'), ['app_name' => 'Tidak boleh'])->assertForbidden();
    $this->artisan('cashflow:admin', ['email' => $admin->email])->assertSuccessful();
    $this->actingAs($admin->refresh())->get(route('identity.index'))->assertOk()->assertSee('Pulihkan aset bawaan');
    $this->get(route('households.settings', $house))->assertRedirect(route('households.onboarding'));

    $this->post(route('identity.update'), [
        'app_name' => 'Tidak valid', 'logo' => UploadedFile::fake()->createWithContent('logo.png', 'teks'),
    ])->assertSessionHasErrors('logo');
    $this->post(route('identity.update'), [
        'app_name' => 'Tidak valid', 'favicon' => UploadedFile::fake()->image('icon.webp'),
    ])->assertSessionHasErrors('favicon');

    $this->post(route('identity.update'), [
        'app_name' => 'Catatan Kami',
        'logo' => UploadedFile::fake()->image('logo.webp', 32, 32),
        'favicon' => UploadedFile::fake()->image('icon.png', 32, 32),
    ])->assertRedirect(route('identity.index'))->assertSessionHasNoErrors();
    $settings = DB::table('system_settings')->find(1);
    expect($settings->app_name)->toBe('Catatan Kami');
    Storage::disk('local')->assertExists([$settings->logo_path, $settings->favicon_path]);
    $logo = route('identity.asset', ['logo', basename($settings->logo_path)]);
    $this->get($logo)->assertOk()->assertHeader('Content-Type', 'image/webp');
    $this->get(route('identity.index'))->assertOk()->assertSee('Pengaturan identitas | Catatan Kami')->assertSee(basename($settings->favicon_path));

    $this->post(route('identity.update'), [
        'app_name' => 'Terlalu besar', 'logo' => UploadedFile::fake()->image('big.png')->size(1025),
    ])->assertSessionHasErrors('logo');
    $this->assertDatabaseHas('system_settings', ['id' => 1, 'app_name' => 'Catatan Kami']);

    if (DB::getDriverName() === 'sqlite') {
        DB::unprepared("CREATE TEMP TRIGGER reject_brand_update BEFORE UPDATE ON system_settings
            BEGIN SELECT RAISE(ABORT, 'Simulated branding write failure'); END");
        try {
            $this->post(route('identity.update'), [
                'app_name' => 'Gagal diganti', 'logo' => UploadedFile::fake()->image('new.png'),
            ])->assertSessionHasErrors('app_name');
        } finally {
            DB::unprepared('DROP TRIGGER reject_brand_update');
        }

        $this->assertDatabaseHas('system_settings', ['id' => 1, 'logo_path' => $settings->logo_path]);
        Storage::disk('local')->assertExists([$settings->logo_path, $settings->favicon_path]);
        expect(Storage::disk('local')->allFiles('branding'))->toHaveCount(2);
    }

    $this->post(route('identity.update'), [
        'app_name' => 'Catatan Kami', 'reset_name' => 1, 'reset_logo' => 1, 'reset_favicon' => 1,
    ])->assertSessionHasNoErrors();
    Storage::disk('local')->assertMissing([$settings->logo_path, $settings->favicon_path]);
    $this->get($logo)->assertNotFound();
    $this->get(route('identity.index'))->assertOk()->assertSee('Cashflow Rumah Tangga Muslim')->assertSee('favicon.ico');
    $this->artisan('cashflow:admin', ['email' => $admin->email, '--revoke' => true])->assertSuccessful();
    $this->actingAs($admin->refresh())->post(route('identity.update'), ['app_name' => 'Dicabut'])->assertForbidden();
});
