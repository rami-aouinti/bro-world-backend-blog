<?php

declare(strict_types=1);

namespace App\Blog\Transport\EventSubscriber;

use App\Blog\Application\Service\Moderation\ContentModerationService;
use App\Blog\Application\Service\Moderation\ModerationWarningService;
use App\Blog\Domain\Entity\Comment;
use App\Blog\Domain\Entity\Post;
use App\Blog\Domain\Repository\Interfaces\CommentRepositoryInterface;
use App\Blog\Domain\Repository\Interfaces\PostRepositoryInterface;
use App\Blog\Transport\Event\CommentCreatedEvent;
use App\Blog\Transport\Event\PostCreatedEvent;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\TransactionRequiredException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

use function array_merge;
use function array_unique;
use function array_values;

final readonly class ContentModerationSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private ContentModerationService $moderationService,
        private ModerationWarningService $warningService,
        private CommentRepositoryInterface $commentRepository,
        private PostRepositoryInterface $postRepository,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CommentCreatedEvent::class => 'onCommentCreated',
            PostCreatedEvent::class => 'onPostCreated',
        ];
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     * @throws TransactionRequiredException
     */
    public function onCommentCreated(CommentCreatedEvent $event): void
    {
        $comment = $event->getComment();
        $violations = $this->moderationService->detectViolations($comment->getContent());

        if ($violations === []) {
            return;
        }

        $this->removeComment($comment);
        $event->block($violations, 'comment_blocked');

        $this->warningService->recordWarning(
            $comment->getAuthor()->toString(),
            'comment',
            $comment->getId(),
            $violations
        );
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     * @throws TransactionRequiredException
     */
    public function onPostCreated(PostCreatedEvent $event): void
    {
        $post = $event->getPost();
        $violations = $this->detectPostViolations($post);

        if ($violations === []) {
            return;
        }

        $this->removePost($post);
        $event->block($violations, 'post_blocked');

        $this->warningService->recordWarning(
            $post->getAuthor()->toString(),
            'post',
            $post->getId(),
            $violations
        );
    }

    /**
     * @return array<int, string>
     */
    private function detectPostViolations(Post $post): array
    {
        $violations = $this->moderationService->detectViolations($post->getTitle());
        $violations = array_merge($violations, $this->moderationService->detectViolations($post->getSummary()));
        $violations = array_merge($violations, $this->moderationService->detectViolations($post->getContent()));

        return array_values(array_unique($violations));
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     * @throws TransactionRequiredException
     */
    private function removeComment(Comment $comment): void
    {
        $this->commentRepository->remove($comment);
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     * @throws TransactionRequiredException
     */
    private function removePost(Post $post): void
    {
        $this->postRepository->remove($post);
    }
}
