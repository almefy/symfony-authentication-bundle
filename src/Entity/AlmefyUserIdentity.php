<?php

namespace Almefy\AuthenticationBundle\Entity;

use Almefy\Identity;
use DateTimeImmutable;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;

class AlmefyUserIdentity implements UserInterface
{
    const ROLE_ALMEFY_USER = 'ROLE_ALMEFY_USER';

    private Uuid $id;
    private string $identifier;
    private bool $locked = false;
    private array $roles = [self::ROLE_ALMEFY_USER];
    private array $tokens = [];
    private DateTimeImmutable $createdAt;

    public function __construct(?Uuid $id = null)
    {
        $this->id = $id ?? Uuid::v4();
        $this->createdAt = new DateTimeImmutable();
    }

    public static function fromIdentity(Identity $identity): self
    {
        $userIdentity = new static(Uuid::fromString($identity->getId()));
        $userIdentity->identifier = $identity->getIdentifier();
        $userIdentity->locked = $identity->isLocked();
        $userIdentity->tokens = $identity->getTokens();

        return $userIdentity;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isLocked(): bool
    {
        return $this->locked;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }
    public function getRoles(): array
    {
        return $this->roles;
    }

    public function getTokens(): array
    {
        return $this->tokens;
    }

    public function eraseCredentials()
    {
    }

    public function getUserIdentifier(): string
    {
        return $this->identifier;
    }
}
