<?php

declare(strict_types=1);

namespace App\Blog\Transport\Controller\Api;

use App\Blog\Application\DTO\Comment\CommentCreate;
use App\Blog\Application\DTO\Comment\CommentPatch;
use App\Blog\Application\DTO\Comment\CommentUpdate;
use App\Blog\Application\Resource\CommentResource;
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
 *
 * @method CommentResource getResource()
 * @method ResponseHandler getResponseHandler()
 */
#[AsController]
#[Route(
    path: '/v1/comment',
)]
#[OA\Tag(name: 'Comment Management')]
#[IsGranted(AuthenticatedVoter::PUBLIC_ACCESS)]
class CommentController extends Controller
{
    use Actions\Admin\CountAction;
    use Actions\Admin\FindAction;
    use Actions\Admin\FindOneAction;
    use Actions\Admin\IdsAction;
    use Actions\Root\CreateAction;
    use Actions\Root\DeleteAction;
    use Actions\Root\PatchAction;
    use Actions\Root\UpdateAction;

    /**
     * @var array<string, string>
     */
    protected static array $dtoClasses = [
        Controller::METHOD_CREATE => CommentCreate::class,
        Controller::METHOD_UPDATE => CommentUpdate::class,
        Controller::METHOD_PATCH => CommentPatch::class,
    ];

    public function __construct(
        CommentResource $resource,
    ) {
        parent::__construct($resource);
    }
}
