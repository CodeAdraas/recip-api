<?php

use \Delight\Router\Router;

$flow = $Auth->flow();

$API = new Router( "/v1" );

$API->post("/subscription",           [$Controller, "static"], [ [ "path" => "api/subscription", "file" => "get" ] ]) && die();
$API->post("/payments",               [$Controller, "static"], [ [ "path" => "api/payments/post", "file" => "webhook" ] ]) && die();
$API->post("/payments/subscriptions", [$Controller, "static"], [ [ "path" => "api/payments/post", "file" => "subscription-webhook" ] ]) && die();
$API->get("/auth/redirect",           [$Controller, "static"], [ [ "path" => "api/auth",          "file" => "redirect" ] ]) && die();

if( $flow ) {

    $API->post("/subscription", [$Controller, "static"], [ [ "path" => "api/subscription/post", "file" => "create" ] ]) && die();
    $API->get("/auth/token",    [$Controller, "static"], [ [ "path" => "api/auth",              "file" => "token" ] ]) && die();
    $API->get("/search",        [$Controller, "static"], [ [ "path" => "api/search",            "file" => "get" ] ]) && die();

    $API->post("/artist",       [$Controller, "static"], [ [ "path" => "api/artist/post",       "file" => "add" ] ]) && die();

    $API->post("/user",         [$Controller, "static"], [ [ "path" => "api/user/post",         "file" => "create" ] ]) && die();
    $API->put("/user",          [$Controller, "static"], [ [ "path" => "api/user/post",         "file" => "update" ] ]) && die();
    $API->delete("/user",       [$Controller, "static"], [ [ "path" => "api/user/post",         "file" => "delete" ] ]) && die();
    $API->get("/user/me",       [$Controller, "static"], [ [ "path" => "api/user",              "file" => "me" ] ]) && die();

    $Response::http(["code" => 404, "response" => [
        "status" => 404,
        "error" => "The provided resource was not found"
    ], "die" => true ]);
}

$Response::http(["code" => 401, "response" => [
    "status" => 401,
    "error" => "No valid token was provided"
], "die" => true ]);