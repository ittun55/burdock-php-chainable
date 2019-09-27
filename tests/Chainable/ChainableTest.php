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

    public $instance_value = 10;
    public function instance_sum(): callable
    {
        $a = $this->instance_value;
        return function($b) use ($a) {
            return $a + $b;
        };
    }

    public function instance_sum_alt($a, $b): int
    {
        return $a + $b;
    }

    public function test_for_instance_method(): void
    {
        $kv = ['xyz' => 'XYZ', 'abc' => 'ABC'];
        $c1 = (new Chainable(new Config($kv), $this->logger))
            ->process('call instance method', function($cfg) { return $cfg->getValue('abc'); });
        $this->assertEquals('ABC', $c1->getValue());
        $c2 = (new Chainable(0, $this->logger))
            ->process('call instance method 1', $this->instance_sum())
            ->process('call instance method 2', [$this, 'instance_sum_alt'], 5);
        $this->assertEquals(15, $c2->getValue());
    }

    public static function sum($a): callable
    {
        return function($b) use ($a) {
            return $a + $b;
        };
    }

    public static function sum_alt($a, $b): int
    {
        return $a + $b;
    }

    public function test_for_static_method(): void
    {
        $c1 = (new Burdock\Chainable\Chainable(1, $this->logger))
            ->process('call static method', self::sum(2));
        $this->assertEquals(3, $c1->getValue());
        $c2 = $c1->process('call static method another way', [__CLASS__, 'sum_alt'], 3)
            ->process('call static method alternatively', ['ChainableTest', 'sum_alt'], 4)
            ->process('call static method ', 'ChainableTest::sum_alt', 5);
        $this->assertEquals(15, $c2->getValue());
    }
}
