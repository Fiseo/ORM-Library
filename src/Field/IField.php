<?php

namespace OrmLibrary\Field;

interface IField
{
    public function get();
    public function set($value);

    public function getFieldName();

}