<?php

use \main\App; 
use \auth\Auth; 
use \auth\CSRF; 
use \auth\oAuth2; 
use \auth\Mollie; 
use \app\Database;
use \app\SQL;
use \app\Response;
use \app\Date;
use \controllers\Controller;

header('Access-Control-Allow-Origin: *');

require "./inc/auth/CORS.php";
require "./inc/app.php";
require "./inc/dependencies.php";

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load(); 

$Response = Response::create();
$SQL = SQL::create([
    "DB" => Database::create([
        "host" => $_ENV["DB_HOST"],
        "user" => $_ENV["DB_USER"],
        "db"  => $_ENV["DB_DB"],
        "pass"  => $_ENV["DB_PASS"]
    ]),
    "Response" => $Response
]);
$Date = Date::createInstance();
$App = App::create(["SQL" => $SQL, "Date" => $Date], true);
$Auth = Auth::create([
    "App" => $App,
    "SQL" => $SQL,
    "Date" => $Date,
    "CSRF" => CSRF::createInstance([
        "SQL" => $SQL,
        "Date" => $Date
    ]),
    "Response" => $Response,
    "Mollie" => Mollie::create([
        "SQL" => $SQL,
        "Date" => $Date,
        "App" => $App,
    ]),
    "oAuth2" => oAuth2::create([
        "SQL" => $SQL,
        "Date" => $Date
    ])
]);
$Controller = Controller::create([
    "App" => $App, 
    "Auth" => $Auth, 
    "SQL" => $SQL,
    "Response" => $Response, 
    "Date" => $Date
]); 

require "./inc/router.php";