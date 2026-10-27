<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\UserNotification;
use App\Repository\UserRepository;
use App\Service\AuthTokenService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/notifications')]
class NotificationController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AuthTokenService $tokens,
        private readonly UserRepository $users,
    ) {}

    #[Route('', name: 'api_notifications_list', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $user = $this->tokens->authenticate($request, $this->users);
        if ($user instanceof JsonResponse) return $user;

        $items = $this->entityManager->getRepository(UserNotification::class)->findBy(
            ['user' => $user], ['createdAt' => 'DESC'], 30
        );
        $unreadCount = $this->entityManager->getRepository(UserNotification::class)->count(['user' => $user, 'readAt' => null]);

        return new JsonResponse([
            'notifications' => array_map(fn (UserNotification $item) => $this->serialize($item), $items),
            'unreadCount' => $unreadCount,
        ]);
    }

    #[Route('/{id}/read', name: 'api_notification_read', methods: ['PATCH'], requirements: ['id' => '\\d+'])]
    public function markRead(int $id, Request $request): JsonResponse
    {
        $user = $this->tokens->authenticate($request, $this->users);
        if ($user instanceof JsonResponse) return $user;

        $item = $this->entityManager->getRepository(UserNotification::class)->find($id);
        if (!$item || $item->getUser()?->getId() !== $user->getId()) {
            return new JsonResponse(['error' => 'Notification not found'], Response::HTTP_NOT_FOUND);
        }
        $item->markRead();
        $this->entityManager->flush();
        return new JsonResponse($this->serialize($item));
    }

    private function serialize(UserNotification $item): array
    {
        return [
            'id' => $item->getId(), 'type' => $item->getType(), 'title' => $item->getTitle(),
            'message' => $item->getMessage(), 'websiteUrl' => $item->getWebsiteUrl(),
            'createdAt' => $item->getCreatedAt()->format(DATE_ATOM), 'read' => $item->isRead(),
        ];
    }
}
