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
$version = functions::validate_version($_POST["v"]);


if ($type == "seq") {
    $seq = preg_replace("/>.*?[\r\n]+/", "", $query);

    $job_id = functions::get_id();

    $out_dir = settings::get_tmpdir_path() . "/" . $job_id;
    mkdir($out_dir);

    $seq = ">SEQUENCE\n$seq";
    $seq_file = "$out_dir/sequence.txt";
    file_put_contents($seq_file, $seq);

    $hmmdb = settings::get_hmmdb_path($version, "all");
    $matches = hmmscan($out_dir, $hmmdb[0], $seq_file);
    //$matches = array();

    $diced_db = settings::get_hmmdb_path($version, "diced");
    $diced = false;
    $dmatches = array();
    if (count($diced_db) > 0) {
        // Check first dicing of each cluster
        $first = true;
        foreach ($diced_db as $parent_cluster => $dicings) {
            foreach ($dicings as $info) {
                $ascore = $info[0];
                $hmm = $info[1];
                $diced_matches = hmmscan($out_dir, $hmm, $seq_file);
                // Skip to next cluster group, no need to cycle through all of the dicings since if it's not in one dicing it's not going to be in the others
                if (count($diced_matches) == 0)
                    break;
                // If both have matches, we compare evalues to find out which is a better match.
                // Lower is better.
                //if ($first && count($matches) > 0 && $diced_matches[0][1] <= $matches[0][1]) {
                //} else if ( || $has_match) {
                    if ($first) {
                        //$matches = array();
                        $first = false;
                        $diced = true;
                        $has_match = true;
                    }
                    $dmatches[$parent_cluster][$ascore] = $diced_matches;
                //}
            }
            // If one of the clusters matched, don't bother checking the others
            if ($diced)
                break;
        }
    }

    print json_encode(array("status" => true, "matches" => $matches, "diced_matches" => $dmatches));
} else if ($type == "id") {

    $file = settings::get_cluster_db_path($version);
    $db = new SQLite3($file);
    $id = $db->escapeString($query);

    // First check if this is in the diced clusters.
    $ascore_sql = "SELECT cluster_id, ascore FROM diced_id_mapping WHERE uniprot_id = '$id'";
    $results = $db->query($ascore_sql);
    $cluster_id = array();
    while ($row = $results->fetchArray()) {
        $cluster_id[$row["ascore"]] = $row["cluster_id"];
    }

    if (count($cluster_id) == 0) {
        $cluster_id = "";
        $sql = "SELECT cluster_id FROM id_mapping WHERE uniprot_id = '$id'";
        $results = $db->query($sql);
        while ($row = $results->fetchArray()) {
            // Want bottom-level cluster
            if (strlen($row["cluster_id"]) > $cluster_id)
                $cluster_id = $row["cluster_id"];
        }
    }

    if ((is_array($cluster_id) && count($cluster_id) > 0) || (!is_array($cluster_id) && $cluster_id))
        print json_encode(array("status" => true, "cluster_id" => $cluster_id));
    else
        print json_encode(array("status" => false, "message" => "ID not found"));

    $db->close();
} else if ($type == "tax") {
    $type = filter_input(INPUT_POST, "type", FILTER_SANITIZE_STRING);
    $field = $type == "genus" ? "genus" : ($type == "family" ? "family" : "species");
    $file = settings::get_cluster_db_path($version);
    $db = new SQLite3($file);

    $search_fn = function($results, $has_ascore, $excludes = array()) {
        $count = array();
        $clusters = array();
        $diced_parents = array();
        while ($row = $results->fetchArray()) {
            $cid = $row["cluster_id"] . ($has_ascore ? "-AS" . $row["ascore"] : "");
            if ($has_ascore) {
                $parent = implode("-", array_slice(explode("-", $row["cluster_id"]), 0, 3));
                $diced_parents[$parent] = 1;
            }
            if (isset($excludes[$cid]))
                continue;
            if (!isset($count[$cid])) {
                $count[$cid] = 0;
                array_push($clusters, $cid);
            }
            $count[$cid]++;
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

        return array($count, $clusters, $diced_parents);
    };


    $sql = "SELECT cluster_id, ascore, species FROM taxonomy INNER JOIN diced_id_mapping ON taxonomy.uniprot_id = diced_id_mapping.uniprot_id WHERE $field LIKE '%$query%' ORDER BY cluster_id, ascore";
    $results = $db->query($sql);

    list($diced_count, $diced_clusters, $diced_parents) = $search_fn($results, true);

    $query = $db->escapeString($query);
    $sql = "SELECT cluster_id, species FROM taxonomy INNER JOIN id_mapping ON taxonomy.uniprot_id = id_mapping.uniprot_id WHERE $field LIKE '%$query%' ORDER BY cluster_id";
    $results = $db->query($sql);

    list($count, $clusters) = $search_fn($results, false, $diced_parents);

//    $count = array();
//    $clusters = array();
//    while ($row = $results->fetchArray()) {
//        if (!isset($count[$row["cluster_id"]])) {
//            $count[$row["cluster_id"]] = 0;
//            array_push($clusters, $row["cluster_id"]);
//        }
//        $count[$row["cluster_id"]]++;
//    }
//
//    usort($clusters, function($a, $b) {
//        $ap = explode("-", $a);
//        $bp = explode("-", $b);
//        $maxidx = count($ap) < count($bp) ? count($ap) : count($bp);
//        for ($i = 1; $i < $maxidx; $i++) {
//            $ai = preg_replace("/[^0-9]/", "", $ap[$i]);
//            $bi = preg_replace("/[^0-9]/", "", $bp[$i]);
//            if ($ai != $bi)
//                return $ai - $bi;
//        }
//        return 0;
//    });

    $matches = array();
    for ($i = 0; $i < count($clusters); $i++) {
        array_push($matches, array($clusters[$i], $count[$clusters[$i]]));
    }

    $diced_matches = array();
    for ($i = 0; $i < count($diced_clusters); $i++) {
        $ascore_match = array();
        $ascore = "";
        $cluster = $diced_clusters[$i];
        if (preg_match("/^(.+)-AS(\d+)$/", $cluster, $ascore_match)) {
            $cluster = $ascore_match[1];
            $ascore = $ascore_match[2];
        }
        array_push($diced_matches, array($cluster, $ascore, $diced_count[$diced_clusters[$i]]));
    }

    usort($diced_matches, function($a, $b) {
        $ap = explode("-", $a[0]);
        $bp = explode("-", $b[0]);
        $aa = implode("-", array_slice($ap, 0, 2));
        $bb = implode("-", array_slice($bp, 0, 2));
        $cmp = strcmp($aa, $bb);
        if (!$cmp) { // same
            $cmp = $a[1] - $b[1];
            if (!$cmp) // same
                return $ap[3] - $bp[3];
            else
                return $cmp;
        } else {
            return $cmp;
        }
    });

    print json_encode(array("status" => true, "matches" => $matches, "diced_matches" => $diced_matches));
} else if ($type == "tax-prefetch") {
    //$field = $type == "genus" ? "genus" : ($type == "family" ? "family" : "species");
    $field = "species";
    $file = settings::get_cluster_db_path($version);
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
    $file = settings::get_cluster_db_path($version);
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




function hmmscan($out_dir, $hmmdb, $seq_file) {
    $hmmscan = settings::get_hmmscan_path();
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

    for ($i = 0; $i < min(count($lines), 10); $i++) {
        if (!strlen($lines[$i]) || $lines[$i][0] == "#")
            continue;
        $parts = preg_split("/\s+/", $lines[$i]);
        if (count($parts) >= 5) {
            $evalue = floatval($parts[4]);
            if ($evalue < 1e-10)
                array_push($matches, array($parts[0], $parts[4]));
        }
    }
    return $matches;
}




