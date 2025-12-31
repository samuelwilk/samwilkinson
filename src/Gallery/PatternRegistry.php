<?php

declare(strict_types=1);

namespace App\Gallery;

/**
 * Central registry of all available photo layout patterns.
 *
 * Provides a curated collection of patterns with different slot counts,
 * compositions, and aspect ratio preferences for the showcase builder.
 */
final class PatternRegistry
{
    /**
     * Get all available layout patterns.
     *
     * @return list<Pattern> Array of pattern definitions
     */
    public static function getPatterns(): array
    {
        return [
            // Hero - single large image, right-anchored
            new Pattern(
                name: 'hero_right',
                slotsNeeded: 1,
                slotDefs: [
                    new SlotDef(x: 65.0, y: 32.0, w: 32.0, h: 54.0, z: 1, rot: 0.0),
                ],
                preferredAspect: AspectRatio::Any
            ),

            // Duo tall - two portraits, slight overlap
            new Pattern(
                name: 'duo_tall',
                slotsNeeded: 2,
                slotDefs: [
                    new SlotDef(x: 64.0, y: 24.0, w: 20.0, h: 48.0, z: 1, rot: 0.0),
                    new SlotDef(x: 80.0, y: 38.0, w: 18.0, h: 44.0, z: 2, rot: -0.8),
                ],
                preferredAspect: AspectRatio::Portrait
            ),

            // Stack 3 - three overlapping images
            new Pattern(
                name: 'stack_3',
                slotsNeeded: 3,
                slotDefs: [
                    new SlotDef(x: 58.0, y: 24.0, w: 26.0, h: 36.0, z: 1, rot: 0.5),
                    new SlotDef(x: 70.0, y: 38.0, w: 24.0, h: 34.0, z: 2, rot: -1.0),
                    new SlotDef(x: 64.0, y: 54.0, w: 22.0, h: 30.0, z: 3, rot: 0.3),
                ],
                preferredAspect: AspectRatio::Any
            ),

            // Floating 2 - two larger floats, well separated
            new Pattern(
                name: 'floating_2',
                slotsNeeded: 2,
                slotDefs: [
                    new SlotDef(x: 64.0, y: 18.0, w: 24.0, h: 30.0, z: 1, rot: -0.5),
                    new SlotDef(x: 72.0, y: 62.0, w: 22.0, h: 28.0, z: 2, rot: 1.2),
                ],
                preferredAspect: AspectRatio::Landscape
            ),

            // Strip 3 - three medium images in vertical cluster
            new Pattern(
                name: 'strip_3',
                slotsNeeded: 3,
                slotDefs: [
                    new SlotDef(x: 68.0, y: 18.0, w: 22.0, h: 20.0, z: 1, rot: 0.0),
                    new SlotDef(x: 72.0, y: 44.0, w: 20.0, h: 22.0, z: 2, rot: -0.6),
                    new SlotDef(x: 66.0, y: 72.0, w: 21.0, h: 18.0, z: 3, rot: 0.8),
                ],
                preferredAspect: AspectRatio::Landscape
            ),

            // Large + small - one dominant, one accent
            new Pattern(
                name: 'large_small',
                slotsNeeded: 2,
                slotDefs: [
                    new SlotDef(x: 62.0, y: 30.0, w: 30.0, h: 50.0, z: 1, rot: 0.0),
                    new SlotDef(x: 84.0, y: 64.0, w: 12.0, h: 16.0, z: 2, rot: -1.5),
                ],
                preferredAspect: AspectRatio::Any
            ),

            // Quad cluster - four larger images with more space
            new Pattern(
                name: 'quad_cluster',
                slotsNeeded: 4,
                slotDefs: [
                    new SlotDef(x: 58.0, y: 18.0, w: 20.0, h: 26.0, z: 1, rot: 0.3),
                    new SlotDef(x: 78.0, y: 22.0, w: 18.0, h: 24.0, z: 2, rot: -0.8),
                    new SlotDef(x: 56.0, y: 54.0, w: 22.0, h: 24.0, z: 3, rot: 1.0),
                    new SlotDef(x: 76.0, y: 60.0, w: 18.0, h: 22.0, z: 4, rot: -0.4),
                ],
                preferredAspect: AspectRatio::Any
            ),

            // Vertical pair - two images stacked vertically
            new Pattern(
                name: 'vertical_pair',
                slotsNeeded: 2,
                slotDefs: [
                    new SlotDef(x: 68.0, y: 20.0, w: 24.0, h: 26.0, z: 1, rot: 0.0),
                    new SlotDef(x: 66.0, y: 60.0, w: 26.0, h: 28.0, z: 2, rot: -0.6),
                ],
                preferredAspect: AspectRatio::Landscape
            ),

            // Single portrait - tall portrait, right edge
            new Pattern(
                name: 'single_portrait',
                slotsNeeded: 1,
                slotDefs: [
                    new SlotDef(x: 72.0, y: 28.0, w: 22.0, h: 58.0, z: 1, rot: 0.0),
                ],
                preferredAspect: AspectRatio::Portrait
            ),

            // Wide landscape - single wide image
            new Pattern(
                name: 'wide_landscape',
                slotsNeeded: 1,
                slotDefs: [
                    new SlotDef(x: 58.0, y: 40.0, w: 38.0, h: 30.0, z: 1, rot: 0.0),
                ],
                preferredAspect: AspectRatio::Landscape
            ),
        ];
    }
}
