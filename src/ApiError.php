<?php

declare(strict_types=1);

namespace Blueprint;

/**
 * An error response of the panel API, e.g. new ApiError("Not found", 404).
 */
class ApiError extends \RuntimeException
{
    public function __construct(string $message, public readonly int $status = 400, public readonly array $details = [])
    {
        parent::__construct($message);
    }
}
