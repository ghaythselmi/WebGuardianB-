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

    #[ORM\Column(length: 16, nullable: true)]
    private ?string $lastAlertedAvailability = null;

    #[ORM\Column(nullable: true)]
    private ?int $lastSslAlertLevel = null;

    #[ORM\Column(nullable: true)] private ?int $httpStatusCode = null;
    #[ORM\Column(nullable: true)] private ?int $responseTimeMs = null;
    #[ORM\Column(length: 2048, nullable: true)] private ?string $finalUrl = null;
    #[ORM\Column(length: 255, nullable: true)] private ?string $pageTitle = null;
    #[ORM\Column(length: 255, nullable: true)] private ?string $sslIssuer = null;
    #[ORM\Column(length: 40, nullable: true)] private ?string $sslExpiresAt = null;
    #[ORM\Column(type: 'json', nullable: true)] private ?array $securityHeadersPresent = null;
    #[ORM\Column(type: 'json', nullable: true)] private ?array $securityHeadersMissing = null;
    #[ORM\Column(nullable: true)] private ?int $headersScore = null;
    #[ORM\Column(length: 2, nullable: true)] private ?string $headersGrade = null;

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

    public function getLastAlertedAvailability(): ?string { return $this->lastAlertedAvailability; }
    public function setLastAlertedAvailability(?string $state): static { $this->lastAlertedAvailability = $state; return $this; }
    public function getLastSslAlertLevel(): ?int { return $this->lastSslAlertLevel; }
    public function setLastSslAlertLevel(?int $level): static { $this->lastSslAlertLevel = $level; return $this; }
    public function getHttpStatusCode(): ?int { return $this->httpStatusCode; }
    public function getResponseTimeMs(): ?int { return $this->responseTimeMs; }
    public function getFinalUrl(): ?string { return $this->finalUrl; }
    public function getPageTitle(): ?string { return $this->pageTitle; }
    public function getSslIssuer(): ?string { return $this->sslIssuer; }
    public function getSslExpiresAt(): ?string { return $this->sslExpiresAt; }
    public function getSecurityHeadersPresent(): ?array { return $this->securityHeadersPresent; }
    public function getSecurityHeadersMissing(): ?array { return $this->securityHeadersMissing; }
    public function getHeadersScore(): ?int { return $this->headersScore; }
    public function getHeadersGrade(): ?string { return $this->headersGrade; }
    public function setCheckDetails(array $availability, array $ssl, array $headers): static
    {
        $this->httpStatusCode = $availability['http_code'] ?? null;
        $this->responseTimeMs = $availability['response_time_ms'] ?? null;
        $this->finalUrl = $availability['final_url'] ?? null;
        $this->pageTitle = $availability['page_title'] ?? null;
        $this->sslIssuer = $ssl['issuer'] ?? null;
        $this->sslExpiresAt = $ssl['expires_at'] ?? null;
        $this->securityHeadersPresent = $headers['present'] ?? [];
        $this->securityHeadersMissing = $headers['missing'] ?? [];
        $this->headersScore = $headers['score'] ?? null;
        $this->headersGrade = $headers['grade'] ?? null;
        return $this;
    }
}
