<?php

namespace App\Entity;

use App\Repository\EventRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use App\Entity\EventTicket;
use App\Entity\EventTicketType;

#[ORM\Entity(repositoryClass: EventRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Event
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['event:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['event:read', 'event:write'])]
    #[Assert\NotBlank(message: 'Le titre est obligatoire')]
    #[Assert\Length(min: 3, max: 255, minMessage: 'Le titre doit contenir au moins 3 caractères')]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['event:read', 'event:write'])]
    #[Assert\NotBlank(message: 'La description est obligatoire')]
    #[Assert\Length(min: 10, minMessage: 'La description doit contenir au moins 10 caractères')]
    private ?string $description = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['event:read', 'event:write'])]
    #[Assert\NotBlank(message: 'La date de l\'événement est obligatoire')]
    #[Assert\GreaterThan('today', message: 'La date de l\'événement doit être dans le futur')]
    private ?\DateTimeInterface $eventDate = null;

    #[ORM\Column(type: Types::TIME_MUTABLE)]
    #[Groups(['event:read', 'event:write'])]
    #[Assert\NotBlank(message: 'L\'heure de début est obligatoire')]
    private ?\DateTimeInterface $startTime = null;

    #[ORM\Column(type: Types::TIME_MUTABLE)]
    #[Groups(['event:read', 'event:write'])]
    #[Assert\NotBlank(message: 'L\'heure de fin est obligatoire')]
    private ?\DateTimeInterface $endTime = null;

    #[ORM\Column(length: 255)]
    #[Groups(['event:read', 'event:write'])]
    #[Assert\NotBlank(message: 'Le lieu est obligatoire')]
    private ?string $venue = null;

    #[ORM\Column(length: 500)]
    #[Groups(['event:read', 'event:write'])]
    #[Assert\NotBlank(message: 'L\'adresse est obligatoire')]
    private ?string $address = null;

    #[ORM\Column(length: 100)]
    #[Groups(['event:read', 'event:write'])]
    #[Assert\NotBlank(message: 'La catégorie est obligatoire')]
    private ?string $category = null;

    #[ORM\Column(length: 500, nullable: true)]
    #[Groups(['event:read', 'event:write'])]
    #[Assert\Url(message: 'L\'URL de l\'image n\'est pas valide')]
    private ?string $imageUrl = null;

    #[ORM\Column]
    #[Groups(['event:read', 'event:write'])]
    #[Assert\NotBlank(message: 'La capacité est obligatoire')]
    #[Assert\Positive(message: 'La capacité doit être positive')]
    private ?int $capacity = null;

    #[ORM\Column]
    #[Groups(['event:read'])]
    private ?int $soldTickets = 0;

    #[ORM\Column(length: 50)]
    #[Groups(['event:read', 'event:write'])]
    #[Assert\Choice(choices: ['draft', 'published', 'cancelled'], message: 'Statut invalide')]
    private ?string $status = 'draft';

    #[ORM\Column(type: Types::JSON)]
    #[Groups(['event:read', 'event:write'])]
    private array $tags = [];

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['event:read'])]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['event:read'])]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\ManyToOne(inversedBy: 'organizedEvents')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['event:read'])]
    private ?User $organizer = null;

    #[ORM\OneToMany(mappedBy: 'event', targetEntity: EventTicket::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups(['event:read', 'event:write'])]
    #[Assert\Valid]
    #[Assert\Count(min: 1, minMessage: 'Au moins un type de billet est requis')]
    private Collection $ticketTypes;

    #[ORM\OneToMany(mappedBy: 'event', targetEntity: Ticket::class, cascade: ['remove'])]
    private Collection $tickets;

    public function __construct()
    {
        $this->ticketTypes = new ArrayCollection();
        $this->tickets = new ArrayCollection();
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTime();
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getEventDate(): ?\DateTimeInterface
    {
        return $this->eventDate;
    }

    public function setEventDate(\DateTimeInterface $eventDate): static
    {
        $this->eventDate = $eventDate;
        return $this;
    }

    public function getStartTime(): ?\DateTimeInterface
    {
        return $this->startTime;
    }

    public function setStartTime(\DateTimeInterface $startTime): static
    {
        $this->startTime = $startTime;
        return $this;
    }

    public function getEndTime(): ?\DateTimeInterface
    {
        return $this->endTime;
    }

    public function setEndTime(\DateTimeInterface $endTime): static
    {
        $this->endTime = $endTime;
        return $this;
    }

    public function getVenue(): ?string
    {
        return $this->venue;
    }

    public function setVenue(string $venue): static
    {
        $this->venue = $venue;
        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(string $address): static
    {
        $this->address = $address;
        return $this;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(string $category): static
    {
        $this->category = $category;
        return $this;
    }

    public function getImageUrl(): ?string
    {
        return $this->imageUrl;
    }

    public function setImageUrl(?string $imageUrl): static
    {
        $this->imageUrl = $imageUrl;
        return $this;
    }

    public function getCapacity(): ?int
    {
        return $this->capacity;
    }

    public function setCapacity(int $capacity): static
    {
        $this->capacity = $capacity;
        return $this;
    }

    public function getSoldTickets(): ?int
    {
        return $this->soldTickets;
    }

    public function setSoldTickets(int $soldTickets): static
    {
        $this->soldTickets = $soldTickets;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getTags(): array
    {
        return $this->tags;
    }

    public function setTags(array $tags): static
    {
        $this->tags = $tags;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getOrganizer(): ?User
    {
        return $this->organizer;
    }

    public function setOrganizer(?User $organizer): static
    {
        $this->organizer = $organizer;
        return $this;
    }

    /**
     * @return Collection<int, EventTicket>
     */
    public function getTicketTypes(): Collection
    {
        return $this->ticketTypes;
    }

    public function addTicketType(EventTicket $ticketType): static
    {
        if (!$this->ticketTypes->contains($ticketType)) {
            $this->ticketTypes->add($ticketType);
            $ticketType->setEvent($this);
        }

        return $this;
    }

    public function removeTicketType(EventTicket $ticketType): static
    {
        if ($this->ticketTypes->removeElement($ticketType)) {
            if ($ticketType->getEvent() === $this) {
                $ticketType->setEvent(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Ticket>
     */
    public function getTickets(): Collection
    {
        return $this->tickets;
    }

    public function addTicket(Ticket $ticket): static
    {
        if (!$this->tickets->contains($ticket)) {
            $this->tickets->add($ticket);
            $ticket->setEvent($this);
        }

        return $this;
    }

    public function removeTicket(Ticket $ticket): static
    {
        if ($this->tickets->removeElement($ticket)) {
            if ($ticket->getEvent() === $this) {
                $ticket->setEvent(null);
            }
        }

        return $this;
    }

    // Méthodes utilitaires

    public function getAvailableTickets(): int
    {
        return $this->capacity - $this->soldTickets;
    }

    public function isAvailable(): bool
    {
        return $this->getAvailableTickets() > 0 && $this->status === 'published';
    }

    public function getMinPrice(): int
    {
        if ($this->ticketTypes->isEmpty()) {
            return 0;
        }

        $prices = [];
        foreach ($this->ticketTypes as $ticketType) {
            if ($ticketType->getAvailableQuantity() > 0) {
                $prices[] = $ticketType->getPrice();
            }
        }

        return empty($prices) ? 0 : min($prices);
    }

    public function getTotalRevenue(): int
    {
        $revenue = 0;
        foreach ($this->ticketTypes as $ticketType) {
            $revenue += $ticketType->getSold() * $ticketType->getPrice();
        }
        return $revenue;
    }

    public function getOccupancyRate(): float
    {
        if ($this->capacity === 0) {
            return 0;
        }
        return ($this->soldTickets / $this->capacity) * 100;
    }

    public function __toString(): string
    {
        return $this->title ?? '';
    }
}