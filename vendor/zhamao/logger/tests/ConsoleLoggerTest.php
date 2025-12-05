<?php

declare(strict_types=1);

namespace Tests;

use Closure;
use InvalidArgumentException;
use Psr\Log\LogLevel;

class ConsoleLoggerTest extends TestCase
{
    /**
     * @dataProvider provideCanLogAtAllLevels
     */
    public function testCanLogAtAllLevels($level, $message): void
    {
        $expected = [
            "this is a {$level} message for testing",
            "this is a {$level} message for testing",
        ];

        $logger = $this->getLogger();
        $logger->{$level}($message);
        $logger->log($level, $message);

        $this->assertSame($expected, $this->getLogs());
    }

    public function provideCanLogAtAllLevels(): array
    {
        return [
            LogLevel::EMERGENCY => [LogLevel::EMERGENCY, 'this is a emergency message for testing'],
            LogLevel::ALERT => [LogLevel::ALERT, 'this is a alert message for testing'],
            LogLevel::CRITICAL => [LogLevel::CRITICAL, 'this is a critical message for testing'],
            LogLevel::ERROR => [LogLevel::ERROR, 'this is a error message for testing'],
            LogLevel::WARNING => [LogLevel::WARNING, 'this is a warning message for testing'],
            LogLevel::NOTICE => [LogLevel::NOTICE, 'this is a notice message for testing'],
            LogLevel::INFO => [LogLevel::INFO, 'this is a info message for testing'],
            LogLevel::DEBUG => [LogLevel::DEBUG, 'this is a debug message for testing'],
        ];
    }

    public function testWillThrowsOnInvalidLevel(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $logger = $this->getLogger();
        $logger->log('invalid', 'this is a message for testing');
    }

    public function testCanReplaceContext(): void
    {
        $logger = $this->getLogger();
        $logger->info('this is a {message} with {nothing}', ['message' => 'info message for testing']);
        $this->assertSame(['this is a info message for testing with {nothing}'], $this->getLogs());
    }

    public function testCanCastObjectToString(): void
    {
        $string = uniqid('DUMMY', true);
        $dummy = $this->createMock(\Stringable::class);
        $dummy->expects($this->once())->method('__toString')->willReturn($string);

        $this->getLogger()->info($dummy);
        $this->assertSame([$string], $this->getLogs());
    }

    /**
     * @dataProvider provideTestCanContainAnythingInContext
     */
    public function testCanContainAnythingInContext($context, $expected): void
    {
        $logger = $this->getLogger();
        $logger->info('{context}', ['context' => $context]);
        $this->assertSame([$expected], $this->getLogs());
    }

    public function provideTestCanContainAnythingInContext(): array
    {
        return [
            'callable' => [[new ConsoleLoggerTest(), 'testCanContainAnythingInContext'], self::class . '@testCanContainAnythingInContext'],
            'closure' => [Closure::fromCallable([$this, 'testCanContainAnythingInContext']), 'closure'],
            'string' => ['string', 'string'],
            'array' => [['123', '42', 'hello', 122], 'array["123","42","hello",122]'],
            'object' => [new \stdClass(), 'stdClass'],
            'resource' => [fopen('php://memory', 'rb'), 'resource(stream)'],
            'null' => [null, 'null'],
            'boolean 1' => [true, 'true'],
            'boolean 2' => [false, 'false'],
            'float' => [123.456, '123.456'],
            'integer' => [123, '123'],
        ];
    }
}
