<?php

/**
 * Sanitize data
 */
$data = self::$Auth->sanitize($_GET, ["token"], ["token"]);
if( !$data || !self::$Auth::$CSRF::check($data["token"]) ) self::$Response::http(["code" => 401, "die" => true]);

$data = self::$Auth->sanitize($_POST, ["id", "subscriptionId"], ["id", "subscriptionId"]);
if( !$data ) self::$Response::http(["code" => 400, "response" => [ "status" => "400", "error" => "subscription id nor payment id was provided" ], "die" => true]);

/**
 * Retrieve user ID
 */
self::$Auth::$Mollie->userId = self::$SQL->query([
    "sql" => "SELECT spot_id FROM subscriptions WHERE subscription_id=? ORDER BY id LIMIT 1",
    "binds" => [ $data["subscriptionId"] ],
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
 * Insert or update payment
 */
$payment = self::$Auth->payment( $data["id"], true );
$subscription = self::$Auth->subscription( $data["subscriptionId"] );
$current_time = self::$Date::current(true);

$check = self::$SQL->query([
    "sql" => "SELECT id FROM payments WHERE spot_id=? AND payment_id=? ORDER BY id LIMIT 1",
    "binds" => [ self::$Auth::$Mollie->userId, $payment->id ],
    "options" => ["return" => true, "close" => true]
])["rows"];

$update = self::$SQL->query([
    "sql" => !$check 
        ? "INSERT INTO payments ( spot_id, customer_id, payment_id, subscription_id, type, status, created_at) VALUES ( ?, ?, ?, ?, ?, ?, ? )"
        : "UPDATE payments SET status=?, last_updated=? WHERE spot_id=? AND payment_id=?",
    "binds" => !$check
        ? [ self::$Auth::$Mollie->userId, self::$Auth->customerId, $payment->id, $subscription["id"], "recurring", $payment->status, $current_time ]
        : [ $payment->status, $current_time, self::$Auth::$Mollie->userId, $payment->id ],
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

    $querys = [[
        "sql" => "UPDATE subscriptions SET next_payment_date=?, last_updated=? WHERE spot_id=? AND subscription_id=?",
        "binds" => [ $subscription["nextPaymentDate"], $current_time, self::$Auth::$Mollie->userId, $subscription->id ],
    ]];

    foreach($querys as $query) self::$SQL->query([
        "sql" => $query["sql"],
        "binds" => $query["binds"],
        "options" => ["close" => true]
    ]);
        
} elseif ( $payment->isFailed() || $payment->isExpired() || $payment->isCanceled() ) {
    /*
     * The payment has failed.
     * Webhook callable 
     */
}

self::$Response::http([ "code" => 200, "die" => true ]);