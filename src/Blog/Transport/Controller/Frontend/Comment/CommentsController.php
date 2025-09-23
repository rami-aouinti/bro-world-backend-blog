<?php

declare(strict_types=1);

namespace App\Blog\Transport\Controller\Frontend\Comment;

use App\Blog\Application\ApiProxy\UserProxy;
use App\Blog\Domain\Entity\Comment;
use App\Blog\Domain\Entity\Post;
use App\General\Domain\Utils\JSON;
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
        $commentData = [];
        $rootComments = array_filter(
            $post->getComments()->toArray(),
            static fn($comment) => $comment->getParent() === null
        );

        $userIds = [];
        foreach ($rootComments as $comment) {
            $this->collectCommentUserIds($comment, $userIds);
        }

        $usersById = [];
        if ($userIds !== []) {
            $users = $this->userProxy->batchSearchUsers(array_values(array_unique($userIds)));
            $usersById = array_column($users, null, 'id');
        }

        foreach ($rootComments as $comment) {
            $commentData[] = $this->formatCommentRecursively($comment, $usersById);
        }
        $output = JSON::decode(
            $this->serializer->serialize(
                $commentData,
                'json',
                [
                    'groups' => 'Comment',
                ]
            ),
            true,
        );
        return new JsonResponse($output);
    }

    /**
     * @param       $comment
     * @param array $usersById
     *
     * @return array
     */
    private function formatCommentRecursively(Comment $comment, array $usersById): array
    {
        $authorId = $comment->getAuthor()->toString();

        $formatted = [
            'id' => $comment->getId(),
            'content' => $comment->getContent(),
            'likes' => [],
            'publishedAt' => $comment->getPublishedAt()?->format(DATE_ATOM),
            'user' => $usersById[$authorId] ?? null,
            'children' => [],
        ];

        foreach ($comment->getLikes() as $like) {
            $likeUserId = $like->getUser()->toString();
            $formatted['likes'][] = [
                'id' => $like->getId(),
                'user' => $usersById[$likeUserId] ?? null,
            ];
        }

        foreach ($comment->getChildren() as $child) {
            $formatted['children'][] = $this->formatCommentRecursively($child, $usersById);
        }

        return $formatted;
    }

    private function collectCommentUserIds(Comment $comment, array &$userIds): void
    {
        $userIds[] = $comment->getAuthor()->toString();

        foreach ($comment->getLikes() as $like) {
            $userIds[] = $like->getUser()->toString();
        }

        foreach ($comment->getChildren() as $child) {
            if ($child instanceof Comment) {
                $this->collectCommentUserIds($child, $userIds);
            }
        }
    }
}
