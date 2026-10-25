<?php

declare(strict_types=1);

namespace Blueprint;

/**
 * Thrown by redirect() to stop rendering and send a redirect response instead.
 */
class Redirect extends \RuntimeException
{
    public function __construct(public readonly string $url, public readonly int $status = 302)
    {
        parent::__construct("Redirect to $url ($status)");
    }
}
