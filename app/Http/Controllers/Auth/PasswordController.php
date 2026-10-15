<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    /**
     * Update the user's password.
     *
     * Accounts created through Google sign-in have no password to confirm, so
     * for them this is a first-time "set a password" instead of a change. The
     * session itself is the proof of identity in that case, exactly as it is
     * for the rest of the profile page.
     */
    public function update(Request $request): RedirectResponse
    {
        $hasPassword = $request->user()->hasPassword();

        $validated = $request->validateWithBag('updatePassword', [
            'current_password' => $hasPassword ? ['required', 'current_password'] : ['nullable'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        return back()->with('status', $hasPassword ? 'password-updated' : 'password-created');
    }
}
