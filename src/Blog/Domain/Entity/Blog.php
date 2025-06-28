<?php

declare(strict_types=1);

/* For licensing terms, see /license.txt */

namespace App\Blog\Domain\Entity;

use App\General\Domain\Entity\Interfaces\EntityInterface;
use App\General\Domain\Entity\Traits\ColorTrait;
use App\General\Domain\Entity\Traits\SlugTrait;
use App\General\Domain\Entity\Traits\Timestampable;
use App\General\Domain\Entity\Traits\Uuid;
use App\General\Domain\Entity\Traits\VisibleTrait;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;
use Ramsey\Uuid\UuidInterface;
use Stringable;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Throwable;
use Doctrine\Common\Collections\Collection;

/**
 * @package App\Feature\Blog\Domain\Entity
 * @author  Rami Aouinti <rami.aouinti@tkdeutschland.de>
 */
#[ORM\Table(name: 'blog')]
#[ORM\Entity]
class Blog implements EntityInterface, Stringable
{
    use ColorTrait;
    use SlugTrait;
    use VisibleTrait;
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
        'Blog',
        'Blog.id',
    ])]
    private UuidInterface $id;

    #[Assert\NotBlank]
    #[ORM\Column(name: 'title', type: 'text', nullable: false)]
    #[Groups([
        'Blog',
        'Post',
    ])]
    protected string $title;

    #[ORM\Column(name: 'blog_subtitle', type: 'string', length: 250, nullable: true)]
    #[Groups([
        'Blog',
        'Post',
    ])]
    protected ?string $blogSubtitle = null;

    #[ORM\Column(type: 'uuid')]
    protected UuidInterface $author;


    #[ORM\Column(type: 'string', nullable: true)]
    #[Groups([
        'Blog'
    ])]
    private ?string $logo = null;

    #[ORM\OneToMany(mappedBy: 'blog', targetEntity: Post::class)]
    private Collection $posts;

    /**
     * @throws Throwable
     */
    public function __construct()
    {
        $this->id = $this->createUuid();
    }

    public function getId(): string
    {
        return $this->id->toString();
    }

    public function __toString(): string
    {
        return $this->getTitle();
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getBlogSubtitle(): ?string
    {
        return $this->blogSubtitle;
    }

    public function setBlogSubtitle(?string $blogSubtitle): self
    {
        $this->blogSubtitle = $blogSubtitle;

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

    public function getLogo(): ?string
    {
        return $this->logo;
    }

    public function setLogo(?string $logo): void
    {
        $this->logo = $logo;
    }
}
