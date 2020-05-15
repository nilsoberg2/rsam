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

$basepath = functions::get_data_dir_path2($db, $version, $ascore, $cluster_id);
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
            } else if ($ascore) {
                $parent_cluster_id = functions::get_dicing_parent($db, $cluster_id, $ascore);
                if ($parent_cluster_id) {
                    $parent_path = functions::get_data_dir_path2($db, $version, $ascore, $parent_cluster_id);
                    $file = "$parent_path/$fname";
                    if (file_exists($file)) {
                        $fpath = $file;
                        $fname = "${parent_cluster_id}_${ascore_prefix}$suffix";
                        break;
                    }
                }
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


functions::send_headers($fname, $filesize);
functions::send_file($fpath);
exit();







function filter_type($type) {
    $RES = "";
    if (startsWith($type, "cr")) {
        $RES = strtoupper(substr($type, 4, 1)) . "_";
        $type = substr($type, 0, 4);
    }
    $types = array(
        "net" => array("lg.png"),
        "hmm" => array("hmm.hmm", "hmm.zip"),
        "hmmpng" => array("hmm.png", "hmm.zip"),
        "hist" => array("length_histogram_lg.png", "length_histogram_uniprot.zip"),
        "hist_filt" => array("length_histogram_filtered_lg.png"),
        "hist_up" => array("length_histogram_uniprot.zip"),
        "hist_ur50" => array("length_histogram_uniref50_lg.png", "length_histogram_uniref50.zip"),
        "msa" => array("msa.afa", "msa.zip"),
        "uniprot_id" => array("uniprot.txt", "uniprot.zip"),
        "uniref50_id" => array("uniref50.txt", "uniref50.zip"),
        "uniref90_id" => array("uniref90.txt", "uniref90.zip"),
        "uniprot_fasta" => array("uniprot.fasta", "uniprot.zip"),
        "uniref50_fasta" => array("uniref50.fasta", "uniref50.zip"),
        "uniref90_fasta" => array("uniref90.fasta", "uniref90.zip"),
        "weblogo" => array("weblogo.png", "weblogo.zip"),
        "crpo" => array("consensus_residue_${RES}position.txt"),
        "crpe" => array("consensus_residue_${RES}percentage.txt"),
        "crid" => array("consensus_residue_${RES}all.zip"),
        "ssn" => array("ssn.zip"));
    if (isset($types[$type]))
        return $types[$type];
    else
        return false;
}

function startsWith($haystack, $needle) {
    $length = strlen($needle);
    return (substr($haystack, 0, $length) === $needle);
}
