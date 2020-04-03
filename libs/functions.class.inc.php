<?php

require_once(__DIR__ . "/settings.class.inc.php");

class functions {
    public static function get_id() {
        $key = uniqid (rand (),true);
        $hash = sha1($key);
        return $hash;
    }
    public static function get_database() {
        $file = settings::get_cluster_db_path();
        try {
            $db = new PDO("sqlite:$file");
        } catch (PDOException $e) {
            return null;
        }
        return $db;
    }
}

