<?php

declare(strict_types=1);

namespace App\Gallery;

/**
 * XorShift32 pseudo-random number generator for deterministic layouts.
 *
 * Implements a 32-bit XorShift algorithm that generates reproducible random
 * sequences based on an initial seed value. Used for creating consistent
 * photo layouts across page loads.
 */
final class XorShift32
{
    private int $state;

    /**
     * @param int $seed Initial seed value (must not be 0)
     */
    public function __construct(int $seed)
    {
        $this->state = $seed !== 0 ? $seed : 1;
    }

    /**
     * Generate next random float in range [0, 1].
     */
    public function nextFloat(): float
    {
        $x = $this->state;
        $x ^= ($x << 13) & 0xFFFFFFFF;
        $x ^= ($x >> 17);
        $x ^= ($x << 5) & 0xFFFFFFFF;
        $this->state = $x & 0xFFFFFFFF;

        return $this->state / 0xFFFFFFFF;
    }

    /**
     * Generate random integer in range [min, max] (inclusive).
     */
    public function int(int $min, int $max): int
    {
        return $min + (int) floor($this->nextFloat() * (($max - $min) + 1));
    }

    /**
     * Apply random jitter to a base value.
     *
     * @param float $base Base value to jitter
     * @param float $amplitude Maximum deviation from base (±amplitude)
     * @return float Jittered value in range [base - amplitude, base + amplitude]
     */
    public function jitter(float $base, float $amplitude): float
    {
        return $base + (($this->nextFloat() * 2) - 1) * $amplitude;
    }
}
