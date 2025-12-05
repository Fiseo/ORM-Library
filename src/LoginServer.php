<?php


namespace OrmLibrary;

use PDO;
use PDOException;

class LoginServer
{
    private static PDO $instance;
    private string $user = "root";
    private string $password = "root";
    private string $server = ""; //Adresse du serveur à utiliser
    private string $base = ""; //Nom de la base de donnée à utiliser

    public function getPdo(): PDO
    {
        if (!isset(self::$instance)) {
            $dsn = "mysql:dbname=" . $this->base . ";host=" . $this->server;
            self::$instance = new PDO($dsn, $this->user, $this->password);
        }
        return self::$instance;
    }
}