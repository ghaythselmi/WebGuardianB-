<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Website;
use App\Repository\UserRepository;
use App\Repository\WebsiteRepository;
use App\Service\AuthTokenService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/websites')]
class WebsiteController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $entityManager, private readonly WebsiteRepository $websites, private readonly AuthTokenService $tokens, private readonly UserRepository $users)
    {
    }

    #[Route('/', name: 'api_website_index', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $user = $this->currentUser($request);
        if ($user instanceof JsonResponse) return $user;
        return new JsonResponse(array_map(fn (Website $website) => $this->serializeWebsite($website), $this->websites->findBy(['user' => $user])));
    }

    #[Route('/{id}', name: 'api_website_show', methods: ['GET'], requirements: ['id' => '\\d+'])]
    public function show(int $id, Request $request): JsonResponse
    {
        $user = $this->currentUser($request);
        if ($user instanceof JsonResponse) return $user;
        $website = $this->ownedWebsite($id, $user);
        return $website instanceof JsonResponse ? $website : new JsonResponse($this->serializeWebsite($website));
    }

    #[Route('/new', name: 'api_website_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $user = $this->currentUser($request);
        if ($user instanceof JsonResponse) return $user;
        $data = json_decode($request->getContent(), true);
        if (!is_array($data) || !is_string($data['url'] ?? null) || !filter_var($data['url'], FILTER_VALIDATE_URL)) {
            return new JsonResponse(['error' => 'A valid website URL is required'], Response::HTTP_BAD_REQUEST);
        }
        if ($this->websites->findOneBy(['user' => $user, 'Url' => $data['url']])) {
            return new JsonResponse(['error' => 'This website is already monitored'], Response::HTTP_CONFLICT);
        }

        $website = new Website();
        $website->setUrl($data['url']);
        $website->setAvailabilty((string) ($data['availability'] ?? 'Unknown'));
        $website->setSSLStatus((string) ($data['ssl_status'] ?? 'Unknown'));
        $website->setExpiresIn((int) ($data['expires_in'] ?? 0));
        $website->setUser($user);
        $website->setLastCheck(new \DateTime());
        $this->entityManager->persist($website);
        $this->entityManager->flush();

        return new JsonResponse($this->serializeWebsite($website), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_website_update', methods: ['PUT'], requirements: ['id' => '\\d+'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $user = $this->currentUser($request);
        if ($user instanceof JsonResponse) return $user;
        $website = $this->ownedWebsite($id, $user);
        if ($website instanceof JsonResponse) return $website;
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) return new JsonResponse(['error' => 'Invalid request body'], Response::HTTP_BAD_REQUEST);
        if (isset($data['url']) && is_string($data['url']) && filter_var($data['url'], FILTER_VALIDATE_URL)) $website->setUrl($data['url']);
        if (isset($data['availability'])) $website->setAvailabilty((string) $data['availability']);
        if (isset($data['ssl_status'])) $website->setSSLStatus((string) $data['ssl_status']);
        if (isset($data['expires_in'])) $website->setExpiresIn((int) $data['expires_in']);
        $website->setLastCheck(new \DateTime());
        $this->entityManager->flush();
        return new JsonResponse($this->serializeWebsite($website));
    }

    #[Route('/{id}', name: 'api_website_delete', methods: ['DELETE'], requirements: ['id' => '\\d+'])]
    public function delete(int $id, Request $request): JsonResponse
    {
        $user = $this->currentUser($request);
        if ($user instanceof JsonResponse) return $user;
        $website = $this->ownedWebsite($id, $user);
        if ($website instanceof JsonResponse) return $website;
        $this->entityManager->remove($website);
        $this->entityManager->flush();
        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/user/{userId}', name: 'api_websites_by_user', methods: ['GET'], requirements: ['userId' => '\\d+'])]
    public function getWebsitesByUser(int $userId, Request $request): JsonResponse
    {
        $user = $this->currentUser($request);
        if ($user instanceof JsonResponse) return $user;
        if ($user->getId() !== $userId) return new JsonResponse(['error' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        return new JsonResponse(array_map(fn (Website $website) => $this->serializeWebsite($website), $this->websites->findBy(['user' => $user])));
    }

    private function currentUser(Request $request): User|JsonResponse
    {
        return $this->tokens->authenticate($request, $this->users);
    }

    private function ownedWebsite(int $id, User $user): Website|JsonResponse
    {
        $website = $this->websites->find($id);
        if (!$website || $website->getUser()?->getId() !== $user->getId()) {
            return new JsonResponse(['error' => 'Website not found'], Response::HTTP_NOT_FOUND);
        }
        return $website;
    }

    private function serializeWebsite(Website $website): array
    {
        return [
            'id' => $website->getId(), 'url' => $website->getUrl(),
            'availability' => $website->getAvailabilty(), 'ssl_status' => $website->getSSLStatus(),
            'expires_in' => $website->getExpiresIn(), 'last_check' => $website->getLastCheck()?->format('Y-m-d H:i:s'),
            'user' => $website->getUser()?->getId(), 'user_id' => $website->getUser()?->getId(),
        ];
    }
}
