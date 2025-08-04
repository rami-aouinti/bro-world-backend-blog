<?php

declare(strict_types=1);

namespace App\Blog\Transport\Controller\Frontend;

use App\Blog\Application\ApiProxy\UserProxy;
use App\Blog\Domain\Entity\Media;
use App\Blog\Infrastructure\Repository\PostRepository;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\TransactionRequiredException;
use OpenApi\Attributes as OA;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

use function array_slice;

/**
 * Class PostsController
 *
 * @package App\Blog\Transport\Controller\Frontend
 * @author  Rami Aouinti <rami.aouinti@tkdeutschland.de>
 */
#[AsController]
#[OA\Tag(name: 'Blog')]
readonly class PostsController
{
    public function __construct(
        private TagAwareCacheInterface $cache, // ✅ utilise cache taggable
        private PostRepository $postRepository,
        private UserProxy $userProxy
    ) {}

    /** ✅ Endpoint principal : liste des posts avec preview
     *
     * @param Request $request
     *
     * @throws InvalidArgumentException
     * @return JsonResponse
     */
    #[Route('/public/post', name: 'public_post_index', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $page   = max(1, (int) $request->query->get('page', 1));
        $limit  = (int) $request->query->get('limit', 10);
        $offset = ($page - 1) * $limit;
        $cacheKey = "posts_page_{$page}_limit_{$limit}";

        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use ($limit, $offset, $page) {
            $item->tag(['posts']); // ✅ tag global
            $item->expiresAfter(20);

            $posts = $this->postRepository->findWithRelations($limit, $offset);
            $total = $this->postRepository->countPosts();

            $userIds = [];
            foreach ($posts as $post) {
                $userIds[] = $post->getAuthor()->toString();
                foreach ($post->getLikes() as $like) {
                    $userIds[] = $like->getUser()->toString();
                }
                foreach ($post->getReactions() as $reaction) {
                    $userIds[] = $reaction->getUser()->toString();
                }
                foreach ($post->getComments() as $comment) {
                    $userIds[] = $comment->getAuthor()->toString();
                }
            }
            $users = $this->userProxy->batchSearchUsers($userIds);

            $data = [];
            foreach ($posts as $post) {
                $data[] = [
                    'id' => $post->getId(),
                    'title' => $post->getTitle(),
                    'summary' => $post->getSummary(),
                    'url' => $post->getUrl(),
                    'slug' => $post->getSlug(),
                    'medias' => $post->getMediaEntities()->map(fn(Media $m) => $m->toArray())->toArray(),
                    'likes_count' => count($post->getLikes()),
                    'reactions_count' => count($post->getReactions()),
                    'totalComments' => count($post->getComments()),
                    'user' => $users[$post->getAuthor()->toString()] ?? null,
                    'comments_preview' => array_slice(array_map(static fn($c) => [
                        'id' => $c->getId(),
                        'content' => $c->getContent(),
                        'user' => $users[$c->getAuthor()->toString()] ?? null
                    ], $post->getComments()->toArray()), 0, 2),
                ];
            }

            return ['data' => $data, 'page' => $page, 'limit' => $limit, 'count' => $total];
        });

        return new JsonResponse($result);
    }

    /** ✅ Endpoint lazy load commentaires
     *
     * @param string  $id
     * @param Request $request
     *
     * @throws InvalidArgumentException
     * @throws NoResultException
     * @throws NonUniqueResultException
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     * @return JsonResponse
     */
    #[Route('/public/post/{id}/comments', name: 'public_post_comments', methods: ['GET'])]
    public function comments(string $id, Request $request): JsonResponse
    {
        $page   = max(1, (int) $request->query->get('page', 1));
        $limit  = (int) $request->query->get('limit', 10);
        $offset = ($page - 1) * $limit;

        $comments = $this->postRepository->getRootComments($id, $limit, $offset);
        $total    = $this->postRepository->countComments($id);

        $userIds = array_map(static fn($c) => $c->getAuthor()->toString(), $comments);
        $users   = $this->userProxy->batchSearchUsers($userIds);

        $data = array_map(static fn($c) => [
            'id' => $c->getId(),
            'content' => $c->getContent(),
            'user' => $users[$c->getAuthor()->toString()] ?? null
        ], $comments);

        return new JsonResponse(['comments' => $data, 'total' => $total, 'page' => $page]);
    }

    /** ✅ Endpoint lazy load likes
     *
     * @param string $id
     *
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws InvalidArgumentException
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws TransactionRequiredException
     * @return JsonResponse
     */
    #[Route('/public/post/{id}/likes', name: 'public_post_likes', methods: ['GET'])]
    public function likes(string $id): JsonResponse
    {
        $post = $this->postRepository->find($id);
        $userIds = array_map(static fn($l) => $l->getUser()->toString(), $post?->getLikes()->toArray());
        $users   = $this->userProxy->batchSearchUsers($userIds);

        $likes = array_map(static fn($l) => [
            'id' => $l->getId(),
            'user' => $users[$l->getUser()->toString()] ?? null
        ], $post?->getLikes()->toArray());

        return new JsonResponse(['likes' => $likes, 'total' => count($likes)]);
    }

    /** ✅ Endpoint lazy load likes
     *
     * @param string $id
     *
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws InvalidArgumentException
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws TransactionRequiredException
     * @return JsonResponse
     */
    #[Route('/public/post/{id}/reactions', name: 'public_post_reactions', methods: ['GET'])]
    public function reactions(string $id): JsonResponse
    {
        $post = $this->postRepository->find($id);
        $userIds = array_map(static fn($l) => $l->getUser()->toString(), $post?->getReactions()->toArray());
        $users   = $this->userProxy->batchSearchUsers($userIds);

        $reactions = array_map(static fn($l) => [
            'id' => $l->getId(),
            'user' => $users[$l->getUser()->toString()] ?? null
        ], $post?->getReactions()->toArray());

        return new JsonResponse(['reactions' => $reactions, 'total' => count($reactions)]);
    }
}
