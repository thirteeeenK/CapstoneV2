<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AccountDeletionService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('accounts:delete-expired')]
#[Description('Permanently delete accounts after their five-day recovery period')]
class DeleteExpiredAccounts extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(AccountDeletionService $deletionService): int
    {
        $deleted = 0;

        User::onlyTrashed()
            ->where('deleted_at', '<=', now()->subDays(AccountDeletionService::GRACE_DAYS))
            ->select('id')
            ->chunkById(100, function ($users) use ($deletionService, &$deleted): void {
                foreach ($users as $user) {
                    if ($deletionService->deleteIfExpired($user->id)) {
                        $deleted++;
                    }
                }
            });

        $this->info("Permanently deleted {$deleted} expired account(s).");

        return self::SUCCESS;
    }
}
