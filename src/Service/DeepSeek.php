<?php

namespace Yabx\Botobase\Service;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;
use Yabx\Botobase\Botobase;
use Yabx\Botobase\Dto\Service;

class DeepSeek {

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

    public function query(string|array $query, ?int $preferredAccountId = null): string {
        $messages = is_string($query) ? [['role' => 'user', 'content' => $query]] : $query;
        $account = $this->botobase->allocateAccount(Service::DeepSeek, $preferredAccountId);
        $req = ['model' => 'deepseek-chat', 'messages' => $messages];
        $start = microtime(true);
        try {
            $this->logger->debug('REQUEST', $req);
            $res = $this->client->request('POST', 'https://api.deepseek.com/v1/chat/completions', [
                RequestOptions::JSON => $req,
                RequestOptions::PROXY => $account->proxy?->string,
                RequestOptions::HEADERS => ['Authorization' => 'Bearer ' . $account->credentials]
            ]);
            $body = $res->getBody()->getContents();
            $time = microtime(true) - $start;
            $json = json_decode($body, true);
            $this->logger->debug('RESPONSE (' . $time . ' sec.)', (array)$json);
            $this->botobase->sendUsageReport($account, $time, $json['usage']);
            return $json['choices'][0]['message']['content'];
        } catch (Throwable $e) {
            $this->logger->error($e->getMessage());
            throw $e;
        }
    }

    public function setLogger(LoggerInterface $logger): void {
        $this->logger = $logger;
    }

}