<?php

use App\Models\Household;
use App\Models\User;
use App\Support\Ledger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('wallet icon uses the wallet type when a provider image is unavailable', function () {
    config(['wallet-brands.bank.provider-without-image' => ['name' => 'Penyedia Contoh']]);
    $html = Blade::render('<x-main.cashflow.wallet-brand type="bank" brand-key="provider-without-image" />');

    expect($html)->toContain('data-wallet-type-mark="bank"', 'Penyedia Contoh')
        ->not->toContain('<img');
});

test('each wallet brand has a local logo with consistent dimensions', function () {
    foreach (config('wallet-brands') as $brands) {
        foreach ($brands as $key => $brand) {
            $file = public_path("assets/images/wallet-brands/{$key}.png");
            $this->assertFileExists($file, $brand['name']);
            $image = getimagesize($file);
            expect([$image[0], $image[1], $image['mime']])->toBe([192, 96, 'image/png']);
            expect($brand['asset_source'])->toStartWith('https://');
        }
    }
});

test('wallet logos are optional and restricted to the selected wallet type', function () {
    $house = Household::create(['name' => 'Rumah']);
    $user = User::factory()->create();
    $house->memberships()->create(['user_id' => $user->id, 'role' => 'owner']);
    $this->actingAs($user);

    $data = ['name' => 'Tabungan', 'type' => 'bank', 'opened_on' => '2025-01-01', 'opening_balance' => 1250000];
    $url = route('ledger.save', 'wallets');
    foreach (['ovo', 'unknown'] as $invalid) {
        $this->post($url, ['brand_key' => $invalid] + $data)->assertSessionHasErrors('brand_key');
    }
    $this->post($url, ['brand_key' => 'bri'] + $data)->assertSessionHasNoErrors();
    $wallet = DB::table('wallets')->where('name', 'Tabungan')->first();
    expect($wallet->brand_key)->toBe('bri');
    $this->get(route('ledger.index', 'wallets'))->assertOk()->assertSee('Rp1.250.000')->assertSee('BRI');
    $this->get(route('ledger.quick-transaction'))->assertOk()->assertSee('Tabungan · BRI');

    $edit = route('ledger.save', ['wallets', $wallet->id]);
    $this->post($edit, ['type' => 'ewallet', 'brand_key' => 'ovo'] + $data)->assertSessionHasNoErrors();
    $this->assertDatabaseHas('wallets', ['id' => $wallet->id, 'brand_key' => 'ovo', 'opening_balance' => 1250000]);
    $this->post($edit, ['type' => 'ewallet', 'brand_key' => ''] + $data)->assertSessionHasNoErrors();
    $this->assertDatabaseHas('wallets', ['id' => $wallet->id, 'brand_key' => null]);
});

test('quick transaction form is scoped to the household and supports JSON validation and saving', function () {
    $house = Household::create(['name' => 'Rumah']);
    $user = User::factory()->create();
    $member = $house->memberships()->create(['user_id' => $user->id, 'role' => 'owner']);
    $quick = route('ledger.quick-transaction');
    $this->get($quick)->assertRedirect(route('login'));
    $this->actingAs($user)->get($quick)->assertStatus(409)
        ->assertJsonPath('walletUrl', route('ledger.index', ['section' => 'wallets', 'baru' => 1]));

    $this->post(route('ledger.save', 'wallets'), [
        'name' => 'Bank', 'type' => 'bank', 'opened_on' => '2025-01-01', 'opening_balance' => '-100.000',
    ])->assertSessionHasNoErrors();
    $this->get($quick)->assertOk()->assertSee('data-quick-transaction-dialog', false)
        ->assertSee('id="quick-occurred_on"', false)->assertDontSee('Navigasi utama');
    $this->get(route('reports.index'))->assertOk()->assertSee($quick);
    $url = route('ledger.save', 'transactions');
    $this->postJson($url, [])->assertUnprocessable()->assertJsonValidationErrors(['type', 'occurred_on', 'amount']);
    $income = [
        'type' => 'income', 'occurred_on' => '2025-01-15', 'amount' => '25.000', 'fee_amount' => null,
        'destination_wallet_id' => Ledger::scoped('wallets', $member)->value('id'),
        'category_id' => Ledger::categories($member)->where('type', 'income')->value('id'),
    ];
    foreach (['12.34', '1,250', 'Rp1.250', '9.223.372.036.854.775.808'] as $invalid) {
        $this->postJson($url, ['amount' => $invalid] + $income)
            ->assertUnprocessable()->assertJsonValidationErrors('amount');
    }
    $this->postJson($url, $income)->assertCreated()->assertJsonPath('saved', true)->assertSessionHas('status');
    $this->assertDatabaseHas('wallets', ['opening_balance' => -100000]);
    $this->assertDatabaseHas('transactions', ['amount' => 25000, 'fee_amount' => 0]);
    $this->get(route('ledger.audit'))->assertSee('Rp25.000');
    $this->assertDatabaseCount('transaction_revisions', 1);
});

test('confirmed early transfer moves both wallet openings and recalculates historical balances', function () {
    $house = Household::create(['name' => 'Rumah']);
    $user = User::factory()->create();
    $member = $house->memberships()->create(['user_id' => $user->id, 'role' => 'owner']);
    $this->actingAs($user);
    foreach (['Bank', 'Kas'] as $name) {
        $this->post(route('ledger.save', 'wallets'), [
            'name' => $name, 'type' => 'bank', 'opened_on' => '2025-02-01', 'opening_balance' => 100000,
        ])->assertSessionHasNoErrors();
    }
    [$source, $destination] = Ledger::scoped('wallets', $member)->orderBy('id')->pluck('id')->all();
    $url = route('ledger.save', 'transactions');
    $data = ['type' => 'transfer', 'occurred_on' => '2025-01-15', 'amount' => 25000,
        'fee_amount' => 2500, 'source_wallet_id' => $source, 'destination_wallet_id' => $destination];

    $this->post($url, $data)->assertSessionHasErrors('occurred_on')->assertSessionHasInput('occurred_on', '2025-01-15');
    $this->assertDatabaseCount('transactions', 0);
    expect(Ledger::balances($member, '2025-01-31'))->toBe([]);

    $this->post($url, $data + ['confirm_early_transaction' => '1'])->assertSessionHasNoErrors();
    expect(Ledger::scoped('wallets', $member)->orderBy('id')->pluck('opened_on')->all())->toBe(['2025-01-15', '2025-01-15']);
    expect(Ledger::balances($member, '2025-01-14'))->toBe([]);
    expect(Ledger::balances($member, '2025-01-31'))->toBe([$source => '72500', $destination => '125000']);
    $this->get(route('ledger.index', ['section' => 'transactions', 'bulan' => '2025-02']))
        ->assertOk()->assertSeeInOrder(['Saldo bulan lalu', 'Rp197.500']);
    $this->assertDatabaseCount('transaction_revisions', 1);
});

test('ledger isolates references, enforces dates and audits transfer, correction and deletion', function () {
    $house = Household::create(['name' => 'Rumah']);
    $user = User::factory()->create();
    $member = $house->memberships()->create(['user_id' => $user->id, 'role' => 'owner']);
    $other = Household::create(['name' => 'Lain']);
    $otherUser = User::factory()->create();
    $other->memberships()->create(['user_id' => $otherUser->id, 'role' => 'owner']);
    $base = ['type' => 'bank', 'opened_on' => '2025-01-01', 'opening_balance' => 100000];
    $this->actingAs($otherUser)->post(route('ledger.save', 'wallets'), $base + ['name' => 'Luar'])->assertRedirect();
    $foreign = DB::table('wallets')->value('id');
    $this->post(route('ledger.save', 'transactions'), [
        'type' => 'income', 'occurred_on' => '2025-01-02', 'amount' => 1000,
        'destination_wallet_id' => $foreign, 'category_id' => DB::table('categories')->where('type', 'income')->value('id'),
        'note' => 'Log rumah tangga lain',
    ])->assertSessionHasNoErrors();

    $this->actingAs($user);
    foreach (['Bank', 'E-wallet'] as $name) {
        $this->post(route('ledger.save', 'wallets'), $base + ['name' => $name])->assertSessionHasNoErrors();
    }
    [$source, $destination] = Ledger::scoped('wallets', $member)->orderBy('id')->pluck('id')->all();
    $this->get(route('ledger.index', ['wallets', $foreign]))->assertNotFound();
    $this->get(route('ledger.index', ['wallets', $source]))->assertOk()->assertSee('Koreksi saldo awal');
    $this->get(route('ledger.index', 'categories'))->assertOk()->assertSee('Zakat');
    $this->get(route('ledger.index', 'transactions'))->assertOk()->assertSee('Catat transaksi');

    $url = route('ledger.save', 'transactions');
    $data = ['type' => 'transfer', 'occurred_on' => '2025-01-02', 'amount' => 200000,
        'fee_amount' => 2500, 'source_wallet_id' => $source, 'destination_wallet_id' => $destination];
    foreach (['fee_amount' => -1, 'occurred_on' => '2024-12-31', 'destination_wallet_id' => $foreign, 'source_wallet_id' => $destination] as $field => $value) {
        $this->post($url, [$field => $value] + $data)->assertSessionHasErrors($field === 'source_wallet_id' ? 'destination_wallet_id' : $field);
    }

    $this->post($url, $data)->assertRedirect(route('ledger.index', ['section' => 'transactions', 'bulan' => '2025-01']))->assertSessionHasNoErrors();
    $id = Ledger::transactions($member)->value('id');
    expect(Ledger::balances($member, '2025-01-31'))->toEqual([$source => '-102500', $destination => '300000']);
    $book = ['section' => 'transactions', 'bulan' => '2025-01'];
    $this->get(route('ledger.index', $book))->assertOk()->assertSee('Saldo bulan lalu')->assertSee('Rp197.500');
    $this->get(route('ledger.index', $book + ['dompet' => $source]))->assertOk()->assertSee('Rp202.500')->assertSee('Rp-102.500');
    $this->get(route('ledger.index', $book + ['dompet' => $destination]))->assertOk()->assertSee('Rp300.000');
    $this->get(route('ledger.index', $book + ['dompet' => $foreign]))->assertNotFound();
    $this->get(route('ledger.index', $book + ['dompet' => $source]), ['X-Cashbook-Results' => '1'])
        ->assertOk()->assertHeader('Vary', 'X-Cashbook-Results')->assertSee('data-cashbook-results')
        ->assertSee('Rp202.500')->assertDontSee('Audit transaksi')->assertDontSee('Catat transaksi');
    $this->get(route('ledger.index', $book + ['dompet' => $foreign]), ['X-Cashbook-Results' => '1'])->assertNotFound();
    $this->get(route('ledger.index', ['bulan' => '2025-02'] + $book))
        ->assertOk()->assertSee('Belum ada transaksi pada periode ini.')->assertSee('Rp197.500');
    $this->get(route('ledger.index', ['transactions', $id]))->assertOk()->assertSee('2.500');

    $this->patch(route('ledger.correct', $source), ['opening_balance' => 300000])->assertRedirect();
    $this->assertDatabaseHas('wallet_opening_balance_revisions', ['before_amount' => 100000, 'after_amount' => 300000]);
    expect(Ledger::balances($member, '2025-01-31')[$source])->toBe('97500');

    $this->delete(route('ledger.remove', ['wallets', $destination]))->assertRedirect();
    $this->post($url, $data)->assertSessionHasErrors('destination_wallet_id');
    $this->post(route('ledger.save', ['transactions', $id]), ['note' => 'Nafkah'] + $data)->assertRedirect();
    $this->delete(route('ledger.remove', ['transactions', $id]))->assertRedirect();
    expect(DB::table('transaction_revisions')->where('transaction_id', $id)->orderBy('id')->pluck('action')->all())->toBe(['create', 'update', 'delete']);
    $this->get(route('ledger.audit'))->assertOk()
        ->assertSeeInOrder(['Dihapus', 'Diubah', 'Dibuat'])
        ->assertSee('Nafkah')->assertDontSee('Log rumah tangga lain');
    $this->get(route('ledger.index', $book))->assertOk()->assertDontSee('Sebelum')->assertDontSee('Nafkah');
    expect(Ledger::balances($member, '2025-01-31'))->toEqual([$source => 300000, $destination => 100000]);
});

test('partners share the ledger and historical holders and categories survive access changes', function () {
    $home = Household::create(['name' => 'Rumah bersama']);
    $owner = User::factory()->create();
    $partner = User::factory()->create(['name' => 'Pasangan lama']);
    $home->memberships()->create(['user_id' => $owner->id, 'role' => 'owner']);
    $holder = $home->memberships()->create(['user_id' => $partner->id, 'role' => 'partner']);

    $this->actingAs($partner)->post(route('ledger.save', 'wallets'), [
        'name' => 'Kas', 'type' => 'cash', 'opened_on' => '2025-01-01',
        'opening_balance' => 0, 'holder_membership_id' => $holder->id,
    ])->assertSessionHasNoErrors();
    $this->post(route('ledger.save', 'categories'), ['name' => 'Kategori lama', 'type' => 'expense'])->assertSessionHasNoErrors();
    $category = DB::table('categories')->where('household_id', $home->id)->value('id');
    $wallet = DB::table('wallets')->value('id');
    $transaction = ['type' => 'expense', 'source_wallet_id' => $wallet, 'category_id' => $category, 'occurred_on' => '2025-01-02', 'amount' => 1];
    $this->post(route('ledger.save', 'transactions'), $transaction)->assertSessionHasNoErrors();
    $this->actingAs($owner)->delete(route('ledger.remove', ['categories', $category]))->assertRedirect();
    $this->post(route('ledger.save', 'transactions'), $transaction)->assertSessionHasErrors('category_id');
    $this->get(route('ledger.index', ['section' => 'transactions', 'bulan' => '2025-01']))->assertOk()->assertSee('Kategori lama');
    $this->delete(route('households.partner.destroy', [$home->id, $holder->id]))->assertRedirect();
    $this->get(route('ledger.index', 'wallets'))->assertOk()->assertSee('Pasangan lama')->assertSee('Saldo negatif');
    $this->actingAs($partner)->post(route('ledger.save', 'transactions'), $transaction)->assertRedirect(route('households.onboarding'));
    $this->assertDatabaseCount('transactions', 1);
});

test('cashbook carries balances across years and pages and reflects historical changes', function () {
    $house = Household::create(['name' => 'Buku kas']);
    $user = User::factory()->create();
    $member = $house->memberships()->create(['user_id' => $user->id, 'role' => 'owner']);
    $this->actingAs($user);
    $walletUrl = route('ledger.save', 'wallets');
    $this->post($walletUrl, ['name' => 'Kas', 'type' => 'cash', 'opened_on' => '2024-12-01', 'opening_balance' => 100])->assertSessionHasNoErrors();
    $this->post($walletUrl, ['name' => 'Bank Baru', 'type' => 'bank', 'opened_on' => '2025-01-01', 'opening_balance' => 400])->assertSessionHasNoErrors();
    [$wallet, $bank] = Ledger::scoped('wallets', $member)->orderBy('id')->pluck('id')->all();
    $category = Ledger::categories($member)->where('type', 'income')->value('id');
    $entry = ['type' => 'income', 'destination_wallet_id' => $wallet, 'category_id' => $category, 'amount' => 50, 'occurred_on' => '2024-12-31'];
    $this->post(route('ledger.save', 'transactions'), $entry)->assertSessionHasNoErrors();
    $old = Ledger::transactions($member)->value('id');

    for ($i = 1; $i <= 25; $i++) {
        $this->post(route('ledger.save', 'transactions'), ['occurred_on' => '2025-01-01', 'amount' => 10, 'note' => "Mutasi $i."] + $entry)
            ->assertSessionHasNoErrors();
    }

    $book = ['section' => 'transactions', 'bulan' => '2025-01'];
    $this->get(route('ledger.index', $book))->assertOk()
        ->assertSeeInOrder(['>No.</th>', '>Tanggal</th>', '>Keterangan</th>', '>Masuk</th>', '>Keluar</th>', '>Saldo</th>', '>Aksi</th>'], false)
        ->assertSeeInOrder(['Saldo bulan lalu', 'Rp150', 'Saldo awal dompet', 'Mutasi 1.', 'Mutasi 24.'])
        ->assertViewHas('cashbook', fn ($cashbook) => $cashbook['rows']->first()['number'] === null
            && $cashbook['rows']->last()['number'] === 24);
    $this->get(route('ledger.index', $book + ['page' => 2]))->assertOk()
        ->assertViewHas('cashbook', fn ($cashbook) => $cashbook['rows']->first()['number'] === 25)
        ->assertSeeInOrder(['Saldo dari halaman sebelumnya', 'Rp790', 'Mutasi 25.', 'Saldo akhir bulan', 'Rp800']);
    $this->get(route('ledger.index', $book + ['page' => 2]), ['X-Cashbook-Results' => '1'])->assertOk()
        ->assertSeeInOrder(['Saldo dari halaman sebelumnya', 'Rp790', 'Mutasi 25.', 'Saldo akhir bulan', 'Rp800']);
    $this->delete(route('ledger.remove', ['wallets', $bank]))->assertRedirect();
    $this->get(route('ledger.index', $book + ['dompet' => $bank]))->assertOk()->assertSee('Diarsipkan')->assertSee('Rp400');

    $this->post(route('ledger.save', ['transactions', $old]), ['amount' => 70] + $entry)->assertSessionHasNoErrors();
    $this->get(route('ledger.index', $book))->assertSeeInOrder(['Saldo bulan lalu', 'Rp170']);
    $this->delete(route('ledger.remove', ['record' => $old] + $book))->assertRedirect(route('ledger.index', $book));
    $this->get(route('ledger.index', $book))->assertSeeInOrder(['Saldo bulan lalu', 'Rp100']);
});
