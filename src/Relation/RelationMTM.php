<?php

namespace OrmLibrary\Relation;

use Exception;
use OrmLibrary\Entity\AbstractEntity;
use OrmLibrary\Entity\EntityRepository;
use OrmLibrary\Helpers;
use OrmLibrary\Query\Join;
use OrmLibrary\Query\Where;

class RelationMTM implements IRelation
{
    private AbstractEntity $owner;
    private string $relation;
    private EntityRepository $repository;
    private array $list;

    public function __construct(AbstractEntity $owner, string $fqcn, string $fqcnRepository) {
        if (!Helpers::isEntity($fqcn))
            throw new Exception("A relation must be with a child entity of AbstractEntity class");
        if (!Helpers::isRepository($fqcnRepository))
            throw new Exception("The repository must be a child entity of EntityRepository class");
        $this->owner = $owner;

        $this->relation = $fqcn;

        /** @var EntityRepository $relation */
        $this->repository = new ($fqcnRepository)();
    }


    public function get(bool $reload = false):array {
        if (!$reload && !empty($this->list))
            return $this->list;

        /** @var AbstractEntity $relation */
        $relation = new ($this->relation)();

        $assoc = EntityRepository::getAssociationEntity($relation::getName(),$this->owner::getName());

        $assocName = array_key_first($assoc);
        $assoWhereField = $assoc[$assocName]['links'][strtolower($this->owner::getName())];
        $assocSelectField = $assoc[$assocName]['links'][strtolower($relation::getName())];

        $w = Where::builder()->entity($assocName)->field($assoWhereField)->value($this->owner->id->get())->build();
        $data = $this->repository->select([$this->repository::getName() => [$assocSelectField]], wheres:$w);

        $result = [];
        foreach ($data as $row) {
            $e = new ($this->relation)($row[$assocSelectField]);
            $result[] = $e;
        }

        $this->list = $result;

        return $this->list;
    }

    public function getLoaded(bool $reload = false):array {
        $this->get($reload);
        foreach ($this->list as $item) {
            //TODO : Optimiser le chargement des données
            $item->load();
        }
        return $this->list;
    }

    public function add(AbstractEntity|int $entity):void {
        if (!Helpers::newClassValidator($entity, $this->relation))
            throw new Exception("Wrong type of class passed in argument");

        if (is_int($entity)) {
            try {
                $entity = new ($this->relation)($entity);
            } catch (Exception $e) {
                throw new Exception("Invalid Id passed in argument");
            }
        }

        $assoc = EntityRepository::getAssociationEntity($entity::getName(),$this->owner::getName());

        $assocName = array_key_first($assoc);
        $ownerField = $assoc[$assocName]['links'][strtolower($this->owner::getName())];
        $relationField = $assoc[$assocName]['links'][strtolower($entity::getName())];

        $this->repository->insert([$ownerField => $this->owner->id->get(), $relationField => $entity->id->get()]);
        $this->list[] = $entity;
    }
}