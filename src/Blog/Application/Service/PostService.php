<?php

declare(strict_types=1);

namespace App\Blog\Application\Service;

use App\Blog\Domain\Entity\Blog;
use App\Blog\Domain\Entity\Post;
use App\Blog\Domain\Entity\Tag;
use App\Blog\Domain\Message\CreatePostMessenger;
use App\Blog\Domain\Repository\Interfaces\BlogRepositoryInterface;
use App\Blog\Domain\Repository\Interfaces\PostRepositoryInterface;
use App\Blog\Domain\Repository\Interfaces\TagRepositoryInterface;
use App\General\Infrastructure\ValueObject\SymfonyUser;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\NotSupported;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\TransactionRequiredException;
use Psr\Cache\InvalidArgumentException;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Throwable;

/**
 * Class PostService
 *
 * @package App\Blog\Application\Service
 * @author  Rami Aouinti <rami.aouinti@tkdeutschland.de>
 */
readonly class PostService
{
    public function __construct(
        private MediaService $mediaService,
        private BlogService $blogService,
        private EntityManagerInterface $entityManager,
        private TagRepositoryInterface $tagRepository,
        private PostRepositoryInterface $postRepository,
        private CacheInterface $cache,
        private MessageBusInterface $bus
    ) {}

    /**
     * @throws InvalidArgumentException
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws Throwable
     * @throws TransactionRequiredException
     * @throws NotSupported
     */
    public function createPost(SymfonyUser $user, Request $request): array
    {
        $medias = $request->files->all() ? $this->mediaService->createMedia($request, 'media') : [];

        $post = $this->generatePostAttributes(
            $this->blogService->getBlog($request, $user),
            $user,
            $request
        );

        $this->bus->dispatch(
            new CreatePostMessenger($post, $this->mediaService->getMediaIds($medias ?? []))
        );
        return array_merge(
            $post->toArray(),
            ['medias' => $medias, 'user' => $user]
        );
    }

    /**
     * @param Post       $post
     * @param array|null $mediaIds
     *
     * @throws InvalidArgumentException
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function savePost(Post $post, ?array $mediaIds): void
    {
        if (!empty($mediaIds)) {
            $post->setMedias($mediaIds);
        }

        $this->postRepository->save($post);
        $this->cache->delete('post_public');
    }

    /**
     * @throws Throwable
     * @throws NotSupported
     */
    public function generatePostAttributes(Blog $blog, SymfonyUser $user, Request $request): Post
    {
        $data = $request->request->all();

        $post = (new Post())
            ->setAuthor(Uuid::fromString($user->getUserIdentifier()))
            ->setTitle($data['title'])
            ->setSlug($data['title']);

        $post->setContent($data['content'] ?? '');
        $post->setSummary($data['summary'] ?? '');

        foreach ($data['tags'] ?? [] as $tagName) {
            $tag = $this->tagRepository->findOneBy(['name' => $tagName]) ?? new Tag($tagName);
            if (!$tag->getId()) {
                $this->entityManager->persist($tag);
            }
            $post->addTag($tag);
        }

        return $post;
    }
}
