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
