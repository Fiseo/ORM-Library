<?php

namespace OrmLibrary\Entity;

use OrmLibrary\Entity\EntityRepository;

abstract class AbstractEntity
{
    protected static string $name;
    private EntityRepository $repository;

    public function __construct()
    {
        $this->repository = getEntityRepository(static::getName());
    }

    static public function getName(): string{
        return static::$name;
    }

    public function getEntityRepository(): EntityRepository {
        return $this->repository;
    }

    public function saveEntity():void {

    }
    public function loadEntity():void {

    }

}