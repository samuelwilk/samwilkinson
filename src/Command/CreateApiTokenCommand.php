<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\ApiToken;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Creates a new API token for bulk photo upload authentication.
 *
 * Usage:
 *   php bin/console app:token:create "iPhone Upload" --expires="+1 year"
 */
#[AsCommand(
    name: 'app:token:create',
    description: 'Create a new API token for bulk photo uploads',
)]
class CreateApiTokenCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::REQUIRED, 'Token name (e.g., "iPhone Upload")')
            ->addOption('expires', null, InputOption::VALUE_REQUIRED, 'Expiration date (e.g., "+1 year", "2025-12-31")', null)
            ->addOption('inactive', null, InputOption::VALUE_NONE, 'Create token as inactive');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $name = $input->getArgument('name');
        $expiresInput = $input->getOption('expires');
        $isActive = !$input->getOption('inactive');

        // Generate secure random token
        $plainToken = bin2hex(random_bytes(32)); // 64 character hex string

        // Create ApiToken entity
        $apiToken = new ApiToken();
        $apiToken->setName($name);
        $apiToken->setPlainToken($plainToken);
        $apiToken->setIsActive($isActive);

        // Set expiration date if provided
        if ($expiresInput !== null) {
            try {
                $expiresAt = new \DateTime($expiresInput);
                $apiToken->setExpiresAt($expiresAt);
            } catch (\Exception $e) {
                $io->error(sprintf('Invalid expiration date: %s', $e->getMessage()));
                return Command::FAILURE;
            }
        }

        // Persist to database
        $this->entityManager->persist($apiToken);
        $this->entityManager->flush();

        // Display token details
        $io->success('API token created successfully!');
        $io->section('Token Details');
        $io->table(
            ['Field', 'Value'],
            [
                ['ID', $apiToken->getId()],
                ['Name', $apiToken->getName()],
                ['Active', $apiToken->isActive() ? 'Yes' : 'No'],
                ['Created', $apiToken->getCreatedAt()->format('Y-m-d H:i:s')],
                ['Expires', $apiToken->getExpiresAt()?->format('Y-m-d H:i:s') ?? 'Never'],
            ]
        );

        $io->section('Bearer Token');
        $io->caution('This token will only be displayed once. Store it securely!');
        $io->text($plainToken);

        $io->section('Usage');
        $io->text('Add this header to your API requests:');
        $io->text(sprintf('Authorization: Bearer %s', $plainToken));

        return Command::SUCCESS;
    }
}
