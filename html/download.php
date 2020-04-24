<?php

require(__DIR__ . "/../libs/settings.class.inc.php");
require(__DIR__ . "/../libs/functions.class.inc.php");


$type = filter_input(INPUT_GET, "t", FILTER_SANITIZE_STRING);
$cluster = filter_input(INPUT_GET, "c", FILTER_SANITIZE_STRING);
$version = filter_input(INPUT_GET, "v", FILTER_SANITIZE_STRING);

$type = filter_type($type);
if (!is_array($type)) {
    //TODO: error
    die();
}
if (!preg_match("/^[cluster0-9\-]+$/", $cluster)) {
    //TODO: error
    die();
}
$db = functions::get_database($version);
if (!functions::validate_cluster_id($db, $cluster)) {
    //TODO: error
    die();
}
$version = functions::filter_version($version);


$basepath = functions::get_data_dir_path($version);
$cpath = "$basepath/$cluster";
$fpath = "";
$fname = "";


if ($type[0] == "ssn") {
    $data = functions::get_ssn_path($db, $cluster);
    if ($data) {
        $fpath = $data["ssn"];
        $fname = "${cluster}_ssn.zip";
    }
} else {
    $options = array("${cluster}_", "");
    foreach ($options as $prefix) {
        foreach ($type as $suffix) {
            $fname = "${prefix}${suffix}";
            $file = "$cpath/$fname";
            if (file_exists($file)) {
                $fpath = $file;
                $fname = "${cluster}_$suffix";
                break;
            }
        }
        if ($fpath)
            break;
    }
}


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
        "uniprot" => array("uniprot.txt", "uniprot.zip"),
        "uniref50" => array("uniref50.txt", "uniref50.zip"),
        "uniref90" => array("uniref90.txt", "uniref90.zip"),
        "weblogo" => array("weblogo.png", "weblogo.zip"),
        "ssn" => array("ssn"));
    if (isset($types[$type]))
        return $types[$type];
    else
        return false;
}


