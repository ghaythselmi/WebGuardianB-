<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\AuthTokenService;
use App\Services\MonitorAlertMailer;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Google\Auth\AccessToken as GoogleAccessToken;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/user')]
class UserController extends AbstractController
{
    #[Route('/google-login', name: 'app_user_google_login', methods: ['POST'])]
    public function googleLogin(Request $request, UserRepository $users, AuthTokenService $tokens, EntityManagerInterface $entityManager): JsonResponse
    {
        $clientId = $this->env('GOOGLE_CLIENT_ID', '');
        if ($clientId === '') {
            return new JsonResponse(['error' => 'Google sign-in is not configured on this server'], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data) || !is_string($data['credential'] ?? null) || $data['credential'] === '') {
            return new JsonResponse(['error' => 'A Google credential is required'], Response::HTTP_BAD_REQUEST);
        }

        $verificationDiagnostic = null;
        try {
            $claims = (new GoogleAccessToken())->verify($data['credential'], ['audience' => $clientId, 'throwException' => true]);
        } catch (\Throwable $exception) {
            $verificationDiagnostic = $exception::class.': '.$exception->getMessage();
            $claims = false;
        }
        if (!is_array($claims)) {
            $verificationDiagnostic ??= 'Google returned an invalid or expired ID token.';
        } elseif (!is_string($claims['sub'] ?? null)) {
            $verificationDiagnostic = 'The verified ID token has no subject claim.';
        } elseif (!is_string($claims['email'] ?? null) || !filter_var($claims['email'], FILTER_VALIDATE_EMAIL)) {
            $verificationDiagnostic = 'The verified ID token has no valid email claim.';
        } elseif (($claims['email_verified'] ?? false) !== true) {
            $verificationDiagnostic = 'Google did not mark the email address as verified.';
        }
        if ($verificationDiagnostic !== null) {
            $response = ['error' => 'Google could not verify this sign-in. Please try again.'];
            if ($this->getParameter('kernel.environment') === 'dev') $response['diagnostic'] = $verificationDiagnostic;
            return new JsonResponse($response, Response::HTTP_UNAUTHORIZED);
        }

        $googleSubject = $claims['sub'];
        $email = strtolower(trim($claims['email']));
        $user = $users->findOneBy(['googleSubject' => $googleSubject]);
        if (!$user) {
            $user = $users->findOneBy(['email' => $email]);
            if ($user && $user->getGoogleSubject() !== null && !hash_equals($user->getGoogleSubject(), $googleSubject)) {
                return new JsonResponse(['error' => 'This email is already linked to another Google account. Sign in with your password first.'], Response::HTTP_CONFLICT);
            }

            if (!$user) {
                $displayName = trim((string) ($claims['name'] ?? ''));
                if ($displayName === '') $displayName = strstr($email, '@', true) ?: 'WebGuardian user';
                $shortName = iconv_substr($displayName, 0, 255, 'UTF-8');
                $user = (new User())
                    ->setUsername($shortName !== false && $shortName !== '' ? $shortName : 'WebGuardian user')
                    ->setEmail($email)
                    ->setPassword(password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT))
                    ->setRole('ROLE_USER');
                $entityManager->persist($user);
            }
            $user->setGoogleSubject($googleSubject);
            $entityManager->flush();
        }

        return new JsonResponse([
            'token' => $tokens->issue($user),
            'tokenType' => 'Bearer',
            'expiresIn' => 3600,
            'user' => $this->serializeUser($user),
        ]);
    }

    #[Route('/forgot-password', name: 'app_user_forgot_password', methods: ['POST'])]
    public function forgotPassword(Request $request, UserRepository $users, Connection $connection, MonitorAlertMailer $mailer): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data) || !is_string($data['email'] ?? null) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return new JsonResponse(['error' => 'Enter a valid email address'], Response::HTTP_BAD_REQUEST);
        }

        $user = $users->findOneBy(['email' => trim($data['email'])]);
        if ($user) {
            $token = bin2hex(random_bytes(32));
            $connection->delete('password_reset_token', ['user_id' => $user->getId()]);
            $connection->insert('password_reset_token', [
                'user_id' => $user->getId(),
                'token_hash' => hash('sha256', $token),
                'expires_at' => (new \DateTimeImmutable('+60 minutes'))->format('Y-m-d H:i:s'),
            ]);
            $frontend = rtrim($this->env('FRONTEND_URL', 'http://localhost:4200'), '/');
            $mailer->sendPasswordReset($user, $frontend.'/reset-password?token='.rawurlencode($token));
        }

        return new JsonResponse(['message' => 'If an account exists for that email, a password reset link has been sent.']);
    }

    #[Route('/reset-password', name: 'app_user_reset_password', methods: ['POST'])]
    public function resetPassword(Request $request, Connection $connection, UserRepository $users, EntityManagerInterface $entityManager): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data) || !is_string($data['token'] ?? null) || !is_string($data['password'] ?? null) || strlen($data['password']) < 8) {
            return new JsonResponse(['error' => 'A valid reset link and a password of at least 8 characters are required'], Response::HTTP_BAD_REQUEST);
        }

        $reset = $connection->fetchAssociative('SELECT user_id FROM password_reset_token WHERE token_hash = ? AND expires_at > ? LIMIT 1', [
            hash('sha256', $data['token']), (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
        if (!$reset || !($user = $users->find((int) $reset['user_id']))) {
            return new JsonResponse(['error' => 'This reset link is invalid or has expired. Request a new one.'], Response::HTTP_BAD_REQUEST);
        }

        $user->setPassword(password_hash($data['password'], PASSWORD_DEFAULT));
        $connection->delete('password_reset_token', ['user_id' => $user->getId()]);
        $entityManager->flush();
        return new JsonResponse(['message' => 'Your password has been updated. You can now sign in.']);
    }

    #[Route('/change-password', name: 'app_user_change_password', methods: ['POST'])]
    public function changePassword(Request $request, UserRepository $users, AuthTokenService $tokens, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $tokens->authenticate($request, $users);
        if ($user instanceof JsonResponse) return $user;
        $data = json_decode($request->getContent(), true);
        if (!is_array($data) || !is_string($data['currentPassword'] ?? null) || !is_string($data['newPassword'] ?? null) || strlen($data['newPassword']) < 8) {
            return new JsonResponse(['error' => 'Enter your current password and a new password of at least 8 characters'], Response::HTTP_BAD_REQUEST);
        }
        if (!password_verify($data['currentPassword'], $user->getPassword() ?? '')) {
            return new JsonResponse(['error' => 'Your current password is incorrect'], Response::HTTP_BAD_REQUEST);
        }
        if (password_verify($data['newPassword'], $user->getPassword() ?? '')) {
            return new JsonResponse(['error' => 'Choose a password you have not used for this account'], Response::HTTP_BAD_REQUEST);
        }

        $user->setPassword(password_hash($data['newPassword'], PASSWORD_DEFAULT));
        $entityManager->flush();
        return new JsonResponse(['message' => 'Your password has been changed.']);
    }

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
        if (array_key_exists('emailAlertsEnabled', $data) && is_bool($data['emailAlertsEnabled'])) $user->setEmailAlertsEnabled($data['emailAlertsEnabled']);
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
        return ['id' => $user->getId(), 'username' => $user->getUsername(), 'email' => $user->getEmail(), 'role' => $user->getRole(), 'createdAt' => $user->getCreatedAt()?->format(DATE_ATOM), 'emailAlertsEnabled' => $user->isEmailAlertsEnabled()];
    }

    private function env(string $name, string $default): string
    {
        $value = $_SERVER[$name] ?? $_ENV[$name] ?? getenv($name);
        return is_string($value) && $value !== '' ? $value : $default;
    }
}
