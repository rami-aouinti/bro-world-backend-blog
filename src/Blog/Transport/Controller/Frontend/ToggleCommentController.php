<?php

declare(strict_types=1);

namespace App\Blog\Transport\Controller\Frontend;

use App\Blog\Application\Service\NotificationService;
use App\Blog\Domain\Entity\Comment;
use App\Blog\Domain\Entity\Like;
use App\Blog\Domain\Repository\Interfaces\LikeRepositoryInterface;
use App\General\Domain\Utils\JSON;
use App\General\Infrastructure\ValueObject\SymfonyUser;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use JsonException;
use OpenApi\Attributes as OA;
use Psr\Cache\InvalidArgumentException;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Throwable;

/**
 * @package App\Blog
 */
#[AsController]
#[OA\Tag(name: 'Blog')]
readonly class ToggleCommentController
{
    public function __construct(
        private SerializerInterface $serializer,
        private LikeRepositoryInterface $likeRepository,
        private NotificationService $notificationService,
        private CacheInterface $cache
    ) {
    }

    /**
     * Get current user blog data, accessible only for 'IS_AUTHENTICATED_FULLY' users.
     *
     * @param SymfonyUser $symfonyUser
     * @param Request     $request
     * @param Comment     $comment
     *
     * @throws ExceptionInterface
     * @throws InvalidArgumentException
     * @throws JsonException
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     * @return JsonResponse
     */
    #[Route(path: '/v1/platform/comment/{comment}/like', name: 'like_comment', methods: [Request::METHOD_POST])]
    public function __invoke(SymfonyUser $symfonyUser, Request $request, Comment $comment): JsonResponse
    {
        for($i = 1; $i < 3; $i++) {
            $cacheKey = 'post_public_' . $i . '_' . 10;
            $this->cache->delete($cacheKey);
        }
        $like = new Like();
        $like->setComment($comment);
        $like->setUser(Uuid::fromString($symfonyUser->getUserIdentifier()));
        $scopeTarget = $comment->getAuthor()->toString();
        $data = [
            'channel' => 'PUSH',
            'scope' => 'INDIVIDUAL',
            'topic' => '/notifications/' . $comment->getAuthor()->toString(),
            'pushTitle' => $symfonyUser->getFullName() . ' liked your comment.',
            'pushSubtitle' => 'Someone commented on your post.',
            'pushContent' => 'https://bro-world-space.com/post/' . $comment->getPost()?->getSlug(),
            'scopeTarget' => [$scopeTarget]
        ];

        $this->notificationService->createPush($request, $data);
        $this->likeRepository->save($like);
        $result = [];
        $result['id'] = $like->getId();
        $result['user'] = $symfonyUser;
        $output = JSON::decode(
            $this->serializer->serialize(
                $result,
                'json',
                [
                    'groups' => 'Like',
                ]
            ),
            true,
        );
        return new JsonResponse($output);
    }
}
