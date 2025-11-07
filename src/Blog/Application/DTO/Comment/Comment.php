<?php

declare(strict_types=1);

namespace App\Blog\Application\DTO\Comment;

use App\Blog\Domain\Entity\Comment as Entity;
use App\Blog\Domain\Entity\Post;
use Bro\WorldCoreBundle\Application\DTO\Interfaces\RestDtoInterface;
use Bro\WorldCoreBundle\Application\DTO\RestDto;
use Bro\WorldCoreBundle\Domain\Entity\Interfaces\EntityInterface;
use DateTimeImmutable;
use DateTimeInterface;
use Override;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Validator\Constraints as Assert;

use function is_string;

/**
 * @package App\Blog\Application\DTO\Comment
 *
 * @method self|RestDtoInterface get(string $id)
 * @method self|RestDtoInterface patch(RestDtoInterface $dto)
 * @method Entity|EntityInterface update(EntityInterface $entity)
 */
class Comment extends RestDto
{
    #[Assert\NotBlank(allowNull: true)]
    #[Assert\Length(min: 5, max: 10000)]
    protected ?string $content = null;

    protected ?UuidInterface $author = null;

    protected ?Post $post = null;

    protected ?Entity $parent = null;

    /**
     * @var array<int, mixed>
     */
    protected array $medias = [];

    protected ?DateTimeImmutable $publishedAt = null;

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): self
    {
        $this->setVisited('content');
        $this->content = $content;

        return $this;
    }

    public function getAuthor(): ?UuidInterface
    {
        return $this->author;
    }

    public function setAuthor(UuidInterface|string|null $author): self
    {
        $this->setVisited('author');

        if (is_string($author)) {
            $author = $author === '' ? null : Uuid::fromString($author);
        }

        $this->author = $author;

        return $this;
    }

    public function getPost(): ?Post
    {
        return $this->post;
    }

    public function setPost(?Post $post): self
    {
        $this->setVisited('post');
        $this->post = $post;

        return $this;
    }

    public function getParent(): ?Entity
    {
        return $this->parent;
    }

    public function setParent(?Entity $parent): self
    {
        $this->setVisited('parent');
        $this->parent = $parent;

        return $this;
    }

    /**
     * @return array<int, mixed>
     */
    public function getMedias(): array
    {
        return $this->medias;
    }

    /**
     * @param array<int, mixed> $medias
     */
    public function setMedias(array $medias): self
    {
        $this->setVisited('medias');
        $this->medias = $medias;

        return $this;
    }

    public function getPublishedAt(): ?DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(DateTimeInterface|string|null $publishedAt): self
    {
        $this->setVisited('publishedAt');

        if ($publishedAt === null) {
            $this->publishedAt = null;

            return $this;
        }

        if (is_string($publishedAt)) {
            $publishedAt = new DateTimeImmutable($publishedAt);
        }

        $this->publishedAt = DateTimeImmutable::createFromInterface($publishedAt);

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
            $this->content = $entity->getContent();
            $this->author = $entity->getAuthor();
            $this->post = $entity->getPost();
            $this->parent = $entity->getParent();
            $this->medias = $entity->getMedias();
            $this->publishedAt = $entity->getPublishedAt();
        }

        return $this;
    }
}
