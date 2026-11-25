<?php

namespace App\Support;

use App\Models\HouseholdMembership;
use Brick\Math\BigInteger;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class Cashbook
{
    public static function read(HouseholdMembership $member, MonthlyLedger $period, ?string $walletId, string $search = '', array $walletNames = [], array $categoryNames = []): array
    {
        return DB::transaction(function () use ($member, $period, $walletId, $search, $walletNames, $categoryNames) {
            $first = $period->month.'-01';
            $previous = Carbon::parse($first)->subDay()->toDateString();
            $balances = $first === '1000-01-01' ? [] : Ledger::balances($member, $previous);
            $balance = BigInteger::zero();
            foreach ($walletId === null ? $balances : [$balances[$walletId] ?? 0] as $amount) {
                $balance = $balance->plus($amount);
            }
            $opening = $carry = (string) $balance;
            $page = LengthAwarePaginator::resolveCurrentPage();
            $perPage = 25;
            $offset = ($page - 1) * $perPage;
            $rows = [];
            $count = 0;
            $transactionNumber = 0;
            $matchingTransactions = $transactionsBeforePage = $transactionsOnPage = 0;
            $needle = mb_strtolower($search);
            $append = function (string $date, ?object $entry, ?object $wallet, BigInteger $in, BigInteger $out) use (&$balance, &$carry, &$rows, &$count, &$transactionNumber, &$matchingTransactions, &$transactionsBeforePage, &$transactionsOnPage, $offset, $perPage, $needle, $walletNames, $categoryNames) {
                $balance = $balance->plus($in)->minus($out);
                $number = $entry === null ? null : ++$transactionNumber;
                if ($needle !== '' && ($entry === null || ! self::matches($entry, $needle, $walletNames, $categoryNames))) {
                    return;
                }
                if ($count < $offset) {
                    $carry = (string) $balance;
                    $transactionsBeforePage += (int) ($entry !== null);
                } elseif ($count - $offset < $perPage) {
                    $rows[] = ['number' => $number, 'date' => $date, 'entry' => $entry, 'wallet' => $wallet, 'in' => (string) $in, 'out' => (string) $out, 'balance' => (string) $balance];
                    $transactionsOnPage += (int) ($entry !== null);
                }
                $matchingTransactions += (int) ($entry !== null);
                $count++;
            };

            $newWallets = Ledger::scoped('wallets', $member)->whereBetween('opened_on', [$first, $period->end])
                ->when($walletId !== null, fn ($query) => $query->where('id', $walletId))
                ->orderBy('opened_on')->orderBy('id')->get();
            $nextWallet = 0;
            $zero = BigInteger::zero();
            $monthlyIn = $monthlyOut = $zero;
            $addOpenings = function (string $date) use ($newWallets, &$nextWallet, $append, $zero) {
                while (($wallet = $newWallets->get($nextWallet)) && $wallet->opened_on <= $date) {
                    $amount = BigInteger::of($wallet->opening_balance);
                    $append($wallet->opened_on, null, $wallet, $amount->isNegative() ? $zero : $amount, $amount->isNegative() ? $amount->negated() : $zero);
                    $nextWallet++;
                }
            };

            $entries = $period->entries()->reorder('occurred_on')->orderBy('id');
            if ($walletId !== null) {
                $entries->where(fn ($query) => $query->where('source_wallet_id', $walletId)->orWhere('destination_wallet_id', $walletId));
            }
            foreach ($entries->cursor() as $entry) {
                $addOpenings($entry->occurred_on);
                $amount = BigInteger::of($entry->amount);
                $fee = BigInteger::of($entry->fee_amount);
                [$in, $out] = match ($entry->type) {
                    'income' => [$amount, $zero],
                    'expense' => [$zero, $amount],
                    'transfer' => match (true) {
                        $walletId === null => [$zero, $fee],
                        (string) $entry->source_wallet_id === $walletId => [$zero, $amount->plus($fee)],
                        default => [$amount, $zero],
                    },
                };
                $monthlyIn = $monthlyIn->plus($in);
                $monthlyOut = $monthlyOut->plus($out);
                $append($entry->occurred_on, $entry, null, $in, $out);
            }
            $addOpenings($period->end);

            return [
                'rows' => (new LengthAwarePaginator($rows, $count, $perPage, $page, ['path' => route('ledger.index', 'transactions')]))
                    ->appends(['bulan' => $period->month, 'dompet' => $walletId, 'q' => $search !== '' ? $search : null]),
                'search' => $search,
                'matchingTransactions' => $matchingTransactions,
                'firstTransaction' => $transactionsOnPage === 0 ? null : $transactionsBeforePage + 1,
                'lastTransaction' => $transactionsOnPage === 0 ? null : $transactionsBeforePage + $transactionsOnPage,
                'monthlyIn' => (string) $monthlyIn,
                'monthlyOut' => (string) $monthlyOut,
                'opening' => $opening,
                'carry' => $carry,
                'ending' => (string) $balance,
            ];
        });
    }

    private static function matches(object $entry, string $needle, array $walletNames, array $categoryNames): bool
    {
        $fields = [
            $entry->note,
            __(Ledger::TYPES[$entry->type]),
            $categoryNames[$entry->category_id] ?? null,
            $walletNames[$entry->source_wallet_id] ?? null,
            $walletNames[$entry->destination_wallet_id] ?? null,
        ];

        foreach ($fields as $field) {
            if ($field !== null && str_contains(mb_strtolower($field), $needle)) {
                return true;
            }
        }

        return false;
    }
}
