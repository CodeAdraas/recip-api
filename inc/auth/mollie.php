<?php

namespace auth;

use Mollie\Api\MollieApiClient;
use Mollie\Api\Exceptions\ApiException;

class Mollie {

    private static $Mollie;
    private static $App;
    private static $SQL;
    private static $Date;
    public $client;
    public $userId;
    public $customerId;
    public $hasPendingMandate = false;

    /**
     * Setup app properties
     */
    private function __construct($services) {
        foreach($services as $key => $service) self::${$key} = $service;

        $this->client = new MollieApiClient();
        $this->client->setApiKey( $_ENV["MOLLIE_API_KEY"] );
    }

    /**
     * Create new Mollie object
     */
    public static function create(array $services = []) {
        isset(self::$Mollie) ?: self::$Mollie = new Mollie($services);
        return self::$Mollie;
    }

    /**
     * Save Mollie customer in database
     */
    public function createCustomer( string $userId, string $email ) {
        try {

            return $this->client->customers->create([
                "email" => $email,
                "metadata" => [
                    "spot_id" => $userId
                ]
            ])->id;

        } catch (ApiException $e) {
            return $e->getMessage();
        }
    }

    /**
     * Check if the user has a valid mandate
     */
    public function checkMandates() {
        try {
            $mandates = $this->client->customers->get( $this->customerId )->mandates();
            $status = false;

            foreach( $mandates as $mandate ) {
                if( $mandate->status === "valid" ) {

                    $status = true;
                    break;

                } else if( $mandate->status === "pending" ) {

                    $status = true;
                    $this->hasPendingMandate = true;
                    break;

                }
            }

            return $status;

        } catch (ApiException $e) {
            return false;
        }
    }

    /**
     * Create Mollie payment
     */
    public function pay( string $webhookState, string $value, bool $isFirstType ) {

        if( $isFirstType && $this->hasPendingMandate ) self::$Response::http([ "code" => 403, 
            "response" => [ 
                "status" => 403, 
                "error" => "Please wait until your mandate is valid"
            ],
            "die" =>  true
        ]);

        $uniqueId = uniqid(self::$Date::current());
        $created_at = self::$Date::current(true);
        $paymentType = $isFirstType ? "first" : "regular";
        $description = $isFirstType ? "First payment #{$uniqueId}" : "Payment #{$uniqueId}";
        
        $paymentObj = [
            "amount" => [
                "currency" => "EUR",
                "value" => $value
            ],
            "customerId" => $this->customerId,
            "description" => $description,
            "redirectUrl" => "http://localhost:8080/account/plan",
            "webhookUrl" => self::$App->webhook . "/api/payments?token=" . $webhookState
        ];

        if($isFirstType) $paymentObj["sequenceType"] = $paymentType;

        try {

            $payment = $this->client->payments->create( $paymentObj );
            
            /**
             * Save payment in database
             */
            $savePayment = self::$SQL->query([
                "sql" => "INSERT INTO payments (payment_id, customer_id, spot_id, url, type, created_at) VALUES (?, ?, ?, ?, ?, ?)",
                "binds" => [$payment->id, $this->customerId,  $this->userId, $payment->getCheckoutUrl(), $paymentType, $created_at],
                "options" => ["close" => true]
            ]);
            
            /**
             * Return checkout URL
             */
            return $savePayment["status"] ? [ "code" => 201, 
                "response" => [
                    "url" => $payment->getCheckoutUrl()
                ],
                "die" =>  true
            ] : [ "code" => 500, "die" =>  true ];

        } catch( ApiException $e ) {
        
            return [ "code" => 500, 
                "response" => [ 
                    "status" => 500, 
                    "error" => $e->getMessage()
                ],
                "die" =>  true
            ];
            
        }
    }

    /**
     * Retrieve/validate certain payment
     */
    public function payment( string $id, bool $returnObj ) {
        try {
            $payment = $this->client->payments->get( $id );
            return $returnObj ? $payment : [
                "id" => $payment->id,
                "status" => $payment->status,
                "description" => $payment->description,
                "amount" => [
                    "value" => $payment->amount->value,
                    "currency" => $payment->amount->currency
                ]
            ];

        } catch (ApiException $e) {
            return false;
        }
    }

    /**
     * Check if the user has a valid subscription
     */
    public function checkSubscriptions() {
        try {
            $subscriptions = $this->client->customers->get( $this->customerId )->subscriptions();
            $status = false;

            foreach( $subscriptions as $subscription ) {
                if( $subscription->status === "pending" ) {

                    $status = true;
                    break;

                } else if( $subscription->status === "active" ) {

                    $status = true;
                    break;

                }
            }

            return $status;

        } catch (ApiException $e) {
            return false;
        }
    }

    /**
     * Create a Mollie subscription for customer
     */
    public function createSubscription( string $webhookState, string $value, string $interval ) {
        try {

            $created_at = self::$Date::current(true);
            $uniqueId = uniqid(self::$Date::current());
            $customer = $this->client->customers->get( $this->customerId );

            $subscription = $customer->createSubscription([
                "amount" => [
                    "currency" => "EUR",
                    "value" => $value,
                ],
                "interval" => $interval,
                "description" => "Subscription #{$uniqueId}",
                "webhookUrl" => self::$App->webhook . "/api/payments/subscriptions?token=" . $webhookState
            ]);

            $querys = [[
                "sql" => "UPDATE user_meta SET meta_value=? WHERE meta_key=? AND spot_id=?",
                "binds" => ["false", "user_first_time", $this->userId],
            ],[
                "sql" => "INSERT INTO subscriptions (spot_id, subscription_id, start_date, min_lasts_until, created_at) VALUES ( ?, ?, ?, ?, ? )",
                "binds" => [ $this->userId, $subscription->id, $subscription->startDate, self::$Date::stToISO( self::$Date::dateToSt( $subscription->startDate ) + 2592000 ), $created_at ]
            ]];

            foreach($querys as $query) self::$SQL->query([
                "sql" => $query["sql"],
                "binds" => $query["binds"],
                "options" => ["close" => true]
            ]);

            return true;

        } catch (ApiException $e) {
            return false;
        }
    }

    /**
     * Return specific subscription
     */
    public function subscription( string $id, bool $returnObj ) {
        try {
            $subscription = $this->client->customers->get( $this->customerId )->getSubscription($id);
            return $returnObj ? $subscription : [
                "id" => $subscription->id,
                "status" => $subscription->status,
                "description" => $subscription->description,
                "startDate" => $subscription->startDate,
                "nextPaymentDate" => isset($subscription->nextPaymentDate) ? $subscription->nextPaymentDate : null,
                "amount" => [
                    "value" => $subscription->amount->value,
                    "currency" => $subscription->amount->currency
                ]
            ];

        } catch (ApiException $e) {
            return false;
        }
    }

    /**
     * Cancel Mollie subscription
     */
    public function cancelSubscription( string $value ) {
        try {

            $customer = $this->client->customers->get( $this->customerId );
            $canceledSubscription = $customer->cancelSubscription( $value );

            return ( $canceledSubscription->status === "canceled" ) ? true : false;

        } catch (ApiException $e) {
            return false;
        }
    }
}