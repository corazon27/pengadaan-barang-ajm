<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

/**
 * Raised when verifying a payment would push the verified total beyond the
 * invoice grand total (INT-4). Rendered by the caller as a 422 response; the
 * payment and invoice are left unchanged.
 */
class PaymentOverpaymentException extends Exception {}
