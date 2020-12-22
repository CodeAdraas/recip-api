<?php

namespace auth;

class CSRF {

    private static $CSRF;
    private static $SQL;
    private static $Date;

    /**
     * Setup app properties
     */
    private function __construct($services) {
        foreach($services as $key => $service) self::${$key} = $service;
    }

    /**
     * Create new auth object
     * Named "createInstance" to not interfere with "create" method
     */
    public static function createInstance(array $services = []) {
        isset(self::$CSRF) ?: self::$CSRF = new CSRF($services);
        return self::$CSRF;
    }

    /**
     * Create anti-CSRF token
     */
    public static function create(bool $nonExpirable = false) {
        $type = "csrf";
        $scope = "client";
        $created_at = self::$Date::current(true);
        $expires_in = $nonExpirable ? NULL : 60 * 5;
        $expire_date = $nonExpirable ? NULL : self::$Date::stToISO(self::$Date::create($expires_in));
        $token = bin2hex(random_bytes(16));
        
        self::$SQL->query([
            "sql" => "INSERT INTO sessions (type, scope, token, expires_in, expire_date, created_at) VALUES ( ?, ?, ?, ?, ?, ?)", 
            "binds" => [$type, $scope, $token, $expires_in, $expire_date, $created_at],
            "options" => [
                "close" => true
            ]
        ]);

        return $token;
    }

    /**
     * Get CSRF token
     */
    public static function get() {
        $headers = apache_request_headers();
        return isset($headers['x-csrf-token']) 
            ? $headers['x-csrf-token']
            : isset($headers['X-Csrf-Token']) 
                ? $headers['x-csrf-token']
                : false;
    }

    /**
     * Check if anti-CSRF token is valid
     */
    public static function check(string $token = "") {
        if($token === "") return false;

        $flow = self::$SQL->query([
            "sql" => "SELECT expire_date FROM sessions WHERE type=? AND token=?", 
            "binds" => ["csrf", $token],
            "options" => [
                "return" => true,
                "close" => true
            ]
        ]);

        if(!$flow["rows"]) return false;

        return (self::$Date::isExpired(self::$Date::ISOToSt($flow["result"]["expire_date"])) && !is_null($flow["result"]["expire_date"]) ) ? false : true;
    }
    
    /**
     * Destroy anti-CSRF token
     */
    public static function destroy(string $token = "") {
        if($token === "") return false;

        $delete = self::$SQL->query([
            "sql" => "DELETE FROM sessions WHERE type=? AND token=?", 
            "binds" => ["csrf", $token],
            "options" => ["close" => true]
        ]);
    }

}