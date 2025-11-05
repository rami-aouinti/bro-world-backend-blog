<?php

declare(strict_types=1);

namespace App\Blog\Application\DTO\Blog;

use App\Blog\Domain\Entity\Blog as Entity;
use Bro\WorldCoreBundle\Application\DTO\Interfaces\RestDtoInterface;
use Bro\WorldCoreBundle\Application\DTO\RestDto;
use Bro\WorldCoreBundle\Domain\Entity\Interfaces\EntityInterface;
use Override;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Validator\Constraints as Assert;

use function filter_var;
use function is_bool;

use const FILTER_NULL_ON_FAILURE;
use const FILTER_VALIDATE_BOOLEAN;

/**
 * @package App\Blog\Application\DTO\Blog
 * @author  Rami Aouinti <rami.aouinti@tkdeutschland.de>
 *
 * @method self|RestDtoInterface get(string $id)
 * @method self|RestDtoInterface patch(RestDtoInterface $dto)
 * @method Entity|EntityInterface update(EntityInterface $entity)
 */
class Blog extends RestDto
{
    #[Assert\NotBlank]
    #[Assert\NotNull]
    #[Assert\Length(min: 2, max: 255)]
    protected string $title = '';

    #[Assert\Length(max: 250)]
    protected ?string $blogSubtitle = null;

    #[Assert\NotNull]
    protected UuidInterface $author;

    #[Assert\Length(max: 255)]
    protected ?string $logo = null;

    #[Assert\Type('array')]
    protected ?array $teams = null;

    #[Assert\Type('bool')]
    protected ?bool $visible = null;

    #[Assert\Length(max: 255)]
    protected ?string $slug = null;

    #[Assert\Length(max: 36)]
    protected ?string $color = null;

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->setVisited('title');
        $this->title = $title;

        return $this;
    }

    public function getBlogSubtitle(): ?string
    {
        return $this->blogSubtitle;
    }

    public function setBlogSubtitle(?string $blogSubtitle): self
    {
        $this->setVisited('blogSubtitle');
        $this->blogSubtitle = $blogSubtitle;

        return $this;
    }

    public function getAuthor(): UuidInterface
    {
        return $this->author;
    }

    public function setAuthor(UuidInterface|string $author): self
    {
        $this->setVisited('author');
        $this->author = $author instanceof UuidInterface ? $author : Uuid::fromString($author);

        return $this;
    }

    public function getLogo(): ?string
    {
        return $this->logo;
    }

    public function setLogo(?string $logo): self
    {
        $this->setVisited('logo');
        $this->logo = $logo;

        return $this;
    }

    /**
     * @return array<int, mixed>|null
     */
    public function getTeams(): ?array
    {
        return $this->teams;
    }

    /**
     * @param array<int, mixed>|null $teams
     */
    public function setTeams(?array $teams): self
    {
        $this->setVisited('teams');
        $this->teams = $teams;

        return $this;
    }

    public function getVisible(): ?bool
    {
        return $this->visible;
    }

    public function setVisible(bool|string|null $visible): self
    {
        $this->setVisited('visible');
        if ($visible === null) {
            $this->visible = null;
        } else {
            $this->visible = is_bool($visible)
                ? $visible
                : filter_var($visible, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool)$visible;
        }

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(?string $slug): self
    {
        $this->setVisited('slug');
        $this->slug = $slug;

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
            $this->title = $entity->getTitle();
            $this->blogSubtitle = $entity->getBlogSubtitle();
            $this->author = $entity->getAuthor();
            $this->logo = $entity->getLogo();
            $this->teams = $entity->getTeams();
            $this->visible = $entity->isVisible();
            $this->slug = $entity->getSlug();
            $this->color = $entity->getColor();
        }

        return $this;
    }
}
