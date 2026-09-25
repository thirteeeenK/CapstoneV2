<?php

namespace App\Http\Controllers\AdminAuth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class AdminPasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): View
    {
        return view('adminAuth.forgot-password');
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        // Sync send by design (instant feedback); a mail-server failure must
        // degrade to a friendly error, never a 500.
        try {
            $status = Password::broker('admins')->sendResetLink(
                $request->only('email')
            );
        } catch (Throwable $e) {
            Log::warning('admin password reset link dispatch failed', [
                'email' => $request->only('email'),
                'exception' => $e->getMessage(),
            ]);

            return back()->withInput($request->only('email'))
                ->withErrors(['email' => 'We could not send the reset link right now. Please try again in a moment.']);
        }

        return $status == Password::RESET_LINK_SENT
                    ? back()->with('status', __($status))
                    : back()->withInput($request->only('email'))
                        ->withErrors(['email' => __($status)]);
    }
}
