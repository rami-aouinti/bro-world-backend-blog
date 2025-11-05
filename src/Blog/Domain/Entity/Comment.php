<?php

declare(strict_types=1);

/* For licensing terms, see /license.txt */

namespace App\Blog\Domain\Entity;

use Bro\WorldCoreBundle\Domain\Entity\Interfaces\EntityInterface;
use Bro\WorldCoreBundle\Domain\Entity\Traits\Timestampable;
use Bro\WorldCoreBundle\Domain\Entity\Traits\Uuid;
use Bro\WorldCoreBundle\Domain\Entity\Traits\VisibleTrait;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Throwable;

use function Symfony\Component\String\u;

/**
 * @package App\Blog\Domain\Entity
 * @author  Rami Aouinti <rami.aouinti@tkdeutschland.de>
 */
#[ORM\Table(name: 'blog_comment')]
#[ORM\Entity]
class Comment implements EntityInterface
{
    use VisibleTrait;
    use Timestampable;
    use Uuid;

    #[ORM\Column(type: 'uuid')]
    #[Groups([
        'Comment',
        'Comment.author',
        Post::SET_BLOG_INDEX,
        'Post',
    ])]
    protected UuidInterface $author;

    /**
     * Unique User ID
     */
    #[ORM\Id]
    #[ORM\Column(
        name: 'id',
        type: UuidBinaryOrderedTimeType::NAME,
        unique: true,
        nullable: false,
    )]
    #[Groups([
        'Comment',
        'Comment.id',
        Post::SET_BLOG_INDEX,
        'Post',
    ])]
    private UuidInterface $id;

    #[ORM\ManyToOne(targetEntity: Post::class, inversedBy: 'comments')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Post $post = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'comment.blank')]
    #[Assert\Length(min: 5, max: 10000, minMessage: 'comment.too_short', maxMessage: 'comment.too_long')]
    #[Groups([
        'Comment',
        'Comment.content',
        'Post',
        Post::SET_BLOG_INDEX,
    ])]
    private ?string $content = null;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    #[ORM\JoinColumn(name: 'parent_id', referencedColumnName: 'id', nullable: true)]
    private ?Comment $parent = null;

    #[ORM\OneToMany(mappedBy: 'parent', targetEntity: self::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups([
        'Comment',
        'Comment.children',
        Post::SET_BLOG_INDEX,
        'Post',
    ])]
    private Collection $children;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups([
        'Comment',
        'Comment.medias',
        Post::SET_BLOG_INDEX,
        'Post',
    ])]
    private ?array $medias;

    #[ORM\OneToMany(mappedBy: 'comment', targetEntity: Like::class, cascade: ['persist', 'remove'])]
    #[Groups([
        'Comment',
        'Comment.likes',
        Post::SET_BLOG_INDEX,
        'Post',
    ])]
    private Collection $likes;

    #[ORM\Column]
    #[Groups([
        'Post',
        Post::SET_BLOG_INDEX,
    ])]
    private DateTimeImmutable $publishedAt;

    #[ORM\OneToMany(mappedBy: 'comment', targetEntity: Reaction::class, cascade: ['remove'])]
    private Collection $reactions;

    /**
     * @throws Throwable
     */
    public function __construct()
    {
        $this->id = $this->createUuid();
        $this->publishedAt = new DateTimeImmutable();
        $this->children = new ArrayCollection();
        $this->likes = new ArrayCollection();
        $this->reactions = new ArrayCollection();
        $this->medias = [];
    }

    public function __toString(): string
    {
        return $this->content ?? '';
    }

    public function getId(): string
    {
        return $this->id->toString();
    }

    #[Assert\IsTrue(message: 'comment.is_spam')]
    public function isLegitComment(): bool
    {
        if ($this->content === null || $this->content === '') {
            return true;
        }

        $containsInvalidCharacters = u($this->content)->indexOf('@') !== null;

        return !$containsInvalidCharacters;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(string $content): void
    {
        $this->content = $content;
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    public function setParent(?self $parent): self
    {
        $this->parent = $parent;

        return $this;
    }

    public function getChildren(): Collection
    {
        return $this->children;
    }

    public function addChildren(self $child): self
    {
        if (!$this->children->contains($child)) {
            $this->children->add($child);
            $child->setParent($this);
        }

        return $this;
    }

    public function removeChildren(self $child): self
    {
        if ($this->children->removeElement($child) && $child->getParent() === $this) {
            $child->setParent(null);
        }

        return $this;
    }

    public function getAuthor(): UuidInterface
    {
        return $this->author;
    }

    public function setAuthor(UuidInterface $author): self
    {
        $this->author = $author;

        return $this;
    }

    public function getPost(): ?Post
    {
        return $this->post;
    }

    public function setPost(?Post $post): void
    {
        $this->post = $post;
    }

    public function getMedias(): array
    {
        return $this->medias ?? [];
    }

    public function setMedias(?array $medias): self
    {
        $this->medias = $medias ?? [];

        return $this;
    }

    public function getLikes(): Collection
    {
        return $this->likes;
    }

    public function addLike(Like $like): self
    {
        if (!$this->likes->contains($like)) {
            $this->likes[] = $like;
        }

        return $this;
    }

    public function removeLike(Like $like): self
    {
        $this->likes->removeElement($like);

        return $this;
    }

    public function getPublishedAt(): DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(DateTimeImmutable $publishedAt): void
    {
        $this->publishedAt = $publishedAt;
    }

    public function getReactions(): Collection
    {
        return $this->reactions;
    }
}
