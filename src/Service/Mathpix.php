<?php

namespace Yabx\Botobase\Service;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Yabx\Botobase\Botobase;
use Yabx\Botobase\Dto\Service;

class Mathpix {

    protected Botobase $botobase;
    protected Client $client;
    protected LoggerInterface $logger;

    public function __construct(Botobase $botobase) {
        $this->logger = new NullLogger();
        $this->botobase = $botobase;
        $this->client = new Client([
            RequestOptions::HTTP_ERRORS => false,
            RequestOptions::TIMEOUT => 30,
            RequestOptions::CONNECT_TIMEOUT => 5,
            RequestOptions::HEADERS => [
                'Accept' => 'application/json',
                'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/117.0.0.0 Safari/537.36',
            ],
        ]);
    }

    public function parseImage(string $path): string {
        try {
            $path = realpath($path) ?: throw new Exception('Invalid path: ' . $path);
            $this->logger->debug(sprintf('Parsing image from %s', $path));
            $this->logger->debug('Requesting account...');
            $account = $this->botobase->allocateAccount(Service::Mathpix);
            $this->logger->debug('Account allocated: #' . $account->id);
        } catch (Exception $exception) {
            $this->logger->error($exception->getMessage());
            throw $exception;
        }
        $f = fopen($path, 'r');
        $this->logger->debug('Upload ' . $path);
        $start = microtime(true);
        $res = $this->client->post('https://snip-api.mathpix.com/v1/snips-multipart', [
            RequestOptions::HEADERS => ['Authorization' => 'Bearer ' . $account->extra['token']],
            RequestOptions::PROXY => $account->proxy?->string,
            RequestOptions::MULTIPART => [
                ['name' => 'file', 'filename' => 'image.png', 'content-type' => 'image/png', 'contents' => $f],
                ['name' => 'options_json', 'content-type' => 'multipart/form-data', 'contents' => '{"config":{"math_inline_delimiters":["$","$"],"math_display_delimiters":["$$\\n","\\n$$"],"idiomatic_eqn_arrays":true,"ocr_version":2},"metadata":{"input_type":"web_editor"}}']
            ],
        ]);
        $body = $res->getBody()->getContents();
        $time = microtime(true) - $start;
        $this->logger->debug('Response (' . $res->getStatusCode() . ', ' . round($time, 2) . ' sec.) ' . $body);
        if(!json_validate($body)) {
            $this->logger->error('Invalid JSON');
            throw new Exception('Failed to request image AI');
        }
        $json = json_decode($body, true);
        if(isset($json['snip_count'])) {
            $this->logger->debug('Sending usage data');
            $this->botobase->sendUsageReport($account, $time, [
                'snip_count' => $json['snip_count'],
                'snip_limit' => $json['snip_limit']
            ]);
        }
        if($json['errors'] ?? false) {
            $error = $json['errors'][0]['message'] ?? 'Unknown error';
            $this->logger->error($error);
            throw new Exception($error);
        }
        $this->logger->debug('Cleanup');
        $this->client->delete('https://snip-api.mathpix.com/v1/snips/' . $json['id'], [
            RequestOptions::HEADERS => ['Authorization' => 'Bearer ' . $account->extra['token']],
            RequestOptions::PROXY => $account->proxy?->string,
        ]);
        $this->logger->debug('Success');
        return $json['text'];
    }

    public function setLogger(LoggerInterface $logger): void {
        $this->logger = $logger;
    }

}