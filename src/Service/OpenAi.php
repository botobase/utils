<?php

namespace Yabx\Botobase\Service;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;
use Yabx\Botobase\Botobase;
use Yabx\Botobase\Dto\Service;

class OpenAi {

    protected Botobase $botobase;
    protected Client $client;
    protected LoggerInterface $logger;

    public function __construct(Botobase $botobase) {
        $this->logger = new NullLogger();
        $this->botobase = $botobase;
        $this->client = new Client([
            RequestOptions::HTTP_ERRORS => false,
            RequestOptions::TIMEOUT => 300,
            RequestOptions::CONNECT_TIMEOUT => 5,
        ]);
    }

    public function query(string|array $query, string $model = 'gpt-4o-mini'): string {
        $messages = is_string($query) ? [['role' => 'user', 'content' => $query]] : $query;
        $account = $this->botobase->allocateAccount(Service::OpenAI);
        $req = ['model' => $model, 'messages' => $messages];
        $start = microtime(true);
        try {
            $this->logger->debug('REQUEST', $req);
            $res = $this->client->request('POST', 'https://api.openai.com/v1/chat/completions', [
                RequestOptions::JSON => $req,
                RequestOptions::PROXY => $account->proxy?->string,
                RequestOptions::HEADERS => ['Authorization' => 'Bearer ' . $account->credentials]
            ]);
            $body = $res->getBody()->getContents();
            $time = microtime(true) - $start;
            $json = json_decode($body, true);
            $this->logger->debug('RESPONSE (' . $time . ' sec.)', $json);
            $this->botobase->sendUsageReport($account, $time, ['model' => $model, 'usage' => $json['usage']]);
            return $json['choices'][0]['message']['content'];
        } catch (Throwable $e) {
            $this->logger->error($e->getMessage());
            throw $e;
        }
    }

    public function speechToText(string $filePath): string {
        $account = $this->botobase->allocateAccount(Service::OpenAI);
        $start = microtime(true);
        try {
            $this->logger->debug('REQUEST: ' . $filePath);
            $res = $this->client->request('POST', 'https://api.openai.com/v1/audio/transcriptions', [
                RequestOptions::PROXY => $account->proxy?->string,
                RequestOptions::HEADERS => ['Authorization' => 'Bearer ' . $account->credentials],
                RequestOptions::MULTIPART => [
                    ['name' => 'model', 'contents' => 'whisper-1'],
                    ['name' => 'file', 'contents' => fopen($filePath, 'r')]
                ]
            ]);
            $body = $res->getBody()->getContents();
            $time = microtime(true) - $start;
            $json = json_decode($body, true);
            $this->botobase->sendUsageReport($account, $time, ['model' => 'whisper-1', 'length' => strlen($json['text'])]);
            $this->logger->debug('RESPONSE', (array)$json);
            return $json['text'];
        } catch (Throwable $e) {
            $this->logger->error($e->getMessage());
            throw new Exception('OpenAI Error: ' . $e->getMessage());
        }
    }

    public function setLogger(LoggerInterface $logger): void {
        $this->logger = $logger;
    }

}