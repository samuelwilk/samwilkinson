<?php

declare(strict_types=1);

namespace App\Gallery;

/**
 * Defines a photo layout pattern with slot positions and preferences.
 *
 * A pattern describes how multiple photos should be arranged within a panel,
 * including their positions, sizes, rotations, and z-index layering.
 */
final class Pattern
{
    /**
     * @param string $name Unique pattern identifier
     * @param int $slotsNeeded Number of photos required for this pattern
     * @param list<SlotDef> $slotDefs Slot definitions (position, size, rotation, z-index)
     * @param AspectRatio $preferredAspect Preferred photo aspect ratio
     */
    public function __construct(
        public readonly string $name,
        public readonly int $slotsNeeded,
        public readonly array $slotDefs,
        public readonly AspectRatio $preferredAspect = AspectRatio::Any
    ) {}
}
