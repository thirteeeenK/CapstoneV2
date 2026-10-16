<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\QrphPaymentAttempt;
use App\Services\Payment\PaymentGatewayException;
use App\Services\Payment\QrphPaymentService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('payments:reconcile-qrph {--gateway=paymongo : paymongo live or qrph sandbox} {--limit=100 : Maximum attempts to check}')]
#[Description('Reconcile existing PayMongo QR Ph intents without creating payments')]
class ReconcileQrphPayments extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(QrphPaymentService $payments): int
    {
        $gateway = (string) $this->option('gateway');
        if (! in_array($gateway, ['paymongo', 'qrph'], true)) {
            $this->error('Choose --gateway=paymongo or --gateway=qrph.');

            return self::FAILURE;
        }
        $payments = QrphPaymentService::forGateway($gateway);
        $attempts = QrphPaymentAttempt::query()
            ->whereNotNull('intent_id')
            ->whereNotIn('status', ['paid', 'review_required'])
            ->where('livemode', $gateway === 'paymongo')
            ->orderByRaw('last_checked_at ASC NULLS FIRST')->orderBy('id')
            ->limit(max(1, min(500, (int) $this->option('limit'))))->get();
        $failed = false;
        foreach ($attempts as $attempt) {
            try {
                $payments->check($attempt);
            } catch (PaymentGatewayException) {
                $failed = true;
                $attempt->update(['last_checked_at' => now()]);
                $this->warn('Could not verify attempt '.$attempt->id.'. It remains unpaid.');
            }
        }
        $this->info('Checked '.$attempts->count().' existing QR Ph attempts.');

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
