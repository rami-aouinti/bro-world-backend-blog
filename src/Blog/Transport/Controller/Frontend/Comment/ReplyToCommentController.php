<?php

declare(strict_types=1);

namespace App\Blog\Transport\Controller\Frontend\Comment;

use App\Blog\Application\Service\Interfaces\CommentNotificationMailerInterface;
use App\Blog\Application\Service\Notification\NotificationService;
use App\Blog\Domain\Entity\Comment;
use App\Blog\Domain\Repository\Interfaces\CommentRepositoryInterface;
use Bro\WorldCoreBundle\Domain\Utils\JSON;
use Bro\WorldCoreBundle\Infrastructure\ValueObject\SymfonyUser;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\TransactionRequiredException;
use JsonException;
use OpenApi\Attributes as OA;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

/**
 * Handles the creation of replies to existing comments.
 */
#[AsController]
#[OA\Tag(name: 'Blog')]
readonly class ReplyToCommentController
{
    public function __construct(
        private SerializerInterface $serializer,
        private CommentRepositoryInterface $commentRepository,
        private NotificationService $notificationService,
        private CommentNotificationMailerInterface $commentNotificationMailer
    ) {
    }

    /**
     * Get current user blog data, accessible only for 'IS_AUTHENTICATED_FULLY' users.
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
     */
    #[Route(path: '/v1/platform/comment/{comment}/reply', name: 'blog_comment_reply', methods: [Request::METHOD_POST])]
    public function __invoke(SymfonyUser $symfonyUser, Request $request, Comment $comment): JsonResponse
    {
        $data = $request->request->all();
        $content = trim((string) ($data['content'] ?? ''));

        if ($content === '') {
            return new JsonResponse(
                ['message' => 'Comment content cannot be empty.'],
                Response::HTTP_BAD_REQUEST
            );
        }

        $newComment = new Comment();
        $newComment->setAuthor(Uuid::fromString($symfonyUser->getId()));
        $newComment->setContent($content);
        $newComment->setParent($comment);

        $this->notificationService->executeCreateNotificationCommand(
            $request->headers->get('Authorization'),
            'PUSH',
            $symfonyUser->getId(),
            $comment->getAuthor()->toString(),
            $comment->getPost()?->getId(),
            'commented on your comment.'
        );

        $this->commentRepository->save($newComment);

        $this->commentNotificationMailer->sendCommentReplyNotificationEmail(
            $comment->getAuthor()->toString(),
            $symfonyUser->getId(),
            $newComment
        );

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
