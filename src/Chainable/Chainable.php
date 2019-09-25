<?php
namespace Burdock\Chainable;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use \Throwable;

class Chainable
{
    private $_value  = null;
    private $_logger = null;
    private $_errors = null;

    public function __construct($value, ?LoggerInterface $logger=null, array $errors=[])
    {
        $this->_value = $value;
        $this->_logger = (is_null($logger)) ? new NullLogger() : $logger;
        $this->_errors = $errors;
    }

    const PROCESS_FAILED_MSG  = '    The process was failed.';
    const PROCESS_SKIPPED_MSG = '    The process was skipped because previous process was failed.';

    public function process(string $name, Callable $func, ...$args) : Chainable
    {
        $this->_logger->info($name);

        if ($this->_value instanceof Failed) {
            $this->_logger->warning(static::PROCESS_SKIPPED_MSG);
            return $this;
        }

        try {
            $this->_logger->debug('    passed value: ' . var_export($this->_value, true));
            return new self($func($this->_value, ...$args), $this->_logger);
        } catch(\Exception $e) {
            $this->_logger->warning(static::PROCESS_FAILED_MSG);
            return new self(new Failed(), $this->_logger, array_merge($this->_errors, [$e]));
        }
    }

    public function getValue(string $path=null)
    {
        if (is_null($path)) {
            return $this->_value;
        } else {
            $nodes = explode('.', $path);
            $value = $this->_value;
            foreach ($nodes as $node) {
                if ($node === '') continue;
                if (preg_match('/(?P<prop>\w+)\[(?P<idx>\d+)\]/', $node, $matches)) {
                    $prop = $matches['prop'];
                    $idx  = (int)$matches['idx'];
                    if (!array_key_exists($prop, $value) || !array_key_exists($idx, $value[$prop])) {
                        throw new \OutOfRangeException('OutOfRange');
                    }
                    $value = $value[$prop][$idx];
                } else {
                    if (!array_key_exists($node, $value)) {
                        throw new \OutOfRangeException('OutOfRange');
                    }
                    $value = $value[$node];
                }
            }
            return $value;
        }
    }

    public function getResult()
    {
        return (count($this->_errors) == 0) ? 0 : 1;
    }

    public function getErrors()
    {
        return $this->_errors;
    }
}