<?php

namespace App\Http\Controllers\AdminAuth;

use App\Http\Controllers\Controller;
use App\Models\AdminModel;
use App\Models\FailedLoginAttempt;
use App\Services\AdminTwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TwoFactorChallengeController extends Controller
{
    public function __construct(protected AdminTwoFactorService $twoFactor) {}

    /**
     * Show the TOTP / recovery-code challenge for a password-verified admin.
     */
    public function create(Request $request): View|RedirectResponse
    {
        if (! $this->pendingAdmin($request) instanceof AdminModel) {
            return redirect()->route('admin.login');
        }

        return view('adminAuth.two-factor-challenge');
    }

    /**
     * Verify the second factor and complete the admin login.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'max:32'],
        ]);

        $admin = $this->pendingAdmin($request);

        if (! $admin instanceof AdminModel) {
            return redirect()->route('admin.login');
        }

        // ponytail: 10-minute pending window, no extra table or job when session expiry covers the rest.
        $issuedAt = (int) $request->session()->get('admin_2fa_pending_at', 0);
        if ($issuedAt <= 0 || now()->timestamp - $issuedAt > 600) {
            $this->clearPending($request);

            throw ValidationException::withMessages([
                'code' => 'Your verification window expired. Please sign in again.',
            ]);
        }

        $throttleKey = 'admin-2fa:'.$admin->getKey().'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'code' => trans('auth.throttle', [
                    'seconds' => $seconds,
                    'minutes' => ceil($seconds / 60),
                ]),
            ]);
        }

        // Fail closed: password was valid but 2FA state is unreadable.
        if (! $admin->hasEnabledTwoFactor()) {
            $this->clearPending($request);

            return redirect()->route('admin.login');
        }

        $secret = $this->twoFactor->decryptSecret($admin->two_factor_secret);
        $code = trim($request->string('code'));

        $passed = $secret !== null && $this->twoFactor->verify($secret, $code);

        if (! $passed && preg_match('/^[A-Za-z0-9-]{6,16}$/', $code)) {
            $remaining = $this->twoFactor->consumeRecoveryCode(
                strtoupper($code),
                $this->twoFactor->decryptHashedCodes($admin->two_factor_recovery_codes) ?? []
            );

            if ($remaining !== null) {
                $admin->forceFill([
                    'two_factor_recovery_codes' => $this->twoFactor->encryptHashedList($remaining),
                ])->save();

                Log::info('admin.mfa.recovery-used', ['admin_id' => $admin->getKey(), 'ip' => $request->ip()]);

                $passed = true;
            }
        }

        if (! $passed) {
            RateLimiter::hit($throttleKey);

            Log::warning('admin.mfa.failed', ['admin_id' => $admin->getKey(), 'ip' => $request->ip()]);

            FailedLoginAttempt::create([
                'email' => $admin->email,
                'ip_address' => $request->ip(),
                'guard' => 'admin',
                'attempted_at' => now(),
            ]);

            throw ValidationException::withMessages([
                'code' => 'The verification code is invalid.',
            ]);
        }

        RateLimiter::clear($throttleKey);

        Log::info('admin.mfa.verified', ['admin_id' => $admin->getKey(), 'ip' => $request->ip()]);

        $remember = (bool) $request->session()->get('admin_2fa_remember', false);
        $this->clearPending($request);

        Auth::guard('admin')->login($admin, $remember);
        $request->session()->regenerate();

        return redirect()->intended(route('admin.dashboard', absolute: false));
    }

    protected function pendingAdmin(Request $request): ?AdminModel
    {
        $id = $request->session()->get('admin_2fa_pending');

        if (! is_numeric($id)) {
            return null;
        }

        return AdminModel::find($id);
    }

    protected function clearPending(Request $request): void
    {
        $request->session()->forget(['admin_2fa_pending', 'admin_2fa_remember', 'admin_2fa_pending_at']);
    }
}
