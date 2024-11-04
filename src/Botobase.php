<?php

namespace Yabx\Botobase;

use DateTimeImmutable;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Yabx\Botobase\Dto\Account;
use Yabx\Botobase\Dto\Gender;
use Yabx\Botobase\Dto\Service;
use Yabx\Botobase\Service\DeepSeek;
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

    public function useDeepSeek(): DeepSeek {
        static $service = new DeepSeek($this);
        return $service;
    }

    public function addUser(int $id, bool $isPremium, ?string $source, ?Gender $gender = null, ?DateTimeImmutable $bornAt = null): void {
        $this->request('POST', '/bots/users', [
            'id' => $id,
            'isPremium' => $isPremium,
            'source' => $source ?: 'organic',
            'gender' => $gender?->value,
            'bornAt' => $bornAt?->format('Y-m-d')
        ]);
    }

    public function updateUser(int $id, ?bool $isPremium = null, ?Gender $gender = null, ?DateTimeImmutable $bornAt = null, bool $isUpdated = false, bool $isAccessed = false): void {
        $params = [
            'id' => $id,
            'isUpdated' => $isUpdated,
            'isAccessed' => $isAccessed,
        ];
        if (isset($isPremium)) $params['isPremium'] = $isPremium;
        if (isset($gender)) $params['gender'] = $gender->value;
        if (isset($bornAt)) $params['bornAt'] = $bornAt->format('Y-m-d');
        $this->request('PATCH', '/bots/users', $params);
    }

    public function allocateAccount(Service $service): Account {
        $account = $this->request('GET', 'accounts/allocate', [
            'service' => $service->value,
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
        if ($method === 'GET') {
            $options[RequestOptions::QUERY] = ['__payload' => json_encode($params)];
        } else {
            $options[RequestOptions::JSON] = $params;
        }
        $res = $this->client->request($method, $path, $options);
        $code = $res->getStatusCode();
        $body = $res->getBody()->getContents();
        $json = json_validate($body) ? json_decode($body, true) : null;
        if ($code >= 200 && $code < 300) {
            return $json['result'] ?? null;
        } elseif ($error = $json['error'] ?? false) {
            throw new Exception('API: ' . $error);
        } else {
            throw new Exception('API: Unexpected error ' . $code . ': ' . substr($body, 0, 100));
        }
    }

}