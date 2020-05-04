<?php

require(__DIR__ . "/../conf/settings.inc.php");

class settings {
    public static function get_hmmscan_path() {
        return defined("SF_HMMSCAN") ? SF_HMMSCAN : "";
    }
    public static function get_hmmdb_path($version = "", $type = "") {
        if ($version == "1.0" && defined("SF_HMMDB_V1")) {
            return SF_HMMDB_V1;
        } else if ($version == "2.0" && defined("SF_HMMDB_V2")) {
            return SF_HMMDB_V2;
        } else if ($version == "2.1" && defined("SF_HMMDB_V2_1")) {
            //TODO: handle dicing in subsequent versions
            $dir = SF_HMMDB_V2_1 . "/hmm";
            if (!$type || $type == "all" || !preg_match("/^[a-z]+$/", $type) || !file_exists("$dir/$type.txt"))
                return array("$dir/all.hmm");
            $lines = file("$dir/$type.txt");
            $db_list = array();
            for ($i = 0; $i < count($lines); $i++) {
                $line = trim($lines[$i]);
                $parts = explode("\t", $line);
                if (count($parts) >= 3) {
                    $file = "$dir/" . $parts[2]; // added in a later step . ".hmm";
                    if (!isset($db_list[$parts[0]]))
                        $db_list[$parts[0]] = array();
                    array_push($db_list[$parts[0]], array($parts[1], $file));
                }
            }
            return $db_list;
            //return SF_HMMDB_V2_1;
        }
        return defined("SF_HMMDB") ? SF_HMMDB : "";
    }
    public static function get_tmpdir_path() {
        return defined("SF_TMPDIR") ? SF_TMPDIR : "";
    }
    public static function get_cluster_db_path($version = "") {
        if ($version == "1.0" && defined("SF_CLUSTERDB_V1"))
            return SF_CLUSTERDB_V1;
        else if ($version == "2.0" && defined("SF_CLUSTERDB_V2"))
            return SF_CLUSTERDB_V2;
        else if ($version == "2.1" && defined("SF_CLUSTERDB_V2_1"))
            return SF_CLUSTERDB_V2_1;
        return defined("SF_CLUSTERDB") ? SF_CLUSTERDB : "";
    }
    public static function get_data_dir($version = "") {
        if ($version == "1.0" && defined("SF_DATA_DIR_V1"))
            return SF_DATA_DIR_V1;
        else if ($version == "2.0" && defined("SF_DATA_DIR_V2"))
            return SF_DATA_DIR_V2;
        else if ($version == "2.1" && defined("SF_DATA_DIR_V2_1"))
            return SF_DATA_DIR_V2_1;
        return defined("SF_DATA_DIR") ? SF_DATA_DIR : "data";
    }
    public static function get_submit_email() {
        return defined("SF_SUBMIT_EMAIL") ? SF_SUBMIT_EMAIL : "";
    }
}

