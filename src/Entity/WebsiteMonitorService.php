<?php

namespace App\Entity;

use App\Repository\WebsiteMonitorServiceRepository;
use Doctrine\ORM\Mapping as ORM;
use GuzzleHttp\Client;
use Symfony\Component\Process\Process;

#[ORM\Entity(repositoryClass: WebsiteMonitorServiceRepository::class)]
class WebsiteMonitorService
{

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;
    private $httpClient;

    public function __construct(Client $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    public function checkAvailability(string $url): array
    {
        $url = $this->ensureHttps($url);
        try {
            $response = $this->httpClient->request('GET', $url, [
                'verify' => false,
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
                ]
            ]);
            return [
                'status' => 'Online',
                'statusCode' => $response->getStatusCode()
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'Offline',
                'error' => $e->getMessage()
            ];
        }
    }
    


    public function checkSslCertificate(string $url): array
    {
        $url = $this->ensureHttps($url);

        $host = parse_url($url, PHP_URL_HOST);
    
        $context = stream_context_create([
            'ssl' => [
                'capture_peer_cert' => true,
                'verify_peer' => true,
                'verify_peer_name' => true,
            ]
        ]);
    
        $client = @stream_socket_client("ssl://$host:443", $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $context);
    
        if (!$client) {
            return ['expires_in_days' => 0, 'error' => "Unable to connect: $errstr ($errno)"];
        }
    
        $params = stream_context_get_params($client);
    
        if (!isset($params['options']['ssl']['peer_certificate'])) {
            return ['expires_in_days' => -1, 'error' => 'No SSL certificate found'];
        }
    
        $certInfo = openssl_x509_parse($params['options']['ssl']['peer_certificate']);
    
        if (!$certInfo || !isset($certInfo['validTo_time_t'])) {
            return ['expires_in_days' => -2, 'error' => 'Invalid certificate information'];
        }
    
        $expirationDate = $this->parseSslCertificateExpiration($certInfo);
    
        return [
            'expires_in_days' => $expirationDate,
            'status' => $expirationDate > 0 ? 'Valid' : 'expired'
        ];
    }
    
    private function parseSslCertificateExpiration(array $certInfo): int
    {
        
        if (isset($certInfo['validTo_time_t'])) {
            $expirationDate = new \DateTime('@' . $certInfo['validTo_time_t']); 
            $now = new \DateTime();
           
            return $now->diff($expirationDate)->days;
        }
        
        return -2; 
    }

    private function ensureHttps(string $url): string
{
    if (strpos($url, 'https://') !== 0) {
        return 'https://' . ltrim($url, '/');
    }
    return $url;
}
    
}
