<?php

require(__DIR__ . "/../conf/settings.inc.php");

class settings {
    public static function get_hmmscan_path() {
        return defined("SF_HMMSCAN") ? SF_HMMSCAN : "";
    }
    public static function get_hmmdb_path() {
        return defined("SF_HMMDB") ? SF_HMMDB : "";
    }
    public static function get_tmpdir_path() {
        return defined("SF_TMPDIR") ? SF_TMPDIR : "";
    }
    public static function get_cluster_db_path() {
        return defined("SF_CLUSTERDB") ? SF_CLUSTERDB : "";
    }
    public static function get_submit_email() {
        return defined("SF_SUBMIT_EMAIL") ? SF_SUBMIT_EMAIL : "";
    }
}

