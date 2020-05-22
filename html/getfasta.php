<?php

require_once(__DIR__ . "/../libs/settings.class.inc.php");
require_once(__DIR__ . "/../libs/functions.class.inc.php");

$version = filter_input(INPUT_POST, "v", FILTER_SANITIZE_STRING);

$db = functions::get_database($version);

// Available actions:
//   cluster    get cluster data
//   kegg       get kegg ids
//
$cluster_id = filter_input(INPUT_POST, "c", FILTER_SANITIZE_STRING);
$ascore = filter_input(INPUT_POST, "as", FILTER_SANITIZE_NUMBER_INT);
$ids = filter_input(INPUT_POST, "ids", FILTER_SANITIZE_STRING);
$org = filter_input(INPUT_POST, "o", FILTER_SANITIZE_STRING);
$id_type = filter_input(INPUT_POST, "it", FILTER_SANITIZE_STRING);

$version = functions::validate_version($version);

if (!$cluster_id || !functions::validate_cluster_id($db, $cluster_id)) {
    echo json_encode(array("valid" => false, "message" => "Invalid request."));
    exit(0);
}

$ids_temp = html_entity_decode($ids);
$ids = json_decode($ids_temp);
if (!is_array($ids)) {
    echo json_encode(array("valid" => false, "message" => "Invalid request [A]."));
    exit(0);
}

if ($id_type != "uniprot" && $id_type != "uniref50" && $id_type != "uniref90")
    $id_type = "uniprot";


$dir = functions::get_data_dir_path2($db, $version, $ascore, $cluster_id);

$fasta = "$dir/$id_type.fasta";


$ids = sanitize_ids($ids);
$ids = array_fill_keys($ids, "1");
//if ($id_type == "uniref50" || $id_type == "uniref90") {
//    $ids = get_uniref_ids($db, $ids, $id_type);
//} else {
//    $ids = array_fill_keys($ids, "1");
//}

$fh = fopen($fasta, "r");
if (!$fh) {
    echo json_encode(array("valid" => false, "message" => "Invalid request [F]."));
    exit(0);
}

$temp = tmpfile();

$id = "";
//$buffer = array();
while (($line = fgets($fh)) !== false) {
    $matches = array();
    if (preg_match("/^>([A-Z0-9]+)/", $line, $matches)) {
        if (isset($ids[$matches[1]]))
            $id = $matches[1];
        else
            $id = "";
    }
    if ($id)
        fwrite($temp, $line);
        //send_line($line, $buffer);
}

//send_line("", $buffer);

fclose($fh);


fseek($temp, 0);


$fname = $cluster_id;
if ($ascore)
    $fname .= "_AS$ascore";
if ($org)
    $fname .= "_$org";
$fname .= "_$id_type";
$fname .= ".fasta";

$stats = fstat($temp);
$filesize = $stats['size'];
functions::send_headers($fname, $filesize);
functions::send_file_handle($temp);

fclose($temp);


//function send_line($line, &$buffer) {
//    array_push($buffer, $line);
//    if (!$line || count($buffer) > 20) {
//        foreach ($buffer as $bufline) {
//            echo $bufline;
//            ob_flush();
//            flush();
//        }
//        $buffer = array();
//    }
//}


function get_uniref_ids($db, $uniprot_ids, $id_type) {
    $uniref_ids = array();
    foreach ($uniprot_ids as $id) {
        $sql = "SELECT DISTINCT(${id_type}_id) FROM uniref_map WHERE uniprot_id = '$id'";
        $sth = $db->prepare($sql);
        $sth->execute();
        $row = $sth->fetch();
        if ($row)
            $uniref_ids[$row[0]] = 1;
    }
    return $uniref_ids;
}


function sanitize_ids($ids_in) {
    $ids = array();
    foreach ($ids_in as $id) {
        if (preg_match("/^[A-Za-z0-9]+$/", $id))
            array_push($ids, $id);
    }
    return $ids;
}


