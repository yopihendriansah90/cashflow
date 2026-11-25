<?php

namespace App\Support;

use App\Models\HouseholdMembership;
use Brick\Math\BigInteger;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class MonthlyLedger
{
    public readonly string $end;

    public function __construct(private readonly HouseholdMembership $member, public readonly string $month)
    {
        $this->end = Carbon::parse($month.'-01')->endOfMonth()->toDateString();
    }

    public static function currentMonth(): string
    {
        return now('Asia/Jakarta')->format('Y-m');
    }

    public static function fromRequest(Request $request, ?string $defaultMonth = null): self
    {
        $month = $request->query('bulan', $defaultMonth ?? self::currentMonth());
        abort_unless(is_string($month) && preg_match('/\A[1-9][0-9]{3}-(?:0[1-9]|1[0-2])\z/', $month), 422, __('Pilih bulan yang valid.'));

        return new self($request->attributes->get('membership'), $month);
    }

    public function entries(): Builder
    {
        return Ledger::transactions($this->member)
            ->whereBetween('occurred_on', [$this->month.'-01', $this->end])
            ->orderByDesc('occurred_on')->orderByDesc('id');
    }

    public function summary(): array
    {
        return DB::transaction(function () {
            $income = BigInteger::zero();
            $expense = BigInteger::zero();
            $breakdown = [];
            $daily = array_fill(1, (int) substr($this->end, -2), ['income' => '0', 'expense' => '0']);
            $giving = ['zakat' => '0', 'infak' => '0', 'sedekah' => '0'];
            $categories = Ledger::categories($this->member)->get()->keyBy('id');
            $admin = $categories->firstWhere('code', 'admin_fee');

            foreach ($this->entries()->cursor() as $entry) {
                $day = (int) substr($entry->occurred_on, -2);
                if ($entry->type === 'income') {
                    $income = $income->plus($entry->amount);
                    $daily[$day]['income'] = (string) BigInteger::of($daily[$day]['income'])->plus($entry->amount);
                    continue;
                }

                $category = $entry->type === 'transfer' ? $admin : $categories[$entry->category_id];
                $amount = $entry->type === 'transfer' ? $entry->fee_amount : $entry->amount;
                if ((string) $amount === '0') {
                    continue;
                }

                $expense = $expense->plus($amount);
                $daily[$day]['expense'] = (string) BigInteger::of($daily[$day]['expense'])->plus($amount);
                $breakdown[$category->id] = [
                    'name' => $category->name,
                    'amount' => (string) BigInteger::of($breakdown[$category->id]['amount'] ?? 0)->plus($amount),
                ];
                if (isset($giving[$category->code])) {
                    $giving[$category->code] = $breakdown[$category->id]['amount'];
                }
            }

            $net = $income->minus($expense);
            $balances = Ledger::balances($this->member, $this->end);
            $walletNames = Ledger::scoped('wallets', $this->member)->pluck('name', 'id');
            $ending = [];
            foreach ($balances as $id => $amount) {
                $ending[] = ['name' => $walletNames[$id], 'amount' => $amount];
            }

            return ['income' => (string) $income, 'expense' => (string) $expense, 'net' => (string) $net]
                + compact('breakdown', 'giving', 'ending', 'daily');
        });
    }

    public function writeCsv($output): void
    {
        $wallets = Ledger::scoped('wallets', $this->member)->pluck('name', 'id');
        $categories = Ledger::categories($this->member)->pluck('name', 'id');
        fwrite($output, "\xEF\xBB\xBF");
        fputcsv($output, ['Tanggal', 'Jenis', 'Nominal (Rp)', 'Biaya Transfer (Rp)', 'Dompet Asal', 'Dompet Tujuan', 'Kategori', 'Catatan'], escape: '');

        foreach ($this->entries()->cursor() as $entry) {
            fputcsv($output, [
                $entry->occurred_on,
                Ledger::TYPES[$entry->type],
                $entry->amount,
                $entry->fee_amount,
                self::spreadsheetText($wallets[$entry->source_wallet_id] ?? ''),
                self::spreadsheetText($wallets[$entry->destination_wallet_id] ?? ''),
                self::spreadsheetText($categories[$entry->category_id] ?? ''),
                self::spreadsheetText($entry->note ?? ''),
            ], escape: '');
        }
    }

    private static function spreadsheetText(string $value): string
    {
        return preg_match('/\A(?:[\t\r\n]|[\s\p{Z}\p{Cf}]*[=+\-@＝＋－＠])/u', $value) ? "'".$value : $value;
    }
}
