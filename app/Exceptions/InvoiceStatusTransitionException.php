<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

/**
 * Raised when an invoice status transition violates the payment state machine
 * (INT-6). Rendered by the global handler as a 422 validation response.
 */
class InvoiceStatusTransitionException extends Exception {}
