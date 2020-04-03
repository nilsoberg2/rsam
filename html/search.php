<?php

require(__DIR__ . "/../libs/settings.class.inc.php");
require(__DIR__ . "/../libs/functions.class.inc.php");

// Design output structure to handle errors, etc

$type = filter_input(INPUT_GET, "t", FILTER_SANITIZE_STRING);
if ($type == "tax-auto" || $type == "tax-prefetch") {
    $query = filter_input(INPUT_GET, "query", FILTER_SANITIZE_STRING);
} else {
    $type = filter_input(INPUT_POST, "t", FILTER_SANITIZE_STRING);
    if (!$type) {
        print json_encode(array("status" => false, "message" => "Invalid request"));
        exit(0);
    }
    $query = filter_input(INPUT_POST, "query", FILTER_SANITIZE_STRING);
}
if (!$query && $type != "tax-prefetch") {
    print json_encode(array("status" => false, "message" => "Invalid input"));
    exit(0);
}


if ($type == "seq") {
    $seq = preg_replace("/>.*?[\r\n]+/", "", $query);

    $job_id = functions::get_id();

    $out_dir = settings::get_tmpdir_path() . "/" . $job_id;
    mkdir($out_dir);

    $seq = ">SEQUENCE\n$seq";
    $seq_file = "$out_dir/sequence.txt";
    file_put_contents($seq_file, $seq);


    $hmmscan = settings::get_hmmscan_path();
    $hmmdb = settings::get_hmmdb_path();
    $out_path = "$out_dir/output.txt";
    $table_path = "$out_dir/results.txt";

    $cmd = "$hmmscan -o $out_path --tblout $table_path $hmmdb $seq_file";
    $cmd_output = "";
    $cmd_results = 0;
    exec($cmd, $cmd_output, $cmd_result);

    if ($cmd_result !== 0) {
        print json_encode(array("status" => false, "message" => "An error occurred"));
        exit(0);
    }

    $lines = file($table_path);

    $matches = array();

    for ($i = 0; $i < count($lines); $i++) {
        if (!strlen($lines[$i]) || $lines[$i][0] == "#")
            continue;
        $parts = preg_split("/\s+/", $lines[$i]);
        if (count($parts) >= 5)
            array_push($matches, array($parts[0], $parts[4]));
    }

    print json_encode(array("status" => true, "matches" => $matches));
} else if ($type == "id") {

    $file = settings::get_cluster_db_path();
    $db = new SQLite3($file);
    $id = $db->escapeString($query);
    $sql = "SELECT cluster_id FROM id_mapping WHERE uniprot_id = '$id'";
    $results = $db->query($sql);
    $row = $results->fetchArray();
    if ($row) {
        print json_encode(array("status" => true, "cluster_id" => $row["cluster_id"]));
    } else {
        print json_encode(array("status" => false, "message" => "ID not found"));
    }

    $db->close();
} else if ($type == "tax") {
    $type = filter_input(INPUT_POST, "type", FILTER_SANITIZE_STRING);
    $field = $type == "genus" ? "genus" : ($type == "family" ? "family" : "species");
    $file = settings::get_cluster_db_path();
    $db = new SQLite3($file);

    $query = $db->escapeString($query);
    $sql = "SELECT cluster_id, species FROM taxonomy WHERE $field LIKE '%$query%' ORDER BY cluster_id";
    $results = $db->query($sql);

    $count = array();
    $clusters = array();
    while ($row = $results->fetchArray()) {
        if (!isset($count[$row["cluster_id"]])) {
            $count[$row["cluster_id"]] = 0;
            array_push($clusters, $row["cluster_id"]);
        }
        $count[$row["cluster_id"]]++;
    }

    usort($clusters, function($a, $b) {
        $ap = explode("-", $a);
        $bp = explode("-", $b);
        $maxidx = count($ap) < count($bp) ? count($ap) : count($bp);
        for ($i = 1; $i < $maxidx; $i++) {
            $ai = preg_replace("/[^0-9]/", "", $ap[$i]);
            $bi = preg_replace("/[^0-9]/", "", $bp[$i]);
            if ($ai != $bi)
                return $ai - $bi;
        }
        return 0;
    });

    $matches = array();
    for ($i = 0; $i < count($clusters); $i++) {
        array_push($matches, array($clusters[$i], $count[$clusters[$i]]));
    }

    print json_encode(array("status" => true, "matches" => $matches));
} else if ($type == "tax-prefetch") {
    //$field = $type == "genus" ? "genus" : ($type == "family" ? "family" : "species");
    $field = "species";
    $file = settings::get_cluster_db_path();
    $db = new SQLite3($file);

    $sql = "SELECT $field FROM taxonomy LIMIT 1000";
    $results = $db->query($sql);

    $data = array();
    while ($row = $results->fetchArray()) {
        array_push($data, $row[$field]);
    }

    print json_encode($data);
} else if ($type == "tax-auto") {
    //$field = $type == "genus" ? "genus" : ($type == "family" ? "family" : "species");
    $field = "species";
    $file = settings::get_cluster_db_path();
    $db = new SQLite3($file);

    $query = $db->escapeString($query);
    $sql = "SELECT DISTINCT $field FROM taxonomy WHERE $field LIKE '$query%' LIMIT 100";
    $results = $db->query($sql);

    $data = array();
    while ($row = $results->fetchArray()) {
        array_push($data, $row[$field]);
    }

    print json_encode($data);
}




