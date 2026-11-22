<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Marketing email consent: the signed unsubscribe link in every promotional
 * email (no sign-in needed, and one-click for mail clients), plus the
 * profile page's email preferences.
 */
class MarketingPreferenceController extends Controller
{
    /**
     * Confirmation page for the unsubscribe link. Opening it changes nothing,
     * so link scanners in mail clients cannot unsubscribe anyone by accident.
     */
    public function confirm(User $user): View
    {
        return view('marketing.unsubscribe', ['user' => $user, 'done' => ! $user->trip_marketing_enabled]);
    }

    /**
     * Withdraw marketing consent. Also the RFC 8058 one-click endpoint, which
     * mail clients call without a session, so it is exempt from CSRF and
     * protected by the link's signature instead.
     */
    public function unsubscribe(Request $request, User $user): View
    {
        $user->setMarketingConsent(false);

        return view('marketing.unsubscribe', ['user' => $user, 'done' => true]);
    }

    /**
     * Save the signed-in traveler's email preferences from the profile page.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate(['reminders' => ['required', 'boolean'], 'marketing' => ['required', 'boolean']]);
        $user = $request->user();
        $user->forceFill(['trip_reminders_enabled' => (bool) $validated['reminders']])->save();
        $user->setMarketingConsent((bool) $validated['marketing']);

        return redirect()->to(route('profile.edit').'#email-preferences')->with('status', 'email-preferences-updated');
    }
}
