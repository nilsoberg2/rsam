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
    public static function get_data_dir_path($cluster = "", $version = "", $ascore = "", $child_id = "") {
        $rel_dir = functions::get_rel_data_dir_path($cluster, $version, $ascore, $child_id);
        $path = dirname(__FILE__) . "/../html";
        $path = "$path/$rel_dir";
        return $path;
    }
    public static function get_rel_data_dir_path($cluster = "", $version = "", $ascore = "", $child_id = "") {
        $dir = settings::get_data_dir($version);
        if (preg_match("/^[a-z0-9\-]+$/", $cluster)) {
            $dir = "$dir/$cluster";
            if (is_numeric($ascore)) {
                $dir = "$dir/dicing-$ascore";
                if ($child_id)
                    $dir = "$dir/$child_id";
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
        $check_fn = function($db, $id, $table_name, $col_name) {
            $sql = "SELECT $col_name FROM $table_name WHERE cluster_id = :id";
            $sth = $db->prepare($sql);
            if (!$sth)
                return false;
            $sth->bindValue("id", $id);
            if (!$sth->execute())
                return false;
            if ($sth->fetch())
                return true;
            else
                return false;
        };

        if ($check_fn($db, $id, "network", "name"))
            return true;
        else if ($check_fn($db, $id, "diced_network", "cluster_id"))
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
    public static function get_generic_sql($table, $parm, $extra_where = "", $check_only = false) {
        if ($check_only)
            $sql = "SELECT COUNT(*) AS $parm FROM $table WHERE cluster_id = :id $extra_where";
        else
            $sql = "SELECT $parm FROM $table WHERE cluster_id = :id $extra_where";
        return $sql;
    }
    public static function get_generic_fetch($db, $cluster_id, $sql, $handle_row_fn, $check_only = false) {
        $sth = $db->prepare($sql);
        if (!$sth)
            return $check_only ? 0 : array();
        $sth->bindValue("id", $cluster_id);
        $sth->execute();
        $data = array();
        while ($row = $sth->fetch()) {
            if ($check_only)
                return $handle_row_fn($row);
            $data_row = $handle_row_fn($row);
            //if (is_array($data_row))
                array_push($data, $data_row);
        }
        return $check_only ? 0 : $data;
    }
    public static function get_dicing_parent($db, $cluster_id, $ascore = "") {
//        $sql = "SELECT parent_id FROM dicing WHERE cluster_id = :id";
        $sql = "SELECT parent_id FROM diced_network WHERE cluster_id = :id";
        if ($ascore)
            $sql .= " AND ascore = :ascore";
        $sth = $db->prepare($sql);
        if (!$sth)
            return array();
        $sth->bindValue("id", $cluster_id);
        if ($ascore)
            $sth->bindValue("ascore", $ascore);
        $sth->execute();
        $row = $sth->fetch();
        if (isset($row) && isset($row["parent_id"]))
            return $row["parent_id"];
        else
            return "";
    }
    // Same as get_data_dir_path but checks for dicing
    public static function get_data_dir_path2($db, $version, $ascore, $cluster_id) {
        $parent_cluster_id = functions::get_dicing_parent($db, $cluster_id, $ascore);
        $child_cluster_id = "";
        if ($parent_cluster_id) {
            $child_cluster_id = $cluster_id;
        } else {
            $parent_cluster_id = $cluster_id;
        }
        $basepath = functions::get_data_dir_path($parent_cluster_id, $version, $ascore, $child_cluster_id);
        return $basepath;
    }
    public static function send_headers($filename, $filesize, $type = "application/octet-stream") {
        header('Pragma: public');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Cache-Control: private', false);
        header('Content-Transfer-Encoding: binary');
        header('Content-Disposition: attachment; filename="' . $filename . '";');
        header('Content-Type: ' . $type);
        header('Content-Length: ' . $filesize);
    }
    public static function send_file($file) {
        $handle = fopen($file, 'rb');
        self::send_file_handle($handle);
        fclose($handle);
    }
    public static function send_file_handle($handle) {
        $chunkSize = 1024 * 1024;
        while (!feof($handle)) {
            $buffer = fread($handle, $chunkSize);
            echo $buffer;
            ob_flush();
            flush();
        }
    }
}

