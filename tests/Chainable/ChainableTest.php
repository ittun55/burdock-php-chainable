<?php

use Burdock\Config\Config;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Burdock\Chainable\Chainable;
use Burdock\Chainable\Failed;


class ChainableTest extends TestCase
{
    public $logger = null;

    public function setUp(): void
    {
        $formatter = new LineFormatter(null, null, true, true);
        $formatter->includeStacktraces(true);
        $stream = new StreamHandler('php://stdout');
        $stream->setFormatter($formatter);
        $this->logger = new Logger('ChainTest');
        $this->logger->pushHandler($stream, Logger::ERROR);
    }

    public function test_constructor(): void
    {
        $v = 'TEST_VALUE';
        $c = new Chainable($v, $this->logger);
        $this->assertEquals($v, $c->getValue());
    }

    public function test_exception(): void
    {
        $sum_args = function(int $arg1, int $arg2) : callable {
            return function(int $arg0) use ($arg1, $arg2) : int {
                return $arg0 + $arg1 + $arg2;
            };
        };
        $v = 50;
        $c = (new Chainable($v, $this->logger))
            ->process('times 2', function(int $a) { return $a * 2; })
            ->process('sum args', $sum_args(100, 200))
            ->process('div by zero', function(int $a, int $b) { return $a / $b; }, 0);
        $this->assertTrue($c->getValue() instanceof Failed);
        $es = $c->getErrors();
        foreach($es as $e) { echo $e->getMessage() . PHP_EOL; };
        $this->assertEquals(1, $c->getResult());
    }

    public function test_getValue(): void
    {
        $v = [
            'abc' => [
                ['xyz' => 'XYZ'],
                ['abc' => 'ABC']
            ]
        ];
        $c = (new Chainable($v, $this->logger));
        $this->assertTrue(is_array($c->getValue('abc')));
        $this->assertEquals('XYZ', $c->getValue('abc[0].xyz'));
        $this->assertEquals('ABC', $c->getValue('abc[1].abc'));
        $this->assertEquals('XYZ', $c->getValue('.abc[0].xyz'));
        $this->assertEquals('ABC', $c->getValue('.abc[1].abc'));
        $this->expectExceptionMessage('OutOfRange');
        $c->getValue('.abc[2].abc');
    }

    public function test_for_instance_method(): void
    {
        $kv = ['xyz' => 'XYZ', 'abc' => 'ABC'];
        $chain = (new Chainable(new Config($kv), $this->logger))
            ->process('call instance method', function($cfg) { return $cfg->getValue('abc'); });
        $this->assertEquals('ABC', $chain->getValue());
    }
}
