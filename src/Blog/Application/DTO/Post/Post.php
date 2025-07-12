<?php

declare(strict_types=1);

namespace App\Blog\Application\DTO\Post;

use App\Blog\Domain\Entity\Post as Entity;
use App\General\Application\DTO\Interfaces\RestDtoInterface;
use App\General\Application\DTO\RestDto;
use App\General\Domain\Entity\Interfaces\EntityInterface;
use DateTimeInterface;
use Override;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @package App\Post
 *
 * @method self|RestDtoInterface get(string $id)
 * @method self|RestDtoInterface patch(RestDtoInterface $dto)
 * @method Entity|EntityInterface update(EntityInterface $entity)
 */
class Post extends RestDto
{

    #[Assert\NotBlank(message: 'User ID cannot be blank.')]
    protected UuidInterface $userId;

    #[Assert\NotBlank]
    #[Assert\NotNull]
    #[Assert\Length(min: 2, max: 255)]
    protected string $title= '';

    #[Assert\NotBlank]
    #[Assert\NotNull]
    protected string $description = '';

    #[Assert\NotBlank]
    #[Assert\NotNull]
    protected string $gender = '';

    protected UuidInterface $photo;

    #[Assert\Date(message: 'The birthday must be a valid date.')]
    protected ?DateTimeInterface $birthday = null;

    protected ?string $googleId = "";

    protected ?string $githubId = "";

    protected ?string $githubUrl = "";

    protected ?string $instagramUrl = "";

    protected ?string $linkedInId = "";

    protected ?string $linkedInUrl = "";

    protected ?string $twitterUrl = "";

    protected ?string $facebookUrl = "";

    protected ?string $phone = "";


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

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->setVisited('description');
        $this->description = $description;

        return $this;
    }

    public function getUserId(): UuidInterface
    {
        return $this->userId;
    }

    public function setUserId(UuidInterface $userId): self
    {
        $this->setVisited('userId');
        $this->userId = $userId;

        return $this;
    }

    public function getGender(): string
    {
        return $this->gender;
    }

    public function setGender(string $gender): self
    {
        $this->setVisited('gender');
        $this->gender = $gender;

        return $this;
    }

    public function getPhoto(): UuidInterface
    {
        return $this->photo;
    }

    public function setPhoto(UuidInterface $photo): self
    {
        $this->setVisited('photo');
        $this->photo = $photo;

        return $this;
    }

    public function getBirthday(): ?DateTimeInterface
    {
        return $this->birthday;
    }

    public function setBirthday(?DateTimeInterface $birthday): self
    {
        $this->setVisited('birthday');
        $this->birthday = $birthday;

        return $this;
    }

    public function getGoogleId(): ?string
    {
        return $this->googleId;
    }

    public function setGoogleId(?string $googleId): self
    {
        $this->setVisited('googleId');
        $this->googleId = $googleId;

        return $this;
    }

    public function getGithubId(): ?string
    {
        return $this->githubId;
    }

    public function setGithubId(?string $githubId): self
    {
        $this->setVisited('githubId');
        $this->githubId = $githubId;

        return $this;
    }

    public function getGithubUrl(): ?string
    {
        return $this->githubUrl;
    }

    public function setGithubUrl(?string $githubUrl): self
    {
        $this->setVisited('githubUrl');
        $this->githubUrl = $githubUrl;

        return $this;
    }

    public function getInstagramUrl(): ?string
    {
        return $this->instagramUrl;
    }

    public function setInstagramUrl(?string $instagramUrl): self
    {
        $this->setVisited('instagramUrl');
        $this->instagramUrl = $instagramUrl;

        return $this;
    }

    public function getLinkedInId(): ?string
    {
        return $this->linkedInId;
    }

    public function setLinkedInId(?string $linkedInId): self
    {
        $this->setVisited('linkedInId');
        $this->linkedInId = $linkedInId;

        return $this;
    }

    public function getLinkedInUrl(): ?string
    {
        return $this->linkedInUrl;
    }

    public function setLinkedInUrl(?string $linkedInUrl): self
    {
        $this->setVisited('linkedInUrl');
        $this->linkedInUrl = $linkedInUrl;

        return $this;
    }

    public function getTwitterUrl(): ?string
    {
        return $this->twitterUrl;
    }

    public function setTwitterUrl(?string $twitterUrl): self
    {
        $this->setVisited('twitterUrl');
        $this->twitterUrl = $twitterUrl;

        return $this;
    }

    public function getFacebookUrl(): ?string
    {
        return $this->facebookUrl;
    }

    public function setFacebookUrl(?string $facebookUrl): self
    {
        $this->setVisited('facebookUrl');
        $this->facebookUrl = $facebookUrl;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): self
    {
        $this->setVisited('phone');
        $this->phone = $phone;

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
            $this->description = $entity->getDescription();
            $this->userId = $entity->getUserId();
            $this->photo = $entity->getPhoto();
            $this->birthday = $entity->getBirthday();
            $this->gender = $entity->getGender();
            $this->googleId = $entity->getGoogleId();
            $this->githubId = $entity->getGithubId();
            $this->githubUrl = $entity->getGithubUrl();
            $this->instagramUrl = $entity->getInstagramUrl();
            $this->linkedInId = $entity->getLinkedInId();
            $this->linkedInUrl = $entity->getLinkedInUrl();
            $this->twitterUrl = $entity->getTwitterUrl();
            $this->facebookUrl = $entity->getFacebookUrl();
            $this->phone = $entity->getPhone();
        }

        return $this;
    }
}
