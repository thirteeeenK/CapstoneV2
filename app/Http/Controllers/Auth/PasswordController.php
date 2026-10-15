<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AccountSecurityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    /**
     * Update the user's password.
     *
     * Google-only accounts must confirm their linked identity before adding
     * their first password; the application session alone is insufficient.
     */
    public function update(Request $request, AccountSecurityService $security): RedirectResponse
    {
        $hasPassword = $request->user()->hasPassword();

        $validated = $request->validateWithBag('updatePassword', [
            'current_password' => $hasPassword ? ['required', 'current_password'] : ['nullable'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        if ($hasPassword) {
            $request->user()->update(['password' => Hash::make($validated['password'])]);
        } else {
            $security->consume($request, 'set-password', $request->input('confirmation_id'), function (User $user) use ($validated): void {
                $user->update(['password' => Hash::make($validated['password'])]);
            });
        }

        return ($hasPassword ? back() : to_route('profile.edit'))
            ->with('status', $hasPassword ? 'password-updated' : 'password-created');
    }
}
