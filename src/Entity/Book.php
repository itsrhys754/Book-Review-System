<?php

namespace App\Entity;

use App\Repository\BookRepository;
use Rhys\ReviewBundle\Entity\Review;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: BookRepository::class)]
class Book
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Serializer\Groups(['review:list', 'review:item'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Title cannot be blank")]
    #[Assert\Length(min: 1, max: 255, maxMessage: "Title cannot be longer than {{ limit }} characters")]
    #[Serializer\Groups(['review:list', 'review:item'])]
    private ?string $title = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Author cannot be blank")]
    #[Assert\Length(min: 1, max: 255, maxMessage: "Author name cannot be longer than {{ limit }} characters")]
    private ?string $author = null;

    #[ORM\Column]
    #[Assert\NotBlank(message: "Number of pages cannot be blank")]
    #[Assert\Positive(message: "Number of pages must be positive")]
    #[Serializer\Exclude]
    private ?int $pages = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Serializer\Exclude]
    private ?string $summary = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Genre cannot be blank")]
    #[Assert\Length(max: 255, maxMessage: "Genre cannot be longer than {{ limit }} characters")]
    #[Serializer\Exclude]
    private ?string $genre = null;

    /**
     * @var Collection<int, Review>
     */
    #[ORM\OneToMany(targetEntity: Review::class, mappedBy: 'book', orphanRemoval: true)]
    private Collection $reviews;

    /**
     * @ORM\Column(type="boolean")
     */
    #[ORM\Column(type: 'boolean')]
    #[Serializer\Exclude]
    private $approved = false; 

    #[ORM\Column(length: 255, nullable: true)]
    #[Serializer\Exclude]
    private ?string $imageFilename = null;
    
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $googleBooksId = null;

    #[ORM\Column(length: 13, nullable: true, unique: true)]
    #[Assert\Isbn(
        type: Assert\Isbn::ISBN_13,
        message: 'This value is not a valid ISBN-13'
    )]
    private ?string $isbn = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255, maxMessage: "Publisher name cannot be longer than {{ limit }} characters")]
    private ?string $publisher = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $publishedDate = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'books')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    public function __construct()
    {
        $this->reviews = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getAuthor(): ?string
    {
        return $this->author;
    }

    public function setAuthor(string $author): static
    {
        $this->author = $author;

        return $this;
    }

    public function getPages(): ?int
    {
        return $this->pages;
    }

    public function setPages(int $pages): static
    {
        $this->pages = $pages;

        return $this;
    }

    public function getSummary(): ?string
    {
        return $this->summary;
    }

    public function setSummary(?string $summary): static
    {
        $this->summary = $summary;

        return $this;
    }

    public function getGenre(): ?string
    {
        return $this->genre;
    }

    public function setGenre(string $genre): static
    {
        $this->genre = $genre;

        return $this;
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
            $review->setBook($this);
        }

        return $this;
    }

    public function removeReview(Review $review): static
    {
        if ($this->reviews->removeElement($review)) {
            // set the owning side to null (unless already changed)
            if ($review->getBook() === $this) {
                $review->setBook(null);
            }
        }

        return $this;
    }

    public function isApproved(): bool
    {
        return $this->approved;
    }

    public function setApproved(bool $approved): self
    {
        $this->approved = $approved;
        return $this;
    }

    public function getImageFilename(): ?string
    {
        return $this->imageFilename;
    }

    public function setImageFilename(?string $imageFilename): self
    {
        $this->imageFilename = $imageFilename;
        return $this;
    }

    public function getGoogleBooksId(): ?string
    {
        return $this->googleBooksId;
    }

    public function setGoogleBooksId(?string $googleBooksId): self
    {
        $this->googleBooksId = $googleBooksId;
        return $this;
    }

    public function getIsbn(): ?string
    {
        return $this->isbn;
    }

    public function setIsbn(?string $isbn): static
    {
        $this->isbn = $isbn;
        return $this;
    }

    public function getPublisher(): ?string
    {
        return $this->publisher;
    }

    public function setPublisher(?string $publisher): static
    {
        $this->publisher = $publisher;

        return $this;
    }

    public function getPublishedDate(): ?string
    {
        return $this->publishedDate;
    }

    public function setPublishedDate(?string $publishedDate): static
    {
        $this->publishedDate = $publishedDate;

        return $this;
    }

    /**
     * Set additional data from Google Books API
     */
    public function setGoogleBooksData(array $volumeInfo): self
    {
        // Only update fields that are empty or if Google Books has more information
        if (empty($this->getAuthor()) && isset($volumeInfo['authors'][0])) {
            $this->setAuthor($volumeInfo['authors'][0]);
        }
        
        if (empty($this->getSummary()) && isset($volumeInfo['description'])) {
            $this->setSummary($volumeInfo['description']);
        }
        
        if (empty($this->getPages()) && isset($volumeInfo['pageCount'])) {
            $this->setPages($volumeInfo['pageCount']);
        }
        
        if (empty($this->getGenre()) && isset($volumeInfo['categories'][0])) {
            $this->setGenre($volumeInfo['categories'][0]);
        }

        if (empty($this->getPublisher()) && isset($volumeInfo['publisher'])) {
            $this->setPublisher($volumeInfo['publisher']);
        }

        if (empty($this->getPublishedDate()) && isset($volumeInfo['publishedDate'])) {
            $this->setPublishedDate($volumeInfo['publishedDate']);
        }

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }
}
