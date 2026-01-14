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
    /** @var AbstractEntity The owner entity of this relation. */
    private AbstractEntity $owner;

    /** @var string The fully qualified class name of the related entity. */
    private string $relation;

    /** @var EntityRepository The repository used to interact with the related entity. */
    private EntityRepository $repository;

    /** @var array The cached list of related entities. */
    private array $list;

    /**
     * Initializes the MTM relation.
     *
     * @param AbstractEntity $owner The owner entity.
     * @param string $fqcn Fully qualified class name of the related entity.
     * @param string $fqcnRepository Fully qualified class name of the repository for the related entity.
     *
     * @throws Exception If $fqcn is not a child of AbstractEntity.
     * @throws Exception If $fqcnRepository is not a child of EntityRepository.
     */
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

    public function __debugInfo():?array
    {
        return null;
    }


    /**
     * Retrieves the related entities.
     *
     * Lazy-loads the entities from the database if needed.
     *
     * @param bool $reload If true, forces a reload from the database even if cached.
     *
     * @return AbstractEntity[] The list of related entities.
     *
     * @throws Exception If the association table cannot be found or queried.
     */
    public function get(bool $reload = false):array {
        if ($this->owner->isNew())
            return [];
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

    /**
     * Retrieves the related entities and ensures they are fully loaded.
     *
     * @return AbstractEntity[] The list of fully loaded related entities.
     *
     * @throws Exception If the association table cannot be found or queried.
     */
    public function getLoaded():array {
        if ($this->owner->isNew())
            return [];

        unset($this->list);

        /** @var AbstractEntity $relation */
        $relation = new ($this->relation)();

        $assoc = EntityRepository::getAssociationEntity($relation::getName(),$this->owner::getName());

        $assocName = array_key_first($assoc);
        $assoWhereField = $assoc[$assocName]['links'][strtolower($this->owner::getName())];
        $assocSelectField = $assoc[$assocName]['links'][strtolower($relation::getName())];

        $w = Where::builder()->entity($assocName)->field($assoWhereField)->value($this->owner->id->get())->build();
        $data = $this->repository->select([$this->repository::getName() => [$assocSelectField]], wheres:$w);

        $ids = [];

        foreach ($data as $row) {
            $ids[] = $row[$assocSelectField];
        }

        $w =Where::builder()->entity($relation::getName())->field('Id')->value($ids)->build();
        $data = $relation->getRepository()->selectAll(wheres:$w);

        foreach ($data as $row) {
            $object = new ($this->relation)($row['Id']);
            $object->import($row);
            $this->list[] = $object;
        }

        return $this->list;
    }

    /**
     * Adds a related entity to the MTM relation.
     *
     * The entity can be passed as an instance of AbstractEntity or its ID.
     *
     * @param AbstractEntity|int $entity The entity or its ID to associate.
     *
     * @throws Exception If the passed entity type is invalid.
     * @throws Exception If an integer ID does not correspond to an existing entity.
     */
    public function add(AbstractEntity|int $entity):void {
        if (!Helpers::newClassValidator($entity, $this->relation))
            throw new Exception("Wrong type of class passed in argument");

        if (is_int($entity)) {
            /** @var AbstractEntity $entity */
            $entity = new ($this->relation)($entity);
        }

        if (!$entity->doIdExist())
            throw new Exception("Entity with invalid Id given in argument");

        $assoc = EntityRepository::getAssociationEntity($entity::getName(),$this->owner::getName());

        $assocName = array_key_first($assoc);
        $ownerField = $assoc[$assocName]['links'][strtolower($this->owner::getName())];
        $relationField = $assoc[$assocName]['links'][strtolower($entity::getName())];

        $this->repository->insert([$ownerField => $this->owner->id->get(), $relationField => $entity->id->get()]);
        $this->list[] = $entity;
    }
}