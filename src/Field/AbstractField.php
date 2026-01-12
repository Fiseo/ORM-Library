<?php

namespace OrmLibrary\Field;

use Closure;
use Error;
use Exception;

/**
 * Base implementation of a typed entity field.
 *
 * This abstract class provides a generic mechanism to define
 * entity fields using closures for:
 * - value retrieval
 * - value assignment
 * - type validation
 *
 * It ensures type safety at runtime and centralizes validation logic.
 */
abstract class AbstractField implements IField
{
    /** @var Closure Closure used to retrieve the field value */
    private Closure $getter;

    /** @var Closure Closure used to assign the field value */
    private Closure $setter;

    /** @var Closure Closure used to validate the assigned value type */
    private Closure $typeValidator;

    /** @var string Error message thrown when type validation fails */
    protected string $setterErrorMessage = "Wrong type of value";

    /**
     * Initializes a new field definition.
     *
     * The provided callables are converted to Closures and bound to the
     * concrete field instance to allow controlled access to its context.
     *
     * @param callable $typeValidator Callable used to validate the value type.
     * @param ?Closure  $getter        Closure used to retrieve the field value.
     * @param Closure  $setter        Closure used to assign the field value.
     *
     * @throws Exception If a closure cannot be bound to the field instance.
     */
    public function __construct(callable $typeValidator, callable $loader, ?closure $getter, closure $setter) {
        $this->typeValidator = $typeValidator(...);

        if ($getter === null) {
            $getter = function () {
                try{
                    return $this->value;
                } catch (Error $e) {}
            };
        }

        $getter = function() use ($loader, $getter) {
            if (!isset($this->value)) {
                try {
                    ($loader)();
                } catch (Exception $e) {}
            }
            if (!isset($this->value))
                return $getter();
            else
                return null;
        };

        $this->getter = $getter(...);
        $this->setter = $setter(...);

        $this->getter = $this->getter->bindTo($this, static::class);
        $this->setter = $this->setter->bindTo($this, static::class);
    }


    /**
     * Retrieves the current value of the field.
     *
     * The value is returned by executing the internally bound getter closure.
     *
     * @return mixed The field value.
     *
     * @throws Exception If the getter closure execution fails.
     */
    public function get():mixed {
        return ($this->getter)();
    }

    /**
     * Assigns a new value to the field.
     *
     * The value is first validated using the type validator callable.
     * If validation passes, the setter closure is executed.
     *
     * @param mixed $value The value to assign.
     *
     * @return void
     *
     * @throws Exception If the value does not match the expected type.
     * @throws Exception If the setter closure execution fails.
     */
    public function set($value):void {
        if(($this->typeValidator)($value))
            ($this->setter)($value);
        else
            throw new \Exception($this->setterErrorMessage);
    }
}

