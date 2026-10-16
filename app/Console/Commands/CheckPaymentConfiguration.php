<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Payment\Drivers\PaymongoDriver;
use App\Services\Payment\PaymentService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

#[Signature('payments:check-configuration')]
#[Description('Check live PayMongo prerequisites without creating payments or displaying credentials')]
class CheckPaymentConfiguration extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(PaymentService $payments): int
    {
        $gatewayStatus = $payments->availableGateways();
        foreach (['card' => 'Stripe', 'qrph' => 'QR Ph Sandbox', 'gotyme' => 'Manual GoTyme', 'paymongo' => 'PayMongo QR Ph Live'] as $gateway => $label) {
            $this->line($label.': '.($gatewayStatus[$gateway] ? 'configured' : 'unavailable'));
        }
        $url = (string) config('services.paymongo.webhook_url');
        $host = parse_url($url, PHP_URL_HOST);
        $checks = [
            'PayMongo secret key is live' => (new PaymongoDriver)->configured(),
            'Live webhook signing secret is configured' => filled(config('services.paymongo.webhook_secret')),
            'Live webhook URL uses public HTTPS and the correct path' => parse_url($url, PHP_URL_SCHEME) === 'https'
                && filled($host) && ! in_array($host, ['localhost', '127.0.0.1', '::1'], true)
                && parse_url($url, PHP_URL_PATH) === '/webhook/payment/paymongo',
            'Payment attempt table exists' => Schema::hasTable('qrph_payment_attempts'),
            'Shared database or Redis cache is selected' => in_array(config('cache.default'), ['database', 'redis'], true),
        ];
        foreach ($checks as $label => $passed) {
            $this->line(($passed ? 'PASS: ' : 'MISSING: ').$label);
        }
        $this->line('This check does not prove webhook registration, worker uptime, account eligibility, or a successful live transaction.');

        return in_array(false, $checks, true) ? self::FAILURE : self::SUCCESS;
    }
}
