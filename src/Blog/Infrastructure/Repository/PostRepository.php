<?php

declare(strict_types=1);

namespace App\Blog\Infrastructure\Repository;

use App\Blog\Domain\Entity\Comment;
use App\Blog\Domain\Entity\Post as Entity;
use App\Blog\Domain\Repository\Interfaces\PostRepositoryInterface;
use App\General\Infrastructure\Repository\BaseRepository;
use DateTimeImmutable;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\Persistence\ManagerRegistry;
use Exception;
use Ramsey\Uuid\Uuid;

use function sprintf;

/**
 * @package App\Blog\Infrastructure\Repository
 * @author  Rami Aouinti <rami.aouinti@tkdeutschland.de>
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
class PostRepository extends BaseRepository implements PostRepositoryInterface
{
    /**
     * @var array<int, string>
     */
    protected static array $searchColumns = ['title', 'content', 'summary'];

    /**
     * @psalm-var class-string
     */
    protected static string $entityName = Entity::class;

    public function __construct(
        protected ManagerRegistry $managerRegistry
    ) {
    }

    public function findWithRelations(int $limit, int $offset, ?string $authorId = null): array
    {
        $qbIds = $this->createQueryBuilder('p')
            ->select('p.id')
            ->orderBy('p.publishedAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        if ($authorId) {
            $qbIds->andWhere('p.author = :author')
                ->setParameter('author', Uuid::fromString($authorId));
        }

        $ids = $qbIds->getQuery()->getScalarResult();
        if (!$ids) {
            return [];
        }

        $ids = array_column($ids, 'id');

        $qb = $this->createQueryBuilder('p')
            ->select('DISTINCT p', 'c', 'l', 'm', 'r', 'cl', 'cr')
            ->leftJoin('p.comments', 'c')->addSelect('c')
            ->leftJoin('p.likes', 'l')->addSelect('l')
            ->leftJoin('p.medias', 'm')->addSelect('m')
            ->leftJoin('p.reactions', 'r')->addSelect('r')
            ->leftJoin('c.likes', 'cl')->addSelect('cl')
            ->leftJoin('c.reactions', 'cr')->addSelect('cr')
            ->where('p.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->orderBy('p.publishedAt', 'DESC');

        return $qb->getQuery()->getResult();
    }

    /**
     * Counts posts, optionally filtered by author.
     *
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function countPosts(?string $authorId = null): int
    {
        $qb = $this->createQueryBuilder('p')
            ->select('COUNT(p.id)');

        if ($authorId) {
            $qb->andWhere('p.author = :author')
                ->setParameter('author', Uuid::fromString($authorId));
        }

        return (int)$qb->getQuery()->getSingleScalarResult();
    }

    public function getRootComments(string $postId, int $limit, int $offset): array
    {
        return $this->getEntityManager()->createQueryBuilder()
            ->select('c', 'children', 'likes')
            ->from(Comment::class, 'c')
            ->leftJoin('c.children', 'children')
            ->leftJoin('c.likes', 'likes')
            ->join('c.post', 'p')
            ->where('p.id = :postId')
            ->andWhere('c.parent IS NULL')
            ->setParameter('postId', Uuid::fromString($postId), 'uuid_binary_ordered_time') // Converts the ID to the UUID binary format.
            ->orderBy('c.publishedAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    /**
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function countComments(string $postId): int
    {
        return (int)$this->getEntityManager()->createQueryBuilder()
            ->select('COUNT(c.id)')
            ->from(Comment::class, 'c')
            ->join('c.post', 'p')
            ->where('p.id = :postId')
            ->andWhere('c.parent IS NULL')
            ->setParameter('postId', Uuid::fromString($postId), 'uuid_binary_ordered_time') // Converts the ID to the UUID binary format.
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @throws Exception
     */
    public function countPostsByMonth(): array
    {
        $qb = $this->createQueryBuilder('b')
            ->select('YEAR(b.createdAt) AS year, MONTH(b.createdAt) AS month, COUNT(b.id) AS count')
            ->groupBy('year, month')
            ->orderBy('year', 'ASC')
            ->addOrderBy('month', 'ASC');

        $result = $qb->getQuery()->getResult();

        $counts = [];
        foreach ($result as $row) {
            $key = sprintf('%04d-%02d', $row['year'], $row['month']);
            $counts[$key] = (int)$row['count'];
        }

        $firstKey = array_key_first($counts) ?? (new DateTimeImmutable('now'))->format('Y-m');
        $lastKey = (new DateTimeImmutable('now'))->format('Y-m');

        $fullMonths = $this->generateMonthRange($firstKey, $lastKey);

        $complete = [];
        foreach ($fullMonths as $month) {
            $complete[$month] = $counts[$month] ?? 0;
        }

        return $complete;
    }

    /**
     * @throws Exception
     */
    private function generateMonthRange(string $start, string $end): array
    {
        $months = [];
        $startDate = new DateTimeImmutable($start . '-01');
        $endDate = new DateTimeImmutable($end . '-01');

        while ($startDate <= $endDate) {
            $months[] = $startDate->format('Y-m');
            $startDate = $startDate->modify('+1 month');
        }

        return $months;
    }
}
