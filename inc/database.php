<?php

namespace app;

use \Exception;
use \PDO;

class Database {

    private static $DB;
    private $host;
    private $user;
    private $pass;
    private $db;

    private function __construct(array $config) {
        $this->host = isset($config["host"]) ? $config["host"] : "";
        $this->user = isset($config["user"]) ? $config["user"] : "";
        $this->pass = isset($config["pass"]) ? $config["pass"] : "";
        $this->db   = isset($config["db"]) ? $config["db"] : "";
    }

    /**
     * Create new database
     */
    public static function create(array $config = []) {
        if(!isset(self::$DB)) self::$DB = new Database($config);
        return self::$DB;
    }

    public function open() {
        // $conn = mysqli_connect($this->host, $this->user, $this->pass, $this->db);
        $conn = new \PDO("mysql:host=".$this->host.";dbname=".$this->db, $this->user, $this->pass);
        return !$conn ? "kon geen verbinding maken" : $conn;
    }
    
    public static function ping($conn = false) {
        return (!$conn || !$conn->query('SELECT 1')) ? false : true; 
    }

    public static function close($conn = false) {
        $conn = null;
        return true;
    }

}