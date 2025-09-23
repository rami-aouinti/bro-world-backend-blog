<?php

declare(strict_types=1);

namespace App\Blog\Application\DTO\Like;

use App\Blog\Domain\Entity\Comment;
use App\Blog\Domain\Entity\Like as Entity;
use App\Blog\Domain\Entity\Post;
use App\General\Application\DTO\Interfaces\RestDtoInterface;
use App\General\Application\DTO\RestDto;
use App\General\Domain\Entity\Interfaces\EntityInterface;
use DateTimeImmutable;
use Override;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @package App\Like
 *
 * @method self|RestDtoInterface get(string $id)
 * @method self|RestDtoInterface patch(RestDtoInterface $dto)
 * @method Entity|EntityInterface update(EntityInterface $entity)
 */
class Like extends RestDto
{
    #[Assert\NotNull(message: 'User cannot be null.')]
    protected UuidInterface $user;

    protected ?Post $post = null;

    protected ?Comment $comment = null;

    protected ?DateTimeImmutable $createdAt = null;

    protected ?DateTimeImmutable $updatedAt = null;

    public function getUser(): UuidInterface
    {
        return $this->user;
    }

    public function setUser(UuidInterface $user): self
    {
        $this->setVisited('user');
        $this->user = $user;

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

    public function getComment(): ?Comment
    {
        return $this->comment;
    }

    public function setComment(?Comment $comment): self
    {
        $this->setVisited('comment');
        $this->comment = $comment;

        return $this;
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): self
    {
        $this->setVisited('createdAt');
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(DateTimeImmutable $updatedAt): self
    {
        $this->setVisited('updatedAt');
        $this->updatedAt = $updatedAt;

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
            $this->user = $entity->getUser();
            $this->post = $entity->getPost();
            $this->comment = $entity->getComment();
            $this->createdAt = $entity->getCreatedAt();
            $this->updatedAt = $entity->getUpdatedAt();
        }

        return $this;
    }
}
