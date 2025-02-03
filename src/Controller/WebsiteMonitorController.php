<?php
namespace App\Controller;

use App\Entity\WebsiteMonitorService;
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
    public function check(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $url = $data['url'];

        $availability = $this->websiteMonitorService->checkAvailability($url);
        $sslStatus = $this->websiteMonitorService->checkSslCertificate($url);

        return new JsonResponse([
            'availability' => $availability,
            'ssl' => $sslStatus
        ]);
    }
}
