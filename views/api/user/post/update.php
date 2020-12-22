<?php

$CRSF_token = self::$Auth::$CSRF::get();

if(!self::$Auth::$CSRF::check($CRSF_token)) self::$Response::http([ "code" => "401", "die" => true]);

self::$Auth::$CSRF::destroy($CRSF_token);

$data = self::$Auth->sanitize(self::$Auth::payload(), ["user"], ["user"]);
if(!$data) self::$Response::http(["code" => "400", "die" => true]);

$updates = self::$Auth->sanitizeObj($data["user"], ["key", "value"], ["key", "value"]);
if(!$updates) self::$Response::http(["code" => "400", "die" => true]);
foreach( $updates as $update ) if( $update["key"] !== "email" ) self::$Response::http(["code" => "400", "die" => true]);

foreach( $updates as $update ) {
    
    $update = self::$SQL->query([
        "sql" => "UPDATE user_meta SET meta_value=? WHERE spot_id=? AND meta_key=?",
        "binds" => [ $update["value"], self::$Auth->userId, $update["key"] ]
    ]);

    if( !$update["status"] ) self::$Response::http(["code" => 500, "die" => true]);
}

self::$Response::http(["code" => "204", "die" => true]);