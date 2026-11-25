<?php

namespace App\Http\Controllers;

use App\Models\HouseholdMembership;
use App\Support\Cashbook;
use App\Support\Ledger;
use App\Support\MonthlyLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class LedgerController extends Controller
{
    public function index(Request $request, string $section, ?string $record = null)
    {
        $member = $request->attributes->get('membership');
        $wallets = Ledger::scoped('wallets', $member)->orderBy('name')->get();
        $categories = Ledger::categories($member)->orderBy('name')->get();
        $holders = $section === 'wallets' ? $member->household->memberships()->with('user')->get()->keyBy('id') : collect();
        $selected = $record === null ? null : $this->row($section, $member, $record);
        $balances = $section === 'wallets' ? Ledger::balances($member, Ledger::today()) : [];
        $period = $cashbook = $walletId = null;
        if ($section === 'transactions') {
            $period = MonthlyLedger::fromRequest($request, $selected ? substr($selected->occurred_on, 0, 7) : null);
            $walletId = $this->cashbookWallet($request, $member);
            $cashbook = Cashbook::read($member, $period, $walletId);
            if ($cashbook['rows']->currentPage() > $cashbook['rows']->lastPage()) {
                return redirect()->to($cashbook['rows']->url($cashbook['rows']->lastPage()));
            }
            if ($request->header('X-Cashbook-Results') === '1') {
                return response()->view('components.main.cashflow.cashbook-results', [
                    'cashbook' => $cashbook,
                    'period' => $period,
                    'walletId' => $walletId,
                    'categoryNames' => $categories->pluck('name', 'id'),
                    'walletNames' => $wallets->pluck('name', 'id'),
                ])->header('Cache-Control', 'private, no-store')->setVary('X-Cashbook-Results');
            }
        }
        $corrections = $section === 'wallets' && $selected ? DB::table('wallet_opening_balance_revisions')
            ->where('wallet_id', $selected->id)->orderByDesc('id')->paginate(15, pageName: 'audit') : collect();

        return response()->view('pages.cashflow.ledger', compact('section', 'selected', 'wallets', 'categories', 'holders', 'balances', 'period', 'cashbook', 'walletId', 'corrections') + ['membership' => $member])
            ->setVary('X-Cashbook-Results');
    }

    public function audit(Request $request)
    {
        $member = $request->attributes->get('membership');
        $holders = $member->household->memberships()->with('user')->get()->keyBy('id');
        $revisions = DB::table('transaction_revisions as revisions')
            ->join('transactions as entry', 'entry.id', '=', 'revisions.transaction_id')
            ->where('entry.household_id', $member->household_id)
            ->select('revisions.*')->orderByDesc('revisions.id')->paginate(15);

        return view('pages.cashflow.audit', compact('revisions', 'holders') + ['membership' => $member]);
    }

    public function quickTransaction(Request $request)
    {
        $member = $request->attributes->get('membership');
        $wallets = Ledger::scoped('wallets', $member)->whereNull('archived_at')->orderBy('name')->get();
        if ($wallets->isEmpty()) {
            return response()->json(['walletUrl' => route('ledger.index', ['section' => 'wallets', 'baru' => 1])], 409)
                ->header('Cache-Control', 'private, no-store');
        }

        $categories = Ledger::categories($member)->whereNull('archived_at')->orderBy('name')->get();

        return response()->view('pages.cashflow.quick-transaction', compact('wallets', 'categories'))
            ->header('Cache-Control', 'private, no-store');
    }

    public function save(Request $request, string $section, ?string $record = null)
    {
        $member = $request->attributes->get('membership');
        $walletId = $section === 'transactions' ? $this->cashbookWallet($request, $member) : null;
        $data = Ledger::write($member, function () use ($request, $section, $record, $member) {
            $before = $record === null ? null : $this->row($section, $member, $record);
            $data = match ($section) {
                'wallets' => $this->wallet($request, $member, $before),
                'categories' => $this->category($request, $before),
                'transactions' => $this->transaction($request, $member, $before),
            };
            $now = now();
            $data['updated_at'] = $now;
            if ($before) {
                Ledger::scoped($section, $member)->where('id', $record)->update($data);
                $id = $before->id;
            } else {
                $data += ['household_id' => $member->household_id, 'created_at' => $now];
                if ($section === 'transactions') {
                    $data['creator_membership_id'] = $member->id;
                }
                $id = DB::table($section)->insertGetId($data);
            }

            if ($section === 'transactions') {
                $this->revision($member, $id, $before ? 'update' : 'create', $before, $this->row($section, $member, (string) $id));
            }

            return $data;
        });

        if ($section === 'transactions' && $record === null && $request->expectsJson()) {
            $request->session()->flash('status', __('Perubahan disimpan.'));

            return response()->json(['saved' => true], 201);
        }

        $context = ['section' => $section];
        if ($section === 'transactions') {
            $context['bulan'] = substr($data['occurred_on'], 0, 7);
            if ($walletId !== null && in_array($walletId, [(string) $data['source_wallet_id'], (string) $data['destination_wallet_id']], true)) {
                $context['dompet'] = $walletId;
            }
        }

        return redirect()->route('ledger.index', $context)->with('status', __('Perubahan disimpan.'));
    }

    public function correct(Request $request, string $record)
    {
        $this->normalizeMoney($request, 'opening_balance');
        $member = $request->attributes->get('membership');
        $data = $this->validateCashflow($request, ['opening_balance' => ['required', 'regex:/\A-?[0-9]+\z/', 'integer']]);
        Ledger::write($member, function () use ($member, $record, $data) {
            $wallet = $this->row('wallets', $member, $record);
            if ((string) $wallet->opening_balance === (string) $data['opening_balance']) {
                return;
            }

            Ledger::scoped('wallets', $member)->where('id', $record)->update($data + ['updated_at' => now()]);
            DB::table('wallet_opening_balance_revisions')->insert([
                'wallet_id' => $wallet->id,
                'actor_membership_id' => $member->id,
                'before_amount' => $wallet->opening_balance,
                'after_amount' => $data['opening_balance'],
                'created_at' => now(),
            ]);
        });

        return redirect()->route('ledger.index', 'wallets')->with('status', __('Saldo awal dikoreksi.'));
    }

    public function remove(Request $request, string $section, string $record)
    {
        $member = $request->attributes->get('membership');
        $context = ['section' => $section];
        if ($section === 'transactions') {
            $context['bulan'] = MonthlyLedger::fromRequest($request)->month;
            $context['dompet'] = $this->cashbookWallet($request, $member);
        }
        Ledger::write($member, function () use ($section, $record, $member) {
            $before = $this->row($section, $member, $record);
            $query = Ledger::scoped($section, $member)->where('id', $record);

            if ($section === 'transactions') {
                $query->update(['deleted_at' => now(), 'updated_at' => now()]);
                $this->revision($member, $before->id, 'delete', $before, $query->first());
                return;
            }

            $query->whereNull('archived_at')->update(['archived_at' => now(), 'updated_at' => now()]);
        });

        return redirect()->route('ledger.index', $context)->with('status', __('Perubahan disimpan.'));
    }

    private function cashbookWallet(Request $request, HouseholdMembership $member): ?string
    {
        $walletId = $request->query('dompet');
        if ($walletId === null || $walletId === '') {
            return null;
        }

        abort_unless(is_string($walletId) && preg_match('/\A[1-9][0-9]*\z/', $walletId)
            && Ledger::scoped('wallets', $member)->where('id', $walletId)->exists(), 404);

        return $walletId;
    }

    private function row(string $section, HouseholdMembership $member, string $id): object
    {
        $query = $section === 'transactions' ? Ledger::transactions($member) : Ledger::scoped($section, $member);

        return $query->where('id', $id)->first() ?? abort(404);
    }

    private function wallet(Request $request, HouseholdMembership $member, ?object $before): array
    {
        $this->normalizeMoney($request, 'opening_balance');
        $data = $this->validateCashflow($request, [
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::in(array_keys(Ledger::WALLETS))],
            'opened_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:1000-01-01'],
            'opening_balance' => [$before ? 'exclude' : 'required', 'regex:/\A-?[0-9]+\z/', 'integer'],
            'holder_membership_id' => ['nullable', 'integer'],
            'brand_key' => ['nullable', 'string', 'max:32'],
        ]);
        $brand = array_key_exists('brand_key', $data) ? $data['brand_key'] : ($before->brand_key ?? null);
        $catalog = config('wallet-brands')[$data['type']] ?? [];
        if (array_key_exists('brand_key', $data)) {
            Ledger::check($brand === null || isset($catalog[$brand]), 'brand_key', 'Pilih logo yang sesuai dengan jenis dompet.');
        }
        $data['brand_key'] = isset($catalog[$brand]) ? $brand : null;
        $holder = $data['holder_membership_id'] ?? null;
        Ledger::check($holder === null || (string) $holder === (string) ($before->holder_membership_id ?? '') ||
            $member->household->memberships()->whereKey($holder)->whereNull('ended_at')->exists(),
            'holder_membership_id', 'Pilih anggota aktif rumah tangga ini.');
        Ledger::check(! $before || ! Ledger::references($member, $before->id)->where('occurred_on', '<', $data['opened_on'])->exists(),
            'opened_on', 'Tanggal mulai melewati tanggal transaksi.');

        return $data + ['holder_membership_id' => $before->holder_membership_id ?? null];
    }

    private function category(Request $request, ?object $before): array
    {
        $data = $this->validateCashflow($request, [
            'name' => ['required', 'string', 'max:120'],
            'type' => [$before ? 'exclude' : 'required', Rule::in(['income', 'expense'])],
        ]);

        return $data;
    }

    private function transaction(Request $request, HouseholdMembership $member, ?object $before): array
    {
        $this->normalizeMoney($request, 'amount', 'fee_amount');
        $data = $this->validateCashflow($request, [
            'type' => ['required', Rule::in(array_keys(Ledger::TYPES))],
            'occurred_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:1000-01-01'],
            'amount' => ['required', 'regex:/\A-?[0-9]+\z/', 'integer', 'min:1'],
            'fee_amount' => ['nullable', 'regex:/\A(?:0|-?[1-9][0-9]*)\z/', 'integer', 'min:0'],
            'source_wallet_id' => ['nullable', 'integer', 'required_unless:type,income'],
            'destination_wallet_id' => ['nullable', 'integer', 'required_unless:type,expense'],
            'category_id' => ['nullable', 'integer', 'required_unless:type,transfer'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $type = $data['type'];
        $confirmedEarly = $request->input('confirm_early_transaction') === '1';
        $data['fee_amount'] = $data['fee_amount'] ?? 0;
        $data += ['source_wallet_id' => null, 'destination_wallet_id' => null, 'category_id' => null, 'note' => null];
        Ledger::check($type !== 'income' || $data['source_wallet_id'] === null, 'source_wallet_id', 'Pemasukan hanya memiliki dompet tujuan.');
        Ledger::check($type !== 'expense' || $data['destination_wallet_id'] === null, 'destination_wallet_id', 'Pengeluaran hanya memiliki dompet asal.');
        Ledger::check($type !== 'transfer' || (int) $data['source_wallet_id'] !== (int) $data['destination_wallet_id'],
            'destination_wallet_id', 'Pilih dua dompet berbeda.');
        Ledger::check($type !== 'transfer' || $data['category_id'] === null, 'category_id', 'Transfer tidak memiliki kategori langsung.');
        Ledger::check($type === 'transfer' || (int) $data['fee_amount'] === 0, 'fee_amount', 'Biaya hanya berlaku untuk transfer.');

        $walletsToBackdate = [];
        foreach (['source_wallet_id', 'destination_wallet_id'] as $field) {
            if ($data[$field] === null) {
                continue;
            }

            $wallet = Ledger::scoped('wallets', $member)->where('id', $data[$field])->first();
            Ledger::check($wallet && (! $wallet->archived_at || (string) $wallet->id === (string) ($before->$field ?? '')),
                $field, 'Pilih dompet aktif rumah tangga ini.');
            if ($wallet->opened_on > $data['occurred_on']) {
                Ledger::check($confirmedEarly, 'occurred_on', 'Tanggal transaksi mendahului tanggal mulai dompet.');
                $walletsToBackdate[] = $wallet->id;
            }
        }

        if ($data['category_id'] !== null) {
            $category = Ledger::categories($member)->where('id', $data['category_id'])->where('type', $type)->first();
            Ledger::check($category && (! $category->archived_at || (string) $category->id === (string) ($before->category_id ?? '')),
                'category_id', 'Pilih kategori aktif sesuai jenis transaksi.');
        }

        if ($walletsToBackdate !== []) {
            Ledger::scoped('wallets', $member)->whereIn('id', $walletsToBackdate)
                ->update(['opened_on' => $data['occurred_on'], 'updated_at' => now()]);
        }

        return $data;
    }

    private function normalizeMoney(Request $request, string ...$fields): void
    {
        foreach ($fields as $field) {
            $value = $request->input($field);
            if (is_string($value) && preg_match('/\A-?(?:[0-9]+|[0-9]{1,3}(?:\.[0-9]{3})+)\z/', $value)) {
                $digits = ltrim(str_replace('.', '', ltrim($value, '-')), '0');
                $negative = str_starts_with($value, '-') && ($digits !== '' || $field !== 'opening_balance');
                $request->merge([$field => ($negative ? '-' : '').($digits ?: '0')]);
            }
        }
    }

    private function revision(HouseholdMembership $member, int $id, string $action, ?object $before, object $after): void
    {
        DB::table('transaction_revisions')->insert([
            'transaction_id' => $id,
            'actor_membership_id' => $member->id,
            'action' => $action,
            'before_snapshot' => $before ? json_encode($before, JSON_THROW_ON_ERROR) : null,
            'after_snapshot' => json_encode($after, JSON_THROW_ON_ERROR),
            'created_at' => now(),
        ]);
    }
}
