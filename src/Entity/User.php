<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['email'], message: 'Il existe déjà un compte avec cet email')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['user:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Groups(['user:read', 'user:write'])]
    #[Assert\NotBlank(message: 'L\'email est obligatoire')]
    #[Assert\Email(message: 'L\'email n\'est pas valide')]
    private ?string $email = null;

    #[ORM\Column]
    #[Groups(['user:read'])]
    private array $roles = [];

    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 255)]
    #[Groups(['user:read', 'user:write'])]
    #[Assert\NotBlank(message: 'Le nom est obligatoire')]
    #[Assert\Length(min: 2, max: 255, minMessage: 'Le nom doit contenir au moins 2 caractères')]
    private ?string $name = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Groups(['user:read', 'user:write'])]
    #[Assert\Regex(pattern: '/^\+?[0-9\s\-\(\)]+$/', message: 'Le numéro de téléphone n\'est pas valide')]
    private ?string $phone = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['user:read', 'user:write'])]
    #[Assert\Url(message: 'L\'URL de l\'avatar n\'est pas valide')]
    private ?string $avatar = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['user:read'])]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\OneToMany(mappedBy: 'organizer', targetEntity: Event::class, cascade: ['remove'])]
    private Collection $organizedEvents;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Ticket::class, cascade: ['remove'])]
    private Collection $tickets;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: CartItem::class, cascade: ['remove'])]
    private Collection $cartItems;

    #[ORM\Column]
    private bool $isVerified = false;

    public function __construct()
    {
        $this->organizedEvents = new ArrayCollection();
        $this->tickets = new ArrayCollection();
        $this->cartItems = new ArrayCollection();
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';
        return array_unique($roles);
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;
        return $this;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;
        return $this;
    }

    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;
        return $this;
    }

    public function getAvatar(): ?string
    {
        return $this->avatar;
    }

    public function setAvatar(?string $avatar): static
    {
        $this->avatar = $avatar;
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

    /**
     * @return Collection<int, Event>
     */
    public function getOrganizedEvents(): Collection
    {
        return $this->organizedEvents;
    }

    public function addOrganizedEvent(Event $organizedEvent): static
    {
        if (!$this->organizedEvents->contains($organizedEvent)) {
            $this->organizedEvents->add($organizedEvent);
            $organizedEvent->setOrganizer($this);
        }

        return $this;
    }

    public function removeOrganizedEvent(Event $organizedEvent): static
    {
        if ($this->organizedEvents->removeElement($organizedEvent)) {
            if ($organizedEvent->getOrganizer() === $this) {
                $organizedEvent->setOrganizer(null);
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
            $ticket->setUser($this);
        }

        return $this;
    }

    public function removeTicket(Ticket $ticket): static
    {
        if ($this->tickets->removeElement($ticket)) {
            if ($ticket->getUser() === $this) {
                $ticket->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, CartItem>
     */
    public function getCartItems(): Collection
    {
        return $this->cartItems;
    }

    public function addCartItem(CartItem $cartItem): static
    {
        if (!$this->cartItems->contains($cartItem)) {
            $this->cartItems->add($cartItem);
            $cartItem->setUser($this);
        }

        return $this;
    }

    public function removeCartItem(CartItem $cartItem): static
    {
        if ($this->cartItems->removeElement($cartItem)) {
            if ($cartItem->getUser() === $this) {
                $cartItem->setUser(null);
            }
        }

        return $this;
    }

    // Méthodes utilitaires

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->getRoles());
    }

    public function isOrganizer(): bool
    {
        return $this->hasRole('ROLE_ORGANIZER');
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('ROLE_ADMIN');
    }

    public function getCartTotal(): float
    {
        $total = 0;
        foreach ($this->cartItems as $item) {
            $total += $item->getSubtotal();
        }
        return $total;
    }

    public function getCartItemsCount(): int
    {
        $count = 0;
        foreach ($this->cartItems as $item) {
            $count += $item->getQuantity();
        }
        return $count;
    }

    public function __toString(): string
    {
        return $this->name ?? $this->email ?? '';
    }

    public function getIsVerified(): bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): static
    {
        $this->isVerified = $isVerified;

        return $this;
    }

    // src/Entity/User.php

public function getFullName(): string
{
    return $this->name;
}
public function FullName(): string
{
    return $this->name;
}

public function isVerified(): ?bool
{
    return $this->isVerified;
}

}