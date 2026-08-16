<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

/**
 * Raised when a payment verification would reconcile an invoice that is on
 * REVIEW_REQUIRED hold. Rendered by the caller as a 422 response; the payment
 * and invoice are left unchanged.
 */
class PaymentReviewRequiredException extends Exception {}
