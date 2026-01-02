<?php

declare(strict_types=1);

namespace App\Gallery;

/**
 * Builds deterministic photo showcase panels using seeded randomization.
 *
 * Generates a series of panels containing photos arranged according to
 * predefined patterns. The same seed (e.g., collection slug) will always
 * produce the same layout, ensuring consistency across page loads.
 */
final class ShowcaseBuilder
{
    /**
     * Build showcase panels from photos using deterministic seeded layout.
     *
     * @param list<array{url:string, alt?:string, width?:int, height?:int, location?:string, year?:string}> $photos Photo data arrays
     * @param string $seedKey Unique key for deterministic layout (e.g., collection slug)
     * @return list<array{layout:string, slots?:list<array>, title?:string, body?:string}> Panel data for rendering
     */
    public function build(array $photos, string $seedKey): array
    {
        if (empty($photos)) {
            return [];
        }

        $seed = crc32($seedKey);
        $rng = new XorShift32($seed);
        $patterns = PatternRegistry::getPatterns();

        $panels = [];
        $remaining = $photos;
        $lastWasTextCard = false;

        while (!empty($remaining)) {
            // Occasionally insert text card (10-20% chance), but never twice in a row
            if (!$lastWasTextCard && $rng->nextFloat() < 0.15 && count($panels) > 0) {
                $panels[] = $this->createTextCard($rng, $seedKey);
                $lastWasTextCard = true;
                continue;
            }

            $lastWasTextCard = false;

            // Pick pattern that fits remaining photos
            $suitablePatterns = array_filter(
                $patterns,
                fn(Pattern $p) => $p->slotsNeeded <= count($remaining)
            );

            if (empty($suitablePatterns)) {
                // Not enough photos for any pattern - use hero for last photo
                $suitablePatterns = array_filter(
                    $patterns,
                    fn(Pattern $p) => $p->name === 'hero_right'
                );
            }

            // Aspect-ratio-aware pattern selection
            $chosenPattern = $this->selectPatternByAspectRatio(
                $suitablePatterns,
                $remaining,
                $rng
            );

            // Take photos for this panel
            $panelPhotos = array_slice($remaining, 0, $chosenPattern->slotsNeeded);
            $remaining = array_slice($remaining, $chosenPattern->slotsNeeded);

            // Build panel with jittered slots
            $panels[] = $this->createPhotoPanel($chosenPattern, $panelPhotos, $rng);
        }

        return $panels;
    }

    /**
     * Select a pattern based on the aspect ratios of upcoming photos.
     *
     * Analyzes the next few photos to determine dominant aspect ratio
     * (portrait/landscape) and selects a pattern that matches.
     *
     * @param list<Pattern> $patterns Available patterns to choose from
     * @param list<array> $photos Remaining photos to layout
     * @param XorShift32 $rng Seeded random number generator
     * @return Pattern Selected pattern
     */
    private function selectPatternByAspectRatio(array $patterns, array $photos, XorShift32 $rng): Pattern
    {
        // Classify first few photos
        $photoAspects = array_map(
            fn($photo) => $this->classifyAspectRatio($photo),
            array_slice($photos, 0, 4)
        );

        $portraitCount = count(array_filter($photoAspects, fn($a) => $a === AspectRatio::Portrait));
        $landscapeCount = count(array_filter($photoAspects, fn($a) => $a === AspectRatio::Landscape));

        // Prefer patterns that match photo aspects
        $dominantAspect = AspectRatio::Any;
        if ($portraitCount > $landscapeCount) {
            $dominantAspect = AspectRatio::Portrait;
        } elseif ($landscapeCount > $portraitCount) {
            $dominantAspect = AspectRatio::Landscape;
        }

        // Filter patterns by preference
        $preferred = array_filter(
            $patterns,
            fn(Pattern $p) => $p->preferredAspect === $dominantAspect || $p->preferredAspect === AspectRatio::Any
        );

        if (empty($preferred)) {
            $preferred = $patterns;
        }

        // Random selection from preferred
        $index = $rng->int(0, count($preferred) - 1);
        return array_values($preferred)[$index];
    }

    /**
     * Classify a photo's aspect ratio as portrait, landscape, or square.
     *
     * @param array{width?:int, height?:int} $photo Photo data with dimensions
     * @return AspectRatio Classified aspect ratio
     */
    private function classifyAspectRatio(array $photo): AspectRatio
    {
        if (!isset($photo['width']) || !isset($photo['height']) || $photo['height'] == 0) {
            return AspectRatio::Any;
        }

        $ratio = $photo['width'] / $photo['height'];

        if ($ratio < 0.85) {
            return AspectRatio::Portrait;
        } elseif ($ratio > 1.15) {
            return AspectRatio::Landscape;
        }

        return AspectRatio::Square;
    }

    /**
     * Create a photo panel by applying jitter to pattern slot definitions.
     *
     * Applies subtle randomization (±2% position, ±2.5% size, ±1.5° rotation)
     * to pattern slots while keeping photos within safe viewport bounds.
     *
     * @param Pattern $pattern Layout pattern to use
     * @param list<array> $photos Photos to place in slots
     * @param XorShift32 $rng Seeded random number generator
     * @return array{layout:string, slots:list<array>} Panel data with positioned slots
     */
    private function createPhotoPanel(Pattern $pattern, array $photos, XorShift32 $rng): array
    {
        $slots = [];

        foreach ($pattern->slotDefs as $i => $def) {
            if (!isset($photos[$i])) {
                break;
            }

            // Apply subtle jitter (±2% position, ±2.5% size, ±1.5deg rotation)
            $x = $this->clamp($rng->jitter($def->x, 2.0), 65.0, 92.0);
            $y = $this->clamp($rng->jitter($def->y, 2.0), 18.0, 82.0);
            $w = $this->clamp($rng->jitter($def->w, 2.5), 10.0, 50.0);
            $h = $this->clamp($rng->jitter($def->h, 2.5), 15.0, 80.0);
            $rot = $rng->jitter($def->rot, 1.5);

            // Ensure doesn't go off right edge
            if ($x + $w > 96.0) {
                $w = 96.0 - $x;
            }

            // Ensure doesn't go off bottom
            if ($y + $h > 82.0) {
                $h = 82.0 - $y;
            }

            $slots[] = [
                'photo' => $photos[$i],
                'x' => round($x, 2),
                'y' => round($y, 2),
                'w' => round($w, 2),
                'h' => round($h, 2),
                'z' => $def->z,
                'rot' => round($rot, 2),
            ];
        }

        return [
            'layout' => $pattern->name,
            'slots' => $slots,
        ];
    }

    /**
     * Create a text card panel with random color and size variation.
     *
     * Text cards provide visual breaks between photo panels in the showcase.
     * Each card is a solid-color rectangle with varied dimensions and positioning.
     *
     * @param XorShift32 $rng Seeded random number generator
     * @param string $seedKey Seed key (unused but kept for consistency)
     * @return array{layout:string, color:string, width:int, height:int, x:int, y:int} Text card panel data
     */
    private function createTextCard(XorShift32 $rng, string $seedKey): array
    {
        // Project colors from design system
        $colors = [
            '#1A1A1A', // ink (black)
            '#3A3A3A', // graphite
            '#2C2C2E', // gunmetal
            '#5C4033', // walnut
            '#9A6324', // cognac
            '#D32F2F', // signal
            '#00897B', // teal
            '#F9A825', // mustard
            '#E64A19', // persimmon
        ];

        // Varied rectangle dimensions (percentage of viewport)
        $widthOptions = [25, 30, 35, 40, 45];  // 25-45% of viewport width
        $heightOptions = [30, 40, 50, 60, 70]; // 30-70% of viewport height

        // Horizontal positioning (favor right side to match current design)
        $xOptions = [55, 60, 65, 70]; // Right-aligned positioning

        // Vertical positioning (varied placement)
        $yOptions = [25, 35, 45]; // Top, middle, bottom thirds

        $colorIndex = $rng->int(0, count($colors) - 1);
        $widthIndex = $rng->int(0, count($widthOptions) - 1);
        $heightIndex = $rng->int(0, count($heightOptions) - 1);
        $xIndex = $rng->int(0, count($xOptions) - 1);
        $yIndex = $rng->int(0, count($yOptions) - 1);

        return [
            'layout' => 'text_card',
            'color' => $colors[$colorIndex],
            'width' => $widthOptions[$widthIndex],
            'height' => $heightOptions[$heightIndex],
            'x' => $xOptions[$xIndex],
            'y' => $yOptions[$yIndex],
        ];
    }

    /**
     * Clamp a value between minimum and maximum bounds.
     *
     * @param float $value Value to clamp
     * @param float $min Minimum allowed value
     * @param float $max Maximum allowed value
     * @return float Clamped value
     */
    private function clamp(float $value, float $min, float $max): float
    {
        return max($min, min($max, $value));
    }
}
