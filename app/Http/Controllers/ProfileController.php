<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Services\AccountDeletionService;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     *
     * Google sign-in accounts have no password to confirm, so they type their
     * own email address instead. The step exists to make the action deliberate
     * rather than to re-authenticate, which the session already did.
     */
    public function destroy(Request $request, AccountDeletionService $deletionService): RedirectResponse
    {
        $user = $request->user();

        if ($user->hasPassword()) {
            $request->validateWithBag('userDeletion', [
                'password' => ['required', 'current_password'],
            ]);
        } else {
            $request->validateWithBag('userDeletion', [
                'confirm_email' => [
                    'required',
                    'string',
                    function (string $attribute, mixed $value, Closure $fail) use ($user): void {
                        if (mb_strtolower(trim((string) $value)) !== mb_strtolower($user->email)) {
                            $fail('That email address does not match this account.');
                        }
                    },
                ],
            ]);
        }

        $deletionService->requestDeletion($user);

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/')
            ->with('account_deletion_requested', true)
            ->with('account_permanently_deleted', ! config('auth.account_deletion_grace_period'));
    }
}
