<?php

namespace OrmLibrary\Relation;

enum ERelation: string
{
    case OTM = "OneToMany";
    case MTO = "ManyToOne";
    case MTM = "ManyToMany";

}
