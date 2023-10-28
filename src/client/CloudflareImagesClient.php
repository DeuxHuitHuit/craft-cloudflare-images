<?php

namespace deuxhuithuit\cfimages\client;

use Exception;
use GuzzleHttp;

class CloudflareImagesClient
{
    public string $baseUrl = 'https://api.cloudflare.com/client/v4/accounts/';
    /** @var \deuxhuithuit\cfimages\models\Settings? */
    public $config;

    public function __construct(\deuxhuithuit\cfimages\models\Settings $config)
    {
        if (!$config->getApiToken()) {
            throw new \Exception('No API token found');
        }
        if (!$config->getAccountId()) {
            throw new \Exception('No account ID found');
        }
        $this->config = $config;
    }

    public function createCfUrl(string $endpoint): string
    {
        return $this->baseUrl . $this->config->getAccountId() . $endpoint;
    }

    public function createHttpHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->config->getApiToken(),
        ];
    }

    public function createJsonRequestHttpHeaders(): array
    {
        return \array_merge($this->createHttpHeaders(), [
            'Content-Type' => 'application/json',
        ]);
    }

    public function uploadImageStream($imageStream, string $imageFilename, array $meta = []): array
    {
        if (!isset($meta['path'])) {
            $meta['path'] = $imageFilename;
        }
        if (!isset($meta['folder'])) {
            $meta['folder'] = \dirname($imageFilename);
        }
        if (!isset($meta['size'])) {
            $meta['size'] = fstat($imageStream)['size'];
        }
        if (!isset($meta['created'])) {
            $meta['created'] = time();
        }
        if (!isset($meta['updated'])) {
            $meta['updated'] = time();
        }
        $client = new GuzzleHttp\Client();
        $uploadRes = $client->request('POST', $this->createCfUrl('/images/v1'), [
            'headers' => $this->createHttpHeaders(),
            'multipart' => [
                [
                    'name' => 'metadata',
                    'contents' => json_encode($meta),
                ],
                [
                    'name' => 'file',
                    'contents' => $imageStream,
                    'filename' => $imageFilename,
                ],
            ],
            'http_errors' => false,
        ]);

        if ($uploadRes->getStatusCode() !== 200) {
            throw new Exception('Error uploading image: ' . $uploadRes->getBody());
        }

        $data = json_decode($uploadRes->getBody(), true);

        return $data['result'];
    }

    public function moveImage(string $imageFilename, string $imageUid, array $meta = []): array
    {
        $meta['path'] = $imageFilename;
        $meta['folder'] = \dirname($imageFilename);
        $meta['updated'] = time();
        $client = new GuzzleHttp\Client();
        $uploadRes = $client->request('PATCH', $this->createCfUrl('/images/v1/' . $imageUid), [
            'headers' => $this->createJsonRequestHttpHeaders(),
            'body' => json_encode(['metadata' => $meta]),
            'http_errors' => false,
        ]);

        if ($uploadRes->getStatusCode() !== 200) {
            throw new Exception('Error uploading image: ' . $uploadRes->getBody());
        }

        $data = json_decode($uploadRes->getBody(), true);

        return $data['result'];
    }

    public function listImages($perPage = 10000, $continueToken = null): array
    {
        $client = new GuzzleHttp\Client();
        $res = $client->request('GET', $this->createCfUrl("/images/v2?per_page=$perPage"), [
            'headers' => $this->createHttpHeaders(),
            'http_errors' => false,
        ]);

        if ($res->getStatusCode() !== 200) {
            throw new Exception('Failed to list images: ' . $res->getBody());
        }

        $data = json_decode($res->getBody(), true);

        if (!isset($data['result']['images'])) {
            return [];
        }

        // TODO: Recursive call for the next page

        return $data['result']['images'];
    }

    public function getImage(string $imageUid): array
    {
        $client = new GuzzleHttp\Client();
        $res = $client->request('GET', $this->createCfUrl('/images/v1/' . $imageUid), [
            'headers' => $this->createHttpHeaders(),
            'http_errors' => false,
        ]);

        if ($res->getStatusCode() !== 200) {
            throw new Exception('Failed to get image: ' . $res->getBody());
        }

        $data = json_decode($res->getBody(), true);

        return $data['result'];
    }

    public function getImageStream(string $imageUid): \Psr\Http\Message\StreamInterface
    {
        $client = new GuzzleHttp\Client();
        $res = $client->request('GET', $this->createCfUrl('/images/v1/' . $imageUid . '/blob'), [
            'headers' => $this->createHttpHeaders(),
            'http_errors' => false,
        ]);

        if ($res->getStatusCode() !== 200) {
            throw new Exception('Failed to get image blob: ' . $res->getBody());
        }

        return $res->getBody();
    }

    public function deleteImage(string $imageUid): void
    {
        $client = new GuzzleHttp\Client();
        $res = $client->request('DELETE', $this->createCfUrl('/images/v1/' . $imageUid), [
            'headers' => $this->createJsonRequestHttpHeaders(),
            'http_errors' => false,
        ]);

        if ($res->getStatusCode() !== 200) {
            throw new Exception('Failed to delete image: ' . $res->getBody());
        }
    }
}
