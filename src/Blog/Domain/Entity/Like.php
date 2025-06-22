<?php

declare(strict_types=1);

namespace App\Blog\Domain\Entity;

use App\General\Domain\Entity\Interfaces\EntityInterface;
use App\General\Domain\Entity\Traits\Timestampable;
use App\General\Domain\Entity\Traits\Uuid;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;
use Ramsey\Uuid\UuidInterface;
use Stringable;
use Symfony\Component\Serializer\Annotation\Groups;
use Throwable;

/**
 * @package App\Blog\Domain\Entity
 * @author  Rami Aouinti <rami.aouinti@tkdeutschland.de>
 */
#[ORM\Entity]
#[ORM\Table(name: 'blog_likes')]
class Like implements EntityInterface, Stringable
{
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
        'Like',
        'Like.id',
        'Post',
        Post::SET_BLOG_INDEX,
    ])]
    private UuidInterface $id;

    #[ORM\ManyToOne(targetEntity: Post::class, inversedBy: 'likes')]
    #[ORM\JoinColumn(name: 'post_id', referencedColumnName: 'id', nullable: true)]
    private ?Post $post = null;

    #[ORM\ManyToOne(targetEntity: Comment::class, inversedBy: 'likes')]
    #[ORM\JoinColumn(name: 'comment_id', referencedColumnName: 'id', nullable: true)]
    private ?Comment $comment = null;

    #[ORM\Column(type: 'uuid')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false)]
    #[Groups([
        'Post'
    ])]
    private UuidInterface $user;

    /**
     * @throws Throwable
     */
    public function __construct()
    {
        $this->id = $this->createUuid();
    }

    public function __toString(): string
    {
        return $this->getId();
    }

    public function getId(): string
    {
        return $this->id->toString();
    }

    public function getPost(): ?Post
    {
        return $this->post;
    }

    public function setPost(?Post $post): void
    {
        $this->post = $post;
    }

    public function getComment(): ?Comment
    {
        return $this->comment;
    }

    public function setComment(?Comment $comment): void
    {
        $this->comment = $comment;
    }

    public function getUser(): UuidInterface
    {
        return $this->user;
    }

    public function setUser(UuidInterface $user): void
    {
        $this->user = $user;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): void
    {
        $this->createdAt = $createdAt;
    }
}
