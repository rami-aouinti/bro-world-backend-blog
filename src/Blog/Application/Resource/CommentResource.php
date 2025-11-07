<?php

declare(strict_types=1);

namespace App\Blog\Application\Resource;

use App\Blog\Domain\Entity\Comment as Entity;
use App\Blog\Domain\Repository\Interfaces\CommentRepositoryInterface as Repository;
use App\Blog\Infrastructure\Repository\CommentRepository;
use Bro\WorldCoreBundle\Application\DTO\Interfaces\RestDtoInterface;
use Bro\WorldCoreBundle\Application\Rest\RestResource;
use Bro\WorldCoreBundle\Domain\Entity\Interfaces\EntityInterface;

/**
 * @package App\Blog\Application\Resource
 *
 * @method Entity getReference(string $id, ?string $entityManagerName = null)
 * @method CommentRepository getRepository()
 * @method Entity[] find(?array $criteria = null, ?array $orderBy = null, ?int $limit = null, ?int $offset = null, ?array $search = null, ?string $entityManagerName = null)
 * @method Entity|null findOne(string $id, ?bool $throwExceptionIfNotFound = null, ?string $entityManagerName = null)
 * @method Entity|null findOneBy(array $criteria, ?array $orderBy = null, ?bool $throwExceptionIfNotFound = null, ?string $entityManagerName = null)
 * @method Entity create(RestDtoInterface $dto, ?bool $flush = null, ?bool $skipValidation = null, ?string $entityManagerName = null)
 * @method Entity update(string $id, RestDtoInterface $dto, ?bool $flush = null, ?bool $skipValidation = null, ?string $entityManagerName = null)
 * @method Entity patch(string $id, RestDtoInterface $dto, ?bool $flush = null, ?bool $skipValidation = null, ?string $entityManagerName = null)
 * @method Entity delete(string $id, ?bool $flush = null, ?string $entityManagerName = null)
 * @method Entity save(EntityInterface $entity, ?bool $flush = null, ?bool $skipValidation = null, ?string $entityManagerName = null)
 */
class CommentResource extends RestResource
{
    /**
     * @param CommentRepository $repository
     */
    public function __construct(
        Repository $repository,
    ) {
        parent::__construct($repository);
    }
}
