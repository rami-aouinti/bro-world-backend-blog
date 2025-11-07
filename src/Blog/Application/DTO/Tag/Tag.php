<?php

declare(strict_types=1);

namespace App\Blog\Application\DTO\Tag;

use App\Blog\Domain\Entity\Tag as Entity;
use Bro\WorldCoreBundle\Application\DTO\Interfaces\RestDtoInterface;
use Bro\WorldCoreBundle\Application\DTO\RestDto;
use Bro\WorldCoreBundle\Domain\Entity\Interfaces\EntityInterface;
use Override;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @package App\Blog\Application\DTO\Tag
 *
 * @method self|RestDtoInterface get(string $id)
 * @method self|RestDtoInterface patch(RestDtoInterface $dto)
 * @method Entity|EntityInterface update(EntityInterface $entity)
 */
class Tag extends RestDto
{
    #[Assert\NotBlank(allowNull: true)]
    #[Assert\Length(max: 255)]
    protected ?string $name = null;

    #[Assert\Length(max: 255)]
    protected ?string $description = null;

    #[Assert\Length(max: 64)]
    protected ?string $color = null;

    protected ?bool $visible = null;

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->setVisited('name');
        $this->name = $name;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->setVisited('description');
        $this->description = $description;

        return $this;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function setColor(?string $color): self
    {
        $this->setVisited('color');
        $this->color = $color;

        return $this;
    }

    public function getVisible(): ?bool
    {
        return $this->visible;
    }

    public function setVisible(?bool $visible): self
    {
        if ($visible !== null) {
            $this->setVisited('visible');
        }

        $this->visible = $visible;

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @param EntityInterface|Entity $entity
     */
    #[Override]
    public function load(EntityInterface $entity): self
    {
        if ($entity instanceof Entity) {
            $this->id = $entity->getId();
            $this->name = $entity->getName();
            $this->description = $entity->getDescription();
            $this->color = $entity->getColor();
            $this->visible = $entity->isVisible();
        }

        return $this;
    }
}
