<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture
{
    public const USER_REFERENCE_PREFIX = 'user_';

    public function __construct(
        private UserPasswordHasherInterface $passwordHasher
    ) {}

    public function load(ObjectManager $manager): void
    {
        $users = [
            [
                'username' => 'admin2',
                'roles' => ['ROLE_ADMIN'],
                'notifications' => [
                    [
                        'message' => 'Welcome to the book review platform!',
                        'isRead' => false,
                    ]
                ]
            ],
            [
                'username' => 'john_reader',
                'roles' => ['ROLE_USER'],
                'notifications' => []
            ],
            [
                'username' => 'jane_bookworm',
                'roles' => ['ROLE_USER'],
                'notifications' => []
            ],
        ];

        foreach ($users as $index => $userData) {
            $user = new User();
            $user->setUsername($userData['username']);
            $user->setRoles($userData['roles']);
            $user->setNotifications($userData['notifications']);

            // Set a standard password for all users in dev environment
            $hashedPassword = $this->passwordHasher->hashPassword(
                $user,
                'Password123'
            );
            $user->setPassword($hashedPassword);

            $manager->persist($user);
            $this->addReference(self::USER_REFERENCE_PREFIX . $index, $user);
        }

        $manager->flush();
    }
}
