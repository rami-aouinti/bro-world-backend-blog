<?php

declare(strict_types=1);

namespace App\Tests\Integration\General\Application\Validator\Constraints\src;

use Bro\WorldCoreBundle\Domain\Entity\Interfaces\EntityInterface;
use DateTimeImmutable;
use Doctrine\ORM\EntityNotFoundException;
use Override;

/**
 * @package App\Tests\Integration\General\Application\Validator\Constraints\src
 * @author  Rami Aouinti <rami.aouinti@tkdeutschland.de>
 */
class TestEntityReference implements EntityInterface
{
    public function __construct(
        private ?bool $throwException = null
    ) {
        $this->throwException ??= false;
    }

    #[Override]
    public function getId(): string
    {
        return 'xxx';
    }

    /**
     * @throws EntityNotFoundException
     */
    #[Override]
    public function getCreatedAt(): ?DateTimeImmutable
    {
        if ($this->throwException) {
            throw new EntityNotFoundException('Entity not found');
        }

        return null;
    }
}
