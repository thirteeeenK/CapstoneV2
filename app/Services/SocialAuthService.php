<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\AbstractUser as GoogleProfile;

class SocialAuthService
{
    /**
     * Locate the account a Google profile belongs to, if there is one.
     *
     * The google_id lookup comes first because it survives a later email change
     * on either side. The email lookup is the one-time bridge for travelers who
     * registered with a password before Google sign-in existed, so it includes
     * soft-deleted rows — those sit inside the deletion grace window and must be
     * recovered rather than collide with the unique email index.
     */
    public function findExistingForGoogle(GoogleProfile $googleUser): ?User
    {
        $byGoogleId = User::withTrashed()
            ->where('google_id', $googleUser->getId())
            ->first();

        if ($byGoogleId) {
            return $byGoogleId;
        }

        return User::withTrashed()
            ->where('email', $this->normalizedEmail($googleUser))
            ->first();
    }

    /**
     * Attach the Google identity to an account that already exists, backfilling
     * only the columns the traveler has not set for themselves.
     */
    public function linkGoogleAccount(User $user, GoogleProfile $googleUser): User
    {
        $user->google_id = $googleUser->getId();

        if (blank($user->avatar)) {
            $user->avatar = $this->avatarUrl($googleUser);
        }

        // Google has already proven ownership of the address, so an account that
        // never confirmed its email does not need to be asked again.
        if ($user->email_verified_at === null) {
            $user->email_verified_at = now();
        }

        $user->save();

        Log::info('auth.google.linked', ['user_id' => $user->id]);

        return $user;
    }

    /**
     * Register a brand-new traveler from their Google profile.
     *
     * Google supplies no address or phone number — both are nullable and get
     * collected at checkout instead. The consent columns are stamped here
     * because the "Continue with Google" button carries the age and policy
     * agreement as its own clickwrap notice.
     */
    public function createFromGoogle(GoogleProfile $googleUser, Request $request): User
    {
        $user = DB::transaction(function () use ($googleUser, $request): User {
            $user = User::create([
                'name' => $this->displayName($googleUser),
                'email' => $this->normalizedEmail($googleUser),
                'google_id' => $googleUser->getId(),
                'avatar' => $this->avatarUrl($googleUser),
                'password' => null,

                'terms_accepted_at' => now(),
                'privacy_accepted_at' => now(),
                'ai_disclosure_accepted_at' => now(),

                'terms_version' => config('legal.documents.terms.version'),
                'privacy_version' => config('legal.documents.privacy.version'),
                'ai_disclosure_version' => config('legal.documents.ai_disclosure.version'),

                'consent_ip_address' => $request->ip(),
                'consent_user_agent' => $request->userAgent(),
            ]);

            // Not mass-assignable, and Google has already proven ownership.
            $user->email_verified_at = now();
            $user->save();

            return $user;
        });

        // Keeps Google signups at parity with password signups, which is what
        // drives the demo-mode booking seeder.
        event(new Registered($user));

        Log::info('auth.google.registered', ['user_id' => $user->id]);

        return $user;
    }

    /**
     * Google reports whether it has confirmed ownership of the address. An
     * unconfirmed one must never create or claim an account, or anyone able to
     * set that address on a Workspace domain could take over the matching
     * SunnyTrips account.
     */
    public function hasVerifiedEmail(GoogleProfile $googleUser): bool
    {
        $raw = $googleUser->getRaw();

        return filled($googleUser->getEmail())
            && filter_var($raw['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    protected function normalizedEmail(GoogleProfile $googleUser): string
    {
        return mb_strtolower(trim((string) $googleUser->getEmail()));
    }

    /**
     * Fall back to the local part of the address when the profile scope returns
     * no display name, so the account is never created nameless.
     */
    protected function displayName(GoogleProfile $googleUser): string
    {
        $name = trim((string) ($googleUser->getName() ?: $googleUser->getNickname()));

        if ($name === '') {
            $name = Str::before($this->normalizedEmail($googleUser), '@');
        }

        // Matches the max length the password registration form enforces.
        return mb_substr($name, 0, 100);
    }

    /**
     * Google's picture URLs comfortably fit the column, but a longer one is
     * dropped rather than truncated into a broken link.
     */
    protected function avatarUrl(GoogleProfile $googleUser): ?string
    {
        $avatar = $googleUser->getAvatar();

        return filled($avatar) && mb_strlen($avatar) <= 255 ? $avatar : null;
    }
}
