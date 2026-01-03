<?php

namespace App\Tests\Entity;

use App\Entity\Collection;
use App\Entity\Photo;
use PHPUnit\Framework\TestCase;

class CollectionTest extends TestCase
{
    public function testCollectionCreation(): void
    {
        $collection = new Collection();

        $this->assertInstanceOf(\DateTimeInterface::class, $collection->getCreatedAt());
        $this->assertInstanceOf(\DateTimeInterface::class, $collection->getUpdatedAt());
        $this->assertFalse($collection->isRestricted());
        $this->assertFalse($collection->allowDownloads());
    }

    public function testSettersAndGetters(): void
    {
        $collection = new Collection();
        $startDate = new \DateTime('2024-01-01');
        $endDate = new \DateTime('2024-01-31');

        $collection->setName('Test Collection');
        $collection->setSlug('test-collection');
        $collection->setLocationName('Tokyo, Japan');
        $collection->setStartDate($startDate);
        $collection->setEndDate($endDate);
        $collection->setIsRestricted(true);
        $collection->setAllowDownloads(false);

        $this->assertEquals('Test Collection', $collection->getName());
        $this->assertEquals('test-collection', $collection->getSlug());
        $this->assertEquals('Tokyo, Japan', $collection->getLocationName());
        $this->assertEquals($startDate, $collection->getStartDate());
        $this->assertEquals($endDate, $collection->getEndDate());
        $this->assertTrue($collection->isRestricted());
        $this->assertFalse($collection->allowDownloads());
    }

    public function testPasswordHashing(): void
    {
        $collection = new Collection();
        $plainPassword = 'secret123';

        $collection->setAccessPassword($plainPassword);

        $hashedPassword = $collection->getAccessPassword();
        $this->assertNotEquals($plainPassword, $hashedPassword);
        $this->assertTrue($collection->verifyPassword($plainPassword));
        $this->assertFalse($collection->verifyPassword('wrongpassword'));
    }

    public function testVisualStyleManagement(): void
    {
        $collection = new Collection();
        $style = ['mood' => 'urban', 'palette' => 'neon'];

        $collection->setVisualStyle($style);
        $this->assertEquals($style, $collection->getVisualStyle());
        $this->assertEquals('urban', $collection->getVisualStyleValue('mood'));
        $this->assertEquals('neon', $collection->getVisualStyleValue('palette'));
        $this->assertNull($collection->getVisualStyleValue('nonexistent'));
    }

    public function testCoverPhoto(): void
    {
        $collection = new Collection();
        $photo = new Photo();
        $photo->setFilename('cover.jpg');

        $collection->setCoverPhoto($photo);
        $this->assertSame($photo, $collection->getCoverPhoto());
    }

    public function testToString(): void
    {
        $collection = new Collection();
        $collection->setName('My Collection');

        $this->assertEquals('My Collection', (string) $collection);
    }
}
