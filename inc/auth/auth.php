<?php

namespace auth;

use Mollie\Api\MollieApiClient;
use GuzzleHttp\Client;

class Auth {

    public $userId;
    public $customerId;
    public $subscriptions;
    public $isRecurringUser;
    public $token;

    private static $Auth;
    private static $App;
    private static $SQL;
    private static $Date;
    private static $Response;
    public static $CSRF;
    private static $oAuth2;
    public static $Mollie;

    /**
     * Setup app properties
     */
    private function __construct($services) {
        foreach($services as $key => $service) self::${$key} = $service;
    }

    /**
     * Create new auth object
     */
    public static function create(array $services = []) {
        isset(self::$Auth) ?: self::$Auth = new Auth($services);
        return self::$Auth;
    }

    /**
     * Set cookie
     */
    public function setCookie(array $options) {
        if(!isset($options["name"]) || !isset($options["value"]) || !isset($options["time"])) return false;
        $domain = isset($options["domain"]) ? $options["domain"] : null;
        $secure = self::$App->testmode ? false : true;
        $httpOnly = (isset($options["httpOnly"]) && !$options["httpOnly"]) ? false : true;
        return (setcookie($options["name"], $options["value"], time() + $options["time"], "/", $domain, $secure, $httpOnly)) ? true : false;
    }

    /**
     * Remove cookie
     */
    public function remCookie(string $name) {
        return (setcookie($name, "", time() - 300, "/", null, false, true)) ? true : false;
    }

    /**
     * Get cookie
     */
    public function getCookie(string $name) {
        return (!isset($_COOKIE[$name])) ? false : $_COOKIE[$name];
    }

    /**
     * Handle thrown exceptions
     */
    public function handleException( $message ) {
        if( is_string( $message ) ) {
            self::$Response::http([ "code" => 500, "response" => [
                "status" => 500,
                "error" => $message
            ]], true );
        } else {        
            self::$Response::http([ "code" => $message ], true );
        }
    }

    /**
     * Get Bearer token
     */
    public static function getBearer() {
        $token = false;
        $headers = apache_request_headers();
        if(isset($headers['Authorization'])){
            $exploded = explode(" ", $headers['Authorization']);
            $token = isset($exploded[1]) ? explode(" ", $headers['Authorization'])[1] : false;
        } 

        return $token;
    }

    /**
     * Get JSON payload
     */
    public static function payload() {
        $request_body = file_get_contents('php://input');
        return json_decode($request_body, true);
    }

    /**
     * Sanitize data
     */
    public function sanitize($SUPERGLOBAL, array $allowed, array $required) { 
        $data  = [];

        if(empty($SUPERGLOBAL)) return false;

        foreach($SUPERGLOBAL as $key => $value) if(!in_array($key, $allowed)) return false;

        foreach($required as $key)
        {
            if(!isset($SUPERGLOBAL[$key])) return false;
            if(empty($SUPERGLOBAL[$key])) return false;
        }

        foreach($SUPERGLOBAL as $key => $value) $data[$key] = (!is_array($value)) ? htmlspecialchars($value) : $value;

        return $data;
    }

    /**
     * Sanitize data
     */
    public function sanitizeObj($SUPERGLOBAL, array $allowed, array $required) { 
        $data  = [];

        if(empty($SUPERGLOBAL)) return false;

        foreach($SUPERGLOBAL as $obj) foreach($obj as $key => $value) if(!in_array($key, $allowed)) return false;

        foreach($required as $required_key) foreach($SUPERGLOBAL as $obj) foreach($obj as $key => $value)
        {
            if(!isset($obj[$required_key])) return false;
            if(is_bool($obj[$key]) && !$obj[$key]) continue;
            if(empty($obj[$key]) || $obj[$key] === "") return false;
        }

        foreach($SUPERGLOBAL as $obj)
        {
            $newObj = [];

            foreach($obj as $key => $value)
            {
                if(is_array($key)) 
                {
                    $newObj[$key] = $value;
                    continue;
                }
                if(is_bool($value)) 
                {
                    $newObj[$key] = $value ? true : false;
                    continue;
                }
                $newObj[$key] = htmlspecialchars($value);
            }
            
            array_push($data, $newObj);
        }

        return $data;
    }

    /**
     * Token flow
     */
    public function flow() {

        if( !$this->getBearer() ) return false;

        $this->token = base64_decode( $this->getBearer() );

        $flow = self::$SQL->query([
            "sql"     => "SELECT expire_date FROM sessions WHERE token=? AND type=?", 
            "binds"   => [$this->token, "session"],
            "options" => [ "return" => true, "close" => true ]
        ]);

        if(!$flow["rows"]) return false;

        $this->userId = substr($this->token, 0, strpos($this->token, "_rec_"));
        self::$Mollie->userId = $this->userId;

        $customerId = self::$SQL->query([
            "sql" => "SELECT meta_value FROM user_meta WHERE spot_id=? AND meta_key=?",
            "binds" => [ $this->userId, "customer_id" ],
            "options" => ["return" => true, "close" => true]
        ]);

        $this->customerId = $customerId["rows"] ? $customerId["result"]["meta_value"] : null;
        self::$Mollie->customerId = $this->customerId;
        
        $subscriptions = self::$SQL->query([
            "sql" => "SELECT subscription_id FROM subscriptions WHERE spot_id=?",
            "binds" => [ $this->userId ],
            "options" => ["return" => true, "array" => true, "close" => true]
        ]);

        $this->subscriptions = $subscriptions["rows"] ? $subscriptions["result"] : [];

        $this->isRecurringUser = self::$SQL->query([
            "sql" => "SELECT meta_value FROM user_meta WHERE spot_id=? AND meta_key=? AND meta_value=?",
            "binds" => [ $this->userId, "user_first_time", "false"],
            "options" => ["return" => true, "close" => true]
        ])["rows"];

        return (self::$Date::isExpired(self::$Date::ISOToSt($flow["result"]["expire_date"]))) ? false : true;
    } 

    /**
     * Init Guzzle HTTP client
     */
    public function client() {
        return new Client();
    }

    /**
     * Request oAuth2 access token
     */
    public function grantAuth( string $code, string $redirect_uri ) {
        return self::$oAuth2->grantAuth( $this->client(), $code, $redirect_uri );
    }

    /**
     * Save oAuth2 access token
     */
    public function saveAccess( $obj ) {
        $response = self::$oAuth2->saveAccess( $this->client(), $obj );
        if( !$response ) return false;
        if( !$response["exists"] ) $this->createUser( $response["data"] );
        return $response["session"];
    }

    /**
     * Save new user in database
     * called when new user authenticates via oAuth2
     */
    private function createUser(array $obj) {

        $obj["user_first_time"] = "true";
        $obj["customer_id"] = self::$Mollie->createCustomer( $obj["id"], $obj["email"] );
        
        foreach($obj as $key => $value) {

            if($key === "external_urls" || $key === "followers" || $key === "href" || $key === "uri" || $key === "type") continue;
            if($key === "images") $value = isset($value[0]["url"]) ? $value[0]["url"] : null;

            self::$SQL->query([
                "sql" => "INSERT INTO user_meta (spot_id, meta_key, meta_value) VALUES (?, ?, ?)", 
                "binds" => [$obj["id"], $key, $value],
                "options" => [ "close" => true ]
            ]);
        }
    }

    /**
     * Refresh oAuth2 access token
     */
    public function refreshAccess( string $refresh_token ) {
        return self::$oAuth2->refreshAccess( $this->client(), $this->userId, $refresh_token );
    }

    public function checkMandates() {
        return self::$Mollie->checkMandates();
    }

    public function pay( string $value, bool $isFirstType = false) {
        return self::$Mollie->pay( self::$CSRF::create(true), $value, $isFirstType );
    }

    public function payment( string $id, bool $returnObj = false) {
        return self::$Mollie->payment($id, $returnObj);
    }

    public function checkSubscriptions() {
        return self::$Mollie->checkSubscriptions();
    }

    public function createSubscription(string $value, string $interval) {
        return self::$Mollie->checkMandates() ? self::$Mollie->createSubscription( self::$CSRF::create(true), $value, $interval) : false;
    }

    public function subscription( string $id, bool $returnObj = false) {
        return self::$Mollie->subscription($id, $returnObj);
    }

    public function cancelSubscription(string $value) {
        return self::$Mollie->cancelSubscription( $value );
    }

}