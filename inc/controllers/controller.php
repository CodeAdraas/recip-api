<?php

namespace controllers;

class Controller {

    private static $Controller;
    private static $App;
    private static $Auth;
    private static $Response;
    private static $Date;
    private static $DB;
    private static $SQL;
    private static $Products;

    /**
     * Dependency injection [BETA]
     */
    private function __construct($services) {
        foreach($services as $key => $service) self::${$key} = $service;
    }

    /**
     * Create new controller
     */
    public static function create(array $services = []) {
        isset(self::$Controller) ?: self::$Controller = new Controller($services);
        return self::$Controller;
    }

    /**
     * 404 template
     */
    public static function page404($die = false) {
        self::$Response::http(["code" => 404]);
        if($die) die();
    }

    /**
     * Route app static
     */
    public function static(array $options) {
        $path = isset($options["path"]) ? $options["path"] : false;
        $file = isset($options["file"]) ? $options["file"] : false;
        $template = isset($options["template"]) ? $options["template"] : false;

        if($template) include_once "./views/template-parts/header.php";

        $fp = (!$path) ? "./views/{$file}.php" : "./views/{$path}/{$file}.php";
        
        file_exists($fp) ? include_once $fp : self::page404(true);

        if($template) include_once "./views/template-parts/footer.php";
    }

    /**
     * Route app dynamic
     */
    public function route(array $options, $page) {
        $path = isset($options["path"]) ? $options["path"] : false;
        $file = isset($options["file"]) ? $options["file"] : false;
        $template = isset($options["template"])  ? $options["template"] : false;
    
        if($template) include_once "./views/template-parts/header.php";

        $fp = (!$path) ? (!$file) ? "./views/{$page}.php" : "./views/{$file}.php": (!$file)  ? "./views/{$path}/{$page}.php" : "./views/{$path}/{$file}.php";

        file_exists($fp) ? include_once $fp : self::page404(true);

        if($template) include_once "./views/template-parts/footer.php";
    }

}