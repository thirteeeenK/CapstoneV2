<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'address' => ['required', 'string', 'max:255'],
            'phone_number' => ['required', 'string', 'regex:/^09\d{9}$/'],

            // Legal age and consent agreements
            'age_confirmed' => ['required', 'accepted'],
            'terms_accepted' => ['required', 'accepted'],
            'privacy_accepted' => ['required', 'accepted'],
            'ai_disclosure_accepted' => ['required', 'accepted'],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'address' => $request->address,
            'phone_number' => $request->phone_number,

            'terms_accepted_at' => now(),
            'privacy_accepted_at' => now(),
            'ai_disclosure_accepted_at' => now(),

            'terms_version' => config('legal.documents.terms.version'),
            'privacy_version' => config('legal.documents.privacy.version'),
            'ai_disclosure_version' => config('legal.documents.ai_disclosure.version'),

            'consent_ip_address' => $request->ip(),
            'consent_user_agent' => $request->userAgent(),
        ]);

        event(new Registered($user));

        // Auth::login($user);

        // return redirect(route('dashboard', absolute: false));

        return redirect(route('login', absolute: false))->with('success', 'Account created Successfully. Please login to Continue.');

        // return redirect(route('dashboard', absolute: false));
        // instead of redirection to the dashboard after creating account, force the user to login their newly created credentials

        // return redirect(route('login', absolute: false))->with('success', 'Account created successfully. Please login to continue.');
    }
}
