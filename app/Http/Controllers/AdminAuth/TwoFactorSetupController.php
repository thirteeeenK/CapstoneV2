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
            'two_factor_required' => true,
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
            'disable_password' => ['required', 'string'],
            'code' => ['required', 'string', 'max:32'],
        ]);

        $admin = $request->user('admin');

        if (! Hash::check($request->string('disable_password'), $admin->password)) {
            throw ValidationException::withMessages([
                'disable_password' => 'The password is incorrect.',
            ]);
        }

        $code = trim($request->string('code'));
        $secret = $this->twoFactor->decryptSecret($admin->two_factor_secret);
        $validCode = $secret !== null && $this->twoFactor->verify($secret, $code);

        if (! $validCode && preg_match('/^[A-Za-z0-9-]{6,16}$/', $code)) {
            $validCode = $this->twoFactor->consumeRecoveryCode(
                strtoupper($code),
                $this->twoFactor->decryptHashedCodes($admin->two_factor_recovery_codes) ?? []
            ) !== null;
        }

        if (! $validCode) {
            Log::warning('admin.mfa.disable-failed', ['admin_id' => $admin->getKey(), 'ip' => $request->ip()]);

            throw ValidationException::withMessages([
                'code' => 'The verification code is invalid.',
            ]);
        }

        $admin->forceFill([
            'two_factor_required' => false,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        $request->session()->forget([
            'admin_2fa_pending',
            'admin_2fa_remember',
            'admin_2fa_pending_at',
            'admin_2fa_setup_secret',
            'admin_2fa_codes',
        ]);
        $request->session()->regenerate();

        Log::warning('admin.mfa.disabled', ['admin_id' => $admin->getKey(), 'ip' => $request->ip()]);

        return redirect()->route('admin.dashboard')->with('two_factor_disabled', 'Two-factor authentication was turned off for your account. You can turn it on again at any time.');
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
