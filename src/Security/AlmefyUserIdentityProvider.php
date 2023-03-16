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
use Almefy\Client;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class AlmefyUserIdentityProvider implements UserProviderInterface, PasswordUpgraderInterface
{
    public function __construct(
        private Client $client,
        private AlmefySessionManager $almefySessionManager,
        private RequestStack $requestStack
    ) {
    }

    /**
     * @todo some caching maybe here?
     * If sessions are mapped to user then we can return them on load - one request only
     */
    public function loadUserByIdentifier($identifier): UserInterface
    {
        return AlmefyUserIdentity::fromIdentity($this->client->getIdentity($identifier));
    }

    public function loadUserByUsername($username): UserInterface
    {
        return $this->loadUserByIdentifier($username);
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof AlmefyUserIdentity) {
            throw new UnsupportedUserException(sprintf('Invalid user class "%s".', get_class($user)));
        }

        if ($this->requestStack->getMainRequest()->hasSession(true)) {
            $sessionId = $this->requestStack->getSession()->get('almefy_session_id');

            $this->almefySessionManager->refreshSessionByIdentifier($sessionId, $user->getUserIdentifier());
        }

        return $user;
    }

    public function supportsClass(string $class): bool
    {
        return AlmefyUserIdentity::class === $class || is_subclass_of($class, AlmefyUserIdentity::class);
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
    }
}
