<?php

/**
 * Spotify redirects the user to this page with a GET request
 * to request an oAuth2 access_token and user session for the app
 */

$data = self::$Auth->sanitize(
    $_GET, 
    ["code", "state", "error"], 
    ["code", "state"]
);

if(!$data) self::$App->redirect($App->prefix . "login", true);
if(isset($data["error"])) self::$App->redirect($App->prefix . "login?error=" . $data["error"], true);

$code = $data["code"];
$token = $data["state"];

/**
 * Check state as anti-CSRF prevention
 * If not authorised, redirect to login page
 * If authorised, proceed and delete CSRF token
 */

if(!self::$Auth::$CSRF::check( $token )) self::$App->redirectFull( self::$App->app . "/" . $App->prefix . "login?error=csrf_mismatch", true);
self::$Auth::$CSRF::destroy( $token );

$grantAuth = self::$Auth->grantAuth( $code, self::$App->API . "/api/auth/redirect" );
if(!$grantAuth) self::$App->redirectFull( self::$App->app . "/" . $App->prefix . "login?error=failed_grant_auth", true);

$saveAccess = self::$Auth->saveAccess( $grantAuth );
if(!$saveAccess) self::$App->redirectFull( self::$App->app . "/" . $App->prefix . "login?error=failed_saving_access", true);

self::$Auth->setCookie( $saveAccess );
self::$App->redirectFull( self::$App->app, true);