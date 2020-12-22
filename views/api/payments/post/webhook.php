<?php

/**
 * Sanitize data
 */
$data = self::$Auth->sanitize($_GET, ["token"], ["token"]);
if( !$data || !self::$Auth::$CSRF::check($data["token"]) ) self::$Response::http(["code" => 401, "die" => true]);

$data = self::$Auth->sanitize($_POST, ["id"], ["id"]);
if( !$data ) self::$Response::http(["code" => 401, "die" => true]);

/**
 * Retrieve user ID
 */
self::$Auth::$Mollie->userId = self::$SQL->query([
    "sql" => "SELECT spot_id FROM payments WHERE payment_id=? ORDER BY id LIMIT 1",
    "binds" => [ $data["id"] ],
    "options" => ["return" => true, "close" => true]
])["result"]["spot_id"];

/**
 * Retrieve customer ID
 */
self::$Auth::$Mollie->customerId = self::$SQL->query([
    "sql" => "SELECT meta_value FROM user_meta WHERE spot_id=? AND meta_key=?",
    "binds" => [ self::$Auth::$Mollie->userId, "customer_id" ],
    "options" => ["return" => true, "close" => true]
])["result"]["meta_value"];

/**
 * Retrieve payment via Mollie
 */
$payment = self::$Auth->payment( $data["id"], true );

/**
 * Update payment status
 */
$update = self::$SQL->query([
    "sql" => "UPDATE payments SET status = ? WHERE payment_id=?",
    "binds" => [$payment->status, $payment->id],
    "options" => ["close" => true]
]);

/**
 * Check status of payments
 */
if ( $payment->isPaid() ) {
    /*
     * The payment has been paid.
     * Webhook callable 
     */

    if ( !self::$Auth->checkSubscriptions() ) self::$Auth->createSubscription( "3.99", "1 month");
        
} elseif ( $payment->isFailed() || $payment->isExpired() || $payment->isCanceled() ) {
    /*
     * The payment has failed.
     * Webhook callable 
     */
}

self::$Response::http([ "code" => 200, "die" => true ]);