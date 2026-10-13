<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Review lifecycle for an individual B2G evidence submission version.
 *
 * A submission is only ever appended; prior versions are retained and marked
 * SUPERSEDED when a newer version is submitted. Accepting evidence does not by
 * itself grant the BUYER_B2G role: the role is granted only through the
 * administrator approval path once a submission is ACCEPTED.
 */
enum B2gEvidenceStatus: string
{
    case SUBMITTED = 'SUBMITTED';
    case UNDER_REVIEW = 'UNDER_REVIEW';
    case ACCEPTED = 'ACCEPTED';
    case REJECTED = 'REJECTED';
    case SUPERSEDED = 'SUPERSEDED';
}
