<?php

if( !empty($_POST) || self::$Auth::payload() ) self::$Response::http([ "code" => 400, "die" => true ]);

$user_meta = self::$SQL->query([
    "sql" => "SELECT meta_key, meta_value FROM user_meta WHERE spot_id=? AND meta_key NOT IN (?, ?, ?)",
    "binds" => [ self::$Auth->userId, "id", "customer_id", "user_first_time"],
    "options" => ["return" => true, "array" => true, "close" => true]
]);

if( !$user_meta["rows"] ) self::$Response::http([ "code" => 404, "die" => true ]);

$userObj = [];

foreach( $user_meta["result"] as $meta) $userObj[$meta["meta_key"]] = $meta["meta_value"];

$userObj["has_subscription"] = !empty(self::$Auth->subscriptions);
$userObj["recurring_user"] = self::$Auth->isRecurringUser ? true : false;

self::$Response::http([ "code" => 200, "response" => $userObj ]);