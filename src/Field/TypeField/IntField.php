<?php

namespace OrmLibrary\Field\TypeField;

use Closure;
use Exception;
use OrmLibrary\Field\AbstractField;

class IntField extends AbstractField
{
    protected ?int $value;

    public function __construct(callable $loader, ?closure $getter = null, ?closure $setter = null)
    {

        if ($getter === null) {
            $getter = function () use ($loader) {
                if (!isset($this->value)) {
                    try {
                        ($loader)();
                    } catch (Exception $e) {}
                }
                return $this->value;
            };
        }

        if ($setter === null) {
            $setter = function (int $value) {
                $this->value = $value;
            };
        }

        parent::__construct("is_int", $getter, $setter);
    }
}

