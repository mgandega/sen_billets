<?php
// src/Entity/Payment.php
namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\PaymentRepository;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;

#[ORM\Entity(repositoryClass: PaymentRepository::class)]
class Payment
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';
    public const STATUS_COMPLETED = 'terminé';
    
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(type: 'float')]
    private float $amount;

    #[ORM\Column(type: 'string', length: 50)]
    private string $status;

    #[ORM\Column(type: 'string', length: 50)]
    private string $method;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $invoicePath = null;

    #[ORM\OneToMany(mappedBy: 'payment', targetEntity: CartItem::class, cascade: ['persist'])]
    private Collection $cartItems;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    private ?string $qrCode = null;
    private ?string $qrCodeImagePath = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $paydunyaToken = null;

    public function __construct()
    {
        $this->cartItems = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();

    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function setAmount(float $amount): self
    {
        $this->amount = $amount;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getPaymentMethod(): string
    {
        return $this->method;
    }

    public function setPaymentMethod(string $method): self
    {
        $this->method = $method;

        return $this;
    }

    public function getInvoicePath(): ?string
    {
        return $this->invoicePath;
    }

    public function setInvoicePath(?string $invoicePath): self
    {
        $this->invoicePath = $invoicePath;

        return $this;
    }

    public function getCartItems(): Collection
    {
        return $this->cartItems;
    }

    public function addCartItem(CartItem $item): self
    {
        if (!$this->cartItems->contains($item)) {
            $this->cartItems[] = $item;
            $item->setPayment($this);
        }

        return $this;
    }

    public function removeCartItem(CartItem $item): self
    {
        if ($this->cartItems->removeElement($item)) {
            if ($item->getPayment() === $this) {
                $item->setPayment(null);
            }
        }

        return $this;
    }

    public function getMethod(): ?string
    {
        return $this->method;
    }

    public function setMethod(string $method): static
    {
        $this->method = $method;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    // src/Entity/Payment.php

    public function getQrCode(): ?string
    {
        return $this->qrCode;
    }

    public function setQrCode(?string $qrCode): self
    {
        $this->qrCode = $qrCode;
        return $this;
    }

    public function getQrCodeImagePath(): ?string
    {
        return $this->qrCodeImagePath;
    }

    public function setQrCodeImagePath(?string $qrCodeImagePath): self
    {
        $this->qrCodeImagePath = $qrCodeImagePath;
        return $this;
    }

    public function getFormattedAmount(): string
    {
        return number_format($this->amount, 2, ',', ' ') . ' XOF'; 
        // Change 'XOF' selon ta monnaie
    }

    public function getPaydunyaToken(): ?string { 
        return $this->paydunyaToken; 
    }
    public function setPaydunyaToken(?string $token): self { 
        $this->paydunyaToken = $token; return $this; 
    }
    
}
