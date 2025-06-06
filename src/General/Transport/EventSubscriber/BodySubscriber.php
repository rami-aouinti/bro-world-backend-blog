<?php

declare(strict_types=1);

namespace App\General\Transport\EventSubscriber;

use App\General\Domain\Utils\JSON;
use JsonException;
use Override;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;

use function in_array;
use function is_array;

/**
 * @package App\General
 */
class BodySubscriber implements EventSubscriberInterface
{
    /**
     * {@inheritdoc}
     */
    #[Override]
    public static function getSubscribedEvents(): array
    {
        return [
            RequestEvent::class => [
                'onKernelRequest',
                10,
            ],
        ];
    }

    /**
     * Implementation of BodySubscriber event. Purpose of this is to convert JSON request data to proper request
     * parameters.
     *
     * @throws JsonException
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        // Get current request
        $request = $event->getRequest();

        // If request has some content and is JSON type convert it to request parameters
        if ($request->getContent() !== '' && $this->isJsonRequest($request)) {
            $this->transformJsonBody($request);
        }
    }

    /**
     * Method to determine if current Request is JSON type or not.
     */
    private function isJsonRequest(Request $request): bool
    {
        return in_array($request->getContentTypeFormat(), [null, 'json', 'txt'], true);
    }

    /**
     * Method to transform JSON type request to proper request parameters.
     *
     * @throws JsonException
     */
    private function transformJsonBody(Request $request): void
    {
        $data = JSON::decode((string)$request->getContent(), true);

        if (is_array($data)) {
            $request->request->replace($data);
        }
    }
}
