<?php

namespace OrmLibrary\Relation;

interface IRelation
{
    public function get():array;
    public function getLoaded():array;

}