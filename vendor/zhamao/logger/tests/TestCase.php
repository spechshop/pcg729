<?php

declare(strict_types=1);

namespace Tests;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use ZM\Logger\ConsoleLogger;

class TestCase extends \PHPUnit\Framework\TestCase
{
    protected $logs = [];

    protected function getLogger(): LoggerInterface
    {
        $logger = new ConsoleLogger(LogLevel::DEBUG);
        $logger::$format = '%body%';
        $logger->addLogCallback(function ($level, $output, $message, $context) {
            $this->logs[] = $output;
            return false;
        });
        return $logger;
    }

    protected function getLogs(): array
    {
        return $this->logs;
    }
}
