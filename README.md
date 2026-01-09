# ORM-Library

A lightweight and expressive **ORM (Object-Relational Mapping)** for PHP, designed to simplify database interactions while keeping full control over your queries.

# Features

- Simple mapping between PHP objects and SQL tables
- Relationship support (```OneToOne```, ```OneToMany```, ```ManyToMany```)
- PDO compatible
- Lightweight, no heavy dependencies

# Quick start

## Define a connection
Create the connection at the start of your file
```PHP
use OrmLibrary\DbContext;

DbContext::setter()
    ->user("root")
    ->password("root")
    ->base("test1")
    ->server("localhost")
    ->set();
```

## Create a repository
Create a repository to make your queries by extending the abstract class ```EntityRepository```
```PHP
use OrmLibrary\Entity\EntityRepository;

class RoleRepository extends EntityRepository
{
    protected static string $entityName = 'Role';

}
```

## Create an entity
Create a entity for each of your table by extending the abstract class ```AbstractEntity```    
To add a field, create a readonly property of a TypeField and initialize it in the constructor. (A getter and setter ```closure``` can be provide to their constructor if needed)  
The different TypeField available are ```StringField```, ```IntField```, ```FloatField```, ```BoolField```, ```DateField``` and ```EntityField``` (```EntityField``` being a relation ```ManyToOne```)  
For each field, give them a ```AField``` attribute (```ARelationField``` for an ```EntityField```)  
Use ```RelationOneToMany``` and ```RelationManyToMany``` to express a ```OneToMany``` or ```ManyToMany``` relation  
❗ Do not create a entity for association tables  
❗ Do not declare the Id (done in ```AbstractEntity```)

```PHP
use App\Repository\AppartenirRepository;
use App\Repository\PersonneRepository;
use OrmLibrary\Entity\AbstractEntity;
use OrmLibrary\Field\AField;
use OrmLibrary\Field\TypeField\StringField;
use OrmLibrary\Relation\ARelationField;
use OrmLibrary\Relation\EntityField;
use OrmLibrary\Relation\RelationMTM;

class Personne extends AbstractEntity
{
    protected static string $entityName = 'Personne';

    #[AField("Nom", false)]
    readonly StringField $nom;

    #[AField("Prenom", false)]
    readonly StringField $prenom;


    #[ARelationField("IdCivilite", false)]
    readonly EntityField $civilite;

    #[ARelationField("IdRole", false)]
    readonly EntityField $role;

    readonly RelationMTM $permis;

    public function __construct($id = null){
        $this->repository = new PersonneRepository();
        parent::__construct($id);

        $this->nom = new StringField([$this, 'load']);
        $this->prenom = new StringField([$this, 'load']);

        $this->civilite = new EntityField(Civilite::class,[$this, 'load']);
        $this->role = new EntityField(Role::class,[$this, 'load']);

        $this->permis = new RelationMTM($this, Permis::class, AppartenirRepository::class);

    }


}
```

## Use it

This saves the data in the database
```php
$r = new Role();
$r->libelle->set("Admin");
$r->save();
```

This loads the data from the database
```php
$r = new Role(1);
$r->load();
$r->libelle->get();
```

# ❗Rules
The primary key field should always be name *Id*
The storage engine of the database needed to be InnoDb
