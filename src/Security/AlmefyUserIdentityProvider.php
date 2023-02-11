<?php

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
