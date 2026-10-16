<?php

declare(strict_types=1);

namespace App\Services\Payment;

use Illuminate\Contracts\Debug\ShouldntReport;
use RuntimeException;

final class PaymentGatewayException extends RuntimeException implements ShouldntReport {}
