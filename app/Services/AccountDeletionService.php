<?php

namespace App\Services;

use App\Models\AdminModel;
use App\Models\ChatSession;
use App\Models\User;
use App\Services\Support\SupportQueueService;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AccountDeletionService
{
    public const GRACE_DAYS = 5;

    public function requestDeletion(User $user, ?bool $gracePeriod = null, ?AdminModel $admin = null, string $reason = '', ?string $confirmEmail = null): bool
    {
        $gracePeriod ??= app(SystemSettingsService::class)->gracePeriodEnabled();

        return DB::transaction(function () use ($user, $gracePeriod, $admin, $reason, $confirmEmail): bool {
            $lockedUser = User::withTrashed()->lockForUpdate()->findOrFail($user->id);
            if ($confirmEmail !== null && mb_strtolower(trim($confirmEmail)) !== mb_strtolower($lockedUser->email)) {
                throw ValidationException::withMessages(['confirm_email' => 'That email address does not match the selected customer.']);
            }
            $before = ['user_id' => $lockedUser->id, 'deleted_at' => $lockedUser->deleted_at?->toIso8601String()];
            $lockedUser->setRememberToken(null);
            $lockedUser->save();
            $this->revokeAccess($lockedUser);
            app(SupportQueueService::class)->closeOpenInquiriesForUser($lockedUser->id);

            if ($gracePeriod) {
                if (! $lockedUser->trashed()) {
                    $lockedUser->delete();
                }
            } else {
                $this->permanentlyDelete($lockedUser);
            }
            if ($admin) {
                AdminAuditService::recordAction($lockedUser, $gracePeriod ? 'account_deletion_scheduled' : 'account_permanently_deleted', $before, [
                    'user_id' => $lockedUser->id,
                    'reason' => $reason,
                    'deadline' => $gracePeriod ? $lockedUser->deleted_at->copy()->addDays(self::GRACE_DAYS)->toIso8601String() : null,
                ], $admin);
            }
            Log::info($gracePeriod ? 'account.deletion_requested' : 'account.permanently_deleted', ['user_id' => $user->id]);

            return ! $gracePeriod;
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

            $this->revokeAccess($user);
            app(SupportQueueService::class)->closeOpenInquiriesForUser($userId);
            $this->permanentlyDelete($user);
            Log::info('account.permanently_deleted', ['user_id' => $userId]);

            return true;
        });
    }

    private function revokeAccess(User $user): void
    {
        // Both guards share this table and may have overlapping numeric account IDs.
        $adminKey = auth('admin')->getName();
        $customerKey = auth('web')->getName();
        DB::table('sessions')->where('user_id', $user->id)->select('id', 'payload')
            ->chunkById(100, function ($sessions) use ($adminKey, $customerKey): void {
                $ids = [];
                foreach ($sessions as $session) {
                    $payload = base64_decode($session->payload, true) ?: '';
                    if (config('session.encrypt') && $payload !== '') {
                        try {
                            $payload = Crypt::decrypt($payload);
                        } catch (DecryptException) {
                            $payload = '';
                        }
                    }
                    if (is_string($payload) && str_contains($payload, $adminKey) && ! str_contains($payload, $customerKey)) {
                        continue;
                    }
                    $ids[] = $session->id;
                }
                DB::table('sessions')->whereIn('id', $ids)->delete();
            });
        DB::table('password_reset_tokens')->where('email', $user->email)->delete();
    }

    private function permanentlyDelete(User $user): void
    {
        $user->notifications()->delete();
        // Retained support history must not become a reclaimable guest conversation.
        ChatSession::where('user_id', $user->id)->eachById(function (ChatSession $session): void {
            $session->update(['session_token' => Str::random(64)]);
        });
        $user->forceDelete();
    }
}
