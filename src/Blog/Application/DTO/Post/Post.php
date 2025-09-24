<?php

declare(strict_types=1);

namespace App\Blog\Application\DTO\Post;

use App\Blog\Domain\Entity\Blog;
use App\Blog\Domain\Entity\Media;
use App\Blog\Domain\Entity\Post as Entity;
use App\Blog\Domain\Entity\Tag;
use App\General\Application\DTO\Interfaces\RestDtoInterface;
use App\General\Application\DTO\RestDto;
use App\General\Domain\Entity\Interfaces\EntityInterface;
use DateTimeImmutable;
use DateTimeInterface;
use Override;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Validator\Constraints as Assert;

use function array_filter;
use function array_map;
use function array_values;
use function in_array;
use function is_string;

/**
 * @package App\Post
 *
 * @method self|RestDtoInterface get(string $id)
 * @method self|RestDtoInterface patch(RestDtoInterface $dto)
 * @method Entity|EntityInterface update(EntityInterface $entity)
 */
class Post extends RestDto
{
    /**
     * @var array<string, string>
     */
    protected static array $mappings = [
        'tags' => 'updateTags',
        'mediaIds' => 'updateMediaRelations',
    ];

    #[Assert\NotBlank(allowNull: true)]
    #[Assert\Length(max: 250)]
    protected ?string $title = null;

    #[Assert\Length(max: 250)]
    #[Assert\Url]
    protected ?string $url = null;

    #[Assert\NotBlank(allowNull: true)]
    #[Assert\Length(max: 255)]
    protected ?string $summary = null;

    #[Assert\NotBlank(allowNull: true)]
    #[Assert\Length(min: 10)]
    protected ?string $content = null;

    protected ?UuidInterface $author = null;

    protected ?Blog $blog = null;

    /**
     * @var array<int, Tag>
     */
    protected array $tags = [];

    /**
     * @var array<int, string>
     */
    #[Assert\All(new Assert\Uuid(message: 'This value is not a valid UUID.'))]
    protected array $mediaIds = [];

    protected ?DateTimeImmutable $publishedAt = null;

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): self
    {
        $this->setVisited('title');
        $this->title = $title;

        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): self
    {
        $this->setVisited('url');
        $this->url = $url;

        return $this;
    }

    public function getSummary(): ?string
    {
        return $this->summary;
    }

    public function setSummary(?string $summary): self
    {
        $this->setVisited('summary');
        $this->summary = $summary;

        return $this;
    }

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

    public function getBlog(): ?Blog
    {
        return $this->blog;
    }

    public function setBlog(?Blog $blog): self
    {
        $this->setVisited('blog');
        $this->blog = $blog;

        return $this;
    }

    /**
     * @return array<int, Tag>
     */
    public function getTags(): array
    {
        return $this->tags;
    }

    /**
     * @param array<int, Tag> $tags
     */
    public function setTags(array $tags): self
    {
        $this->setVisited('tags');
        $this->tags = array_values(array_filter(
            $tags,
            static fn ($tag): bool => $tag instanceof Tag
        ));

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function getMediaIds(): array
    {
        return $this->mediaIds;
    }

    /**
     * @param array<int, string> $mediaIds
     */
    public function setMediaIds(array $mediaIds): self
    {
        $this->setVisited('mediaIds');
        $this->mediaIds = $mediaIds;

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
            $this->title = $entity->getTitle();
            $this->url = $entity->getUrl();
            $this->summary = $entity->getSummary();
            $this->content = $entity->getContent();
            $this->author = $entity->getAuthor();
            $this->blog = $entity->getBlog();
            $this->tags = $entity->getTags()->toArray();
            $this->mediaIds = $entity->getMediaEntities()->map(
                static fn (Media $media): string => $media->getId()
            )->toArray();
            $this->publishedAt = $entity->getPublishedAt();
        }

        return $this;
    }

    protected function updateTags(Entity $entity, array $tags): void
    {
        $ids = array_map(static fn (Tag $tag): string => $tag->getId(), $tags);

        foreach ($entity->getTags() as $existing) {
            if (!in_array($existing->getId(), $ids, true)) {
                $entity->removeTag($existing);
            }
        }

        $entity->addTag(...$tags);
    }

    /**
     * @param array<int, string> $mediaIds
     */
    protected function updateMediaRelations(Entity $entity, array $mediaIds): void
    {
        $mediaIds = array_filter($mediaIds, static fn ($value): bool => is_string($value) && $value !== '');

        foreach ($entity->getMediaEntities() as $media) {
            if (!in_array($media->getId(), $mediaIds, true)) {
                $entity->removeMedia($media);
            }
        }
    }
}
