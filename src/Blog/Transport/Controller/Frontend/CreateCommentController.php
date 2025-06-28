<?php

declare(strict_types=1);

namespace App\Blog\Transport\Controller\Frontend;

use App\Blog\Application\Service\Interfaces\CommentNotificationMailerInterface;
use App\Blog\Application\Service\NotificationService;
use App\Blog\Domain\Entity\Comment;
use App\Blog\Domain\Entity\Post;
use App\Blog\Domain\Repository\Interfaces\CommentRepositoryInterface;
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

/**
 * @package App\Blog
 */
#[AsController]
#[OA\Tag(name: 'Blog')]
readonly class CreateCommentController
{
    public function __construct(
        private SerializerInterface $serializer,
        private CommentRepositoryInterface $commentRepository,
        private CommentNotificationMailerInterface $commentNotificationMailer,
        private NotificationService $notificationService,
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
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     * @return JsonResponse
     */
    #[Route(path: '/v1/platform/post/{post}/comment', name: 'comment_create', methods: [Request::METHOD_POST])]
    public function __invoke(SymfonyUser $symfonyUser, Request $request, Post $post): JsonResponse
    {
        for($i = 1; $i < 3; $i++) {
            $cacheKey = 'post_public_' . $i . '_' . 10;
            $this->cache->delete($cacheKey);
        }
        $data = $request->request->all();
        $comment = new Comment();
        $comment->setAuthor(Uuid::fromString($symfonyUser->getUserIdentifier()));
        $comment->setContent($data['content']);
        $comment->setPost($post);

        $this->commentNotificationMailer->sendCommentNotificationEmail(
            $post->getAuthor()->toString(),
            $symfonyUser->getUserIdentifier(),
            $post->getSlug()
        );
        $authorId = $post->getAuthor()->toString();
        $data = [
            'channel' => 'PUSH',
            'scope' => 'INDIVIDUAL',
            'topic' => '/notifications/' . $authorId,
            'pushTitle' => $symfonyUser->getFullName() . ' commented on your post.',
            'pushSubtitle' => 'Someone commented on your post.',
            'pushContent' => 'https://bro-world-space.com/post/' . $post->getSlug(),
            'scopeTarget' => [$authorId]
        ];

        $this->notificationService->createPush($request, $data);
        $this->commentRepository->save($comment);

        $output = JSON::decode(
            $this->serializer->serialize(
                $comment,
                'json',
                [
                    'groups' => 'Comment',
                ]
            ),
            true,
        );
        return new JsonResponse($output);
    }
}
