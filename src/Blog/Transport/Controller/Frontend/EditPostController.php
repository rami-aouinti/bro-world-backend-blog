<?php

declare(strict_types=1);

namespace App\Blog\Transport\Controller\Frontend;

use App\Blog\Domain\Entity\Post;
use App\Blog\Domain\Repository\Interfaces\PostRepositoryInterface;
use App\General\Domain\Utils\JSON;
use App\General\Infrastructure\ValueObject\SymfonyUser;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use JsonException;
use OpenApi\Attributes as OA;
use Psr\Cache\InvalidArgumentException;
use Random\RandomException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\Cache\CacheInterface;

use function strlen;

/**
 * @package App\Blog
 */
#[AsController]
#[OA\Tag(name: 'Blog')]
readonly class EditPostController
{
    public function __construct(
        private SerializerInterface $serializer,
        private PostRepositoryInterface $postRepository,
        private CacheInterface $cache
    ) {
    }

    /**
     * Get current user blog data, accessible only for 'IS_AUTHENTICATED_FULLY' users.
     *
     * @param SymfonyUser $symfonyUser
     * @param Request     $request
     * @param Post        $post
     *
     * @throws ExceptionInterface
     * @throws InvalidArgumentException
     * @throws JsonException
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws RandomException
     * @return JsonResponse
     */
    #[Route(path: '/v1/platform/post/{post}', name: 'edit_post', methods: [Request::METHOD_PUT])]
    public function __invoke(SymfonyUser $symfonyUser, Request $request, Post $post): JsonResponse
    {
        $this->cache->delete('post_public');
        $data = $request->request->all();
        if(isset($data['title'])) {
            $post->setTitle($data['title']);
            $post->setSlug($data['title'] ?? $this->generateRandomString(20));
        }
        if(isset($data['content'])) {
            $post->setContent($data['content']);
        }
        if(isset($data['url'])) {
            $post->setUrl($data['url']);
        }

        $this->postRepository->save($post);
        $output = JSON::decode(
            $this->serializer->serialize(
                $post,
                'json',
                [
                    'groups' => 'Post',
                ]
            ),
            true,
        );
        return new JsonResponse($output);
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
