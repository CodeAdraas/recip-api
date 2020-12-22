<?php

namespace main;

use Mollie\Api\MollieApiClient;

class App {

    public $testmode;
    public $protocol;
    public $domain;
    public $uri;
    public $path; 
    public $query;
    public $parameters;
    public $URL;
    public $ngrok;
    public $webhook;
    public $API;
    public $app;

    private static $App;
    private static $SQL;
    private static $Date;

    /**
     * Setup app properties
     */
    private function __construct($services, $testmode) {

        foreach($services as $key => $service) self::${$key} = $service;

        $this->testmode = $testmode; //determines whether in testmode (localhost), standaard false
        $this->protocol = ($this->testmode) ? "http://" : "https://"; //determine protocol based on testmodus or not
        $this->domain   = $this->protocol . $_SERVER["HTTP_HOST"];
        $this->API      = $testmode ? "http://localhost:3000" : "https://recipi.achoendov.nl";
        $this->app      = $testmode ? "http://localhost:8080" : "https://recip.achoendov.nl";
        $this->ngrok    = $_ENV["NGROK_URL"];
        $this->webhook  = $testmode ? $this->ngrok : "https://recipi.achoendov.nl";
        $this->uri      = strtok($_SERVER["REQUEST_URI"], "?");
        $this->query    = strpos($_SERVER["REQUEST_URI"], "?") ? substr($_SERVER["REQUEST_URI"], (strpos($_SERVER["REQUEST_URI"], "?") + 1)) : "";
        $this->path     = array_values(array_filter(explode("/", $this->uri)));

        $this->uri = join("/", $this->path); //create rest URI e.g. /store/product/890
        $this->URL = $this->domain."/".$this->uri."?".$this->query; //create full URL e.g. 
    }

    /**
     * Create new app
     */
    public static function create(array $services = [], $testmode = false) {
        isset(self::$App) ?: self::$App = new App($services, $testmode);
        return self::$App;
    }
    
    /**
     * Check if page(s) is/are equal to URI
     */
    public function isPage($pages) {
        if(!is_array($pages))
        {
            return ($pages !== "/" . $this->uri) ? false : true;
        }
        else
        {
            foreach($pages as $page)
            {
                if($page !== "/" . $this->uri) continue;
                if($page === "/" . $this->uri) return true;
            }
            return false;
        }
    }

    /**
     * Check if URI contains certain string
     */
    public function containsPage($page) {
        return !strstr($this->uri, $page) ? false : true;
    }

    /**
     * Redirect
     */
    public function redirect($path, $die = false) {
        header("Location: ".$this->domain . $path);
        if($die) die();
    }

    /**
     * Redirect full URL
     */
    public function redirectFull($URL, $die = false) {
        header("Location: ".$URL);
        (!$die) ?: die();
    }

}