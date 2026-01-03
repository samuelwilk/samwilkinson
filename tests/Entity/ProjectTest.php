<?php

namespace App\Tests\Entity;

use App\Entity\Project;
use PHPUnit\Framework\TestCase;

class ProjectTest extends TestCase
{
    public function testProjectCreation(): void
    {
        $project = new Project();

        $this->assertInstanceOf(\DateTimeInterface::class, $project->getCreatedAt());
        $this->assertInstanceOf(\DateTimeInterface::class, $project->getUpdatedAt());
        $this->assertFalse($project->isPublished());
        $this->assertFalse($project->isFeatured());
        $this->assertEquals([], $project->getTags());
        $this->assertEquals([], $project->getMetrics());
    }

    public function testSettersAndGetters(): void
    {
        $project = new Project();
        $now = new \DateTime();

        $project->setTitle('Test Project');
        $project->setSlug('test-project');
        $project->setSummary('A test summary');
        $project->setContent('Test content');
        $project->setTags(['PHP', 'Symfony']);
        $project->setUrl('https://example.com');
        $project->setGithubUrl('https://github.com/test/repo');
        $project->setPublishedAt($now);
        $project->setSortOrder(5);
        $project->setIsPublished(true);
        $project->setIsFeatured(true);
        $project->setThumbnailImage('/images/thumb.jpg');

        $this->assertEquals('Test Project', $project->getTitle());
        $this->assertEquals('test-project', $project->getSlug());
        $this->assertEquals('A test summary', $project->getSummary());
        $this->assertEquals('Test content', $project->getContent());
        $this->assertEquals(['PHP', 'Symfony'], $project->getTags());
        $this->assertEquals('https://example.com', $project->getUrl());
        $this->assertEquals('https://github.com/test/repo', $project->getGithubUrl());
        $this->assertEquals($now, $project->getPublishedAt());
        $this->assertEquals(5, $project->getSortOrder());
        $this->assertTrue($project->isPublished());
        $this->assertTrue($project->isFeatured());
        $this->assertEquals('/images/thumb.jpg', $project->getThumbnailImage());
    }

    public function testPublishingAutoSetsPublishedAt(): void
    {
        $project = new Project();
        $this->assertNull($project->getPublishedAt());

        $project->setIsPublished(true);
        $this->assertInstanceOf(\DateTimeInterface::class, $project->getPublishedAt());
    }

    public function testTagManagement(): void
    {
        $project = new Project();

        $project->addTag('PHP');
        $this->assertContains('PHP', $project->getTags());

        $project->addTag('Symfony');
        $this->assertContains('Symfony', $project->getTags());

        // Adding duplicate should not create duplicates
        $project->addTag('PHP');
        $this->assertCount(2, $project->getTags());

        $project->removeTag('PHP');
        $this->assertNotContains('PHP', $project->getTags());
        $this->assertContains('Symfony', $project->getTags());
    }

    public function testMetricsManagement(): void
    {
        $project = new Project();

        $project->setMetric('stars', 100);
        $this->assertEquals(100, $project->getMetric('stars'));

        $project->setMetric('forks', 25);
        $this->assertEquals(25, $project->getMetric('forks'));

        $this->assertNull($project->getMetric('nonexistent'));

        $metrics = $project->getMetrics();
        $this->assertArrayHasKey('stars', $metrics);
        $this->assertArrayHasKey('forks', $metrics);
    }

    public function testToString(): void
    {
        $project = new Project();
        $project->setTitle('My Project');

        $this->assertEquals('My Project', (string) $project);
    }

    public function testPreUpdateSetsUpdatedAt(): void
    {
        $project = new Project();
        $originalUpdatedAt = $project->getUpdatedAt();

        sleep(1);
        $project->preUpdate();

        $this->assertGreaterThan($originalUpdatedAt, $project->getUpdatedAt());
    }
}
