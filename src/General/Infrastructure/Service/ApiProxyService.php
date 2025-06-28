<?php

declare(strict_types=1);

namespace App\General\Infrastructure\Service;

use App\General\Domain\Service\Interfaces\ApiProxyServiceInterface;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Class ApiProxyService
 *
 * @package App\General\Infrastructure\Service
 */
readonly class ApiProxyService implements ApiProxyServiceInterface
{
    private array $baseUrls;

    public function __construct(
        private HttpClientInterface $httpClient,
        string $apiMediaBaseUrl,
        string $apiNotificationBaseUrl,
    ) {
        $this->baseUrls = [
            'media'         => $apiMediaBaseUrl,
            'notification'         => $apiNotificationBaseUrl,
        ];
    }

    /**
     *
     * @param string  $method
     * @param string  $type
     * @param Request $request
     * @param array   $body
     * @param string  $path
     *
     * @throws TransportExceptionInterface
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @return array
     */
    public function request(string $method, string $type, Request $request, array $body = [], string $path = ''): array
    {
        if (!isset($this->baseUrls[$type])) {
            throw new InvalidArgumentException("Failed : {$type}");
        }

        $options = [
            'headers' => array_filter([
                'Content-Type' => 'application/json',
                'Authorization' => $request->headers->get('Authorization'),
            ]),
            'body' => !empty($body) ? json_encode($body) : null,
        ];

        $response = $this->httpClient->request($method, $this->baseUrls[$type] . $path, array_filter($options));

        return $response->toArray();
    }

    public function requestFile(string $method, string $type, Request $request, array $body = [], string $path = ''): array
    {
        if (!isset($this->baseUrls[$type])) {
            throw new InvalidArgumentException("Failed : {$type}");
        }

        $filesRequest = $request->files->all();
        $files = $filesRequest['files'] ?? [];
        $filesArray = [];

        foreach ($files as $key => $file) {
            $filesArray[$key] = new DataPart(
                fopen($file->getPathname(), 'r'),
                $file->getClientOriginalName(),
                $file->getMimeType()
            );
        }

        $formData = new FormDataPart([
            'contextKey'  => $body['context'],
            'contextId'   => 'af356024-2a00-1ef9-9b6d-1f8defb25086',
            'workplaceId' => '20000000-0000-1000-8000-000000000006',
            'private'     => "1",
            'mediaFolder' => $body['context'],
            'files'       => $filesArray,
        ]);

        $headers = $formData->getPreparedHeaders()->toArray();
        $headers['Authorization'] = $request->headers->get('Authorization');

        $options = [
            'headers' => $headers,
            'body'    => $formData->bodyToString(),
        ];

        $response = $this->httpClient->request($method, $this->baseUrls[$type] . $path, $options);

        return $response->toArray();
    }
}
