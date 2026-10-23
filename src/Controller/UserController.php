<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\AuthTokenService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/user')]
class UserController extends AbstractController
{
    #[Route('/login', name: 'app_user_login', methods: ['POST'])]
    public function login(Request $request, UserRepository $users, AuthTokenService $tokens): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data) || !is_string($data['email'] ?? null) || !is_string($data['password'] ?? null)) {
            return new JsonResponse(['error' => 'Email and password are required'], Response::HTTP_BAD_REQUEST);
        }

        $user = $users->findOneBy(['email' => trim($data['email'])]);
        if (!$user || !password_verify($data['password'], $user->getPassword() ?? '')) {
            return new JsonResponse(['error' => 'Invalid email or password'], Response::HTTP_UNAUTHORIZED);
        }

        return new JsonResponse([
            'token' => $tokens->issue($user),
            'tokenType' => 'Bearer',
            'expiresIn' => 3600,
            'user' => $this->serializeUser($user),
        ]);
    }

    #[Route('/me', name: 'app_user_me', methods: ['GET'])]
    public function me(Request $request, UserRepository $users, AuthTokenService $tokens): JsonResponse
    {
        $user = $tokens->authenticate($request, $users);
        return $user instanceof JsonResponse ? $user : new JsonResponse($this->serializeUser($user));
    }

    #[Route('/', name: 'app_user_index', methods: ['GET'])]
    public function index(Request $request, UserRepository $users, AuthTokenService $tokens): JsonResponse
    {
        $user = $tokens->authenticate($request, $users);
        if ($user instanceof JsonResponse) return $user;
        if ($user->getRole() !== 'ROLE_ADMIN') return new JsonResponse(['error' => 'Forbidden'], Response::HTTP_FORBIDDEN);

        return new JsonResponse(array_map(fn (User $item) => $this->serializeUser($item), $users->findAll()));
    }

    #[Route('/new', name: 'app_user_new', methods: ['POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, UserRepository $users): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data) || !is_string($data['username'] ?? null) || !is_string($data['email'] ?? null)
            || !filter_var($data['email'], FILTER_VALIDATE_EMAIL) || !is_string($data['password'] ?? null)
            || strlen($data['password']) < 8) {
            return new JsonResponse(['error' => 'Provide a username, valid email, and password of at least 8 characters'], Response::HTTP_BAD_REQUEST);
        }
        if ($users->findOneBy(['email' => trim($data['email'])])) {
            return new JsonResponse(['error' => 'An account with this email already exists'], Response::HTTP_CONFLICT);
        }

        $user = (new User())
            ->setUsername(trim($data['username']))
            ->setEmail(trim($data['email']))
            ->setPassword(password_hash($data['password'], PASSWORD_DEFAULT))
            ->setRole('ROLE_USER');
        $entityManager->persist($user);
        $entityManager->flush();

        return new JsonResponse($this->serializeUser($user), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'app_user_show', methods: ['GET'], requirements: ['id' => '\\d+'])]
    public function show(int $id, Request $request, UserRepository $users, AuthTokenService $tokens): JsonResponse
    {
        $user = $tokens->authenticate($request, $users);
        if ($user instanceof JsonResponse) return $user;
        if ($user->getId() !== $id && $user->getRole() !== 'ROLE_ADMIN') {
            return new JsonResponse(['error' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }
        $target = $users->find($id);
        return $target ? new JsonResponse($this->serializeUser($target)) : new JsonResponse(['error' => 'User not found'], Response::HTTP_NOT_FOUND);
    }

    #[Route('/{id}/edit', name: 'app_user_edit', methods: ['PUT'], requirements: ['id' => '\\d+'])]
    public function edit(int $id, Request $request, UserRepository $users, AuthTokenService $tokens, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $tokens->authenticate($request, $users);
        if ($user instanceof JsonResponse) return $user;
        if ($user->getId() !== $id) return new JsonResponse(['error' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) return new JsonResponse(['error' => 'Invalid request body'], Response::HTTP_BAD_REQUEST);

        if (isset($data['username']) && is_string($data['username'])) $user->setUsername(trim($data['username']));
        if (isset($data['email']) && is_string($data['email']) && filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $user->setEmail(trim($data['email']));
        if (isset($data['password']) && is_string($data['password']) && strlen($data['password']) >= 8) $user->setPassword(password_hash($data['password'], PASSWORD_DEFAULT));
        $entityManager->flush();

        return new JsonResponse($this->serializeUser($user));
    }

    #[Route('/{id}', name: 'app_user_delete', methods: ['DELETE'], requirements: ['id' => '\\d+'])]
    public function delete(int $id, Request $request, UserRepository $users, AuthTokenService $tokens, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $tokens->authenticate($request, $users);
        if ($user instanceof JsonResponse) return $user;
        if ($user->getId() !== $id && $user->getRole() !== 'ROLE_ADMIN') {
            return new JsonResponse(['error' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }
        $target = $users->find($id);
        if (!$target) return new JsonResponse(['error' => 'User not found'], Response::HTTP_NOT_FOUND);
        $entityManager->remove($target);
        $entityManager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    private function serializeUser(User $user): array
    {
        return ['id' => $user->getId(), 'username' => $user->getUsername(), 'email' => $user->getEmail(), 'role' => $user->getRole()];
    }
}
