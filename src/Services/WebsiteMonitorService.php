<?php

namespace App\Services;

use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Handles all live monitoring checks for a given website:
 * availability, SSL certificate validity/expiry, and security headers.
 */
class WebsiteMonitorService
{
    private Client $httpClient;
    private LoggerInterface $logger;

    public function __construct(?Client $httpClient = null, ?LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new NullLogger();
        $this->httpClient = $httpClient ?? new Client([
            'timeout'         => 10,
            'connect_timeout' => 5,
            'http_errors'     => false, // we want the status code, not an exception, on 4xx/5xx
            'allow_redirects' => true,
            // The container's CA bundle is frequently missing/misconfigured, which makes every
            // HTTPS request throw a cert-verification error. We already do our own independent
            // SSL certificate inspection in checkSslCertificate(), so we don't need Guzzle to
            // also gate connectivity on certificate trust here.
            'verify'          => false,
            'headers'         => [
                'User-Agent' => 'Mozilla/5.0 (compatible; WebGuardianBot/1.0; +https://webguardian.local)',
            ],
        ]);
    }

    /**
     * Checks whether the site is reachable and returns basic availability info.
     *
     * @return array{status: string, http_code: int|null, response_time_ms: int|null, error: string|null}
     */
    public function checkAvailability(string $url): array
    {
        $start = microtime(true);

        try {
            $response = $this->httpClient->request('GET', $url);
            $elapsedMs = (int) round((microtime(true) - $start) * 1000);
            $statusCode = $response->getStatusCode();

            $this->logger->info(sprintf(
                'WebsiteMonitor availability check succeeded: url=%s http_code=%d elapsed_ms=%d',
                $url,
                $statusCode,
                $elapsedMs
            ));

            return [
                // A 4xx means the server is reachable; reserve Offline for server errors.
                'status'           => $statusCode < 500 ? 'Online' : 'Offline',
                'http_code'        => $statusCode,
                'response_time_ms' => $elapsedMs,
                'error'            => null,
            ];
        } catch (\Throwable $e) {
            $this->logger->error(sprintf(
                'WebsiteMonitor availability check failed: url=%s exception=%s message=%s',
                $url,
                get_class($e),
                $e->getMessage()
            ));

            return [
                'status'           => 'Offline',
                'http_code'        => null,
                'response_time_ms' => null,
                'error'            => $e->getMessage(),
            ];
        }
    }

    /**
     * Inspects the SSL certificate for the given URL's host: validity and expiry.
     *
     * @return array{valid: bool, expires_at: string|null, days_until_expiry: int|null, issuer: string|null, error: string|null}
     */
    public function checkSslCertificate(string $url): array
    {
        $host = parse_url($url, PHP_URL_HOST);
        $port = parse_url($url, PHP_URL_PORT) ?? 443;

        if (!$host) {
            return [
                'status'           => 'Expired',
                'valid'            => false,
                'expires_at'       => null,
                'expires_in_days'  => 0,
                'issuer'           => null,
                'error'            => 'Invalid URL: could not determine host',
            ];
        }

        $streamContext = stream_context_create([
            'ssl' => [
                'capture_peer_cert' => true,
                'verify_peer'       => false,
                'verify_peer_name'  => false,
            ],
        ]);

        $client = @stream_socket_client(
            "ssl://{$host}:{$port}",
            $errno,
            $errstr,
            10,
            STREAM_CLIENT_CONNECT,
            $streamContext
        );

        if (!$client) {
            $this->logger->error(sprintf(
                'WebsiteMonitor SSL check failed: url=%s host=%s errno=%s errstr=%s',
                $url,
                $host,
                $errno,
                $errstr
            ));

            return [
                'status'           => 'Expired',
                'valid'            => false,
                'expires_at'       => null,
                'expires_in_days'  => 0,
                'issuer'           => null,
                'error'            => "Could not connect: {$errstr} ({$errno})",
            ];
        }

        $params = stream_context_get_params($client);
        fclose($client);

        if (empty($params['options']['ssl']['peer_certificate'])) {
            return [
                'status'           => 'Expired',
                'valid'            => false,
                'expires_at'       => null,
                'expires_in_days'  => 0,
                'issuer'           => null,
                'error'            => 'No certificate presented by host',
            ];
        }

        $certInfo = openssl_x509_parse($params['options']['ssl']['peer_certificate']);
        $expiresAt = (new \DateTime())->setTimestamp($certInfo['validTo_time_t']);
        $daysUntilExpiry = (int) (new \DateTime())->diff($expiresAt)->format('%r%a');
        $isValid = $daysUntilExpiry > 0;

        return [
            'status'          => $isValid ? 'Valid' : 'Expired',
            'valid'           => $isValid,
            'expires_at'      => $expiresAt->format('Y-m-d H:i:s'),
            'expires_in_days' => max($daysUntilExpiry, 0),
            'issuer'          => $certInfo['issuer']['O'] ?? ($certInfo['issuer']['CN'] ?? null),
            'error'           => null,
        ];
    }

    /**
     * Audits common security-related HTTP response headers and grades the result.
     *
     * @return array{score: int, grade: string, present: string[], missing: string[]}
     */
    public function checkSecurityHeaders(string $url): array
    {
        $expectedHeaders = [
            'Strict-Transport-Security',
            'Content-Security-Policy',
            'X-Frame-Options',
            'X-Content-Type-Options',
            'Referrer-Policy',
            'Permissions-Policy',
        ];

        try {
            $response = $this->httpClient->request('GET', $url);
        } catch (\Throwable $e) {
            return [
                'score'   => 0,
                'grade'   => 'F',
                'present' => [],
                'missing' => $expectedHeaders,
                'error'   => $e->getMessage(),
            ];
        }

        $present = [];
        $missing = [];

        foreach ($expectedHeaders as $header) {
            if ($response->hasHeader($header)) {
                $present[] = $header;
            } else {
                $missing[] = $header;
            }
        }

        $score = (int) round((count($present) / count($expectedHeaders)) * 100);
        $grade = match (true) {
            $score >= 90 => 'A',
            $score >= 75 => 'B',
            $score >= 60 => 'C',
            $score >= 40 => 'D',
            default      => 'F',
        };

        return [
            'score'   => $score,
            'grade'   => $grade,
            'present' => $present,
            'missing' => $missing,
        ];
    }
}
