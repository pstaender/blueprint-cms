<?php

declare(strict_types=1);

namespace Blueprint;

/**
 * An image that can be scaled and encoded as AVIF, see ImageOptimizer.
 */
interface Image
{
    public function width(): int;

    public function height(): int;

    public function scale(float $factor): void;

    public function avif(int $quality): string;
}
