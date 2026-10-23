<?php
namespace App\Controller;

use App\Services\WebsiteMonitorService;
use App\Repository\UserRepository;
use App\Service\AuthTokenService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/monitor')]
class WebsiteMonitorController extends AbstractController
{
    private $websiteMonitorService;

    public function __construct(WebsiteMonitorService $websiteMonitorService)
    {
        $this->websiteMonitorService = $websiteMonitorService;
    }

    #[Route('/check', name: 'check_website', methods: ['POST'])]
    public function check(Request $request, AuthTokenService $tokens, UserRepository $users): JsonResponse
    {
        $user = $tokens->authenticate($request, $users);
        if ($user instanceof JsonResponse) return $user;
        $data = json_decode($request->getContent(), true);
        $url = $data['url'] ?? null;
        if (!is_string($url) || !filter_var($url, FILTER_VALIDATE_URL) || !in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
            return new JsonResponse(['error' => 'A valid HTTP or HTTPS URL is required'], 400);
        }

        $availability = $this->websiteMonitorService->checkAvailability($url);
        $sslStatus = $this->websiteMonitorService->checkSslCertificate($url);
        $headers = $this->websiteMonitorService->checkSecurityHeaders($url);

        return new JsonResponse([
            'availability' => $availability,
            'ssl' => $sslStatus,
            'headers' => $headers,
        ]);
    }
}
