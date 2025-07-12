<?php

declare(strict_types=1);

namespace App\Blog\Infrastructure\Repository;

use App\Blog\Domain\Entity\Blog as Entity;
use App\Blog\Domain\Repository\Interfaces\BlogRepositoryInterface;
use App\General\Infrastructure\Repository\BaseRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @package App\Blog
 *
 * @psalm-suppress LessSpecificImplementedReturnType
 * @codingStandardsIgnoreStart
 *
 * @method Entity|null find(string $id, ?int $lockMode = null, ?int $lockVersion = null, ?string $entityManagerName = null)
 * @method Entity|null findAdvanced(string $id, string | int | null $hydrationMode = null, string|null $entityManagerName = null)
 * @method Entity|null findOneBy(array $criteria, ?array $orderBy = null, ?string $entityManagerName = null)
 * @method Entity[] findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null, ?string $entityManagerName = null)
 * @method Entity[] findByAdvanced(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null, ?array $search = null, ?string $entityManagerName = null)
 * @method Entity[] findAll(?string $entityManagerName = null)
 *
 * @codingStandardsIgnoreEnd
 */
class BlogRepository extends BaseRepository implements BlogRepositoryInterface
{
    /**
     * @var array<int, string>
     */
    protected static array $searchColumns = ['username', 'firstName', 'lastName', 'email'];

    /**
     * @psalm-var class-string
     */
    protected static string $entityName = Entity::class;

    public function __construct(
        protected ManagerRegistry $managerRegistry
    ) {
    }

    public function countBlogsByMonth(): array
    {
        $qb = $this->createQueryBuilder('b')
            ->select("CONCAT(YEAR(b.createdAt), '-', LPAD(MONTH(b.createdAt), 2, '0')) AS month, COUNT(b.id) AS count")
            ->groupBy('month')
            ->orderBy('month', 'DESC');

        return array_column($qb->getQuery()->getResult(), 'count', 'month');
    }
}
