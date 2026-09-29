<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * The "feed already used" history could not be generated.
 *
 * Carries a message meant for the farmer, so a caller can pass it straight
 * back rather than reporting a generic failure.
 */
class FeedBackfillException extends RuntimeException
{
}
