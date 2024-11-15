<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Automatically upgrades (rehashes) the user's password over time.
     * This method is called when a user's password needs to be updated.
     *
     * @param PasswordAuthenticatedUserInterface $user The user whose password is being upgraded.
     * @param string $newHashedPassword The new hashed password to set for the user.
     * @throws UnsupportedUserException If the user is not an instance of the User class.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        // Check if the user is an instance of the User class
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        // Set the new hashed password for the user
        $user->setPassword($newHashedPassword);
        // Persist the user entity to the database
        $this->getEntityManager()->persist($user);
        // Flush changes to the database
        $this->getEntityManager()->flush();
    }

    /**
     * Search for users by their username.
     * This method returns an array of users whose usernames match the search term.
     *
     * @param string $searchTerm The term to search for in usernames.
     * @return User[] An array of users matching the search criteria.
     */
    public function searchByUsername(string $searchTerm): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.username LIKE :search')
            ->setParameter('search', '%' . $searchTerm . '%')
            ->getQuery()
            ->getResult();
    }
}
