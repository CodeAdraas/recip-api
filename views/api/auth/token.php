<?php

$CRSF_token = self::$Auth::$CSRF::get();

if(!self::$Auth::$CSRF::check($CRSF_token)) self::$Response::http([ "code" => "401", "die" => true]);

self::$Auth::$CSRF::destroy($CRSF_token);

$access_token = self::$SQL->query([
    "sql" => "SELECT token, expire_date FROM sessions WHERE token like ? AND type=? ORDER BY id DESC LIMIT 1",
    "binds" => ["%" . self::$Auth->userId . "%", "access_token"],
    "options" => [ "close" => true, "return" => true]
])["result"];

if(self::$Date::isExpired(self::$Date::ISOToSt($access_token["expire_date"]))) {

    $refresh_token = self::$SQL->query([
        "sql" => "SELECT token, expire_date FROM sessions WHERE token like ? AND type=? ORDER BY id DESC LIMIT 1",
        "binds" => ["%" . self::$Auth->userId . "%", "refresh_token"],
        "options" => ["close" => true, "return" => true]
    ])["result"]["token"];

    $access_token = self::$Auth->refreshAccess( substr($refresh_token, ( strpos( self::$Auth->s_t, "_rec_") + 5 ) ) );

    if( $access_token ) self::$Response::http([
        "code" => "200",
        "response" => [
            "access_token" => $access_token
        ],
        "die" => true
    ]);

    self::$Response::http([ "code" => "500", "die" => true]);
}

$access_token = substr($access_token["token"], ( strpos(self::$Auth->s_t, "_rec_") + 5));

self::$Response::http([
    "code" => "200",
    "response" => [
        "access_token" => $access_token
    ]
]);