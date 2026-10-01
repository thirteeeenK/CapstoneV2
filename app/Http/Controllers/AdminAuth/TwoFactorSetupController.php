<?php

namespace App\Http\Controllers\AdminAuth;

use App\Http\Controllers\Controller;
use App\Services\AdminTwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TwoFactorSetupController extends Controller
{
    public function __construct(protected AdminTwoFactorService $twoFactor) {}

    /**
     * Show enrollment QR for admins without 2FA, or manage/reset for enabled ones.
     */
    public function create(Request $request): View
    {
        $admin = $request->user('admin');

        if ($admin->hasEnabledTwoFactor()) {
            return view('adminAuth.two-factor-setup', [
                'enabled' => true,
                'codes' => $request->session()->get('admin_2fa_codes', []),
            ]);
        }

        $secret = $this->twoFactor->decryptSecret($request->session()->get('admin_2fa_setup_secret'));

        if ($secret === null) {
            $secret = $this->twoFactor->generateSecret();
            $request->session()->put('admin_2fa_setup_secret', $this->twoFactor->encryptSecret($secret));
        }

        $otpauthUrl = $this->twoFactor->qrCodeUrl($admin->email, $secret);

        return view('adminAuth.two-factor-setup', [
            'enabled' => false,
            'qrSvg' => $this->twoFactor->qrCodeSvg($otpauthUrl),
            'manualKey' => $secret,
            'codes' => [],
        ]);
    }

    /**
     * Confirm enrollment with password + a valid TOTP code, then issue recovery codes.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'string'],
            'code' => ['required', 'string', 'size:6'],
        ]);

        $admin = $request->user('admin');
        $secret = $this->twoFactor->decryptSecret($request->session()->get('admin_2fa_setup_secret'));

        if ($secret === null) {
            throw ValidationException::withMessages([
                'code' => 'Your setup session expired. Reload this page and try again.',
            ]);
        }

        if (! Hash::check($request->string('password'), $admin->password)) {
            throw ValidationException::withMessages([
                'password' => 'The password is incorrect.',
            ]);
        }

        if (! $this->twoFactor->verify($secret, trim($request->string('code')))) {
            throw ValidationException::withMessages([
                'code' => 'The verification code is invalid.',
            ]);
        }

        $codes = $this->twoFactor->generateRecoveryCodes();

        $admin->forceFill([
            'two_factor_secret' => $this->twoFactor->encryptSecret($secret),
            'two_factor_recovery_codes' => $this->twoFactor->encryptHashedCodes($codes),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $request->session()->forget('admin_2fa_setup_secret');
        $request->session()->put('admin_2fa_codes', $codes);

        Log::info('admin.mfa.enabled', ['admin_id' => $admin->getKey(), 'ip' => $request->ip()]);

        return redirect()->route('admin.two-factor.setup')->with('success', 'Two-factor authentication is now protecting your account. Save these recovery codes — each works once.');
    }

    /**
     * Disable 2FA after password confirmation.
     *
     * @throws ValidationException
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'string'],
        ]);

        $admin = $request->user('admin');

        if (! Hash::check($request->string('password'), $admin->password)) {
            throw ValidationException::withMessages([
                'password' => 'The password is incorrect.',
            ]);
        }

        $admin->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        $request->session()->forget(['admin_2fa_setup_secret', 'admin_2fa_codes']);

        Log::warning('admin.mfa.disabled', ['admin_id' => $admin->getKey(), 'ip' => $request->ip()]);

        return redirect()->route('admin.two-factor.setup')->with('success', 'Two-factor authentication was turned off. Re-enroll now — it is required for admin access.');
    }

    /**
     * Regenerate recovery codes after password confirmation. Old codes stop working.
     *
     * @throws ValidationException
     */
    public function regenerateCodes(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'string'],
        ]);

        $admin = $request->user('admin');

        if (! Hash::check($request->string('password'), $admin->password)) {
            throw ValidationException::withMessages([
                'password' => 'The password is incorrect.',
            ]);
        }

        $codes = $this->twoFactor->generateRecoveryCodes();

        $admin->forceFill([
            'two_factor_recovery_codes' => $this->twoFactor->encryptHashedCodes($codes),
        ])->save();

        $request->session()->put('admin_2fa_codes', $codes);

        Log::info('admin.mfa.codes-regenerated', ['admin_id' => $admin->getKey(), 'ip' => $request->ip()]);

        return redirect()->route('admin.two-factor.setup')->with('success', 'New recovery codes issued. Your old codes no longer work.');
    }
}
