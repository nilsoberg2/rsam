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
    public static function get_data_dir_path($cluster = "", $version = "", $ascore = "") {
        $rel_dir = functions::get_rel_data_dir_path($cluster, $version, $ascore);
        $path = dirname(__FILE__) . "/../html";
        $path = "$path/$rel_dir";
        return $path;
    }
    public static function get_rel_data_dir_path($cluster = "", $version = "", $ascore = "") {
        $dir = settings::get_data_dir($version);
        if (preg_match("/^[a-z0-9\-]+$/", $cluster)) {
            $dir = "$dir/$cluster";
            if (is_numeric($ascore)) {
                $dir = "$dir/dicing-$ascore";
            }
        }
        return $dir;
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
    public static function get_ssn_path($db, $cluster) {
        $sql = "SELECT ssn FROM ssn WHERE cluster_id = :id";
        $sth = $db->prepare($sql);
        if (!$sth)
            return false;
        $sth->bindValue("id", $cluster);
        $data = false;
        if ($sth->execute()) {
            $data = $sth->fetch();
        }
        return $data;
    }
}

