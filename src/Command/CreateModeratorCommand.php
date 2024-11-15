<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-moderator',
    description: 'Creates a moderator user',
)]
class CreateModeratorCommand extends Command
{
    private EntityManagerInterface $entityManager;
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->passwordHasher = $passwordHasher;
    }

    protected function configure(): void
    {
        $this
            ->addArgument('username', InputArgument::REQUIRED, 'The username of the moderator')
            ->addArgument('password', InputArgument::REQUIRED, 'The password of the moderator');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $username = $input->getArgument('username');
        $password = $input->getArgument('password');

        // Check if the username already exists
        $existingUser = $this->entityManager->getRepository(User::class)
            ->findOneBy(['username' => $username]);

        if ($existingUser) {
            $output->writeln('<error>This username is already taken. Please choose another one.</error>');
            return Command::FAILURE;
        }

        // Create a new user
        $user = new User();
        $user->setUsername($username);
        $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashedPassword);
        $user->setRoles(['ROLE_MODERATOR']); // Set the role to moderator

        // Save the user
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $output->writeln('<info>Moderator created successfully!</info>');
        return Command::SUCCESS;
    }
} 