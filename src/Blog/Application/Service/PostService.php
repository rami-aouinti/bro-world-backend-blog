<?php

declare(strict_types=1);

namespace App\Blog\Application\Service;

use App\Blog\Application\ApiProxy\UserProxy;
use App\Blog\Domain\Entity\Blog;
use App\Blog\Domain\Entity\Media;
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
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Throwable;
use Traversable;

use function array_key_exists;
use function iterator_to_array;
use function sprintf;
use function strlen;
use function trim;

/**
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
        private MessageBusInterface $bus,
        private string $postDirectory,
        private SluggerInterface $slugger,
    ) {
    }

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
        $post = $this->generatePostAttributes(
            $this->blogService->getBlog($request, $user),
            $user,
            $request
        );
        $post = $request->files->all() ? $this->uploadFiles($request, $post) : $post;

        $this->bus->dispatch(
            new CreatePostMessenger($post, null)
        );

        return array_merge(
            $post->toArray(),
            [
                'medias' => $post->getMediaEntities()->map(
                    fn (Media $media) => $media->toArray()
                )->toArray(),
                'user' => $this->userProxy->searchUser($user->getUserIdentifier()),
            ]
        );
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function savePost(Post $post, ?array $mediaIds): Post
    {
        if (!empty($mediaIds)) {
            //$post->setMedias($mediaIds);
        }
        $this->postRepository->save($post);

        return $post;
    }

    /**
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function getMedia(?array $mediaIds): array
    {
        $medias = [];
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

        $title = $data['title'] ?? '';
        $slug = $data['slug'] ?? null;

        if ($slug === null && !array_key_exists('title', $data)) {
            $slug = $this->generateRandomString(20);
        }

        $post = (new Post())
            ->setAuthor(Uuid::fromString($user->getUserIdentifier()))
            ->setTitle($title)
            ->setSlug($slug);

        $post->setUrl($data['url'] ?? '');
        $post->setContent($data['content'] ?? '');
        $post->setSummary($data['summary'] ?? '');

        foreach ($data['tags'] ?? [] as $tagName) {
            $tagName = trim((string)$tagName);
            if ($tagName === '') {
                continue;
            }

            $tag = $this->tagRepository->findOneBy([
                'name' => $tagName,
            ]);

            if ($tag === null) {
                $tag = new Tag($tagName);
                $tag->setDescription($this->buildTagDescription($tagName));
                $this->entityManager->persist($tag);
            } elseif (trim($tag->getDescription()) === '') {
                $tag->setDescription($this->buildTagDescription($tagName));
            }

            $post->addTag($tag);
        }

        return $post;
    }

    /**
     * @return JsonResponse|array
     */
    public function uploadFiles(Request $request, Post $post): JsonResponse|Post
    {
        $files = $request->files->get('files');

        if ($files instanceof Traversable) {
            $files = iterator_to_array($files, false);
        }

        if (!is_array($files) || $files === []) {
            return new JsonResponse([
                'error' => 'No files uploaded.',
            ], 400);
        }

        foreach ($files as $file) {
            $type = $file->getMimeType();
            $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $safeFilename = $this->slugger->slug($originalFilename);
            $newFilename = $safeFilename . '-' . uniqid('', true) . '.' . $file->guessExtension();

            try {
                $file->move(
                    $this->postDirectory,
                    $newFilename
                );
            } catch (FileException $e) {
                return new JsonResponse([
                    'error' => $e->getMessage(),
                ], 500);
            }
            $baseUrl = $request->getSchemeAndHttpHost();
            $relativePath = '/uploads/post/' . $newFilename;
            $media = new Media();
            $media->setUrl($baseUrl . $relativePath);
            $media->setType($type);
            $media->setPost($post);
            $post->addMedia($media);
        }

        return $post;
    }

    private function buildTagDescription(string $tagName): string
    {
        return sprintf('Posts tagged with %s', $tagName);
    }

    /**
     * @throws RandomException
     */
    private function generateRandomString(int $length): string
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';

        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[random_int(0, $charactersLength - 1)];
        }

        return $randomString;
    }
}
