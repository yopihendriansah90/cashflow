<?php

namespace App\Support;

use App\Models\Household;
use App\Models\HouseholdMembership;
use Brick\Math\BigInteger;
use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class Ledger
{
    public const TYPES = ['income' => 'Pemasukan', 'expense' => 'Pengeluaran', 'transfer' => 'Transfer'];
    public const WALLETS = ['cash' => 'Kas', 'bank' => 'Rekening', 'ewallet' => 'E-wallet'];

    public static function write(HouseholdMembership $member, Closure $action): mixed
    {
        return DB::transaction(function () use ($member, $action) {
            Household::lockForUpdate()->findOrFail($member->household_id);
            abort_if(HouseholdMembership::lockForUpdate()->findOrFail($member->id)->ended_at, 403);

            return $action();
        }, 3);
    }

    public static function scoped(string $table, HouseholdMembership $member): Builder
    {
        return DB::table($table)->where('household_id', $member->household_id);
    }

    public static function categories(HouseholdMembership $member): Builder
    {
        return DB::table('categories')->where(fn (Builder $query) => $query
            ->whereNull('household_id')->orWhere('household_id', $member->household_id));
    }

    public static function transactions(HouseholdMembership $member): Builder
    {
        return self::scoped('transactions', $member)->whereNull('deleted_at');
    }

    public static function references(HouseholdMembership $member, int $wallet): Builder
    {
        return self::scoped('transactions', $member)->where(fn (Builder $query) => $query
            ->where('source_wallet_id', $wallet)->orWhere('destination_wallet_id', $wallet));
    }

    public static function check(bool $condition, string $field, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages([$field => __($message)]);
        }
    }

    public static function today(): string
    {
        return now('Asia/Jakarta')->toDateString();
    }

    public static function rupiah(int|string $amount): string
    {
        return 'Rp'.preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', (string) $amount);
    }

    public static function auditSnapshot(?string $json): ?string
    {
        $snapshot = json_decode($json ?? '', true, 512, JSON_BIGINT_AS_STRING);
        if (! is_array($snapshot)) {
            return $json;
        }

        foreach (['amount', 'fee_amount'] as $field) {
            if (isset($snapshot[$field]) && preg_match('/\A-?[0-9]+\z/', (string) $snapshot[$field])) {
                $snapshot[$field] = self::rupiah($snapshot[$field]);
            }
        }

        return json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    public static function date(string $date): string
    {
        return \Illuminate\Support\Carbon::parse($date)->locale('id')->translatedFormat('j F Y');
    }

    public static function auditTime(string $date): string
    {
        $wib = \Illuminate\Support\Carbon::parse($date, 'UTC')->setTimezone('Asia/Jakarta');

        return self::date($wib->toDateString()).' '.$wib->format('H:i:s').' WIB';
    }

    public static function balances(HouseholdMembership $member, string $until): array
    {
        $balances = self::scoped('wallets', $member)->where('opened_on', '<=', $until)
            ->pluck('opening_balance', 'id')->all();

        foreach (self::transactions($member)->where('occurred_on', '<=', $until)->cursor() as $row) {
            if (isset($balances[$row->source_wallet_id])) {
                $balances[$row->source_wallet_id] = (string) BigInteger::of($balances[$row->source_wallet_id])->minus($row->amount)->minus($row->fee_amount);
            }
            if (isset($balances[$row->destination_wallet_id])) {
                $balances[$row->destination_wallet_id] = (string) BigInteger::of($balances[$row->destination_wallet_id])->plus($row->amount);
            }
        }

        return $balances;
    }
}
