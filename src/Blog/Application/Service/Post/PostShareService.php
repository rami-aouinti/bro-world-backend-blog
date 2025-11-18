<?php

declare(strict_types=1);

namespace App\Blog\Application\Service\Post;

use App\Blog\Domain\Entity\Post;
use App\Blog\Domain\Message\CreatePostMessenger;
use Bro\WorldCoreBundle\Infrastructure\ValueObject\SymfonyUser;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

use function md5;
use function sprintf;
use function substr;
use function trim;
use function uniqid;

/**
 * @package App\Blog\Application\Service\Post
 */
final readonly class PostShareService
{
    public function __construct(
        private MessageBusInterface $bus,
        private SluggerInterface $slugger
    ) {
    }

    /**
     * Builds a lightweight shared post and dispatches it to the messenger bus.
     *
     * @throws ExceptionInterface
     */
    public function share(Post $originalPost, SymfonyUser $symfonyUser, ?string $content): Post
    {
        $title = $this->resolveTitle($originalPost, $content);
        $sharedPost = (new Post())
            ->setAuthor(Uuid::fromString($symfonyUser->getId()))
            ->setTitle($title)
            ->setSummary($this->resolveSummary($originalPost, $content))
            ->setContent($this->resolveContent($originalPost, $content))
            ->setSlug($this->buildSlug($title, $originalPost->getSlug()))
            ->setBlog($originalPost->getBlog())
            ->setSharedFrom($originalPost);

        $this->bus->dispatch(new CreatePostMessenger($sharedPost, null));

        return $sharedPost;
    }

    private function resolveTitle(Post $originalPost, ?string $content): string
    {
        $trimmed = trim((string)$content);

        if ($trimmed !== '') {
            return $trimmed;
        }

        $originalTitle = $originalPost->getTitle();

        if ($originalTitle !== null && trim($originalTitle) !== '') {
            return $originalTitle;
        }

        return 'Shared post';
    }

    private function resolveSummary(Post $originalPost, ?string $content): ?string
    {
        $trimmed = trim((string)$content);

        if ($trimmed !== '') {
            return $trimmed;
        }

        return $originalPost->getSummary();
    }

    private function resolveContent(Post $originalPost, ?string $content): ?string
    {
        $trimmed = trim((string)$content);

        if ($trimmed !== '') {
            return $trimmed;
        }

        return $originalPost->getContent();
    }

    private function buildSlug(string $title, ?string $originalSlug): string
    {
        $base = trim((string)$title) !== '' ? $title : ($originalSlug ?? 'shared-post');
        $slug = (string)$this->slugger->slug($base)->lower();

        if ($slug === '') {
            $slug = 'shared-post';
        }

        return sprintf('%s-%s', $slug, substr(md5(uniqid('', true)), 0, 8));
    }
}
