<?php

declare(strict_types=1);

namespace App\General\Transport\Rest\Traits\Actions\Root;

use App\General\Transport\Rest\Traits\Methods\CountMethod;
use OpenApi\Attributes as OA;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\Property;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Throwable;

/**
 * Trait to add 'countAction' for REST controllers for 'ROLE_ROOT' users.
 *
 * @see \App\General\Transport\Rest\Traits\Methods\CountMethod for detailed documents.
 *
 * @package App\General
 */
trait CountAction
{
    use CountMethod;

    /**
     * Count entities, accessible only for 'ROLE_ROOT' users.
     *
     * @throws Throwable
     */
    #[Route(
        path: '/count',
        methods: [Request::METHOD_GET],
    )]

    #[OA\Response(
        response: 200,
        description: 'success',
        content: new JsonContent(
            properties: [
                new Property(property: 'count', type: 'integer'),
            ],
            type: 'object',
            example: [
                'count' => 1,
            ],
        ),
    )]
    #[OA\Response(
        response: 403,
        description: 'Access denied',
        content: new JsonContent(
            properties: [
                new Property(property: 'code', description: 'Error code', type: 'integer'),
                new Property(property: 'message', description: 'Error description', type: 'string'),
            ],
            type: 'object',
            example: [
                'code' => 403,
                'message' => 'Access denied',
            ],
        ),
    )]
    public function countAction(Request $request): Response
    {
        return $this->countMethod($request);
    }
}
