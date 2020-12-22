<?php

$CRSF_token = self::$Auth::$CSRF::get();

if(!self::$Auth::$CSRF::check($CRSF_token)) self::$Response::http([ "code" => "401", "die" => true]);

/**
 * Check if there already is an active payment going
 * Redirect to checkout if true
 */

$checkOngoingPayment = self::$SQL->query([
    "sql"      => "SELECT url FROM payments WHERE spot_id=? AND status IS NULL ORDER BY id DESC LIMIT 1",
    "binds"    => [ self::$Auth->userId ],
    "options"  => [ "return" => true, "close" => true ]
]);

if( $checkOngoingPayment["rows"] ) self::$Response::http([ 
    "code" => 200, 
    "response" => [ //JSON object containing field representing checkout URL
        "url" => $checkOngoingPayment["result"]["url"]
    ],
    "die" =>  true
]);

/**
 * Create payment
 */
self::$Response::http( self::$Auth->pay( "4.00", !self::$Auth->checkMandates() ) );