<?php

declare(strict_types=1);

namespace App\Blog\Transport\Controller\Api;

use App\Blog\Application\Resource\LikeResource;
use App\General\Transport\Rest\Controller;
use App\General\Transport\Rest\ResponseHandler;
use App\General\Transport\Rest\Traits\Actions;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * @package App\Blog\Transport\Controller\Api
 * @author  Rami Aouinti <rami.aouinti@tkdeutschland.de>
 *
 * @method LikeResource getResource()
 * @method ResponseHandler getResponseHandler()
 */
#[AsController]
#[Route(
    path: '/v1/like',
)]
#[OA\Tag(name: 'Like Management')]
#[IsGranted(AuthenticatedVoter::PUBLIC_ACCESS)]
class LikeController extends Controller
{
    use Actions\Admin\CountAction;
    use Actions\Admin\FindAction;
    use Actions\Admin\FindOneAction;
    use Actions\Admin\IdsAction;

    public function __construct(
        LikeResource $resource,
    ) {
        parent::__construct($resource);
    }
}
