<?php

namespace App\Entity;

use App\Repository\TradeOfferRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TradeOfferRepository::class)]
class TradeOffer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: "receiver_name", referencedColumnName: "id", nullable: false)]
    #[Assert\NotNull(message: 'Please select a receiver')]
    private ?User $receiver_name = null;

    #[ORM\ManyToOne(targetEntity: Artwork::class)]
    #[ORM\JoinColumn(name: "offered_item", referencedColumnName: "id", nullable: false)]
    #[Assert\NotNull(message: 'Please select an item to offer')]
    private ?Artwork $offered_item = null;

    #[ORM\ManyToOne(targetEntity: Artwork::class)]
    #[ORM\JoinColumn(name: "received_item", referencedColumnName: "id", nullable: false)]
    #[Assert\NotNull(message: 'Please select an item to receive')]
    private ?Artwork $received_item = null;

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: "datetime")]
    private ?\DateTimeInterface $creation_date = null;

    #[ORM\Column(type: "string", length: 50, options: ["default" => "pending"])]
    private ?string $status = "pending";

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReceiverName(): ?User
    {
        return $this->receiver_name;
    }

    public function setReceiverName(?User $receiver_name): static
    {
        $this->receiver_name = $receiver_name;

        return $this;
    }

    public function getOfferedItem(): ?Artwork
    {
        return $this->offered_item;
    }

    public function setOfferedItem(?Artwork $offered_item): static
    {
        $this->offered_item = $offered_item;

        return $this;
    }

    public function getReceivedItem(): ?Artwork
    {
        return $this->received_item;
    }

    public function setReceivedItem(?Artwork $received_item): static
    {
        $this->received_item = $received_item;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getCreationDate(): ?\DateTimeInterface
    {
        return $this->creation_date;
    }

    public function setCreationDate(\DateTimeInterface $creation_date): static
    {
        $this->creation_date = $creation_date;

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

    public function __toString(): string
    {
        return (string) $this->id; 
    }
}
