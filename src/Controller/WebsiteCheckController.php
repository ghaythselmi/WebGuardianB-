<?php

namespace App\Controller;

use App\Entity\WebsiteCheck;
use App\Repository\UserRepository;
use App\Repository\WebsiteRepository;
use App\Service\AuthTokenService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/websites/{id}/checks', requirements: ['id' => '\\d+'])]
class WebsiteCheckController extends AbstractController
{
    #[Route('', methods: ['GET'])]
    public function history(int $id, Request $request, AuthTokenService $tokens, UserRepository $users, WebsiteRepository $websites, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $tokens->authenticate($request, $users);
        if ($user instanceof JsonResponse) return $user;
        $website = $websites->find($id);
        if (!$website || $website->getUser()?->getId() !== $user->getId()) return new JsonResponse(['error' => 'Website not found'], 404);
        $checks = $entityManager->getRepository(WebsiteCheck::class)->findBy(['website' => $website], ['checkedAt' => 'DESC'], 100);
        return new JsonResponse(array_map(static fn (WebsiteCheck $check) => [
            'checkedAt' => $check->getCheckedAt()->format(DATE_ATOM), 'availability' => $check->getAvailability(),
            'httpStatusCode' => $check->getHttpStatusCode(), 'responseTimeMs' => $check->getResponseTimeMs(),
            'sslStatus' => $check->getSslStatus(), 'sslExpiresInDays' => $check->getSslExpiresInDays(),
            'headersScore' => $check->getHeadersScore(), 'headersGrade' => $check->getHeadersGrade(),
        ], $checks));
    }
}
