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
    private $queries = [];
    
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

    public function queries( int $index = 0) {
        return isset( $this->queries[ $index - 1 ] ) ? $this->queries[ $index - 1 ] : false;
    }

    /**
    * Check if given array is associative or not
    */
    public static function isAssoc(array $arr){
        if (array() === $arr) return false;
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    /**
    * Query
    */
    public function query(array $args) 
    {

        $required = ["sql"];
        foreach($required as $key) if(!isset($args[$key])) throw new \Exception("Missing required query fields");

        $this->conn = isset($args["conn"]) ? $args["conn"] : self::$DB->open();
        if(!$this->conn) throw new \Exception("No connection with database");

        $this->sql    = $args["sql"];
        $this->types  = isset($args["binds"]) ? self::paramTypes($args["binds"]) : false;
        $this->binds  = isset($args["binds"]) ? $args["binds"] : [];
        $this->status = false;
        $this->rows   = 0;
        $this->result = false;
        $this->error  = false;

        try {

            $stmt = $this->conn->prepare($this->sql);
            $stmt->execute($this->binds);

            if(isset($args["options"]["return"]) && $args["options"]["return"]) {
                $data = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                $this->rows = count($data);
                if($data) {
                    $this->result = (isset($args["options"]["array"]) && $args["options"]["array"]) ? $data : $data[0];

                    if( !self::isAssoc( $this->result ) ) {                      
                        foreach( $this->result as $i => $obj ) {
                            foreach( $obj as $key => $value ) {
                                if( $value === "true" ) {
                                    $this->result[$i][$key] = true;
                                } elseif ( $value === "false") {
                                    $this->result[$i][$key] = false;
                                }
                            }
                        }
                    } else {
                        foreach( $this->result as $key => $value ) {
                            if( $value === "true" ) {
                                $this->result[$key] = true;
                            } elseif ( $value === "false") {
                                $this->result[$key] = false;
                            }
                        }
                    }
                }
            }

            if(isset($args["options"]["close"]) && $args["options"]["close"]) {
                self::$DB::close($this->conn);
                $this->conn = false;
            }

            array_push($this->queries, array(
                "status" => $this->status,
                "conn"   => $this->conn,
                "rows"   => $this->rows,
                "result" => $this->result
            ));

            return self::$SQL;
        } catch( \PDOException $e) {
            throw new \Exception( $e->getMessage() );
        }
    }

}