<?php

declare(strict_types=1);

namespace App\Blog\Domain\Entity;

use Bro\WorldCoreBundle\Domain\Entity\Interfaces\EntityInterface;
use Bro\WorldCoreBundle\Domain\Entity\Traits\SlugTrait;
use Bro\WorldCoreBundle\Domain\Entity\Traits\Timestampable;
use Bro\WorldCoreBundle\Domain\Entity\Traits\Uuid;
use Bro\WorldCoreBundle\Domain\Entity\Traits\VisibleTrait;
use Bro\WorldCoreBundle\Domain\Entity\Traits\WorkplaceTrait;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;
use Ramsey\Uuid\UuidInterface;
use Stringable;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Throwable;

/**
 * @package App\Blog\Domain\Entity
 * @author  Rami Aouinti <rami.aouinti@gmail.com>
 */
#[ORM\Table(name: 'blog_post')]
#[ORM\Entity]
class Post implements EntityInterface, Stringable
{
    use VisibleTrait;
    use SlugTrait;
    use Timestampable;
    use Uuid;
    use WorkplaceTrait;

    final public const string SET_BLOG_INDEX = 'set.BlogIndex';

    #[ORM\Column(name: 'title', type: 'string', length: 250, nullable: true)]
    #[Groups([
        'Post',
        'Post_Show',
        self::SET_BLOG_INDEX,
    ])]
    protected ?string $title = null;

    #[ORM\Column(name: 'url', type: 'string', length: 250, nullable: true)]
    #[Groups([
        'Post',
        'Post_Show',
        self::SET_BLOG_INDEX,
    ])]
    protected ?string $url = null;

    #[ORM\Column(type: 'uuid')]
    #[Groups([
        'Post',
        'Post_Show',
        self::SET_BLOG_INDEX,
    ])]
    protected UuidInterface $author;

    #[ORM\ManyToOne(targetEntity: Blog::class, cascade: ['persist'], inversedBy: 'posts')]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups([
        'Post',
        'Post_Show',
        self::SET_BLOG_INDEX,
    ])]
    protected ?Blog $blog = null;

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
        'Post',
        'Post.id',
        'Post_Show',
        self::SET_BLOG_INDEX,
    ])]
    private UuidInterface $id;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    #[Assert\NotBlank(message: 'post.blank_summary')]
    #[Assert\Length(max: 255)]
    #[Groups([
        'Post',
        'Post_Show',
        self::SET_BLOG_INDEX,
    ])]
    private ?string $summary = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\NotBlank(message: 'post.blank_content')]
    #[Assert\Length(min: 10, minMessage: 'post.too_short_content')]
    #[Groups([
        'Post_Show',
        self::SET_BLOG_INDEX,
    ])]
    private ?string $content = null;

    /**
     * @var Collection<int, Comment>
     */
    #[ORM\OneToMany(mappedBy: 'post', targetEntity: Comment::class, cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy([
        'publishedAt' => 'DESC',
    ])]
    #[Groups([
        'Post',
        'Post_Show',
        self::SET_BLOG_INDEX,
    ])]
    private Collection $comments;

    /**
     * @var Collection<int, Tag>
     */
    #[ORM\ManyToMany(targetEntity: Tag::class, cascade: ['persist'])]
    #[ORM\JoinTable(name: 'blog_post_tag')]
    #[ORM\OrderBy([
        'name' => 'ASC',
    ])]
    #[Assert\Count(max: 4, maxMessage: 'post.too_many_tags')]
    #[Groups([
        'Post',
        'Post_Show',
        self::SET_BLOG_INDEX,
    ])]
    private Collection $tags;

    /**
     * @var Collection<int, Media>
     */
    #[ORM\OneToMany(mappedBy: 'post', targetEntity: Media::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups([
        'Post',
        self::SET_BLOG_INDEX,
    ])]
    private Collection $medias;

    #[ORM\OneToMany(mappedBy: 'post', targetEntity: Like::class, cascade: ['persist', 'remove'])]
    #[Groups([
        'Post',
        self::SET_BLOG_INDEX,
    ])]
    private Collection $likes;

    #[ORM\OneToMany(mappedBy: 'post', targetEntity: Reaction::class, cascade: ['remove'])]
    private Collection $reactions;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'sharedBy')]
    #[ORM\JoinColumn(name: 'shared_from_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Post $sharedFrom = null;

    #[ORM\OneToMany(mappedBy: 'sharedFrom', targetEntity: self::class)]
    private Collection $sharedBy;

    #[ORM\Column]
    #[Groups([
        'Post',
        self::SET_BLOG_INDEX,
    ])]
    private DateTimeImmutable $publishedAt;

    /**
     * @throws Throwable
     */
    public function __construct()
    {
        $this->id = $this->createUuid();
        $this->publishedAt = new DateTimeImmutable();
        $this->slug = '';
        $this->comments = new ArrayCollection();
        $this->tags = new ArrayCollection();
        $this->likes = new ArrayCollection();
        $this->medias = new ArrayCollection();
        $this->reactions = new ArrayCollection();
        $this->sharedBy = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->getTitle() ?? '';
    }

    public function getId(): string
    {
        return $this->id->toString();
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): self
    {
        $this->url = $url;

        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): self
    {
        $this->content = $content;

        return $this;
    }

    /**
     * @return Collection<int, Comment>
     */
    public function getComments(): Collection
    {
        return $this->comments;
    }

    public function addComment(Comment $comment): void
    {
        $comment->setPost($this);

        if (!$this->comments->contains($comment)) {
            $this->comments->add($comment);
        }
    }

    public function removeComment(Comment $comment): void
    {
        $this->comments->removeElement($comment);
    }

    public function addTag(Tag ...$tags): void
    {
        foreach ($tags as $tag) {
            if (!$this->tags->contains($tag)) {
                $this->tags->add($tag);
            }
        }
    }

    public function getSummary(): ?string
    {
        return $this->summary;
    }

    public function setSummary(?string $summary): self
    {
        $this->summary = $summary;

        return $this;
    }

    public function removeTag(Tag $tag): void
    {
        $this->tags->removeElement($tag);
    }

    /**
     * @return Collection<int, Tag>
     */
    public function getTags(): Collection
    {
        return $this->tags;
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

    public function getBlog(): ?Blog
    {
        return $this->blog;
    }

    public function setBlog(?Blog $blog): self
    {
        $this->blog = $blog;

        return $this;
    }

    public function getMediaEntities(): Collection
    {
        return $this->medias;
    }

    public function addMedia(Media $media): self
    {
        if (!$this->medias->contains($media)) {
            $this->medias[] = $media;
            $media->setPost($this);
        }

        return $this;
    }

    public function removeMedia(Media $media): self
    {
        if ($this->medias->removeElement($media)) {
            // break the association
            if ($media->getPost() === $this) {
                $media->setPost(null);
            }
        }

        return $this;
    }

    public function getLikes(): Collection
    {
        return $this->likes;
    }

    public function getReactions(): Collection
    {
        return $this->reactions;
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

    public function getSharedFrom(): ?self
    {
        return $this->sharedFrom;
    }

    public function setSharedFrom(?self $post): self
    {
        $this->sharedFrom = $post;

        return $this;
    }

    /**
     * @return Collection<int, Post>
     */
    public function getSharedBy(): Collection
    {
        return $this->sharedBy;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->getId(),
            'title' => $this->getTitle(),
            'author' => $this->getAuthor()->toString(),
            'content' => $this->getContent(),
            'summary' => $this->getSummary(),
            'publishedAt' => $this->getPublishedAt()->format('Y-m-d H:i:s'),
            'comments' => $this->getComments()->toArray(),
            'tags' => $this->getTags()->toArray(),
            'medias' => $this->getMediaEntities()->map(
                fn (Media $media) => $media->toArray()
            )->toArray(),
            'likes' => $this->getLikes()->toArray(),
            'reactions' => $this->getReactions()->toArray(),
            'sharedFrom' => $this->sharedFrom ? [
                'id' => $this->sharedFrom->getId(),
                'title' => $this->sharedFrom->getTitle(),
                'summary' => $this->sharedFrom->getSummary(),
            ] : null,
        ];
    }
}
