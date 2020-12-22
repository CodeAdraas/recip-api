<?php

namespace app;
use \Datetime;
use \DateTimeZone;

class Date {

    private static $Date;

    const TIMEZONE = "Europe/Amsterdam";

    private function __construct(array $services) {
        foreach($services as $key => $service) self::${$key} = $service;
    }

    /**
     * Create new date
     * Named "createInstance" to not interfere with "create" method
     */
    public static function createInstance(array $services = []) {
        isset(self::$Date) ?: self::$Date = new Date($services);
        return self::$Date;
    }

    /**
    * Retrieve current timestamp
    */
    public static function current(bool $format = false) {
        $date = new DateTime(null, new DateTimeZone(self::TIMEZONE));
        $curr_date = $format ? $date->format($date::ATOM) : $date->getTimestamp();
        return $curr_date;
    }

    /**
     * Create date
     */
    public static function create($time, bool $format = false) {
        $curr_date = self::current();
        $date = $curr_date + $time;
        return $format ? self::stToISO($date) : $date;
    }

    /**
     * Convert timestamp to date in ISO 8601 format
     */
    public static function stToISO($timestamp) {
        $date = new DateTime(null, new DateTimeZone(self::TIMEZONE));
        $date->setTimestamp($timestamp);
        return $date->format($date::ATOM);
    }

    /**
     * Convert date in ISO (8601) format to timestamp
     */
    public static function ISOToSt($ISOdate) {
        $date = new DateTime($ISOdate, new DateTimeZone(self::TIMEZONE));
        return $date->getTimestamp();
    }

    /**
     * Convert any given date into timestamp
     */
    public static function dateToSt($date) {
        $date = new DateTime($date, new DateTimeZone(self::TIMEZONE));
        return $date->getTimestamp();
    }

    /**
     * Check if date is epxired
     */
    public static function isExpired($date) {
        $curr_date = self::current();
        if($curr_date >= $date) return true;
        return false;
    }

}