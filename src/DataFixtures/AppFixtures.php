<?php

namespace App\DataFixtures;

use App\Entity\Collection;
use App\Entity\Photo;
use App\Entity\Post;
use App\Entity\Project;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        // Create admin user
        $admin = new User();
        $admin->setEmail('admin@example.com');
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'admin'));
        $admin->setRoles(['ROLE_ADMIN']);
        $manager->persist($admin);

        // Create Mind The Wait project
        $mindTheWait = new Project();
        $mindTheWait->setTitle('Mind The Wait');
        $mindTheWait->setSlug('mind-the-wait');
        $mindTheWait->setSummary('Restaurant queue management system with real-time SMS notifications and capacity tracking.');
        $mindTheWait->setContent('# Mind The Wait

A queue management system designed for restaurants to handle customer flow efficiently.

## Features

- Real-time SMS notifications via Twilio
- Dynamic capacity tracking
- Customer queue management
- Wait time estimates

## Technical Implementation

Built with Symfony 7 and SQLite for zero-config deployment. The system sends SMS notifications to customers when their table is ready, reducing congestion in waiting areas.

## Results

- Sub-second notification delivery
- Easy deployment with minimal infrastructure
- Scalable queue management');
        $mindTheWait->setTags(['Symfony 7', 'SQLite', 'Twilio API']);
        $mindTheWait->setMetrics([
            'role' => 'Solo Developer',
            'constraint' => 'Sub-second notification delivery'
        ]);
        $mindTheWait->setUrl('https://mindthewait.example.com');
        $mindTheWait->setGithubUrl('https://github.com/samwilk/mind-the-wait');
        $mindTheWait->setIsPublished(true);
        $mindTheWait->setPublishedAt(new \DateTime('2024-06-15'));
        $mindTheWait->setSortOrder(1);
        $manager->persist($mindTheWait);

        // Create DevBox project
        $devBox = new Project();
        $devBox->setTitle('DevBox');
        $devBox->setSlug('devbox');
        $devBox->setSummary('Local development environment orchestrator for Symfony projects with one-command setup.');
        $devBox->setContent('# DevBox

A development environment orchestrator that simplifies local Symfony project setup.

## Features

- One-command project initialization
- Docker Compose integration
- Environment variable management
- Service orchestration (database, cache, mail)

## Technical Implementation

Built with PHP 8.3 and Symfony Console commands. DevBox wraps Docker Compose with intelligent defaults for Symfony projects, handling database setup, cache configuration, and service dependencies automatically.

## Results

- Zero-config initial setup
- Consistent development environments across team
- Reduced onboarding time for new developers');
        $devBox->setTags(['PHP 8.3', 'Docker Compose', 'Symfony Console']);
        $devBox->setMetrics([
            'role' => 'Creator',
            'constraint' => 'Zero-config initial setup'
        ]);
        $devBox->setGithubUrl('https://github.com/samwilk/devbox');
        $devBox->setIsPublished(true);
        $devBox->setPublishedAt(new \DateTime('2023-09-20'));
        $devBox->setSortOrder(2);
        $manager->persist($devBox);

        // Create Studio posts
        $post1 = new Post();
        $post1->setTitle('Building a Modern Photo Portfolio');
        $post1->setSlug('building-modern-photo-portfolio');
        $post1->setContent('# Building a Modern Photo Portfolio

A deep dive into the architecture and design decisions behind this portfolio site.

## Design Principles

- Modernist warm minimalism
- Museum label clarity
- Object-first presentation

## Technical Stack

Built with Symfony 7.4, Tailwind CSS, and thoughtful interactions.');
        $post1->setExcerpt('A deep dive into the architecture and design decisions behind this portfolio site.');
        $post1->setIsPublished(true);
        $post1->setPublishedAt(new \DateTime('2025-12-15'));
        $post1->setReadingTimeMinutes(8);
        $post1->setTags(['Symfony', 'Design', 'Architecture']);
        $manager->persist($post1);

        $post2 = new Post();
        $post2->setTitle('Lessons from Five Years of Development');
        $post2->setSlug('lessons-five-years-development');
        $post2->setContent('# Lessons from Five Years of Development

Reflections on building software from 2020 to 2025.

## Key Learnings

- Simplicity beats complexity
- YAGNI is real
- Design systems pay dividends');
        $post2->setExcerpt('Reflections on building software from 2020 to 2025.');
        $post2->setIsPublished(true);
        $post2->setPublishedAt(new \DateTime('2025-11-20'));
        $post2->setReadingTimeMinutes(6);
        $post2->setTags(['Career', 'Lessons', 'Development']);
        $manager->persist($post2);

        // Create photo collections (without actual photo files for testing)
        $collection1 = new Collection();
        $collection1->setName('Vancouver 2024');
        $collection1->setSlug('vancouver-2024');
        $collection1->setDescription('Street photography from the West Coast.');
        $collection1->setLocationName('Vancouver');
        $collection1->setCountry('Canada');
        $collection1->setStartDate(new \DateTime('2024-08-01'));
        $collection1->setEndDate(new \DateTime('2024-08-15'));
        $collection1->setIsPublished(true);
        $collection1->setIsRestricted(false);
        $collection1->setSortOrder(1);
        $collection1->setVisualStyle(['color' => '#00897B', 'texture' => 'linen']);
        $manager->persist($collection1);

        // Create a password-protected collection for E2E testing
        $collection2 = new Collection();
        $collection2->setName('Private Moments');
        $collection2->setSlug('private-moments');
        $collection2->setDescription('A restricted collection requiring authentication.');
        $collection2->setIsPublished(true);
        $collection2->setIsRestricted(true);
        $collection2->setAccessPassword(password_hash('secret123', PASSWORD_BCRYPT));
        $collection2->setAllowDownloads(true);
        $collection2->setSortOrder(2);
        $collection2->setVisualStyle(['color' => '#D32F2F', 'texture' => 'canvas']);
        $manager->persist($collection2);

        $manager->flush();
    }
}
