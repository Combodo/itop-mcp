<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Psr\Log\LoggerInterface;


/**
 * @see https://symfony.com/doc/current/security/custom_authenticator.html
 */
class AuthTokenAuthenticator extends AbstractAuthenticator
{
    public function __construct(private LoggerInterface $mcpLogger)
    {
        
    }
    /**
     * Called on every request to decide if this authenticator should be
     * used for the request. Returning `false` will cause this authenticator
     * to be skipped.
     */
    public function supports(Request $request): ?bool
    {
        if ($request->getMethod() === 'OPTIONS') return false;
        return $request->headers->has('Authorization');
    }

    public function authenticate(Request $request): Passport
    {
        $authorizationHeader = $request->headers->get('Authorization');
        if (null === $authorizationHeader) {
        // The Authorization header was empty, authentication fails with HTTP Status
        // Code 401 "Unauthorized"
            $this->mcpLogger->debug("No 'Authorization' header received.", ['headers' => $request->headers]);
            throw new CustomUserMessageAuthenticationException('No API token provided');
        }
        
        // Extract the token from the header: the format is "Bearer XXXXXXX" where XXXXXXX is the token
        $matches = [];
        if (!preg_match('/^Bearer (.+)$/', $authorizationHeader, $matches)) {
            // The Authorization header was improperly formatted, authentication fails
            $this->mcpLogger->debug("Invalid authorization header format. Expecting: 'Bearer <token>'.");
            throw new CustomUserMessageAuthenticationException('Invalid authorization header format. Expecting: Bearer <token>');
        }
        $token = $matches[1];
        $this->mcpLogger->debug("Authorization header format Ok.");
        $this->mcpLogger->debug("Bearer token received.");
        return new SelfValidatingPassport(new UserBadge($token), []);
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // on success, let the request continue
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $data = [
            // you may want to customize or obfuscate the message first
            'message' => strtr($exception->getMessageKey(), $exception->getMessageData()),

            // or to translate this message
            // $this->translator->trans($exception->getMessageKey(), $exception->getMessageData())
        ];

        return new JsonResponse($data, Response::HTTP_UNAUTHORIZED);
    }
}
