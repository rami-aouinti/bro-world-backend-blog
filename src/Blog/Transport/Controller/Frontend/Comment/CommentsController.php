<?php

declare(strict_types=1);

namespace App\Blog\Transport\Controller\Frontend\Comment;

use App\Blog\Application\ApiProxy\UserProxy;
use App\Blog\Domain\Entity\Post;
use JsonException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
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
 * @package App\Blog
 */
#[AsController]
#[OA\Tag(name: 'Blog')]
readonly class CommentsController
{
    public function __construct(
        private SerializerInterface $serializer,
        private UserProxy $userProxy
    ) {
    }

    /**
     * Get current user blog data, accessible only for 'IS_AUTHENTICATED_FULLY' users
     *
     * @param Post $post
     *
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ExceptionInterface
     * @throws JsonException
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     * @return JsonResponse
     */
    #[Route(path: '/v1/platform/post/{post}/comments', name: 'platform_post_comments', methods: [Request::METHOD_GET])]
    public function __invoke(Post $post): JsonResponse
    {
        $users = $this->userProxy->getUsers();

        $usersById = [];
        foreach ($users as $user) {
            $usersById[$user['id']] = $user;
        }
        $commentData = [];
        $rootComments = array_filter(
            $post->getComments()->toArray(),
            static fn($comment) => $comment->getParent() === null
        );

        foreach ($rootComments as $comment) {
            $commentData[] = $this->formatCommentRecursively($comment, $usersById);
        }
        $json = $this->serializer->serialize(
            $commentData,
            'json',
            [
                'groups' => 'Comment',
            ]
        );

        return JsonResponse::fromJsonString($json);
    }

    /**
     * @param       $comment
     * @param array $usersById
     *
     * @return array
     */
    private function formatCommentRecursively($comment, array $usersById): array
    {
        $authorId = $comment->getAuthor()->toString();

        $formatted = [
            'id' => $comment->getId(),
            'content' => $comment->getContent(),
            'likes' => $comment->getLikes(),
            'publishedAt' => $comment->getPublishedAt()?->format(DATE_ATOM),
            'user' => $usersById[$authorId] ?? null,
            'children' => [],
        ];

        foreach ($comment->getChildren() as $child) {
            $formatted['children'][] = $this->formatCommentRecursively($child, $usersById);
        }

        return $formatted;
    }
}
