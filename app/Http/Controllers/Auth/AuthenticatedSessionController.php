<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $redirect = $request->input('redirect');
        if (is_string($redirect) && $this->isSafeLocalRedirect($redirect)) {
            return redirect($redirect);
        }

        return redirect()->intended(route('dashboard', absolute: false));
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
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
