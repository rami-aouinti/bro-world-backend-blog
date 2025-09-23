<?php

declare(strict_types=1);

namespace App\Tests\Unit\Blog\Entity;

use App\Blog\Domain\Entity\Blog;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

class BlogTest extends TestCase
{
    #[TestDox('It exposes getters and setters for core properties')]
    public function testAccessors(): void
    {
        $blog = new Blog();
        $author = Uuid::uuid4();

        $blog
            ->setTitle('My awesome blog')
            ->setBlogSubtitle('All about testing')
            ->setSlug('my-awesome-blog')
            ->setAuthor($author);

        $blog->setLogo('logo.png');
        $blog->setTeams(['team-a', 'team-b']);
        $blog->setVisible(false);
        $blog->setColor('#123abc');

        self::assertNotEmpty($blog->getId());
        self::assertSame('My awesome blog', $blog->getTitle());
        self::assertSame('All about testing', $blog->getBlogSubtitle());
        self::assertSame($author, $blog->getAuthor());
        self::assertSame('my-awesome-blog', $blog->getSlug());
        self::assertSame('logo.png', $blog->getLogo());
        self::assertSame(['team-a', 'team-b'], $blog->getTeams());
        self::assertFalse($blog->isVisible());
        self::assertSame('#123abc', $blog->getColor());
        self::assertSame('My awesome blog', (string)$blog);

        $blog->setColor(null);
        self::assertNull($blog->getColor());
        $blog->setVisible(true);
        self::assertTrue($blog->isVisible());
    }
}
