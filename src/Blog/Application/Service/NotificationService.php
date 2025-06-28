<?php

declare(strict_types=1);

namespace App\Blog\Application\Service;

use App\General\Infrastructure\Service\ApiProxyService;
use App\General\Infrastructure\ValueObject\SymfonyUser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Throwable;

/**
 * Class MediaService
 *
 * @package App\Blog\Application\Service
 * @author  Rami Aouinti <rami.aouinti@tkdeutschland.de>
 */
readonly class NotificationService
{
    private const string PATH = 'notification';
    private const string CREATE_NOTIFICATION_PATH = 'api/v1/platform/notifications';

    public function __construct(
        private ApiProxyService $proxyService
    ) {}

    /**
     * @param Request     $request
     * @param array      $data
     * @param SymfonyUser $user
     *
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     * @return array
     */
    public function createPush(
        Request $request,
        array $data,
        SymfonyUser $user
    ): array
    {
        return $this->proxyService->request(
            Request::METHOD_POST,
            self::PATH,
            $request,
            [
                'channel' => 'PUSH',
                'topic' => $data['topic'],
                'pushTitle' => $data['pushTitle'],
                'pushContent' => $data['pushContent'] ?? '',
                'pushSubtitle' => $data['pushSubtitle'] ?? '',
                'scope' => 'INDIVIDUAL',
                'scopeTarget' => $data['scopeTarget']
            ],
            self::CREATE_NOTIFICATION_PATH
        );
    }

    /**
     * @param Request     $request
     * @param array       $data
     * @param SymfonyUser $user
     *
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     * @return array
     */
    public function createEmail(
        Request $request,
        array $data,
        SymfonyUser $user): array
    {
        return $this->proxyService->request(
            Request::METHOD_POST,
            self::PATH,
            $request,
            [
                'channel' => 'EMAIL',
                'templateId' => $data['templateId'],
                'emailSenderName' => $data['emailSenderName'],
                'emailSenderEmail' => $data['emailSenderEmail'],
                'emailSubject' => $data['emailSubject'],
                'recipients' => $data['recipients'],
                'scope' => 'INDIVIDUAL',
                'scopeTarget' => [$user->getUserIdentifier()]
            ],
            self::CREATE_NOTIFICATION_PATH
        );
    }
}
