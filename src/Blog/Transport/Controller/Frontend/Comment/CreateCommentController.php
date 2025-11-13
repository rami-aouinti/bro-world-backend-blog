<?php

declare(strict_types=1);

namespace App\Blog\Transport\Controller\Frontend\Comment;

use App\Blog\Domain\Entity\Comment;
use App\Blog\Domain\Entity\Post;
use App\Blog\Domain\Message\CreateCommentMessenger;
use Bro\WorldCoreBundle\Infrastructure\ValueObject\SymfonyUser;
use JsonException;
use OpenApi\Attributes as OA;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @package App\Blog\Transport\Controller\Frontend\Comment
 * @author  Rami Aouinti <rami.aouinti@gmail.com>
 */
#[AsController]
#[OA\Tag(name: 'Blog')]
readonly class CreateCommentController
{
    public function __construct(
        private SerializerInterface $serializer,
        private MessageBusInterface $bus
    ) {
    }

    /**
     * Get current user blog data, accessible only for 'IS_AUTHENTICATED_FULLY' users.
     *
     * @throws ExceptionInterface
     * @throws JsonException
     * @throws \Symfony\Component\Messenger\Exception\ExceptionInterface
     */
    #[Route(path: '/v1/platform/post/{post}/comment', name: 'comment_create', methods: [Request::METHOD_POST])]
    public function __invoke(SymfonyUser $symfonyUser, Request $request, Post $post): JsonResponse
    {
        $data = $request->request->all();
        $comment = new Comment();
        $comment->setPost($post);
        $comment->setAuthor(Uuid::fromString($symfonyUser->getId()));
        $comment->setContent($data['content']);
        $this->bus->dispatch(
            new CreateCommentMessenger(
                $request->headers->get('Authorization'),
                $comment,
                $post->getId(),
                $symfonyUser->getId(),
                $post->getAuthor()->toString(),
                $data
            )
        );

        $json = $this->serializer->serialize(
            $comment,
            'json',
            [
                'groups' => 'Comment',
            ]
        );

        return JsonResponse::fromJsonString($json);
    }
}
