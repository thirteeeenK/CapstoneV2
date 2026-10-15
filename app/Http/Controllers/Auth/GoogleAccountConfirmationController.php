<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\AccountSecurityConfirmationRequest;
use App\Models\User;
use App\Services\AccountSecurityService;
use App\Services\SocialAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Symfony\Component\HttpFoundation\RedirectResponse as ProviderRedirect;
use Throwable;

class GoogleAccountConfirmationController extends Controller
{
    public function __construct(private AccountSecurityService $security) {}

    public function start(AccountSecurityConfirmationRequest $request): ProviderRedirect|RedirectResponse
    {
        if (! filled(config('services.google.client_id')) || ! filled(config('services.google.client_secret'))) {
            return to_route('profile.edit')->withErrors(['confirmation' => 'Google confirmation is temporarily unavailable.']);
        }
        $data = $request->validated();
        $this->security->begin($request, $data['purpose'], array_intersect_key($data, array_flip(['name', 'email'])));
        $previousState = $request->session()->pull('state');
        try {
            $redirect = $this->provider()->with(['prompt' => 'select_account'])->redirect();
            $this->security->rememberState($request, $request->session()->pull('state'));
        } catch (Throwable $exception) {
            $this->security->cancel($request);
            Log::warning('auth.google.confirmation_start_failed', ['type' => $exception::class]);

            return to_route('profile.edit')->withErrors(['confirmation' => 'Could not start Google confirmation. Please try again.']);
        } finally {
            $request->session()->forget('state');
            if ($previousState !== null) {
                $request->session()->put('state', $previousState);
            }
        }

        return $redirect;
    }

    public function callback(Request $request, SocialAuthService $socialAuth): RedirectResponse
    {
        $previousState = $request->session()->pull('state');
        $stage = 'pending';
        try {
            $operation = $this->security->pending($request);
            $state = $request->input('state');
            if (! is_string($state) || ! is_string($operation['state']) || ! hash_equals($operation['state'], $state)) {
                throw new \RuntimeException('Invalid confirmation state.');
            }
            $request->session()->put('state', $operation['state']);
            $stage = 'provider';
            $google = $this->provider()->user();
            if (! $socialAuth->hasVerifiedEmail($google)) {
                throw new \RuntimeException('Unverified provider identity.');
            }
            $stage = 'identity';
            $this->security->confirm($request, $google->getId());
        } catch (Throwable $exception) {
            $this->security->cancel($request);
            Log::notice('auth.google.confirmation_failed', ['user_id' => $request->user()->id, 'type' => $exception::class, 'stage' => $stage]);

            return to_route('profile.edit')->withErrors(['confirmation' => 'Google confirmation failed. Choose your linked Google account and try again.']);
        } finally {
            $request->session()->forget('state');
            if ($previousState !== null) {
                $request->session()->put('state', $previousState);
            }
        }

        return to_route('account-security.show');
    }

    public function show(Request $request): View|RedirectResponse
    {
        try {
            $operation = $this->security->pending($request, confirmed: true);
        } catch (ValidationException) {
            return to_route('profile.edit')->withErrors(['confirmation' => 'Confirmation expired. Please start again.']);
        }

        return view('profile.confirm-account-change', compact('operation'));
    }

    public function commit(Request $request): RedirectResponse
    {
        $this->security->consume($request, 'change-email', $request->input('confirmation_id'), function (User $user, array $operation): void {
            $data = Validator::make($operation['data'], [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)->ignore($user->id)],
            ])->validate();
            $user->fill($data);
            if ($user->isDirty('email')) {
                $user->email_verified_at = null;
            }
            $user->save();
        });

        return to_route('profile.edit')->with('status', 'profile-updated');
    }

    public function cancel(Request $request): RedirectResponse
    {
        $this->security->cancel($request);

        return to_route('profile.edit');
    }

    private function provider(): GoogleProvider
    {
        return Socialite::buildProvider(GoogleProvider::class, array_merge(config('services.google'), [
            'redirect' => config('services.google.reauth_redirect'),
        ]));
    }
}
