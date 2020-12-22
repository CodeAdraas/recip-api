<?php

namespace app;

class Response {

    private static $Response;
    private static $allowed_codes;
    private static $allowed_types;
    private static $response;
    private static $code;
    private static $type;
    
    private function __construct() {

    }

    /**
     * Create new database
     */
    public static function create() 
    {
        isset(self::$Response) ?: self::$Response = new Response();
        return self::$Response;
    }
    
    /**
	 * Echo user error
	 */
    public static function error(string $response = "")
    {
        echo $response;
        die();
    }

    /**
	 * Return json encoded data
	 */
    public static function JSON($response)
    {
        return json_encode($response);
    }
    
    /**
	 * Return data html encoded
	 */
    public static function HTML($response)
    {
        return htmlspecialchars_decode($response);
    }

    /**
	 * Return http response including code
	 */
    public static function http($args) 
    {
        self::$allowed_codes = [200, 201, 204, 400, 401, 403, 404, 409, 500];
        self::$allowed_types = ["html"];
        self::$response      = false;
        if(!isset($args["code"])) 
        {
            self::error("No code was set");
        } else
        {
            self::$code = $args["code"];
            self::$type = isset($args["type"]) ? $args["type"] : "json";
            self::$response = isset($args["response"]) ? $args["response"] : false;

            if(!in_array(self::$code, self::$allowed_codes)) self::error("Code illegal");
            if(self::$code === "204" && isset($args["response"])) self::error("Can't return response with code 204");
            if(isset($args["type"]) && !in_array(self::$type, self::$allowed_types)) self::error("Type illegal");

            $code = self::$code;
            header("HTTP/1.1 {$code}");

            if(self::$response && self::$type === "json")
            {
                header("Content-Type: application/json; charset=utf-8");
                echo self::JSON(self::$response);
            } else if(self::$response && self::$type === "html") 
            {
                header('Content-Type: text/html; charset=utf-8');
                echo self::HTML(join(" ", self::$response));
            }

            if(isset($args["die"]) && $args["die"]) die();
        }
    }

}