<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AccountDeletionService;
use App\Services\RegistrationAbuseService;
use App\Services\SocialAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\AbstractUser;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Throwable;

class GoogleAuthController extends Controller
{
    public function __construct(
        protected SocialAuthService $socialAuth,
        protected AccountDeletionService $deletionService,
    ) {}

    /**
     * Hand the traveler over to Google's consent screen.
     */
    public function redirect(): SymfonyRedirect|RedirectResponse
    {
        if (! $this->isConfigured()) {
            return $this->unavailable();
        }

        return Socialite::driver('google')->redirect();
    }

    /**
     * Receive the traveler back from Google and put them into a session.
     */
    public function callback(Request $request, RegistrationAbuseService $abuse): RedirectResponse
    {
        if (! $this->isConfigured()) {
            return $this->unavailable();
        }

        try {
            /** @var AbstractUser $googleUser */
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable $exception) {
            // Covers a denied consent screen, an expired state token and any
            // network failure reaching Google — none of which should surface
            // as a stack trace on the login page.
            Log::warning('auth.google.callback_failed', [
                'ip' => $request->ip(),
                'reason' => $exception->getMessage(),
            ]);

            return redirect()->route('login')
                ->with('error', 'We could not finish signing you in with Google. Please try again.');
        }

        if (! $this->socialAuth->hasVerifiedEmail($googleUser)) {
            Log::warning('auth.google.unverified_email', ['ip' => $request->ip()]);

            return redirect()->route('login')
                ->with('error', 'Google has not confirmed that email address. Please verify it with Google, or sign in with your password.');
        }

        $existing = $this->socialAuth->findExistingForGoogle($googleUser);

        if ($existing) {
            if (filled($existing->google_id) && $existing->google_id !== $googleUser->getId()) {
                return redirect()->route('login')->with('error', 'Please use the Google account already linked to this profile.');
            }
            if (! $this->deletionService->recoverForLogin($existing)) {
                return redirect()->route('login')
                    ->with('error', 'That account has been closed. Please create a new one to continue.');
            }

            if ($existing->isBanned()) {
                return redirect()->route('account.suspended')
                    ->with('suspended', $existing->banNotice());
            }

            $user = $this->socialAuth->linkGoogleAccount($existing, $googleUser);
            $isNewAccount = false;
        } else {
            $abuse->reserve($request->ip());
            $user = $this->socialAuth->createFromGoogle($googleUser, $request);
            $isNewAccount = true;
        }

        // There is no "keep me signed in" checkbox on the Google button, and
        // re-consenting with Google is a single click, so the session is kept.
        Auth::login($user, remember: true);

        $request->session()->regenerate();

        if ($isNewAccount) {
            return redirect()->route('onboarding.index')
                ->with('success', 'Welcome to SunnyTrips, '.$user->name.' — let\'s personalize your escape.');
        }

        return redirect()->intended(route('dashboard', absolute: false))
            ->with('show_chat_guide', true);
    }

    /**
     * Google sign-in is optional: without credentials the button is hidden, so
     * reaching these routes means a stale link or a hand-typed URL.
     */
    protected function isConfigured(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'));
    }

    protected function unavailable(): RedirectResponse
    {
        return redirect()->route('login')
            ->with('error', 'Google sign-in is not available right now. Please sign in with your email and password.');
    }
}
