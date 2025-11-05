<?php

declare(strict_types=1);

namespace App\Blog\Transport\Controller\Api;

use App\Blog\Application\DTO\Post\PostCreate;
use App\Blog\Application\DTO\Post\PostPatch;
use App\Blog\Application\DTO\Post\PostUpdate;
use App\Blog\Application\Resource\PostResource;
use Bro\WorldCoreBundle\Transport\Rest\Controller;
use Bro\WorldCoreBundle\Transport\Rest\ResponseHandler;
use Bro\WorldCoreBundle\Transport\Rest\Traits\Actions;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * @package App\Blog\Transport\Controller\Api
 * @author  Rami Aouinti <rami.aouinti@tkdeutschland.de>
 *
 * @method PostResource getResource()
 * @method ResponseHandler getResponseHandler()
 */
#[AsController]
#[Route(
    path: '/v1/post',
)]
#[OA\Tag(name: 'Post Management')]
#[IsGranted(AuthenticatedVoter::PUBLIC_ACCESS)]
class PostController extends Controller
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
        Controller::METHOD_CREATE => PostCreate::class,
        Controller::METHOD_UPDATE => PostUpdate::class,
        Controller::METHOD_PATCH => PostPatch::class,
    ];

    public function __construct(
        PostResource $resource,
    ) {
        parent::__construct($resource);
    }
}
