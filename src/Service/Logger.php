<?php

namespace Yabx\Botobase\Service;

use DateTime;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Stringable;

class Logger implements LoggerInterface {

    protected string $name;
    protected string $path;
    protected string $prefix;

    public function __construct(string $path, ?string $prefix = null) {
        $this->path = $path;
        $this->prefix = $prefix ? $prefix . ' ' : '';
        if(!is_dir($path)) mkdir($path, 0755, true);
    }

    public function emergency(string|Stringable $message, array $context = []): void {
        $this->log(LogLevel::EMERGENCY, $message, $context);
    }

    public function log(mixed $level, string|Stringable $message, array $context = []): void {
        $f = fopen($this->path . '/' . date('Y-m-d') . '.log', 'a');
        fwrite($f, (new DateTime())->format('[H:i:s.u]') . ' ' . strtoupper($level) . ': ' . $this->prefix . $message . (!empty($context) ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_SLASHES) : '') . PHP_EOL);
        fclose($f);
    }

    public function alert(string|Stringable $message, array $context = []): void {
        $this->log(LogLevel::ALERT, $message, $context);
    }

    public function critical(string|Stringable $message, array $context = []): void {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    public function error(string|Stringable $message, array $context = []): void {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    public function warning(string|Stringable $message, array $context = []): void {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    public function notice(string|Stringable $message, array $context = []): void {
        $this->log(LogLevel::NOTICE, $message, $context);
    }

    public function info(string|Stringable $message, array $context = []): void {
        $this->log(LogLevel::INFO, $message, $context);
    }

    public function debug(string|Stringable $message, array $context = []): void {
        $this->log(LogLevel::DEBUG, $message, $context);
    }

}
