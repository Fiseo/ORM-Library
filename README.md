# ORM-Library

A lightweight and expressive **ORM (Object-Relational Mapping)** for PHP, 
designed to simplify database interactions.

---

# Features

- Simple mapping between PHP objects and SQL tables
- Relationship support (```OneToOne```, ```OneToMany```, ```ManyToMany```)
- PDO compatible
- Lightweight, no heavy dependencies
- Supports CTI Inheritance
- Supports Json conversion

---

# ❗Rules❗
- The primary key field should always be named **Id**  
- The database storage engine must be **InnoDB**

---

# Quick start

## Define a connection
Create the connection at the start of your file
```PHP
use OrmLibrary\DbContext;

DbContext::setter()
    ->user("root")
    ->password("root")
    ->base("your_database_name")
    ->server("your_server_address")
    ->set();
```

## Create a repository
Create a repository to perform your queries by extending the abstract class ```EntityRepository```
```PHP
use OrmLibrary\Entity\EntityRepository;

class UserRepository extends EntityRepository
{
    protected static string $entityName = 'User';

}
```

## Create an entity

Create an entity for each of your tables by extending the abstract class ```AbstractEntity```    
To add a field, create a readonly property of a [field class](#fields) and initialize it in the constructor.  
❗ Do not create an entity for association tables !  
❗ Do not declare the **Id** field (done in ```AbstractEntity```) !  
❗ An error will be thrown if ```$entityName``` and ```$repositoryClass``` are not define !

```PHP
class User extends AbstractEntity
{
    protected static string $entityName = 'User';
    
    protected static string $repositoryClass = UserRepository::class;

    #[AField("Name", false)]
    readonly StringField $name;

    #[AField("FirstName", false)]
    readonly StringField $firstName;

    #[ARelationField("IdCivility", false)]
    readonly EntityField $civility;

    readonly RelationMTM $drivingLicenses;

    public function __construct($id = null){
        parent::__construct($id);

        $this->name = new StringField([$this, 'load']);
        $this->firstName = new StringField([$this, 'load']);

        $this->civility = new EntityField(Civilite::class,[$this, 'load']);

        $this->drivingLicenses = new RelationMTM($this, DrivingLicense::class, User_DrivingLicenseRepository::class);
    }
}
```



## Use it

This saves the data in the database.
```php
$p = new User();
$p->name->set('Doe');
$p->firstName->set('Jane');
$p->save();
```

This loads the data from the database.
```php
$p = new User(1);
$p->load();
```

This deletes the entity from the database.
```php
$p = new User(1)
$p->delete();
```

---

# Core Concept

## Fields
❗**All fields properties should have a ```readonly``` visibility**❗  
  
Fields represent a field of the database table.  
There are 5 field types as of now :
- ```StringField```
- ```IntField```
- ```FloatField```
- ```BoolField```
- ```DateField```

To add a field in your entity just add a property like this :
```php
#[AField("Name", false)]
readonly StringField $name;
```

The attribute ```AField()``` requires 2 arguments :  
- A String : The name of the field in your database.
- A boolean : True if your field allow nullable value, False otherwise.  

Now that your property is created, you need to instance it in the constructor.
```php
$this->name = new StringField([$this, 'load']);
```
The constructor of the fields requires 1 argument, but 2 optional arguments are available :

- The loader : the first argument will always be the loader
of your current entity. It allows lazy loading.  
If you want to disable lazy loading, give an empty ```Closure``` as this parameter.
```php
[$this, 'load'] // The loader
function () {} //An empty Closure
```

- The getter : the second argument is an optional getter. It allows
you to personalize the behavior of the getter. ❗**Do not forget to return a value.** ❗
```php
function () {
    return $this->value;
};
```

- The setter : the last argument is an optional setter. It allows
you to add your own verification in the setter. ❗**Do not forget to set the value.** ❗
```php
function ($value) {
    if($value == 'Exemple')
        throw new Exception('This string cannot be equal to "Exemple"')
    $this->value = $value;
};
```

## Relation
❗**All relations properties should have a ```readonly``` visibility**❗

### ManyToOne
A ```ManyToOne``` relation is represented by a property ```EntityField```.

To add a ```ManyToOne``` relation in your entity just add a property like this :
```php
#[ARelationField("IdCivility", false)]
readonly EntityField $civility;
```

The attribute ```ARelationField()``` requires 2 arguments :
- A String : The name of the foreign key in your database.
- A boolean : True if your field allow nullable value, False otherwise.

Then instance your property in the constructor :

```php
$this->civility = new EntityField(Civility:class,[$this, 'load']);
```
The constructor of this relation requires 2 arguments :
- The FQCN : The first argument needed is the fully qualified class name of your linked class.
- The loader : The second argument needed is the loader of your entity.  
    You can once again disable the lazy loading by giving an empty ```Closure```.

### OneToMany
A ```OneToMany``` relation is represented by a property ```RelationOTM```.

```php
$this->user = new RelationOTM($this, User::class);
```
The constructor of this relation requires 2 arguments :
- The instance : The first argument is the instance of the class the relation is defined in.
- The FQCN : The second argument needed is the fully qualified class name of your linked class.

### ManyToMany
A ```ManyToMany``` is represented by a property ```RelationMTM```.

```php
$this->drivingLicenses = new RelationMTM($this,
                                        DrivingLicense::class,
                                        User_DrivingLicenseRepository::class
                                        );
```

The constructor of this relation requires 3 arguments :
- The instance : The first argument is the instance of the class the relation is defined in.
- The first FQCN : The second argument needed is the fully qualified class name of your linked class.
- The second FQCN : The last argument needed is the fully qualified class name of the repository of the association table.

## Inheritance
To use inheritance, just make your child entity extends another entity.
```php
class Consumer extends User
{
    protected static string $entityName = 'Consumer';
    
    protected static string $repositoryClass = ConsumerRepository::class
    
    readonly RelationOTM $purchase;

    public function __construct($id = null){
        parent::__construct($id);
        
        $this->purchase = new RelationOTM($this, Purchase::class);
    }
}
```