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

use function sprintf;

#[ORM\Entity]
#[ORM\Table(name: 'blog_reactions')]
#[ORM\UniqueConstraint(name: 'uniq_user_post', columns: ['user', 'post_id'])]
#[ORM\Index(columns: ['user', 'post_id'])]
class Reaction implements EntityInterface, Stringable
{
    use Timestampable;
    use Uuid;

    #[ORM\Id]
    #[ORM\Column(
        name: 'id',
        type: UuidBinaryOrderedTimeType::NAME,
        unique: true,
        nullable: false
    )]
    #[Groups(['Reaction', 'Post', 'Comment'])]
    private UuidInterface $id;

    #[ORM\ManyToOne(targetEntity: Post::class)]
    #[ORM\JoinColumn(name: 'post_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?Post $post = null;

    #[ORM\ManyToOne(targetEntity: Comment::class)]
    #[ORM\JoinColumn(name: 'comment_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?Comment $comment = null;

    #[ORM\Column(type: 'uuid')]
    #[Groups(['Reaction', 'Post', 'Comment'])]
    private UuidInterface $user;

    #[ORM\Column(type: 'string', length: 20)]
    #[Groups(['Reaction', 'Post', 'Comment'])]
    private string $type;

    /**
     * @throws Throwable
     */
    public function __construct()
    {
        $this->id = $this->createUuid();
    }

    public function __toString(): string
    {
        return sprintf('%s reacted %s', $this->user->toString(), $this->type);
    }

    public function getId(): string
    {
        return $this->id->toString();
    }

    public function setPost(?Post $post): void
    {
        $this->post = $post;
    }

    public function setComment(?Comment $comment): void
    {
        $this->comment = $comment;
    }

    public function getPost(): ?Post
    {
        return $this->post;
    }

    public function getComment(): ?Comment
    {
        return $this->comment;
    }

    public function getUser(): UuidInterface
    {
        return $this->user;
    }

    public function setUser(UuidInterface $user): void
    {
        $this->user = $user;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getType(): string
    {
        return $this->type;
    }
}
