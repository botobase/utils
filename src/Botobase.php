<?php

namespace Yabx\Botobase;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Yabx\Botobase\Dto\Account;
use Yabx\Botobase\Dto\Service;
use Yabx\Botobase\Service\Mathpix;
use Yabx\Botobase\Utils\EnumNormalizer;

class Botobase {

    protected Client $client;
    protected Serializer $serializer;

    public function __construct(string $apiKey, string $baseUrl = 'https://api.botobase.com/') {
        $encoders = [new JsonEncoder];
        $normalizers = [new EnumNormalizer, new ObjectNormalizer];
        $this->serializer = new Serializer($normalizers, $encoders);
        $this->client = new Client([
            'base_uri' => $baseUrl,
            RequestOptions::HEADERS => ['Authorization' => 'Bearer ' . $apiKey]
        ]);
    }

    public function useMathpix(): Mathpix {
        static $service = new Mathpix($this);
        return $service;
    }

    public function allocateAccount(Service $service, ?int $preferredId = null): Account {
        $account = $this->request('GET', 'accounts/allocate', [
            'service' => $service->value,
            'preferredId' => $preferredId
        ]);
        return $this->serializer->denormalize($account, Account::class);
    }

    public function sendUsageReport(Account $account, float $time, array $data): void {
        $this->request('POST', 'accounts/' . $account->id . '/usage', [
            'time' => $time,
            'data' => $data
        ]);
    }

    public function request(string $method, string $path, array $params = []): mixed {
        $options = [
            RequestOptions::CONNECT_TIMEOUT => 5,
            RequestOptions::TIMEOUT => 30,
            RequestOptions::HTTP_ERRORS => false
        ];
        if($method === 'GET') {
            $options[RequestOptions::QUERY] = ['__payload' => json_encode($params)];
        } else {
            $options[RequestOptions::JSON] = $params;
        }
        $res = $this->client->request($method, $path, $options);
        $code = $res->getStatusCode();
        $body = $res->getBody()->getContents();
        $json = json_validate($body) ? json_decode($body, true) : null;
        if($code >= 200 && $code < 300) {
            return $json['result'] ?? null;
        } elseif($error = $json['error'] ?? false) {
            throw new Exception($error);
        } else {
            throw new Exception('Unexpected error ' . $code . ': ' . substr($body, 0, 100));
        }
    }

}