<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/** Issues short-lived, HMAC-signed bearer tokens for the JSON API. */
final class AuthTokenService
{
    private const TOKEN_TTL = 3600;

    public function __construct(private readonly string $secret)
    {
    }

    public function issue(User $user): string
    {
        $now = time();
        $header = $this->encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $payload = $this->encode(json_encode([
            'sub' => $user->getId(),
            'iat' => $now,
            'exp' => $now + self::TOKEN_TTL,
            'jti' => bin2hex(random_bytes(16)),
        ], JSON_THROW_ON_ERROR));
        $unsigned = $header.'.'.$payload;

        return $unsigned.'.'.$this->encode(hash_hmac('sha256', $unsigned, $this->secret, true));
    }

    public function authenticate(Request $request, UserRepository $users): User|JsonResponse
    {
        $authorization = $request->headers->get('Authorization', '');
        if (!preg_match('/^Bearer\s+(\S+)$/i', $authorization, $matches)) {
            return new JsonResponse(['error' => 'Authentication required'], Response::HTTP_UNAUTHORIZED);
        }

        $parts = explode('.', $matches[1]);
        if (count($parts) !== 3) {
            return new JsonResponse(['error' => 'Invalid or expired token'], Response::HTTP_UNAUTHORIZED);
        }

        [$header, $payload, $signature] = $parts;
        $expected = $this->encode(hash_hmac('sha256', $header.'.'.$payload, $this->secret, true));
        if (!hash_equals($expected, $signature)) {
            return new JsonResponse(['error' => 'Invalid or expired token'], Response::HTTP_UNAUTHORIZED);
        }

        $claims = json_decode($this->decode($payload), true);
        if (!is_array($claims) || ($claims['exp'] ?? 0) <= time() || !is_numeric($claims['sub'] ?? null)) {
            return new JsonResponse(['error' => 'Invalid or expired token'], Response::HTTP_UNAUTHORIZED);
        }

        $user = $users->find((int) $claims['sub']);

        return $user ?? new JsonResponse(['error' => 'User no longer exists'], Response::HTTP_UNAUTHORIZED);
    }

    private function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function decode(string $value): string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return $decoded === false ? '' : $decoded;
    }
}
