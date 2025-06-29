<?php

declare(strict_types=1);

namespace App\Blog\Transport\Controller\Api;

use App\Blog\Application\DTO\Blog\BlogCreate;
use App\Blog\Application\DTO\Blog\BlogPatch;
use App\Blog\Application\DTO\Blog\BlogUpdate;
use App\Blog\Application\Resource\BlogResource;
use App\General\Infrastructure\ValueObject\SymfonyUser;
use App\General\Transport\Rest\Controller;
use App\General\Transport\Rest\ResponseHandler;
use App\General\Transport\Rest\Traits\Actions;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * @package App\Blog
 *
 * @method BlogResource getResource()
 * @method ResponseHandler getResponseHandler()
 */
#[AsController]
#[Route(
    path: '/v1/blog',
)]
#[OA\Tag(name: 'Blog Management')]
#[IsGranted(AuthenticatedVoter::PUBLIC_ACCESS)]
class BlogController extends Controller
{
    use Actions\Admin\CountAction;
    use Actions\Admin\FindAction;
    use Actions\Admin\FindOneAction;
    use Actions\Admin\IdsAction;
    use Actions\Root\CreateAction;
    use Actions\Root\PatchAction;
    use Actions\Root\UpdateAction;

    /**
     * @var array<string, string>
     */
    protected static array $dtoClasses = [
        Controller::METHOD_CREATE => BlogCreate::class,
        Controller::METHOD_UPDATE => BlogUpdate::class,
        Controller::METHOD_PATCH => BlogPatch::class,
    ];

    public function __construct(
        BlogResource $resource,
    ) {
        parent::__construct($resource);
    }
}
