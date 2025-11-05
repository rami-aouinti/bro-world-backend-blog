<?php

declare(strict_types=1);

namespace App\Blog\Domain\Entity;

use Bro\WorldCoreBundle\Domain\Entity\Interfaces\EntityInterface;
use Bro\WorldCoreBundle\Domain\Entity\Traits\ColorTrait;
use Bro\WorldCoreBundle\Domain\Entity\Traits\NameTrait;
use Bro\WorldCoreBundle\Domain\Entity\Traits\Timestampable;
use Bro\WorldCoreBundle\Domain\Entity\Traits\Uuid;
use Bro\WorldCoreBundle\Domain\Entity\Traits\VisibleTrait;
use Doctrine\ORM\Mapping as ORM;
use JsonSerializable;
use Override;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;
use Ramsey\Uuid\UuidInterface;
use Stringable;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Throwable;

/**
 * @package App\Blog\Domain\Entity
 * @author  Rami Aouinti <rami.aouinti@tkdeutschland.de>
 */
#[ORM\Entity]
#[ORM\Table(name: 'blog_tag')]
class Tag implements EntityInterface, Stringable, JsonSerializable
{
    use ColorTrait;
    use VisibleTrait;
    use NameTrait;
    use Timestampable;
    use Uuid;

    #[ORM\Id]
    #[ORM\Column(
        name: 'id',
        type: UuidBinaryOrderedTimeType::NAME,
        unique: true,
        nullable: false,
    )]
    #[Groups([
        'Tag',
        'Tag.id',
        'Post',
    ])]
    private UuidInterface $id;

    #[ORM\Column(name: 'visible', type: 'boolean', nullable: false, options: [
        'default' => true,
    ])]
    #[Assert\NotNull]
    private bool $visible = true;

    /**
     * @throws Throwable
     */
    public function __construct(string $name)
    {
        $this->id = $this->createUuid();
        $this->setName($name);
        $this->setDescription($name);
    }

    public function __toString(): string
    {
        return $this->getName();
    }

    /**
     * @return non-empty-string
     */
    #[Override]
    public function getId(): string
    {
        return $this->id->toString();
    }

    public function jsonSerialize(): string
    {
        return $this->name;
    }

    public function isVisible(): bool
    {
        return $this->visible;
    }

    public function setVisible(bool $visible): void
    {
        $this->visible = $visible;
    }

    public function getColorSafe(): string
    {
        return $this->getColor() ?? '';
    }
}
