<?php

declare(strict_types=1);

namespace App\Blog\Infrastructure\DataFixtures\ORM;

use App\Blog\Domain\Entity\Blog;
use App\Blog\Domain\Entity\Comment;
use App\Blog\Domain\Entity\Like;
use App\Blog\Domain\Entity\Post;
use App\Blog\Domain\Entity\Reaction;
use App\Blog\Domain\Entity\Tag;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Exception;
use Faker\Factory;
use Faker\Generator;
use Override;
use Ramsey\Uuid\Uuid;
use Random\RandomException;
use Symfony\Component\String\Slugger\SluggerInterface;
use Throwable;

use function array_slice;
use function in_array;

/**
 * @package App\Blog\Infrastructure\DataFixtures\ORM
 * @author  Rami Aouinti <rami.aouinti@gmail.com>
 */
final class LoadBlogData extends Fixture implements OrderedFixtureInterface
{
    public static array $uuids = [
        '20000000-0000-1000-8000-000000000021',
        '20000000-0000-1000-8000-000000000022',
        '20000000-0000-1000-8000-000000000023',
        '20000000-0000-1000-8000-000000000024',
        '20000000-0000-1000-8000-000000000032',
        '20000000-0000-1000-8000-000000000033',
    ];

    private Generator $faker;

    private array $reactionTypes = ['like', 'love', 'haha', 'wow', 'sad', 'angry'];

    public function __construct(
        private readonly SluggerInterface $slugger
    ) {
        $this->faker = Factory::create();
    }

    /**
     * @throws Throwable
     * @throws RandomException
     */
    #[Override]
    public function load(ObjectManager $manager): void
    {
        $this->loadTags($manager);
        $this->loadPosts($manager);
    }

    #[Override]
    public function getOrder(): int
    {
        return 1;
    }

    /**
     * @throws Throwable
     */
    private function loadTags(ObjectManager $manager): void
    {
        foreach ($this->getTagData() as $name) {
            $tag = new Tag($name);
            $tag->setDescription('description' . $name);
            $manager->persist($tag);
            $this->addReference('tag-' . $name, $tag);
        }

        $manager->flush();
    }

    /**
     * @throws RandomException
     * @throws Exception
     */
    private function loadPosts(ObjectManager $manager): void
    {
        $blogGeneral = new Blog();
        $blogGeneral->setTitle('public');
        $blogGeneral->setBlogSubtitle('All public posts');
        $blogGeneral->setSlug((string)$this->slugger->slug($blogGeneral->getTitle())->lower());
        $blogGeneral->setAuthor(Uuid::uuid1());
        $manager->persist($blogGeneral);

        foreach ($this->getPostData() as [$title, $slug, $summary, $content, $publishedAt, $author, $tags]) {
            $post = new Post();
            $post->setTitle($title);
            $post->setSlug((string)$slug);
            $post->setSummary($summary);
            $post->setContent($content);
            $post->setPublishedAt($publishedAt);
            $post->setAuthor($author);
            $post->addTag(...$tags);
            $post->setBlog($blogGeneral);

            // Adds comments.
            foreach (range(1, random_int(1, 8)) as $i) {
                $comment = new Comment();
                $comment->setAuthor(Uuid::fromString('20000000-0000-1000-8000-00000000000' . random_int(1, 6)));
                $comment->setContent($this->faker->sentence(random_int(5, 15)));
                $comment->setPublishedAt(new DateTimeImmutable('now + ' . $i . ' seconds'));
                $post->addComment($comment);
                $manager->persist($comment);

                // Adds likes to comments.
                foreach (range(1, random_int(1, 4)) as $_) {
                    $like = new Like();
                    $like->setUser(Uuid::fromString('20000000-0000-1000-8000-00000000000' . random_int(1, 6)));
                    $like->setComment($comment);
                    $manager->persist($like);
                }

                // Adds reactions to comments.
                $this->addRandomReactions($manager, null, $comment);
            }

            // Adds likes to posts.
            foreach (range(1, random_int(1, 6)) as $_) {
                $like = new Like();
                $like->setUser(Uuid::fromString('20000000-0000-1000-8000-00000000000' . random_int(1, 6)));
                $like->setPost($post);
                $manager->persist($like);
            }

            // Adds reactions to posts.
            $this->addRandomReactions($manager, $post, null);

            $manager->persist($post);
        }

        $manager->flush();
    }

    /**
     * @return string[]
     */
    private function getTagData(): array
    {
        return [
            'lorem', 'ipsum', 'consectetur', 'adipiscing',
            'incididunt', 'labore', 'voluptate', 'dolore', 'pariatur',
        ];
    }

    /**
     * @throws Exception
     */
    private function getPostData(): array
    {
        $posts = [];

        // Adds additional Faker-generated titles.
        $titles = array_merge($this->getPhrases(), $this->generateFakerTitles(290));

        foreach ($titles as $i => $title) {
            $posts[] = [
                $title,
                $this->slugger->slug($title)->lower(),
                $this->faker->sentence(12),
                $this->faker->paragraphs(5, true),
                (new DateTimeImmutable('now - ' . $i . ' days'))->setTime(random_int(8, 17), random_int(0, 59)),
                Uuid::fromString('20000000-0000-1000-8000-00000000000' . random_int(1, 6)),
                $this->getRandomTags(),
            ];
        }

        return $posts;
    }

    private function getPhrases(): array
    {
        return [
            'Lorem ipsum dolor sit amet consectetur adipiscing elit',
            'Pellentesque vitae velit ex',
            'Mauris dapibus risus quis suscipit vulputate',
            'Eros diam egestas libero eu vulputate risus',
            'In hac habitasse platea dictumst',
            'Morbi tempus commodo mattis',
            'Ut suscipit posuere justo at vulputate',
            'Ut eleifend mauris et risus ultrices egestas',
            'Aliquam sodales odio id eleifend tristique',
            'Urna nisl sollicitudin id varius orci quam id turpis',
        ];
    }

    /**
     * @throws RandomException
     */
    private function generateFakerTitles(int $count): array
    {
        $titles = [];
        foreach (range(1, $count) as $_) {
            $titles[] = $this->faker->sentence(random_int(4, 8));
        }

        return $titles;
    }

    /**
     * @throws Exception
     */
    private function getRandomTags(): array
    {
        $tagNames = $this->getTagData();
        shuffle($tagNames);
        $selectedTags = array_slice($tagNames, 0, random_int(2, 4));

        return array_map(fn ($tagName) => $this->getReference('tag-' . $tagName, Tag::class), $selectedTags);
    }

    /**
     * Adds random reactions for a post or a comment.
     *
     * @throws RandomException
     */
    private function addRandomReactions(ObjectManager $manager, ?Post $post = null, ?Comment $comment = null): void
    {
        $usedUsers = []; // Prevent duplicate user-to-post or user-to-comment combinations.
        $maxReactions = random_int(1, 6);

        foreach (range(1, $maxReactions) as $_) {
            // Generate a unique user for this post or comment.
            do {
                $userUuid = '20000000-0000-1000-8000-00000000000' . random_int(1, 6);
            } while (in_array($userUuid, $usedUsers, true));

            $usedUsers[] = $userUuid;

            $reaction = new Reaction();
            $reaction->setType($this->reactionTypes[array_rand($this->reactionTypes)]);
            $reaction->setUser(Uuid::fromString($userUuid));

            if ($post) {
                $reaction->setPost($post);
            }
            if ($comment) {
                $reaction->setComment($comment);
            }

            $manager->persist($reaction);
        }
    }
}
