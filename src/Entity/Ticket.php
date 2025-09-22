<?php

namespace App\Entity;

use App\Repository\TicketRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use App\Entity\EventTicket;

#[ORM\Entity(repositoryClass: TicketRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Ticket
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['ticket:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    #[Groups(['ticket:read'])]
    private ?string $qrCode = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['ticket:read'])]
    private ?\DateTimeInterface $purchasedAt = null;

    #[ORM\Column(length: 50)]
    #[Groups(['ticket:read'])]
    #[Assert\Choice(choices: ['valid', 'used', 'cancelled'], message: 'Statut invalide')]
    private ?string $status = 'valid';

    #[ORM\Column(length: 255)]
    #[Groups(['ticket:read'])]
    #[Assert\NotBlank(message: 'Le nom du client est obligatoire')]
    private ?string $customerName = null;

    #[ORM\Column(length: 255)]
    #[Groups(['ticket:read'])]
    #[Assert\NotBlank(message: 'L\'email du client est obligatoire')]
    #[Assert\Email(message: 'L\'email n\'est pas valide')]
    private ?string $customerEmail = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['ticket:read'])]
    private ?\DateTimeInterface $usedAt = null;

    #[ORM\ManyToOne(inversedBy: 'tickets')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['ticket:read'])]
    private ?Event $event = null;

    #[ORM\ManyToOne(inversedBy: 'tickets')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: EventTicket::class, inversedBy: 'tickets')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['ticket:read'])]
    private ?EventTicket $ticketType = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isUsed = false;

    #[ORM\ManyToOne(targetEntity: Payment::class)]
    private ?Payment $payment = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $pdfPath = null;


    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $validatedBy = null;

    public function __construct()
    {
        $this->purchasedAt = new \DateTime();
        $this->qrCode = $this->generateQrCode();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getQrCode(): ?string
    {
        return $this->qrCode;
    }

    public function setQrCode(string $qrCode): static
    {
        $this->qrCode = $qrCode;
        return $this;
    }

    public function getPurchasedAt(): ?\DateTimeInterface
    {
        return $this->purchasedAt;
    }

    public function setPurchasedAt(\DateTimeInterface $purchasedAt): static
    {
        $this->purchasedAt = $purchasedAt;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        
        // Marquer la date d'utilisation si le billet est utilisé
        if ($status === 'used' && $this->usedAt === null) {
            $this->usedAt = new \DateTime();
        }
        
        return $this;
    }

    public function getCustomerName(): ?string
    {
        return $this->customerName;
    }

    public function setCustomerName(string $customerName): static
    {
        $this->customerName = $customerName;
        return $this;
    }

    public function getCustomerEmail(): ?string
    {
        return $this->customerEmail;
    }

    public function setCustomerEmail(string $customerEmail): static
    {
        $this->customerEmail = $customerEmail;
        return $this;
    }

    public function getUsedAt(): ?\DateTimeInterface
    {
        return $this->usedAt;
    }

    public function setUsedAt(?\DateTimeInterface $usedAt): static
    {
        $this->usedAt = $usedAt;
        return $this;
    }

    public function getEvent(): ?Event
    {
        return $this->event;
    }

    public function setEvent(?Event $event): static
    {
        $this->event = $event;
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

    public function getTicketType(): ?EventTicket
    {
        return $this->ticketType;
    }

    public function setTicketType(?EventTicket $ticketType): static
    {
        $this->ticketType = $ticketType;
        return $this;
    }

    // Méthodes utilitaires

    private function generateQrCode(): string
    {
        return 'SB-' . strtoupper(uniqid()) . '-' . date('Ymd');
    }

    public function isValid(): bool
    {
        return $this->status === 'valid';
    }

    public function isUsed(): bool
    {
        return $this->status === 'used';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function canBeUsed(): bool
    {
        if (!$this->isValid()) {
            return false;
        }

        // Vérifier si l'événement n'est pas encore passé
        if ($this->event && $this->event->getEventDate() < new \DateTime()) {
            return false;
        }

        return true;
    }

    public function getStatusLabel(): string
    {
        return match($this->status) {
            'valid' => 'Valide',
            'used' => 'Utilisé',
            'cancelled' => 'Annulé',
            default => 'Inconnu'
        };
    }

    public function getStatusBadgeClass(): string
    {
        return match($this->status) {
            'valid' => 'bg-success',
            'used' => 'bg-secondary',
            'cancelled' => 'bg-danger',
            default => 'bg-light'
        };
    }

    public function __toString(): string
    {
        return $this->qrCode ?? '';
    }

    //     // Génère un code unique si vide
    // public function generateQrCode(): void
    // {
    //     if (!$this->qrCode) {
    //         $this->qrCode = bin2hex(random_bytes(10));
    //     }
    // }
    // src/Entity/Ticket.php

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $qrCodeImagePath = null;

    public function getQrCodeImagePath(): ?string
    {
        return $this->qrCodeImagePath;
    }

    public function setQrCodeImagePath(?string $path): self
    {
        $this->qrCodeImagePath = $path;
        return $this;
    }

    public function setIsUsed(bool $isUsed): static
    {
        $this->isUsed = $isUsed;

        return $this;
    }

    public function getPayment(): ?Payment
    {
        return $this->payment;
    }

    public function setPayment(?Payment $payment): static
    {
        $this->payment = $payment;

        return $this;
    }

    public function getQrCodeBase64(): ?string
    {
        $path = __DIR__ . '/../../public' . $this->qrCodeImagePath;

        if (!file_exists($path)) {
            return null;
        }

        $data = file_get_contents($path);
        return base64_encode($data);
    }

        public function getPdfPath(): ?string
    {
        return $this->pdfPath;
    }

    public function setPdfPath(?string $pdfPath): self
    {
        $this->pdfPath = $pdfPath;
        return $this;
    }

    public function getValidatedBy(): ?User
    {
        return $this->validatedBy;
    }

    public function setValidatedBy(?User $user): static
    {
        $this->validatedBy = $user;
        return $this;
    }


}