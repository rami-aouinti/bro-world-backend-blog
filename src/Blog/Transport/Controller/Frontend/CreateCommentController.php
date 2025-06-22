<?php

declare(strict_types=1);

namespace App\Blog\Transport\Controller\Frontend;

use App\Blog\Domain\Entity\Comment;
use App\Blog\Domain\Entity\Post;
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
readonly class CreateCommentController
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
     * @param Post        $post
     *
     * @throws Throwable
     * @throws NotSupported
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws TransactionRequiredException
     * @throws InvalidArgumentException
     * @return JsonResponse
     */
    #[Route(path: '/v1/platform/post/{post}/comment', name: 'comment_create', methods: [Request::METHOD_POST])]
    public function __invoke(SymfonyUser $symfonyUser, Request $request, Post $post): JsonResponse
    {
        $data = $request->request->all();
        $comment = new Comment();
        $comment->setAuthor(Uuid::fromString($symfonyUser->getUserIdentifier()));
        $comment->setContent($data['content']);
        $comment->setPost($post);

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
