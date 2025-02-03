<?php
namespace App\Entity;

use App\Repository\WebsiteRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WebsiteRepository::class)]
class Website
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $Url = null;

    #[ORM\Column(length: 255)]
    private ?string $Availabilty = null;

    #[ORM\Column(length: 255)]
    private ?string $SSL_Status = null;

    #[ORM\Column(length: 255)]
    private ?int $Expires_in = null;

    #[ORM\Column(type: 'datetime', nullable: false)]
    private ?\DateTimeInterface $lastCheck = null;

    #[ORM\ManyToOne(inversedBy: 'websites')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUrl(): ?string
    {
        return $this->Url;
    }

    public function setUrl(string $Url): static
    {
        $this->Url = $Url;
        return $this;
    }

    public function getAvailabilty(): ?string
    {
        return $this->Availabilty;
    }

    public function setAvailabilty(string $Availabilty): static
    {
        $this->Availabilty = $Availabilty;
        return $this;
    }

    public function getSSLStatus(): ?string
    {
        return $this->SSL_Status;
    }

    public function setSSLStatus(string $SSL_Status): static
    {
        $this->SSL_Status = $SSL_Status;
        return $this;
    }

    public function getExpiresIn(): ?int
    {
        return $this->Expires_in;
    }

    public function setExpiresIn(int $Expires_in): static
    {
        $this->Expires_in = $Expires_in;
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
    public function getLastCheck(): ?\DateTimeInterface
    {
        return $this->lastCheck;
    }

    public function setLastCheck(\DateTimeInterface $lastCheck): static
    {
        $this->lastCheck = $lastCheck;
        return $this;
    }
}
