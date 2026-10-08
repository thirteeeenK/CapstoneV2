<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AccountDeletionService
{
    public const GRACE_DAYS = 5;

    public function requestDeletion(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            $lockedUser->setRememberToken(null);
            $lockedUser->save();
            $lockedUser->delete();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();
            Log::info('account.deletion_requested', ['user_id' => $user->id]);
        });
    }

    /**
     * Called only after the authentication guard has verified the password.
     */
    public function recoverForLogin(User $user): bool
    {
        return DB::transaction(function () use ($user): bool {
            $lockedUser = User::withTrashed()->lockForUpdate()->find($user->id);

            if (! $lockedUser) {
                return false;
            }

            if ($lockedUser->trashed()) {
                if ($lockedUser->deleted_at->copy()->addDays(self::GRACE_DAYS)->lessThanOrEqualTo(now())) {
                    return false;
                }

                if (! $lockedUser->isBanned()) {
                    $lockedUser->restore();
                    $user->setRawAttributes($lockedUser->getAttributes(), true);
                    session()->put('account_recovered', true);
                    Log::info('account.recovered', ['user_id' => $user->id]);
                }
            }

            return true;
        });
    }

    public function deleteIfExpired(int $userId): bool
    {
        return DB::transaction(function () use ($userId): bool {
            $user = User::onlyTrashed()->lockForUpdate()->find($userId);

            if (! $user || $user->deleted_at->copy()->addDays(self::GRACE_DAYS)->isFuture()) {
                return false;
            }

            DB::table('sessions')->where('user_id', $userId)->delete();
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();
            $user->forceDelete();
            Log::info('account.permanently_deleted', ['user_id' => $userId]);

            return true;
        });
    }
}
