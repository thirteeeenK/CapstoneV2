<?php

namespace App\Http\Controllers\AdminAuth;

use App\Http\Controllers\Controller;
use App\Models\FailedLoginAttempt;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminAuth extends Controller
{
    /**
     * Display the admin login view.
     */
    public function create(): View
    {
        return view('adminAuth.login');
    }

    /**
     * Handle an incoming admin authentication request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = Str::transliterate(Str::lower($request->string('email')).'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'email' => trans('auth.throttle', [
                    'seconds' => $seconds,
                    'minutes' => ceil($seconds / 60),
                ]),
            ]);
        }

        if (! Auth::guard('admin')->attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($throttleKey);

            Log::warning('auth.failed', [
                'email' => $request->input('email'),
                'ip' => $request->ip(),
                'guard' => 'admin',
                'time' => now()->toIso8601String(),
            ]);

            FailedLoginAttempt::create([
                'email' => $request->input('email'),
                'ip_address' => $request->ip(),
                'guard' => 'admin',
                'attempted_at' => now(),
            ]);

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($throttleKey);

        $request->session()->regenerate();

        $redirect = $request->input('redirect');
        if (is_string($redirect) && $this->isSafeLocalRedirect($redirect)) {
            return redirect($redirect);
        }

        return redirect()->intended(route('admin.dashboard', absolute: false));
    }

    /**
     * Only allow redirects that point back into this application's own host.
     */
    protected function isSafeLocalRedirect(string $url): bool
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $base = url('/');

        return $url === $base || Str::startsWith($url, $base.'/');
    }

    /**
     * Destroy an authenticated admin session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('admin')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
