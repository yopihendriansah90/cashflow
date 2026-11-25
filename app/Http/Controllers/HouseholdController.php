<?php

namespace App\Http\Controllers;

use App\Models\Household;
use App\Models\HouseholdJoinCode;
use App\Models\HouseholdMembership;
use App\Models\User;
use App\Support\Ledger;
use App\Support\MonthlyLedger;
use Brick\Math\BigInteger;
use Closure;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class HouseholdController extends Controller
{
    public function onboarding(Request $request): View|RedirectResponse
    {
        return $request->user()->activeMembership()->exists()
            ? redirect()->route('dashboard')
            : view('pages.households.onboarding');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateCashflow($request, ['name' => ['required', 'string', 'max:120']]);

        DB::transaction(function () use ($request, $data): void {
            $this->lockUnassignedUser($request->user());
            Household::create($data)->memberships()->create([
                'user_id' => $request->user()->id,
                'role' => HouseholdMembership::OWNER,
            ]);
        }, 3);

        return redirect()->route('dashboard');
    }

    public function join(Request $request): RedirectResponse
    {
        $data = $this->validateCashflow($request, ['code' => ['required', 'string', 'regex:/\A[a-fA-F0-9]{32}\z/']], [
            'code.regex' => __('Kode undangan tidak valid atau sudah tidak dapat digunakan.'),
        ]);
        $hash = hash('sha256', strtolower($data['code']));

        DB::transaction(function () use ($request, $hash): void {
            $this->lockUnassignedUser($request->user());
            $candidate = HouseholdJoinCode::where('code_hash', $hash)->first();

            if (! $candidate) {
                $this->invalidCode();
            }

            $household = Household::lockForUpdate()->findOrFail($candidate->household_id);
            $code = $household->joinCodes()->lockForUpdate()->findOrFail($candidate->id);
            $now = now();

            if ($code->used_at || $code->revoked_at || $code->expires_at->lte($now) || $this->activePartner($household)) {
                $this->invalidCode();
            }

            $household->memberships()->create([
                'user_id' => $request->user()->id,
                'role' => HouseholdMembership::PARTNER,
            ]);
            $code->update(['used_at' => $now]);
        }, 3);

        return redirect()->route('dashboard');
    }

    public function dashboard(Request $request): View
    {
        $membership = $request->attributes->get('membership');
        $report = MonthlyLedger::fromRequest($request);
        $month = $report->month;
        $summary = $report->summary();
        $balances = Ledger::balances($membership, Ledger::today());
        $total = (string) BigInteger::sum(0, ...$balances);
        $negative = collect($balances)->contains(fn ($amount) => BigInteger::of($amount)->isNegative());
        $hasWallet = Ledger::scoped('wallets', $membership)->whereNull('archived_at')->exists();
        $recent = Ledger::transactions($membership)->orderByDesc('occurred_on')->orderByDesc('id')->limit(5)->get();

        return view('pages.households.dashboard', compact('membership', 'month', 'summary', 'total', 'negative', 'hasWallet', 'recent'));
    }

    public function settings(Request $request): Response
    {
        $membership = $request->attributes->get('membership');
        $household = $membership->household;

        $activeCodes = $this->pendingCodes($household)->where('expires_at', '>', now());
        $encrypted = $request->session()->pull('join_code');
        $code = $encrypted && $membership->role === HouseholdMembership::OWNER ? decrypt($encrypted) : null;
        if ($code && ! (clone $activeCodes)->where('code_hash', hash('sha256', $code))->exists()) {
            $code = null;
        }

        return response()->view('pages.households.settings', [
            'membership' => $membership,
            'household' => $household,
            'partner' => $household->memberships()->where('active_role', HouseholdMembership::PARTNER)->with('user')->first(),
            'hasCode' => $activeCodes->exists(),
            'code' => $code,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function issueCode(Request $request): RedirectResponse
    {
        $code = $this->withMembership($request, HouseholdMembership::OWNER, function (Household $household, HouseholdMembership $membership): string {
            if ($this->activePartner($household)) {
                throw ValidationException::withMessages(['household' => __('Rumah tangga sudah memiliki pasangan.')]);
            }

            $this->pendingCodes($household)->update(['revoked_at' => now()]);
            $code = bin2hex(random_bytes(16));
            $household->joinCodes()->create([
                'created_by_membership_id' => $membership->id,
                'code_hash' => hash('sha256', $code),
                'expires_at' => now()->addDay(),
            ]);

            return $code;
        });

        return redirect()->route('households.settings', $request->route('household'))->with('join_code', encrypt($code));
    }

    public function revokeCode(Request $request): RedirectResponse
    {
        $this->withMembership($request, HouseholdMembership::OWNER, function (Household $household): void {
            $this->pendingCodes($household)->update(['revoked_at' => now()]);
        });

        return back()->with('status', __('Kode undangan dicabut.'));
    }

    public function revokePartner(Request $request, string $household, string $partner): RedirectResponse
    {
        $this->withMembership($request, HouseholdMembership::OWNER, function (Household $household) use ($partner): void {
            $membership = $this->activePartner($household);
            abort_unless($membership && (string) $membership->id === $partner, 404);
            $membership->update(['ended_at' => now()]);
        });

        return back()->with('status', __('Akses pasangan dicabut.'));
    }

    public function leave(Request $request): RedirectResponse
    {
        $this->withMembership($request, HouseholdMembership::PARTNER, function (Household $household, HouseholdMembership $membership): void {
            $membership->update(['ended_at' => now()]);
        });

        return redirect()->route('households.onboarding');
    }

    private function lockUnassignedUser(User $user): void
    {
        User::lockForUpdate()->findOrFail($user->id);

        if ($user->activeMembership()->exists()) {
            throw ValidationException::withMessages(['household' => __('Anda sudah menjadi anggota rumah tangga.')]);
        }
    }

    private function withMembership(Request $request, string $role, Closure $action): mixed
    {
        $current = $request->attributes->get('membership');

        return DB::transaction(function () use ($current, $role, $action): mixed {
            // Code and membership changes share the household row lock.
            $household = Household::lockForUpdate()->findOrFail($current->household_id);
            $membership = $household->memberships()->lockForUpdate()->findOrFail($current->id);
            abort_if($membership->ended_at || $membership->role !== $role, 403);

            return $action($household, $membership);
        }, 3);
    }

    private function activePartner(Household $household): ?HouseholdMembership
    {
        return $household->memberships()->where('active_role', HouseholdMembership::PARTNER)->lockForUpdate()->first();
    }

    private function pendingCodes(Household $household): HasMany
    {
        return $household->joinCodes()->whereNull('used_at')->whereNull('revoked_at');
    }

    private function invalidCode(): never
    {
        throw ValidationException::withMessages(['code' => __('Kode undangan tidak valid atau sudah tidak dapat digunakan.')]);
    }
}
