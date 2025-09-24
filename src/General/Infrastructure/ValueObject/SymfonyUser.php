<?php

declare(strict_types=1);

namespace App\General\Infrastructure\ValueObject;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @package App\General\Infrastructure\ValueObject
 * @author  Rami Aouinti <rami.aouinti@tkdeutschland.de>
 */
final readonly class SymfonyUser implements UserInterface
{
    public function __construct(
        private ?string $userIdentifier,
        private ?string $fullName,
        private ?string $avatar,
        private ?array $roles
    ) {
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function eraseCredentials(): void
    {
    }

    public function getUserIdentifier(): string
    {
        return $this->userIdentifier;
    }

    public function getFullName(): ?string
    {
        return $this->fullName;
    }

    public function getAvatar(): ?string
    {
        return $this->avatar;
    }
}
