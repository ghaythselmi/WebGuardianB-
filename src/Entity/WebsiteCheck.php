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

    public function __construct(Website $website, string $availability, ?int $httpStatusCode, ?int $responseTimeMs, string $sslStatus, ?int $sslExpiresInDays, ?int $headersScore, ?string $headersGrade)
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
    }

    public function getCheckedAt(): \DateTimeImmutable { return $this->checkedAt; }
    public function getAvailability(): string { return $this->availability; }
    public function getHttpStatusCode(): ?int { return $this->httpStatusCode; }
    public function getResponseTimeMs(): ?int { return $this->responseTimeMs; }
    public function getSslStatus(): string { return $this->sslStatus; }
    public function getSslExpiresInDays(): ?int { return $this->sslExpiresInDays; }
    public function getHeadersScore(): ?int { return $this->headersScore; }
    public function getHeadersGrade(): ?string { return $this->headersGrade; }
}
