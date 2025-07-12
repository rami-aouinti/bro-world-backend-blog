<?php

declare(strict_types=1);

namespace App\Blog\Infrastructure\Repository;

use App\Blog\Domain\Entity\Like as Entity;
use App\Blog\Domain\Repository\Interfaces\LikeRepositoryInterface;
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
class LikeRepository extends BaseRepository implements LikeRepositoryInterface
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

    public function countLikesByMonth(): array
    {
        $qb = $this->createQueryBuilder('l')
            ->select("CONCAT_WS('-', YEAR(l.createdAt), LPAD(MONTH(l.createdAt), 2, '0')) AS month, COUNT(l.id) AS count")
            ->groupBy("CONCAT_WS('-', YEAR(l.createdAt), LPAD(MONTH(l.createdAt), 2, '0'))")
            ->orderBy("CONCAT_WS('-', YEAR(l.createdAt), LPAD(MONTH(l.createdAt), 2, '0'))", 'DESC');

        return array_column($qb->getQuery()->getResult(), 'count', 'month');
    }
}
