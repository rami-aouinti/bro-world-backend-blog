<?php

declare(strict_types=1);

namespace App\Blog\Application\Service;

use App\Blog\Application\ApiProxy\UserProxy;
use App\Blog\Domain\Entity\Blog;
use App\Blog\Domain\Entity\Post;
use App\Blog\Domain\Entity\Tag;
use App\Blog\Domain\Message\CreatePostMessenger;
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
use Random\RandomException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Throwable;

use function strlen;

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
        private UserProxy $userProxy,
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
            new CreatePostMessenger($post, $medias)
        );

        return array_merge(
            $post->toArray(),
            [
                'medias' => $this->getMedia($medias),
                'user' => $user
            ]
        );
    }

    /**
     * @param Post       $post
     * @param array|null $mediaIds
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function savePost(Post $post, ?array $mediaIds): void
    {
        if (!empty($mediaIds)) {
            $post->setMedias($mediaIds);
        }
        $this->postRepository->save($post);
    }

    /**
     * @param array|null $mediaIds
     *
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     * @return array
     */
    public function getMedia(?array $mediaIds): array
    {
        $medias  = [];
        foreach ($mediaIds as $id) {
            $medias[] = $this->userProxy->getMedia($id);
        }
        return $medias;
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
            ->setTitle($data['title'] ?? '')
            ->setSlug($data['title'] ?? $this->generateRandomString(20));

        $post->setUrl($data['url'] ?? '');
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

    /**
     * @throws RandomException
     */
    private function generateRandomString(int $length): string {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';

        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[random_int(0, $charactersLength - 1)];
        }

        return $randomString;
    }
}
