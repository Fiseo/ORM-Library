<?php

namespace OrmLibrary\Relation;

use Exception;
use OrmLibrary\Entity\AbstractEntity;
use OrmLibrary\Entity\EntityRepository;
use OrmLibrary\Helpers;
use OrmLibrary\Query\Where;

class RelationOTM implements IRelation
{
    private AbstractEntity $owner;
    private string $relation;
    private array $value;

    public function __construct(AbstractEntity $owner, string $fqcn) {
        if (!Helpers::isEntity($fqcn))
            throw new Exception("A relation must be with a child entity of AbstractEntity class");
        $this->owner = $owner;
        $this->relation = $fqcn;
    }

    public function get():array {
        //Where::builder()
        /** @var AbstractEntity $relation */
        $relation = new ($this->relation)();
        $link = EntityRepository::getLink($this->owner::getName(), $relation::getName());
        $field = $link[array_key_first($link)];

        $w = Where::builder()->field($field)->value($this->owner->id->get())->entity($relation::getName())->build();
        $repository = $relation->getRepository();
        $data = $repository->select(fields: [$repository::getName() => ["Id"]], wheres: $w);
        var_dump($data);
        foreach ($data as $item)
            $this->value[] = new $this->relation($item["Id"]);
        return $this->value;
    }

    public function getLoaded():array {
        $result = $this->get();
        foreach ($result as $item) {
            //TODO : Optimiser le chargement des données
            $item->load();
        }
        return $result;
    }

}