<?php

declare(strict_types=1);

namespace App\Blog\Transport\Controller\Frontend;

use App\Blog\Application\Service\NotificationService;
use App\Blog\Domain\Entity\Comment;
use App\Blog\Domain\Repository\Interfaces\CommentRepositoryInterface;
use App\General\Domain\Utils\JSON;
use App\General\Infrastructure\ValueObject\SymfonyUser;
use Doctrine\ORM\Exception\NotSupported;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\TransactionRequiredException;
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
readonly class CommentCommentController
{
    public function __construct(
        private SerializerInterface $serializer,
        private CommentRepositoryInterface $commentRepository,
        private NotificationService $notificationService
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
     * @throws JsonException
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws TransactionRequiredException
     * @throws TransportExceptionInterface
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @return JsonResponse
     */
    #[Route(path: '/v1/platform/comment/{comment}/comment', name: 'comment_comment', methods: [Request::METHOD_POST])]
    public function __invoke(SymfonyUser $symfonyUser, Request $request, Comment $comment): JsonResponse
    {
        $data = $request->request->all();
        $newComment = new Comment();
        $newComment->setAuthor(Uuid::fromString($symfonyUser->getUserIdentifier()));
        $newComment->setContent($data['content']);
        $newComment->setParent($comment);

        $this->notificationService->createNotification(
            $request->headers->get('Authorization'),
            'PUSH',
            $symfonyUser->getUserIdentifier(),
            $comment->getPost()?->getId(),
            $comment->getId(),
            $comment->getPost()?->getBlog()?->getId()
        );

        $this->commentRepository->save($newComment);

        $output = JSON::decode(
            $this->serializer->serialize(
                $newComment,
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
