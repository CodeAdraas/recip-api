<?php

namespace app;

class SQL {

    private static $SQL;
    private static $DB;
    private static $Response;
    private $conn;
    private $sql;
    private $types;
    private $binds;
    private $status;
    private $rows;
    private $result;
    private $error;
    
    private function __construct($services) {
        foreach($services as $key => $service) self::${$key} = $service;
    }

    /**
     * Create new SQL
     */
    public static function create(array $services = []) {
        isset(self::$SQL) ?: self::$SQL = new SQL($services);
        return self::$SQL;
    }
    /**
    * Return param types string
    */
    private static function paramTypes(array $params = []) {
        $types = "";
        foreach($params as $param) {        
            if(is_int($param)) {
                $types .= 'i';              //integer
            } elseif (is_float($param)) {
                $types .= 'd';              //double
            } elseif (is_string($param)) {
                $types .= 's';              //string
            } else {
                $types .= 'b';              //blob and unknown
            }
        }
        return $types;
    }

    /**
    * Query
    */
    public function query(array $args) 
    {
        $required = ["sql"];

        foreach($required as $key) {
            if(!isset($args[$key])) {
                self::$Response::http(["code" => 400, "die"  => true]);
            }
        }

        $this->conn = isset($args["conn"]) ? $args["conn"] : self::$DB->open();

        if(!$this->conn)
        {
            self::$Response::http([ "code" => 500, "response" => [
                "status" => 500,
                "message" => "geen verbinding met database"
            ], "die" => true]);
        }
        else
        {
            $this->sql    = $args["sql"];
            $this->types  = isset($args["binds"]) ? self::paramTypes($args["binds"]) : false;
            $this->binds  = isset($args["binds"]) ? $args["binds"] : [];
            $this->status = false;
            $this->rows   = 0;
            $this->result = false;
            $this->error  = false;

            $stmt = $this->conn->prepare($this->sql);

            if($stmt)
            {
                if($stmt->execute($this->binds)) $this->status = true;
                if(isset($args["options"]["return"]) && $args["options"]["return"]) {
                    $data = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                    $this->rows = count($data);
                    if($data) $this->result = (isset($args["options"]["array"]) && $args["options"]["array"]) ? $data : $data[0];
                }
            }

            if(!self::$DB::ping($this->conn)) $this->error = true;
            if(isset($args["options"]["close"]) && $args["options"]["close"]) self::$DB::close($this->conn);
            if(isset($args["options"]["close"]) && $args["options"]["close"]) $this->conn = false;

            return array(
                "status" => $this->status,
                "conn"   => $this->conn,
                "rows"   => $this->rows,
                "result" => $this->result,
                "error"  => $this->error,
            );
        }
    }

}