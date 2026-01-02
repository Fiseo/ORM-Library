<?php

namespace OrmLibrary\Relation;

use Exception;
use OrmLibrary\Entity\AbstractEntity;
use OrmLibrary\Entity\EntityRepository;
use OrmLibrary\Helpers;
use OrmLibrary\Query\Where;

class RelationOTM implements IRelation
{
    /** @var AbstractEntity The owner entity of this relation. */
    private AbstractEntity $owner;

    /** @var string The fully qualified class name of the related entity. */
    private string $relation;

    /** @var AbstractEntity[] The cached list of related entities. */
    private array $list;

    /**
     * Initializes the OTM relation.
     *
     * @param AbstractEntity $owner The owner entity.
     * @param string $fqcn Fully qualified class name of the related entity.
     *
     * @throws Exception If $fqcn is not a child of AbstractEntity.
     */
    public function __construct(AbstractEntity $owner, string $fqcn) {
        if (!Helpers::isEntity($fqcn))
            throw new Exception("A relation must be with a child entity of AbstractEntity class");
        $this->owner = $owner;
        $this->relation = $fqcn;
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
     * @throws Exception If the relation cannot be resolved via the repository.
     */
    public function get(bool $reload = false):array {
        if (!$reload && !empty($this->list))
            return $this->list;
        /** @var AbstractEntity $relation */
        $relation = new ($this->relation)();
        $link = EntityRepository::getLink($this->owner::getName(), $relation::getName());
        $field = $link[array_key_first($link)];

        $w = Where::builder()->field($field)->value($this->owner->id->get())->entity($relation::getName())->build();
        $repository = $relation->getRepository();
        $data = $repository->select(fields: [$repository::getName() => ["Id"]], wheres: $w);
        var_dump($data);
        foreach ($data as $item)
            $this->list[] = new $this->relation($item["Id"]);
        return $this->list;
    }

    /**
     * Retrieves the related entities and ensures they are fully loaded.
     *
     * @param bool $reload If true, forces a reload from the database even if cached.
     *
     * @return AbstractEntity[] The list of fully loaded related entities.
     *
     * @throws Exception If the relation cannot be resolved via the repository
     */
    public function getLoaded(bool $reload):array {
        $this->get($reload);
        foreach ($this->list as $item) {
            //TODO : Optimiser le chargement des données
            $item->load();
        }
        return $this->list;
    }

}