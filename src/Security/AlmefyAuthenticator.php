<?php
/*
 * Copyright (c) 2023 ALMEFY GmbH
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

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
use Symfony\Component\HttpFoundation\RedirectResponse;
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
        private string $apiSecret,
        private string $authenticateUrl,
        private ?string $successRedirectUrl = null,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return $request->headers->has('X-Almefy-Auth') && $request->getPathInfo() === $this->authenticateUrl;
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
                    $user = AlmefyUserIdentity::fromIdentity($this->client->getIdentity($user->getUserIdentifier()));

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

                $request->getSession()->set('almefy_session_id', $authenticateResult?->getSession()?->getId());
                $this->almefySessionManager->addSession($authenticateResult->getSession());

                return true;
            }, $token)
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        if ($this->successRedirectUrl) {
            return new RedirectResponse($this->successRedirectUrl);
        }

        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $request->attributes->set('_request_event_security_exception', $exception);

        return null;
    }
}

