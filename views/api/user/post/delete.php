<?php

$CRSF_token = self::$Auth::$CSRF::get();

if(!self::$Auth::$CSRF::check($CRSF_token)) self::$Response::http([ "code" => "401", "die" => true]);

self::$Auth::$CSRF::destroy($CRSF_token);

$subscription = self::$SQL->query([
    "sql" => "SELECT subscription_id AS id FROM subscriptions WHERE spot_id=? ORDER BY id DESC LIMIT 1",
    "binds" => [ self::$Auth->userId ],
    "options" => [ "close" => true, "return" => true ]
])["result"];

$cancelSubscription = self::$Auth->cancelSubscription( $subscription["id"] );

if($cancelSubscription) {

    $deleteUser = self::$SQL->query([
        "sql" => "DELETE FROM user_meta WHERE spot_id=?",
        "binds" => [ self::$Auth->userId ]
    ]);

    $last_updated = self::$Date::current(true);
    $updateSubscription = self::$SQL->query([
        "sql" => "UPDATE subscriptions SET canceled=?, last_updated=? WHERE spot_id=?",
        "binds" => [ "true", $last_updated, self::$Auth->userId ],
        "options" => [ "close" => true ]
    ]);

    self::$Auth->remCookie("s_t");

    if( !$cancelSubscription || !$deleteUser["status"] || !$updateSubscription["status"] ) self::$Response::http(["code" => "500", "die" => true]);
} else {
    self::$Response::http(["code" => "500", "die" => true]);
}