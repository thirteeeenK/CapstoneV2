<?php

declare(strict_types=1);

namespace App\Services\Payment\Drivers;

final class PaymongoDriver extends QrphDriver
{
    public function name(): string
    {
        return 'paymongo';
    }
}
