<?php

namespace OrmLibrary\Field;

use Closure;
use DateTime;
use Exception;

abstract class AbstractField implements IField
{
    protected Closure $load;
    public function __construct(callable $load) {
        $this->load = $load(...);
    }

    abstract public function get();
    abstract public function set($value):void;
}

