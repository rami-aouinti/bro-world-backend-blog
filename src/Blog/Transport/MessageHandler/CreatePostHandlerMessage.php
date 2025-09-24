<?php

declare(strict_types=1);

namespace App\Blog\Transport\MessageHandler;

use App\Blog\Application\Service\PostFeedCacheService;
use App\Blog\Application\Service\PostFeedResponseBuilder;
use App\Blog\Application\Service\PostService;
use App\Blog\Domain\Message\CreatePostMessenger;
use App\Blog\Domain\Repository\Interfaces\PostRepositoryInterface;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @package App\Post\Transport\MessageHandler
 * @author  Rami Aouinti <rami.aouinti@tkdeutschland.de>
 */
#[AsMessageHandler]
readonly class CreatePostHandlerMessage
{
    public function __construct(
        private PostService $postService,
        private PostFeedCacheService $postFeedCacheService,
        private PostRepositoryInterface $postRepository,
        private PostFeedResponseBuilder $postFeedResponseBuilder,
    ) {
    }

    /**
     * @throws InvalidArgumentException
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function __invoke(CreatePostMessenger $message): void
    {
        $this->handleMessage($message);
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     * @throws InvalidArgumentException
     */
    private function handleMessage(CreatePostMessenger $message): void
    {
        $this->postService->savePost($message->getPost(), $message->getMediasIds());

        $page = 1;
        $limit = 10;

        $this->postFeedCacheService->invalidateTags();
        $this->postFeedCacheService->delete($page, $limit);

        $this->postFeedCacheService->get($page, $limit, function () use ($page, $limit): array {
            $offset = ($page - 1) * $limit;

            $posts = $this->postRepository->findWithRelations($limit, $offset);
            $total = $this->postRepository->countPosts();

            return $this->postFeedResponseBuilder->build($posts, $page, $limit, $total);
        });
    }
}
