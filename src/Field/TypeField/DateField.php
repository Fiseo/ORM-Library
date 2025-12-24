<?php

namespace OrmLibrary\Field\TypeField;

use Closure;
use DateTime;
use Exception;
use OrmLibrary\Field\AbstractField;

class DateField extends AbstractField
{
    protected ?DateTime $value = null;

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
            $setter = function (string|DateTime $value) {
                if ($value instanceof DateTime)
                    $this->value = $value;
                elseif (is_string($value)) {
                    $dt = date_create($value);
                    if ($dt === false)
                        throw new Exception("Invalid date string");
                    $this->value = $dt;
                }
            };
        }

        parent::__construct([$this,"is_DateTime"], $getter, $setter);
    }

    private function is_DateTime($value):bool {
        if ($value instanceof DateTime)
            return true;
        elseif (is_string($value))
            return true;
        else
            return false;
    }
}