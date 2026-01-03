<?php

namespace App\DataFixtures;

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

        $manager->flush();
    }
}
