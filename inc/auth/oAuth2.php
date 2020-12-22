<?php

namespace auth;

use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Exception\RequestException;

class oAuth2 {

    private static $oAuth2;
    private static $SQL;
    private static $Date;

    public $clientId;
    public $clientSecret;
    private $userId;

    /**
     * Setup app properties
     */
    private function __construct($services) {

        foreach($services as $key => $service) self::${$key} = $service;

        $this->clientId = $_ENV['SPOTIFY_CLIENT_ID'];
        $this->clientSecret = $_ENV['SPOTIFY_CLIENT_SECRET'];

    }

    /**
     * Create new auth object
     */
    public static function create(array $services = []) {
        isset(self::$oAuth2) ?: self::$oAuth2 = new oAuth2($services);
        return self::$oAuth2;
    }

    /**
     * Request access token
     * Called on authentication via user login
     */
    public function grantAuth($client, string $code, string $redirect_uri) {
        $promise = $client->requestAsync(
            'POST', 'https://accounts.spotify.com/api/token', [
            'form_params' => [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $redirect_uri
            ],
            "headers" => [
                "Authorization" => "Basic " . base64_encode($this->clientId . ":" . $this->clientSecret),
                'Accept' => 'application/json',
            ]
        ])->then( 
            fn(ResponseInterface $res) => json_decode($res->getBody(), true),
            fn(RequestException $e) => false
        );

        return $promise->wait();
    }

    /**
     * Save access token
     * New user, create user [createUser()]
     */
    public function saveAccess($client, array $obj ) {
        
        $promise = $client->requestAsync(
            'GET', 'https://api.spotify.com/v1/me', [
            "headers" => [
                "Authorization" => "Bearer " . $obj["access_token"],
                'Accept' => 'application/json'
            ]
        ])->then( 
            fn(ResponseInterface $res) => json_decode($res->getBody(), true),
            fn(RequestException $e) => false
        );

        $response = $promise->wait();

        /* =============== */

        if(!$response) return false;

        $created_at = self::$Date::current(true);
        $s_t = $response["id"]."_rec_".bin2hex(random_bytes(16));
        $access_token = $response["id"] . "_rec_" . $obj["access_token"];
        $refresh_token = $response["id"] . "_rec_" . $obj["refresh_token"];

        $checkUser = self::$SQL->query([
            "sql" => "SELECT spot_id FROM user_meta WHERE spot_id=? LIMIT 1", 
            "binds" => [ $response["id"] ],
            "options" => [ "close" => true, "return" => true ]
        ]);

        $expire_date = self::$Date::stToISO( self::$Date::create( (3600 * 4) ));

        $addSession = self::$SQL->query([
            "sql" => "INSERT INTO sessions (type, scope, token, expires_in, expire_date, created_at) VALUES ( ?, ?, ?, ?, ?, ?)", 
            "binds" => ["session", "app", $s_t, (3600 * 4) , $expire_date, $created_at],
            "options" => [
                "close" => true
            ]
        ]);

        $expire_date = self::$Date::stToISO( self::$Date::create($obj["expires_in"]) );
        
        $addAcces = self::$SQL->query([
            "sql" => "INSERT INTO sessions (type, scope, token, expires_in, expire_date, created_at) VALUES ( ?, ?, ?, ?, ?, ?)", 
            "binds" => ["access_token", $obj["scope"], $access_token, $obj["expires_in"], $expire_date, $created_at],
            "options" => [
                "close" => true
            ]
        ]);

        $addRefresh = self::$SQL->query([
            "sql" => "INSERT INTO sessions (type, scope, token, expires_in, expire_date, created_at) VALUES ( ?, ?, ?, ?, ?, ?)", 
            "binds" => ["refresh_token", $obj["scope"], $refresh_token, null, null, $created_at],
            "options" => [
                "close" => true
            ]
        ]);

        return ($addSession["status"] && $addAcces["status"] && $addRefresh["status"]) ? [
            "exists" => $checkUser["rows"],
            "data" => $response,
            "session" => ["name" => "s_t", "value" => base64_encode($s_t), "time" => (3600 * 4), "httpOnly" => false]
        ] : false;
    }

    /**
     * Refresh access token
     * Callable via the token API endpoint and directly returns a new access token uppon succes
     */
    public function refreshAccess($client, string $userId, string $token ) {

        $this->userId = $userId; /* Fixes scope issue with promise */

        $promise = $client->requestAsync(
            'POST', 'https://accounts.spotify.com/api/token', [
            'form_params' => [
                'grant_type' => 'refresh_token',
                'refresh_token' => $token
            ],
            "headers" => [
                "Authorization" => "Basic " . base64_encode($this->clientId . ":" . $this->clientSecret),
                'Accept' => 'application/json',
            ]
        ])->then(
            function (ResponseInterface $res) {
    
                $obj = json_decode($res->getBody(), true);

                $created_at = self::$Date::current(true);
                $expire_date = self::$Date::stToISO(self::$Date::create($obj["expires_in"]));
                $access_token = $this->userId . "_rec_" . $obj["access_token"];
                $type = "access_token";

                $addAcces = self::$SQL->query([
                    "sql" => "INSERT INTO sessions (type, scope, token, expires_in, expire_date, created_at) VALUES ( ?, ?, ?, ?, ?, ?)", 
                    "binds" => [$type, $obj["scope"], $access_token, $obj["expires_in"], $expire_date, $created_at],
                    "options" => [
                        "close" => true
                    ]
                ]);

                return $addAcces["status"] ? $obj["access_token"] : false;
            },
            function (RequestException $e) {
                return false;
            }
        );

        return $promise->wait();
    }

}