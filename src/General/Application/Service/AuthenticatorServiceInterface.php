<?php

declare(strict_types=1);

namespace App\General\Application\Service;

use App\General\Domain\ValueObject\UserId;
use App\General\Infrastructure\ValueObject\SymfonyUser;

interface AuthenticatorServiceInterface
{
    public function getUserId(): ?UserId;

    public function getToken(string $id): ?string;

    public function getSymfonyUser(): ?SymfonyUser;
}
