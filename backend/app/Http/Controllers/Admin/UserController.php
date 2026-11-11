<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\InsufficientFunds;
use App\Http\Controllers\Controller;
use App\Models\AgentProfile;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Ledger;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $f = $request->only('q', 'role', 'status');
        $q = User::with('wallet:id,user_id,balance', 'agentProfile')->latest();
        if (! empty($f['role'])) {
            $q->where('role', $f['role']);
        }
        if (! empty($f['status'])) {
            $q->where('status', $f['status']);
        }
        if ($term = trim($f['q'] ?? '')) {
            $q->where(fn ($w) => $w->where('phone', 'like', "%{$term}%")->orWhere('name', 'like', "%{$term}%")
                ->orWhereHas('agentProfile', fn ($a) => $a->where('business_name', 'like', "%{$term}%")));
        }

        return Inertia::render('Admin/Users/Index', [
            'users' => $q->paginate(30)->withQueryString()->through(fn (User $u) => [
                'id' => $u->id, 'name' => $u->name, 'phone' => $u->phone, 'role' => $u->role, 'status' => $u->status,
                'balance' => $u->wallet?->balance ?? 0, 'business' => $u->agentProfile?->business_name,
                'tier' => $u->agentProfile?->commission_tier, 'last_login_at' => $u->last_login_at?->toIso8601String(),
                'created_at' => $u->created_at->toIso8601String(),
            ]),
            'filters' => $f,
        ]);
    }

    public function show(User $user)
    {
        $user->load('wallet', 'virtualAccount', 'agentProfile');
        $wallet = $user->walletOrCreate();

        return Inertia::render('Admin/Users/Show', [
            'user' => $user->only('id', 'name', 'phone', 'role', 'status', 'locale', 'device_id') + [
                'has_pin' => $user->hasPin(),
                'pin_locked_until' => $user->pin_locked_until?->toIso8601String(),
                'two_factor' => (bool) $user->two_factor_confirmed_at,
                'created_at' => $user->created_at->toIso8601String(),
                'last_login_at' => $user->last_login_at?->toIso8601String(),
                'virtual_account' => $user->virtualAccount?->only('bank', 'account_number', 'account_name'),
                'agent' => $user->agentProfile?->only('business_name', 'location', 'commission_tier'),
                'spent_today' => $user->spentToday(),
                'daily_limit' => $user->dailyLimit(),
            ],
            'wallet' => ['balance' => $wallet->balance, 'ledger' => $wallet->ledgerBalance()],
            'entries' => $wallet->entries()->latest('id')->paginate(25, ['*'], 'entries')->withQueryString(),
            'transactions' => $user->transactions()->with('service')->latest()->limit(15)->get()->map->toApi(),
        ]);
    }

    /** Manual credit or debit. Always a ledger entry with the admin and reason recorded. */
    public function adjust(Request $request, User $user, Ledger $ledger)
    {
        $data = $request->validate([
            'type' => 'required|in:credit,debit',
            'amount' => 'required|numeric|min:1|max:5000000',
            'reason' => 'required|string|min:8|max:200',
        ]);
        $kobo = (int) round($data['amount'] * 100);
        $ref = 'ADM-'.strtoupper(Str::random(10));
        $extra = ['description' => $data['reason'], 'created_by' => $request->user()->id];

        try {
            $data['type'] === 'credit'
                ? $ledger->credit($user, $kobo, $ref, 'manual_credit', $extra)
                : $ledger->debit($user, $kobo, $ref, 'manual_debit', $extra);
        } catch (InsufficientFunds) {
            throw ValidationException::withMessages(['amount' => 'The wallet does not have that much.']);
        }
        AuditLog::record("wallet.manual_{$data['type']}", $user, ['amount' => $kobo, 'reference' => $ref, 'reason' => $data['reason']]);

        return back()->with('success', ucfirst($data['type']).' of ₦'.number_format($data['amount'], 2).' posted as '.$ref.'.');
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => 'nullable|string|max:80',
            'role' => 'required|in:customer,agent,admin',
            'status' => 'required|in:active,suspended',
            'business_name' => 'required_if:role,agent|nullable|string|max:120',
            'location' => 'nullable|string|max:120',
            'commission_tier' => 'required_if:role,agent|nullable|in:bronze,silver,gold',
        ]);
        if ($user->is($request->user()) && ($data['role'] !== 'admin' || $data['status'] !== 'active')) {
            throw ValidationException::withMessages(['role' => 'You cannot demote or suspend yourself.']);
        }
        $before = $user->only('name', 'role', 'status') + ['agent' => $user->agentProfile?->only('business_name', 'location', 'commission_tier')];

        $user->forceFill(['name' => $data['name'], 'role' => $data['role'], 'status' => $data['status']])->save();
        if ($data['role'] === 'agent') {
            AgentProfile::updateOrCreate(['user_id' => $user->id], [
                'business_name' => $data['business_name'], 'location' => $data['location'] ?? null, 'commission_tier' => $data['commission_tier'],
            ]);
        }
        if ($data['status'] === 'suspended') {
            $user->tokens()->delete();
        }
        AuditLog::record('user.updated', $user, ['before' => $before, 'after' => $data]);

        return back()->with('success', 'User updated.');
    }

    public function unlockPin(User $user)
    {
        $user->forceFill(['pin_attempts' => 0, 'pin_locked_until' => null])->save();
        AuditLog::record('user.pin_unlocked', $user);

        return back()->with('success', 'PIN unlocked.');
    }
}
