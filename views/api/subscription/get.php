<?php

/**
 * TEST RUN
 */

try {

    $userFirsTime = self::$SQL->query([
        "sql" => "SELECT subscription_id AS id, start_date, min_lasts_until AS min_period, canceled FROM subscriptions WHERE spot_id=?",
        "binds" => [ "d_achoendov" ],
        "options" => [ "return" => true, "array" => true ]
    ])->query([
        "sql" => "SELECT meta_value FROM user_meta WHERE spot_id=? AND meta_key=?",
        "binds" => [ "d_achoendov", "user_first_time"],
        "options" => [ "return" => true ]
    ])->queries(2)["result"]["meta_value"];


    self::$Response::http([ "code" => 200, "response" => [
        "subscriptions" => self::$SQL->queries(1)["result"],
        "user_first_time" => $userFirsTime
    ]], true );

} catch( \Exception $e ) {

    self::$Auth->handleException( $e->getMessage() );
    
}