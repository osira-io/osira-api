<?php

declare(strict_types=1);

namespace App\Security\Agent;

use App\Service\Agent\AgentCredentialAuthenticator;
use App\Service\Agent\InvalidAgentCredential;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

final class AgentBearerAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private AgentCredentialAuthenticator $credentialAuthenticator,
        private ClockInterface $clock,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return true;
    }

    public function authenticate(Request $request): Passport
    {
        $header = $request->headers->get('Authorization');
        if (!\is_string($header) || !str_starts_with($header, 'Bearer ')) {
            throw new CustomUserMessageAuthenticationException('Agent bearer authentication is required.');
        }

        $rawToken = trim(substr($header, 7));
        if ('' === $rawToken) {
            throw new CustomUserMessageAuthenticationException('Agent bearer authentication is required.');
        }

        return new SelfValidatingPassport(new UserBadge($rawToken, function () use ($rawToken): AuthenticatedAgent {
            try {
                return new AuthenticatedAgent($this->credentialAuthenticator->authenticate($rawToken, $this->clock->now()));
            } catch (InvalidAgentCredential $exception) {
                throw new CustomUserMessageAuthenticationException('Invalid agent credential.', previous: $exception);
            }
        }));
    }

    public function onAuthenticationSuccess(Request $request, \Symfony\Component\Security\Core\Authentication\Token\TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        return new JsonResponse(['message' => 'Authentication failed.'], Response::HTTP_UNAUTHORIZED);
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new JsonResponse(['message' => 'Authentication required.'], Response::HTTP_UNAUTHORIZED);
    }
}
