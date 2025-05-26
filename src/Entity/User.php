<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Rhys\ReviewBundle\Entity\Review;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use JMS\Serializer\Annotation as Serializer;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_USERNAME', fields: ['username'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    #[Serializer\Groups(['review:list', 'review:item'])]
    private ?string $username = null;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column(type: "json")]
    #[Serializer\Exclude]
    
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    #[Serializer\Exclude]
    private ?string $password = null;

    /**
     * @var Collection<int, Review>
     */
    #[ORM\OneToMany(targetEntity: Review::class, mappedBy: 'user', orphanRemoval: true)]
    private Collection $reviews;

    /**
     * @var Collection<int, Book>
     */
    #[ORM\OneToMany(targetEntity: Book::class, mappedBy: 'user')]
    private Collection $books;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $avatarFilename = null;

    #[ORM\Column(type: "json")]
    #[Serializer\Exclude]
    private array $notifications = [];

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $googleId = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $googleAccessToken = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $googleRefreshToken = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $googleTokenExpires = null;

    public function __construct()
    {
        $this->reviews = new ArrayCollection();
        $this->books = new ArrayCollection();
        $this->notifications = [];
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): static
    {
        $this->username = $username;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->username;
    }

    /**
     * @see UserInterface
     *
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function getSalt(): ?string
    {
        return null;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }

    /**
     * @return Collection<int, Review>
     */
    public function getReviews(): Collection
    {
        return $this->reviews;
    }

    public function addReview(Review $review): static
    {
        if (!$this->reviews->contains($review)) {
            $this->reviews->add($review);
            $review->setUser($this);
        }

        return $this;
    }

    public function removeReview(Review $review): static
    {
        if ($this->reviews->removeElement($review)) {
            // set the owning side to null (unless already changed)
            if ($review->getUser() === $this) {
                $review->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Book>
     */
    public function getBooks(): Collection
    {
        return $this->books;
    }

    public function getAvatarFilename(): ?string
    {
        return $this->avatarFilename;
    }

    public function setAvatarFilename(?string $avatarFilename): self
    {
        $this->avatarFilename = $avatarFilename;
        return $this;
    }

     /**
     * @return array<array-key, array{message: string, createdAt: \DateTime, isRead: bool}>
     */
    public function getNotifications(): array
    {
        return $this->notifications ?? []; // Provide default empty array if null
    }

    public function addNotification(string $message): static
    {
        if (!is_array($this->notifications)) {
            $this->notifications = [];
        }

        $this->notifications[] = [
            'message' => $message,
            'isRead' => false,
        ];

        return $this;
    }

    public function clearNotifications(): static
    {
        $this->notifications = [];
        return $this;
    }

    public function markNotificationAsRead(int $index): static
    {
        if (isset($this->notifications[$index])) {
            $this->notifications[$index]['isRead'] = true;
        }
        return $this;
    }

    public function setNotifications(array $notifications): static
    {
        $this->notifications = $notifications;
        return $this;
    }

    public function getGoogleId(): ?string
    {
        return $this->googleId;
    }

    public function setGoogleId(?string $googleId): self
    {
        $this->googleId = $googleId;
        return $this;
    }

    public function getGoogleAccessToken(): ?string
    {
        return $this->googleAccessToken;
    }

    public function setGoogleAccessToken(?string $googleAccessToken): self
    {
        $this->googleAccessToken = $googleAccessToken;
        return $this;
    }

    public function getGoogleRefreshToken(): ?string
    {
        return $this->googleRefreshToken;
    }

    public function setGoogleRefreshToken(?string $googleRefreshToken): self
    {
        $this->googleRefreshToken = $googleRefreshToken;
        return $this;
    }

    public function getGoogleTokenExpires(): ?\DateTimeInterface
    {
        return $this->googleTokenExpires;
    }

    public function setGoogleTokenExpires(?\DateTimeInterface $googleTokenExpires): self
    {
        $this->googleTokenExpires = $googleTokenExpires;
        return $this;
    }

    public function isGoogleConnected(): bool
    {
        return $this->googleId !== null;
    }
}