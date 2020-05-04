<?php

require(__DIR__ . "/../libs/settings.class.inc.php");
require(__DIR__ . "/../libs/functions.class.inc.php");


$type = filter_input(INPUT_GET, "t", FILTER_SANITIZE_STRING);
$cluster_id = filter_input(INPUT_GET, "c", FILTER_SANITIZE_STRING);
$version = filter_input(INPUT_GET, "v", FILTER_SANITIZE_STRING);
$ascore = filter_input(INPUT_GET, "as", FILTER_SANITIZE_NUMBER_INT);

$type = filter_type($type);
if (!is_array($type)) {
    //TODO: error
    die();
}
if (!preg_match("/^[cluster0-9\-]+$/", $cluster_id)) {
    //TODO: error
    die();
}
$db = functions::get_database($version);
if (!functions::validate_cluster_id($db, $cluster_id)) {
    //TODO: error
    die();
}
$version = functions::filter_version($version);

$parent_cluster_id = get_dicing_parent($db, $cluster_id);
$child_cluster_id = "";
if ($parent_cluster_id) {
    $child_cluster_id = $cluster_id;
} else {
    $parent_cluster_id = $cluster_id;
}
$basepath = functions::get_data_dir_path($parent_cluster_id, $version, $ascore, $child_cluster_id);
$fpath = "";
$fname = "";
$ascore_prefix = $ascore ? "AS${ascore}_" : "";


//if ($type[0] == "ssn") {
//    $data = functions::get_ssn_path($db, $cluster_id);
//    if ($data) {
//        $fpath = $data["ssn"];
//        $fname = "${cluster_id}_ssn.zip";
//    }
//} else {
    $options = array("${cluster_id}_", "");
    foreach ($options as $prefix) {
        foreach ($type as $suffix) {
            $fname = "${prefix}${suffix}";
            $file = "$basepath/$fname";
            if (file_exists($file)) {
                $fpath = $file;
                $fname = "${cluster_id}_${ascore_prefix}$suffix";
                break;
            }
        }
        if ($fpath)
            break;
    }
//}


if (!$fpath) {
    //TODO: error
    die();
}

$filesize = filesize($fpath);


send_headers($fname, $filesize);
send_file($fpath);
exit();






function send_file($file) {
    $chunkSize = 1024 * 1024;
    $handle = fopen($file, 'rb');
    while (!feof($handle)) {
        $buffer = fread($handle, $chunkSize);
        echo $buffer;
        ob_flush();
        flush();
    }
    fclose($handle);
}


function send_headers($filename, $filesize, $type = "application/octet-stream") {
    header('Pragma: public');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Cache-Control: private', false);
    header('Content-Transfer-Encoding: binary');
    header('Content-Disposition: attachment; filename="' . $filename . '";');
    header('Content-Type: ' . $type);
    header('Content-Length: ' . $filesize);
}


function filter_type($type) {
    $types = array(
        "net" => array("lg.png"),
        "hmm" => array("hmm.hmm", "hmm.zip"),
        "hist" => array("length_histogram_lg.png"),
        "hist_filt" => array("length_histogram_filtered_lg.png"),
        "msa" => array("msa.afa", "msa.zip"),
        "uniprot_id" => array("uniprot.txt", "uniprot.zip"),
        "uniref50_id" => array("uniref50.txt", "uniref50.zip"),
        "uniref90_id" => array("uniref90.txt", "uniref90.zip"),
        "uniprot_fasta" => array("uniprot.fasta", "uniprot.zip"),
        "uniref50_fasta" => array("uniref50.fasta", "uniref50.zip"),
        "uniref90_fasta" => array("uniref90.fasta", "uniref90.zip"),
        "weblogo" => array("weblogo.png", "weblogo.zip"),
        "ssn" => array("ssn.zip"));
    if (isset($types[$type]))
        return $types[$type];
    else
        return false;
}


function get_dicing_parent($db, $cluster_id) {
    $sql = functions::get_generic_sql("dicing", "parent_id");
    $row_fn = function($row) {
        return $row["parent_id"];
    };
    $data = functions::get_generic_fetch($db, $cluster_id, $sql, $row_fn, true); // true = return only first row in this case;
    return isset($data) ? $data : "";
}


