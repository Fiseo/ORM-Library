<?php


namespace OrmLibrary;

use PDO;
use PDOException;

class DbContext
{
    private static ?PDO $instance;
    private static string $user;
    private static string $password;
    private static string $server; //Adresse du serveur à utiliser
    private static string $base; //Nom de la base de donnée à utiliser

    private function __construct() {}

    public static function getPdo():PDO {
        if(!isset(self::$user) || !isset(self::$password) || !isset(self::$server) || !isset(self::$base))
            throw new PDOException("PDO connection settings are not set");
        if (!isset(self::$instance)) {
            $dsn = "mysql:dbname=" . self::$base . ";host=" . self::$server;
            try {
                self::$instance = new PDO($dsn, self::$user, self::$password);
            } catch (PDOException $e) {
                throw new \Exception("Something went wrong while connecting to database");
            }
        }
        return self::$instance;
    }

    public static function getBase():string {
        return self::$base;
    }

    public static function setServer(string $server): void
    {
        self::$instance = null;
        self::$server = $server;
    }

    public static function setBase(string $base): void
    {
        self::$instance = null;
        self::$base = $base;
    }

    public static function setPassword(string $password): void
    {
        self::$instance = null;
        self::$password = $password;
    }

    public static function setUser(string $user): void
    {
        self::$instance = null;
        self::$user = $user;
    }

    public static function setter():DbContextSetter {
        return new DbContextSetter();
    }


}

class DbContextSetter {
    private string $user;
    private string $password;
    private string $server; //Adresse du serveur à utiliser
    private string $base; //Nom de la base de donnée à utiliser

    public function user(string $user):DbContextSetter {
        $this->user = $user;
        return $this;
    }
    public function password(string $password):DbContextSetter {
        $this->password = $password;
        return $this;
    }
    public function server(string $server):DbContextSetter {
        $this->server = $server;
        return $this;
    }
    public function base(string $base):DbContextSetter {
        $this->base = $base;
        return $this;
    }

    public function set():void {
        DbContext::setUser($this->user);
        DbContext::setPassword($this->password);
        DbContext::setServer($this->server);
        DbContext::setBase($this->base);
    }
}