<?php

declare(strict_types=1);

namespace App\Tests\Unit\Blog\Application\Service;

use App\Blog\Application\Service\Post\PostShareService;
use App\Blog\Domain\Entity\Post;
use App\Blog\Domain\Message\CreatePostMessenger;
use Bro\WorldCoreBundle\Infrastructure\ValueObject\SymfonyUser;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\String\UnicodeString;

final class PostShareServiceTest extends TestCase
{
    public function testShareBuildsPostAndDispatchesMessage(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $slugger = $this->createMock(SluggerInterface::class);
        $slugger->method('slug')->willReturn(new UnicodeString('shared-body'));

        $originalPost = (new Post())
            ->setTitle('Original post')
            ->setSummary('Original summary')
            ->setContent('Original content')
            ->setSlug('original-slug');

        $user = new SymfonyUser(Uuid::uuid4()->toString(), 'Sharer', null, ['ROLE_USER']);

        $bus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (CreatePostMessenger $message) use ($originalPost) {
                $sharedPost = $message->getPost();
                self::assertSame($originalPost, $sharedPost->getSharedFrom());

                return true;
            }));

        $service = new PostShareService($bus, $slugger);
        $sharedPost = $service->share($originalPost, $user, 'Shared body');

        self::assertSame('Shared body', $sharedPost->getTitle());
        self::assertSame('Shared body', $sharedPost->getSummary());
        self::assertSame('Shared body', $sharedPost->getContent());
        self::assertSame($originalPost, $sharedPost->getSharedFrom());
        self::assertStringContainsString('shared-body', $sharedPost->getSlug());
    }
}
