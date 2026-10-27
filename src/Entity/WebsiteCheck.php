<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Index(columns: ['website_id', 'checked_at'])]
class WebsiteCheck
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Website $website;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $checkedAt;

    #[ORM\Column(length: 16)]
    private string $availability;

    #[ORM\Column(nullable: true)]
    private ?int $httpStatusCode = null;

    #[ORM\Column(nullable: true)]
    private ?int $responseTimeMs = null;

    #[ORM\Column(length: 16)]
    private string $sslStatus;

    #[ORM\Column(nullable: true)]
    private ?int $sslExpiresInDays = null;

    #[ORM\Column(nullable: true)]
    private ?int $headersScore = null;

    #[ORM\Column(length: 2, nullable: true)]
    private ?string $headersGrade = null;

    #[ORM\Column(length: 2048, nullable: true)] private ?string $finalUrl = null;
    #[ORM\Column(length: 255, nullable: true)] private ?string $pageTitle = null;
    #[ORM\Column(length: 255, nullable: true)] private ?string $sslIssuer = null;
    #[ORM\Column(length: 40, nullable: true)] private ?string $sslExpiresAt = null;
    #[ORM\Column(type: 'json', nullable: true)] private ?array $securityHeadersPresent = null;
    #[ORM\Column(type: 'json', nullable: true)] private ?array $securityHeadersMissing = null;

    public function __construct(Website $website, string $availability, ?int $httpStatusCode, ?int $responseTimeMs, string $sslStatus, ?int $sslExpiresInDays, ?int $headersScore, ?string $headersGrade, ?string $finalUrl = null, ?string $pageTitle = null, ?string $sslIssuer = null, ?string $sslExpiresAt = null, ?array $securityHeadersPresent = null, ?array $securityHeadersMissing = null)
    {
        $this->website = $website;
        $this->checkedAt = new \DateTimeImmutable();
        $this->availability = $availability;
        $this->httpStatusCode = $httpStatusCode;
        $this->responseTimeMs = $responseTimeMs;
        $this->sslStatus = $sslStatus;
        $this->sslExpiresInDays = $sslExpiresInDays;
        $this->headersScore = $headersScore;
        $this->headersGrade = $headersGrade;
        $this->finalUrl = $finalUrl;
        $this->pageTitle = $pageTitle;
        $this->sslIssuer = $sslIssuer;
        $this->sslExpiresAt = $sslExpiresAt;
        $this->securityHeadersPresent = $securityHeadersPresent;
        $this->securityHeadersMissing = $securityHeadersMissing;
    }

    public function getCheckedAt(): \DateTimeImmutable { return $this->checkedAt; }
    public function getAvailability(): string { return $this->availability; }
    public function getHttpStatusCode(): ?int { return $this->httpStatusCode; }
    public function getResponseTimeMs(): ?int { return $this->responseTimeMs; }
    public function getSslStatus(): string { return $this->sslStatus; }
    public function getSslExpiresInDays(): ?int { return $this->sslExpiresInDays; }
    public function getHeadersScore(): ?int { return $this->headersScore; }
    public function getHeadersGrade(): ?string { return $this->headersGrade; }
    public function getFinalUrl(): ?string { return $this->finalUrl; }
    public function getPageTitle(): ?string { return $this->pageTitle; }
    public function getSslIssuer(): ?string { return $this->sslIssuer; }
    public function getSslExpiresAt(): ?string { return $this->sslExpiresAt; }
    public function getSecurityHeadersPresent(): ?array { return $this->securityHeadersPresent; }
    public function getSecurityHeadersMissing(): ?array { return $this->securityHeadersMissing; }
}
