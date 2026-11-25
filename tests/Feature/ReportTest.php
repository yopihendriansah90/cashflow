<?php

use App\Models\Household;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('monthly report and CSV respect dates, historical changes, archived references and spreadsheet text', function () {
    $house = Household::create(['name' => 'Rumah laporan']);
    $user = User::factory()->create();
    $house->memberships()->create(['user_id' => $user->id, 'role' => 'owner']);
    $this->actingAs($user);

    foreach (['=Bank' => '2025-01-01', 'Tunai' => '2025-01-01', 'Dompet masa depan' => '2025-03-01'] as $name => $opened) {
        $this->post(route('ledger.save', 'wallets'), [
            'name' => $name, 'type' => 'cash', 'opened_on' => $opened, 'opening_balance' => 0,
        ])->assertSessionHasNoErrors();
    }
    [$source, $destination] = DB::table('wallets')->orderBy('id')->pluck('id')->all();
    $url = route('ledger.save', 'transactions');
    $this->post($url, ['type' => 'income', 'amount' => 500000, 'occurred_on' => '2025-01-30',
        'destination_wallet_id' => $source, 'category_id' => DB::table('categories')->where('code', 'salary')->value('id')])->assertSessionHasNoErrors();
    $this->post($url, ['type' => 'transfer', 'amount' => 200000, 'fee_amount' => 2500, 'occurred_on' => '2025-02-01',
        'source_wallet_id' => $source, 'destination_wallet_id' => $destination, 'note' => '=1+1'])->assertSessionHasNoErrors();
    $this->post($url, ['type' => 'expense', 'amount' => 1000, 'occurred_on' => '2025-02-03',
        'source_wallet_id' => $source, 'category_id' => DB::table('categories')->where('code', 'zakat')->value('id')])->assertSessionHasNoErrors();
    $this->delete(route('ledger.remove', ['wallets', $destination]))->assertRedirect();
    $report = route('reports.index', ['bulan' => '2025-02']);
    $dashboard = route('dashboard', ['bulan' => '2025-02']);
    $summary = $this->get($dashboard)->assertOk()->assertSee($report)->viewData('summary');
    expect($summary['daily'])->toHaveCount(28)
        ->and($summary['daily'][1])->toBe(['income' => '0', 'expense' => '2500'])
        ->and($summary['daily'][3])->toBe(['income' => '0', 'expense' => '1000'])
        ->and(array_sum(array_column($summary['daily'], 'expense')))->toBe((int) $summary['expense']);
    $this->get(route('dashboard', ['bulan' => '2025-03']))->assertOk()->assertSee('Belum ada arus kas pada bulan ini.');
    $this->get(route('dashboard', ['bulan' => '2025-13']))->assertUnprocessable();
    $this->get($report)->assertOk()->assertSee('Rp200.000')
        ->assertSee('Rp3.500')->assertSee('Rp296.500')->assertSee('Biaya Admin')->assertDontSee('Dompet masa depan');

    $csv = $this->get(route('reports.export', ['bulan' => '2025-02']))->assertOk()->assertDownload('transaksi-2025-02.csv');
    $lines = preg_split('/\r?\n/', trim($csv->streamedContent()));
    expect($lines)->toHaveCount(3);
    expect(str_getcsv($lines[2], escape: ''))->toBe(['2025-02-01', 'Transfer', '200000', '2500', "'=Bank", 'Tunai', '', "'=1+1"]);

    $this->patch(route('ledger.correct', $source), ['opening_balance' => 10000])->assertRedirect();
    $last = DB::table('transactions')->max('id');
    $this->delete(route('ledger.remove', ['transactions', $last]))->assertRedirect();
    $summary = $this->get($dashboard)->assertOk()->viewData('summary');
    expect($summary['daily'][3]['expense'])->toBe('0');
    $this->get($report)->assertOk()->assertSee('Rp307.500')->assertDontSee('Rp3.500');
});
