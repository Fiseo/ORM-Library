<?php
namespace OrmLibrary\Field;


use DateTime;
use Exception;

abstract class StringField extends AbstractField
{
    protected ?string $value;
    public function get():string {
        if (!isset($this->value))
            $this->load;
        return $this->value;
    }
    public function set($value):void {
        if(!is_string($value) && !is_null($value))
            throw new Exception("Value must be string");
        $this->value = $value;
    }

}

abstract class IntField extends AbstractField
{
    protected ?int $value = null;

    public function get(): ?int
    {
        if (!isset($this->value))
            $this->load;
        return $this->value;
    }

    public function set($value): void
    {
        if (!is_null($value) && !is_int($value))
            throw new Exception("Value must be int");
        $this->value = $value;
    }
}

abstract class FloatField extends AbstractField
{
    protected ?float $value = null;

    public function get(): ?float
    {
        if (!isset($this->value))
            $this->load;
        return $this->value;
    }

    public function set($value): void
    {
        if (!is_null($value) && !is_float($value) && !is_int($value))
            throw new Exception("Value must be float");
        $this->value = is_null($value) ? null : (float)$value;
    }
}

abstract class BoolField extends AbstractField
{
    protected ?bool $value = null;

    public function get(): ?bool
    {
        if (!isset($this->value))
            $this->load;
        return $this->value;
    }

    public function set($value): void
    {
        if (!is_null($value) && !is_bool($value))
            throw new Exception("Value must be bool");
        $this->value = $value;
    }
}

abstract class DateField extends AbstractField
{
    protected ?DateTime $value = null;


    public function get(): ?DateTime
    {
        if (!isset($this->value))
            $this->load;
        return $this->value;
    }


    public function set($value): void
    {
        if (is_null($value))
            $this->value = null;

        elseif ($value instanceof DateTime)
            $this->value = $value;
        elseif (is_string($value)) {
            $dt = date_create($value);
            if ($dt === false)
                throw new Exception("Invalid date string");
            $this->value = $dt;
        } else
            throw new Exception("Value must be DateTime or valid date string");
    }
}