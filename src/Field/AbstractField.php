<?php

namespace OrmLibrary\Field;

use Closure;

abstract class AbstractField implements IField
{
    private Closure $getter;
    private Closure $setter;
    protected string $setterErrorMessage = "Wrong type of value";
    private Closure $typeValidator;
    public function __construct(callable $typeValidator,closure $getter, closure $setter) {
        $this->typeValidator = $typeValidator(...);

        $this->getter = $getter(...);
        $this->setter = $setter(...);

        $this->getter = $this->getter->bindTo($this, self::class);
        $this->setter = $this->setter->bindTo($this, self::class);
    }


    public function get():mixed {
        if(isset($this->value))
            return ($this->getter)();
        else
            return null;
    }

    public function set($value):void {
        if(($this->typeValidator)($value))
            ($this->setter)($value);
        else
            throw new \Exception($this->setterErrorMessage);
    }
}

