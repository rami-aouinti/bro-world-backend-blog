<?php

declare(strict_types=1);

namespace App\Blog\Transport\Controller\Frontend;

use App\Blog\Domain\Entity\Comment;
use App\Blog\Infrastructure\Repository\CommentRepository;
use App\General\Domain\Utils\JSON;
use App\General\Infrastructure\ValueObject\SymfonyUser;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use JsonException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * @package App\Blog
 */
#[AsController]
#[OA\Tag(name: 'Blog')]
readonly class DeleteCommentController
{
    public function __construct(
        private SerializerInterface $serializer,
        private CommentRepository $commentRepository
    ) {
    }

    /**
     * Get current user blog data, accessible only for 'IS_AUTHENTICATED_FULLY' users.
     *
     * @param SymfonyUser $symfonyUser
     * @param Request     $request
     * @param Comment     $comment
     *
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws JsonException
     * @throws ExceptionInterface
     * @return JsonResponse
     */
    #[Route(path: '/v1/platform/comment/{comment}', name: 'delete_comment', methods: [Request::METHOD_DELETE])]
    public function __invoke(SymfonyUser $symfonyUser, Request $request, Comment $comment): JsonResponse
    {
        $this->commentRepository->remove($comment);
        $output = JSON::decode(
            $this->serializer->serialize(
                'deleted',
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
