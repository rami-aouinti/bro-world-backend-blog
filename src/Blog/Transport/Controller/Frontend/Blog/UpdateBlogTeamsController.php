<?php

declare(strict_types=1);

namespace App\Blog\Transport\Controller\Frontend\Blog;

use App\Blog\Domain\Entity\Blog;
use App\Blog\Domain\Repository\Interfaces\BlogRepositoryInterface;
use Bro\WorldCoreBundle\Infrastructure\ValueObject\SymfonyUser;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use JsonException;
use OpenApi\Attributes as OA;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\SerializerInterface;

use function is_array;

/**
 * @package App\Blog\Transport\Controller\Frontend\Blog
 * @author  Rami Aouinti <rami.aouinti@tkdeutschland.de>
 */
#[AsController]
#[OA\Tag(name: 'Blog')]
readonly class UpdateBlogTeamsController
{
    public function __construct(
        private SerializerInterface $serializer,
        private BlogRepositoryInterface $blogRepository
    ) {
    }

    /**
     * Get current user blog data, accessible only for 'IS_AUTHENTICATED_FULLY' users
     *
     * @throws ExceptionInterface
     * @throws JsonException
     * @throws ORMException
     * @throws OptimisticLockException
     */
    #[Route(path: '/v1/blog/{blog}/teams', name: 'update_blog_teams', methods: [Request::METHOD_PATCH])]
    public function __invoke(SymfonyUser $symfonyUser, Blog $blog, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['teams']) || !is_array($data['teams'])) {
            return new JsonResponse([
                'error' => 'Missing or invalid "teams" array',
            ], Response::HTTP_BAD_REQUEST);
        }

        $teams = array_filter($data['teams'], static fn ($uuid) => Uuid::isValid($uuid));
        $blog->setTeams($teams);

        $this->blogRepository->save($blog);

        $json = $this->serializer->serialize(
            $blog,
            'json',
            [
                'groups' => 'BlogProfile',
            ]
        );

        return JsonResponse::fromJsonString($json);
    }
}
