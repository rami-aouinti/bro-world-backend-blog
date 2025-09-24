<?php

declare(strict_types=1);

namespace App\Tests\Unit\Blog\Entity;

use App\Blog\Domain\Entity\Tag;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

class TagTest extends TestCase
{
    #[TestDox('It exposes name, visibility and color helpers')]
    public function testTagAccessors(): void
    {
        $tag = new Tag('Testing');

        self::assertNotEmpty($tag->getId());
        self::assertSame('Testing', $tag->getName());
        self::assertSame('Testing', $tag->getDescription());
        self::assertSame('Testing', (string)$tag);
        self::assertSame('Testing', $tag->jsonSerialize());
        self::assertTrue($tag->isVisible());
        self::assertSame('', $tag->getColorSafe());

        $tag->setDescription('A test tag');
        self::assertSame('A test tag', $tag->getDescription());

        $tag->setVisible(false);
        $tag->setColor('#ffffff');
        self::assertFalse($tag->isVisible());
        self::assertSame('#ffffff', $tag->getColorSafe());
    }
}
