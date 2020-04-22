<?php

require_once(__DIR__ . "/settings.class.inc.php");

class functions {
    public static function get_id() {
        $key = uniqid (rand (),true);
        $hash = sha1($key);
        return $hash;
    }
    public static function get_database($version = "") {
        $file = settings::get_cluster_db_path($version);
        try {
            $db = new PDO("sqlite:$file");
        } catch (PDOException $e) {
            return null;
        }
        return $db;
    }
    public static function get_data_dir_path($version = "") {
        $dir = settings::get_data_dir($version);
        $path = getcwd();
        return "$path/$dir";
    }
    public static function validate_version($version) {
        return self::filter_version($version);
    }
    public static function filter_version($version) {
        if (!isset($version))
            $version = $_GET["v"];
        if ($version === "1.0" || $version === "2.0" || $version === "2.1")
            return $version;
        return "";
    }
    public static function validate_cluster_id($db, $id) {
        $sql = "SELECT name FROM network WHERE cluster_id = :id";
        $sth = $db->prepare($sql);
        $sth->bindValue("id", $id);
        if (!$sth->execute())
            return false;
        if ($sth->fetch())
            return true;
        else
            return false;
    }
}

