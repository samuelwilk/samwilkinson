<?php

namespace App\Command;

use App\Entity\ApiToken;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:create-api-token',
    description: 'Create an API token for iOS Shortcut bulk photo upload',
)]
class CreateApiTokenCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Token name/description', 'iOS Shortcut')
            ->addOption('token', null, InputOption::VALUE_REQUIRED, 'Custom token value (auto-generated if not provided)')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $name = $input->getOption('name');

        // Generate or use custom token
        $tokenValue = $input->getOption('token');
        if (!$tokenValue) {
            // Generate secure random token (32 bytes = 64 hex chars)
            $tokenValue = bin2hex(random_bytes(32));
        }

        // Create API token
        $apiToken = new ApiToken();
        $apiToken->setName($name);
        $apiToken->setPlainToken($tokenValue); // Hashes token before storing

        $this->entityManager->persist($apiToken);
        $this->entityManager->flush();

        $io->success(sprintf('API token created: %s', $name));
        $io->section('Token Details');
        $io->table(
            ['Property', 'Value'],
            [
                ['ID', $apiToken->getId()],
                ['Name', $name],
                ['Token', $tokenValue],
                ['Created', $apiToken->getCreatedAt()->format('Y-m-d H:i:s')],
                ['Active', $apiToken->isActive() ? 'Yes' : 'No'],
            ]
        );

        $io->note([
            'Save this token securely!',
            'You will need to add it to your iOS Shortcut configuration.',
            'Add as Authorization header: Bearer ' . $tokenValue,
        ]);

        return Command::SUCCESS;
    }
}
