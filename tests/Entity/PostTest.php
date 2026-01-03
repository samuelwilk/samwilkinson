<?php

namespace App\Tests\Entity;

use App\Entity\Post;
use PHPUnit\Framework\TestCase;

class PostTest extends TestCase
{
    public function testPostCreation(): void
    {
        $post = new Post();

        $this->assertInstanceOf(\DateTimeInterface::class, $post->getCreatedAt());
        $this->assertInstanceOf(\DateTimeInterface::class, $post->getUpdatedAt());
        $this->assertFalse($post->isPublished());
        $this->assertEquals([], $post->getTags());
    }

    public function testSettersAndGetters(): void
    {
        $post = new Post();
        $now = new \DateTime();

        $post->setTitle('Test Post');
        $post->setSlug('test-post');
        $post->setContent('# Test\n\nContent here.');
        $post->setExcerpt('A brief excerpt');
        $post->setPublishedAt($now);
        $post->setIsPublished(true);
        $post->setReadingTimeMinutes(5);
        $post->setTags(['development', 'testing']);

        $this->assertEquals('Test Post', $post->getTitle());
        $this->assertEquals('test-post', $post->getSlug());
        $this->assertEquals('# Test\n\nContent here.', $post->getContent());
        $this->assertEquals('A brief excerpt', $post->getExcerpt());
        $this->assertEquals($now, $post->getPublishedAt());
        $this->assertTrue($post->isPublished());
        $this->assertEquals(5, $post->getReadingTimeMinutes());
        $this->assertEquals(['development', 'testing'], $post->getTags());
    }

    public function testPreUpdateSetsUpdatedAt(): void
    {
        $post = new Post();
        $originalUpdatedAt = $post->getUpdatedAt();

        sleep(1);
        $post->preUpdate();

        $this->assertGreaterThan($originalUpdatedAt, $post->getUpdatedAt());
    }

    public function testToString(): void
    {
        $post = new Post();
        $post->setTitle('My Post');

        $this->assertEquals('My Post', (string) $post);
    }
}
