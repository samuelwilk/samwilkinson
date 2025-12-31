<?php

declare(strict_types=1);

namespace App\Gallery;

/**
 * Defines the position, size, and styling for a single photo slot.
 *
 * All position and size values are percentages relative to the panel viewport.
 */
final class SlotDef
{
    /**
     * @param float $x Horizontal position (% from left edge)
     * @param float $y Vertical position (% from top edge)
     * @param float $w Width (% of panel width)
     * @param float $h Height (% of panel height)
     * @param int $z Z-index for layering (higher = on top)
     * @param float $rot Rotation in degrees (positive = clockwise)
     */
    public function __construct(
        public readonly float $x,
        public readonly float $y,
        public readonly float $w,
        public readonly float $h,
        public readonly int $z,
        public readonly float $rot
    ) {}
}
