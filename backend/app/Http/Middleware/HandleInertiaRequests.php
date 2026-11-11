<?php

namespace App\Http\Middleware;

use App\Models\SupportTicket;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => $user ? [
                'id' => $user->id,
                'name' => $user->name,
                'phone' => $user->phone,
                'role' => $user->role,
                'locale' => $user->locale,
                'has_pin' => $user->hasPin(),
            ] : null,
            // Payments simulated: show the sandbox banner and "simulate transfer" buttons.
            'sandbox' => config('haske.drivers.payments') === 'fake',
            'billsSandbox' => config('haske.drivers.bills') === 'fake',
            'vtpassSandbox' => config('haske.drivers.bills') === 'live' && str_contains((string) config('haske.vtpass.base_url'), 'sandbox'),
            'escalated' => fn () => $user?->isAdmin()
                ? Transaction::where('status', 'pending')->whereNotNull('escalated_at')->count()
                : null,
            'openTickets' => fn () => $user?->isAdmin() ? SupportTicket::where('status', 'open')->count() : null,
            'support' => config('haske.support_whatsapp'),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'dev_code' => fn () => $request->session()->get('dev_code'),
            ],
        ];
    }
}
