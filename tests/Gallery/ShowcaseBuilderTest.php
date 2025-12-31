<?php

declare(strict_types=1);

namespace App\Tests\Gallery;

use App\Gallery\ShowcaseBuilder;
use PHPUnit\Framework\TestCase;

final class ShowcaseBuilderTest extends TestCase
{
    private ShowcaseBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new ShowcaseBuilder();
    }

    /**
     * Test that the same seed + same photos produces identical output (determinism)
     */
    public function testDeterministicLayoutGeneration(): void
    {
        $photos = [
            ['url' => '/photo1.jpg', 'alt' => 'Photo 1', 'width' => 1200, 'height' => 800],
            ['url' => '/photo2.jpg', 'alt' => 'Photo 2', 'width' => 800, 'height' => 1200],
            ['url' => '/photo3.jpg', 'alt' => 'Photo 3', 'width' => 1600, 'height' => 900],
            ['url' => '/photo4.jpg', 'alt' => 'Photo 4', 'width' => 1000, 'height' => 1000],
            ['url' => '/photo5.jpg', 'alt' => 'Photo 5', 'width' => 1400, 'height' => 1000],
        ];

        $seedKey = 'test-album-2024';
        $collectionData = ['name' => 'Test Album', 'description' => 'Test description'];

        // Build panels twice with same seed
        $panels1 = $this->builder->build($photos, $seedKey, $collectionData);
        $panels2 = $this->builder->build($photos, $seedKey, $collectionData);

        // Should produce identical results
        $this->assertSame($panels1, $panels2, 'Same seed should produce identical panel layouts');
    }

    /**
     * Test that different seeds produce different output
     */
    public function testDifferentSeedsProduceDifferentLayouts(): void
    {
        $photos = [
            ['url' => '/photo1.jpg', 'alt' => 'Photo 1', 'width' => 1200, 'height' => 800],
            ['url' => '/photo2.jpg', 'alt' => 'Photo 2', 'width' => 800, 'height' => 1200],
            ['url' => '/photo3.jpg', 'alt' => 'Photo 3', 'width' => 1600, 'height' => 900],
        ];

        $panels1 = $this->builder->build($photos, 'album-one');
        $panels2 = $this->builder->build($photos, 'album-two');

        // Different seeds should produce different layouts
        $this->assertNotSame($panels1, $panels2, 'Different seeds should produce different layouts');
    }

    /**
     * Test that panels are created with correct structure
     */
    public function testPanelStructure(): void
    {
        $photos = [
            ['url' => '/photo1.jpg', 'alt' => 'Photo 1', 'width' => 1200, 'height' => 800],
            ['url' => '/photo2.jpg', 'alt' => 'Photo 2', 'width' => 800, 'height' => 1200],
        ];

        $panels = $this->builder->build($photos, 'test');

        $this->assertNotEmpty($panels, 'Should create panels');

        foreach ($panels as $panel) {
            $this->assertArrayHasKey('layout', $panel, 'Panel should have layout key');

            if ($panel['layout'] === 'text_card') {
                $this->assertArrayHasKey('title', $panel, 'Text card should have title');
                $this->assertArrayHasKey('body', $panel, 'Text card should have body');
            } else {
                $this->assertArrayHasKey('slots', $panel, 'Photo panel should have slots');
                $this->assertIsArray($panel['slots'], 'Slots should be array');

                foreach ($panel['slots'] as $slot) {
                    $this->assertArrayHasKey('photo', $slot, 'Slot should have photo');
                    $this->assertArrayHasKey('x', $slot, 'Slot should have x position');
                    $this->assertArrayHasKey('y', $slot, 'Slot should have y position');
                    $this->assertArrayHasKey('w', $slot, 'Slot should have width');
                    $this->assertArrayHasKey('h', $slot, 'Slot should have height');
                    $this->assertArrayHasKey('z', $slot, 'Slot should have z-index');
                    $this->assertArrayHasKey('rot', $slot, 'Slot should have rotation');

                    // Verify safe area constraints
                    $this->assertGreaterThanOrEqual(65.0, $slot['x'], 'X should be >= 65%');
                    $this->assertLessThanOrEqual(96.0, $slot['x'], 'X should be <= 96%');
                    $this->assertGreaterThanOrEqual(18.0, $slot['y'], 'Y should be >= 18%');
                    $this->assertLessThanOrEqual(82.0, $slot['y'], 'Y should be <= 82%');
                }
            }
        }
    }

    /**
     * Test empty photos array
     */
    public function testEmptyPhotosArray(): void
    {
        $panels = $this->builder->build([], 'test');
        $this->assertEmpty($panels, 'Empty photos should produce empty panels');
    }

    /**
     * Test that all photos are used in panels
     */
    public function testAllPhotosAreUsed(): void
    {
        $photos = [
            ['url' => '/photo1.jpg', 'alt' => 'Photo 1', 'width' => 1200, 'height' => 800],
            ['url' => '/photo2.jpg', 'alt' => 'Photo 2', 'width' => 800, 'height' => 1200],
            ['url' => '/photo3.jpg', 'alt' => 'Photo 3', 'width' => 1600, 'height' => 900],
        ];

        $panels = $this->builder->build($photos, 'test');

        $photoCount = 0;
        foreach ($panels as $panel) {
            if (isset($panel['slots'])) {
                $photoCount += count($panel['slots']);
            }
        }

        $this->assertEquals(
            count($photos),
            $photoCount,
            'All photos should be placed in panels'
        );
    }
}
