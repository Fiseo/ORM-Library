<?php

namespace OrmLibrary\Relation;

interface IRelation
{
    public function get(bool $reload):array;
    public function getLoaded():array;

}