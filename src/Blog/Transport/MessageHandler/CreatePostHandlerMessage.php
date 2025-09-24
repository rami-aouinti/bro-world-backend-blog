<?php

declare(strict_types=1);

namespace App\Blog\Transport\MessageHandler;

use App\Blog\Application\Service\Post\PostFeedCacheService;
use App\Blog\Application\Service\Post\PostFeedResponseBuilder;
use App\Blog\Application\Service\Post\PostService;
use App\Blog\Domain\Entity\Post;
use App\Blog\Domain\Message\CreatePostMessenger;
use App\Blog\Domain\Repository\Interfaces\PostRepositoryInterface;
use App\Blog\Transport\Event\PostCreatedEvent;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Psr\Cache\InvalidArgumentException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
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
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @throws InvalidArgumentException
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function __invoke(CreatePostMessenger $message): void
    {
        $post = $this->handleMessage($message);

        $event = new PostCreatedEvent($post);
        $this->eventDispatcher->dispatch($event);

        if ($event->isBlocked()) {
            return;
        }

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

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    private function handleMessage(CreatePostMessenger $message): Post
    {
        return $this->postService->executeSavePostCommand($message->getPost(), $message->getMediasIds());
    }
}
