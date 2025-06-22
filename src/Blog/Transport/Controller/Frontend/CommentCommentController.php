<?php

declare(strict_types=1);

namespace App\Blog\Transport\Controller\Frontend;

use App\Blog\Domain\Entity\Comment;
use App\Blog\Domain\Repository\Interfaces\CommentRepositoryInterface;
use App\General\Domain\Utils\JSON;
use App\General\Infrastructure\ValueObject\SymfonyUser;
use Doctrine\ORM\Exception\NotSupported;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\TransactionRequiredException;
use OpenApi\Attributes as OA;
use Psr\Cache\InvalidArgumentException;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
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
        private CommentRepositoryInterface $commentRepository
    ) {
    }

    /**
     * Get current user blog data, accessible only for 'IS_AUTHENTICATED_FULLY' users.
     *
     * @param SymfonyUser $symfonyUser
     * @param Request     $request
     * @param Comment     $comment
     *
     * @throws Throwable
     * @throws NotSupported
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws TransactionRequiredException
     * @throws InvalidArgumentException
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
