<?php

namespace Almefy\AuthenticationBundle\Security;

use Almefy\AuthenticationBundle\Entity\AlmefyUserIdentity;
use Almefy\AuthenticationBundle\Service\AlmefySessionManager;
use Almefy\AuthenticationChallenge;
use Almefy\Client;
use Almefy\Session;
use DateInterval;
use DateTimeImmutable;
use Exception;
use Lcobucci\Clock\FrozenClock;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\Validation\Constraint\IssuedBy;
use Lcobucci\JWT\Validation\Constraint\PermittedFor;
use Lcobucci\JWT\Validation\Constraint\RelatedTo;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Constraint\StrictValidAt;
use Lcobucci\JWT\Validation\RequiredConstraintsViolated;
use Lcobucci\JWT\Validation\Validator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\CustomCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;

class AlmefyAuthenticator extends AbstractAuthenticator
{
    private const REQUEST_TIMESTAMP_LEEWAY = 60;
    public function __construct(
        private Client $client,
        private AlmefySessionManager $almefySessionManager,
        private string $apiSecret
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return $request->headers->has('X-Almefy-Auth') && $request->getPathInfo() === '/almefy/authenticate';
    }

    public function authenticate(Request $request): Passport
    {
        // First try to decode the JWT
        try {
            $token = (new Parser(new JoseEncoder()))->parse($request->headers->get('X-Almefy-Auth'));
        } catch (Exception $e) {
            throw new AuthenticationException($e->getMessage(),0, $e);
        }

        // Store the token for request event subscriber
        $request->attributes->set('_request_event_security_token', [
            'headers' => $token->headers()->all(),
            'claims' => $token->claims()->all()
        ]);

        return new Passport(
            new UserBadge($token->claims()->get('sub')),
            new CustomCredentials(function($credentials, UserInterface $user) use ($request) {
                $user = AlmefyUserIdentity::fromIdentity($this->client->getIdentity($user->getUserIdentifier()));

                $authenticateResult = $this->client->authenticate(new AuthenticationChallenge(
                    $credentials->claims()->get('jti'),
                    $credentials->claims()->get('sub'),
                    $credentials->claims()->get('otp')
                ));

                if (!$authenticateResult) {
                    throw new AccessDeniedHttpException(sprintf('Access denied for user: %s', $credentials->claims()->get('sub')));
                }

                // Validate JWT
                $now = new FrozenClock(new DateTimeImmutable());
                $validator = new Validator();

                try {
                    $validator->assert(
                        $credentials,
                        new IssuedBy($this->client->getApi()),
                        new PermittedFor($this->client->getKey()),
                        new RelatedTo($user->getUserIdentifier()),
                        new StrictValidAt($now, new DateInterval(sprintf('PT%dS', self::REQUEST_TIMESTAMP_LEEWAY))),
                        new SignedWith(new Sha256(), InMemory::base64Encoded($this->apiSecret))
                    );
                } catch (RequiredConstraintsViolated $e) {
                    throw new AuthenticationException($e->getMessage(),0, $e);
                }

                $request->getSession()->set('almefy_session_id', $authenticateResult['id'] ?? null);
                $this->almefySessionManager->addSession(Session::fromArray($authenticateResult));

                return true;
            }, $token)
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $request->attributes->set('_request_event_security_exception', $exception);

        return null;
    }
}

