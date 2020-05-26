<?php

require_once(__DIR__ . "/../libs/settings.class.inc.php");
require_once(__DIR__ . "/../libs/functions.class.inc.php");

//const ASCORE_COL = "alignment_score";
//const DICED_SIZE = "size_diced";
//const DICED_NETWORK = "dicing";
//const DICED_ID_MAPPING = "id_mapping_diced";
//const DICED_SSN = "ssn";
//const USE_UNIPROT_ID_JOIN = false;
const ASCORE_COL = "ascore";
const DICED_SIZE = "diced_size";
const DICED_NETWORK = "diced_network";
const DICED_ID_MAPPING = "diced_id_mapping";
const DICED_SSN = "diced_ssn";
const USE_UNIPROT_ID_JOIN = true;

//$version = filter_input(INPUT_GET, "v", FILTER_SANITIZE_NUMBER_INT);
$version = functions::validate_version();

$db = functions::get_database($version);

// Available actions:
//   cluster    get cluster data
//   kegg       get kegg ids
//
$action = filter_input(INPUT_GET, "a", FILTER_SANITIZE_STRING);
$cluster_id = filter_input(INPUT_GET, "cid", FILTER_SANITIZE_STRING);
$ascore = filter_input(INPUT_GET, "as", FILTER_SANITIZE_NUMBER_INT);

if (!validate_action($action) || ($cluster_id && !functions::validate_cluster_id($db, $cluster_id))) {
    echo json_encode(array("valid" => false, "message" => "Invalid request."));
    exit(0);
}


$data = array("valid" => true, "message" => "");

if ($action == "kegg") {
    $kegg = get_kegg($db, $cluster_id, $ascore);
    if ($kegg === false) {
        $data["valid"] = false;
        $data["message"] = "KEGG error.";
    } else {
        $data["kegg"] = $kegg;
    }
} else if ($action == "cluster") {
    $cluster = get_cluster($db, $cluster_id, $ascore, $version);
    if ($cluster === false) {
        $data["valid"] = false;
        $data["message"] = "Cluster error.";
    } else {
        $data["cluster"] = $cluster;
        $data["network_map"] = get_all_network_names($db);
        $data["sfld_map"] = get_sfld_map($db);
        $data["sfld_desc"] = get_sfld_desc($db);
        $data["enzymecodes"] = get_enzyme_codes($db);
    }
} else if ($action == "tax") {
    $tax = get_tax_data($db, $cluster_id, $ascore);
    if ($tax  === false) {
        $data["valid"] = false;
        $data["message"] = "Retrieval error.";
    } else {
        $data = $tax;
        //$data["tax"] = $tax;
    }
} else if ($action == "netinfo") {
    $data["network_map"] = get_all_network_names($db);
} 


echo json_encode($data);






function get_cluster($db, $cluster_id, $ascore, $version) {
    $data = array(
        "size" => array(
            "uniprot" => 0,
            "uniref90" => 0,
            "uniref50" => 0,
        ),
        "alignment_score" => "",
        "default_alignment_score" => "",
        "name" => "",
        "desc" => "",
        "image" => "",
        "title" => "",
        "display" => array(),
        "download" => array(),
        "regions" => array(),
        "subgroups" => array(),
        "public" => array(
            "has_kegg" => false,
            "swissprot" => array(),
            "pdb" => array(),
        ),
        "anno" => array(),
        "pubs" => array(),
        "families" => array(
            "tigr" => array(),
        ),
        "dicing" => array(
            "parent" => "",
            "children" => array(),
        ),
        "alt_ssn" => array(),
        "cons_res" => array(),
        "dir" => "",
    );

    $data["dicing"]["parent"] = functions::get_dicing_parent($db, $cluster_id, $ascore);
    $data["dicing"]["children"] = get_dicing_children($db, $cluster_id, $ascore);
    $parent_cluster_id = $data["dicing"]["parent"];
    $child_cluster_id = "";
    $is_child = $parent_cluster_id ? true : false;
    if ($is_child) {
        $child_cluster_id = $cluster_id;
    } else {
        $parent_cluster_id = $cluster_id;
    }

    $info = get_network_info($db, $cluster_id, $is_child, $parent_cluster_id);
    $data["name"] = $info["name"];
    $data["title"] = $info["title"];
    $data["desc"] = $info["desc"];
    $data["image"] = $cluster_id;
    $data["public"]["has_kegg"] = get_kegg($db, $cluster_id, $ascore, true);
    $data["size"] = get_sizes($db, $cluster_id, $ascore, $is_child);
    $data["public"]["swissprot"] = get_swissprot($db, $cluster_id, $ascore);
    $data["public"]["pdb"] = get_pdb($db, $cluster_id, $ascore);
    $data["families"]["tigr"] = get_tigr($db, $cluster_id);
    $data["display"] = get_display($db, $parent_cluster_id, $version, $ascore, $child_cluster_id);
    $data["download"] = get_download($db, $parent_cluster_id, $version, $ascore, $child_cluster_id);
    $data["regions"] = get_regions($db, $cluster_id);
    $data["alt_ssn"] = get_alt_ssns($db, $cluster_id);
    $data["cons_res"] = get_consensus_residues($db, $parent_cluster_id, $version, $ascore, $child_cluster_id);

    $data["dir"] = functions::get_rel_data_dir_path($parent_cluster_id, $version, $ascore, $child_cluster_id);
    if ($ascore)
        $data["alignment_score"] = $ascore;
    $data["default_alignment_score"] = get_default_alignment_score($db, $cluster_id);
//    $data["dir"] = settings::get_data_dir($version) . "/$cluster_id";
//    if ($ascore) {
//        $full_dir = functions::get_data_dir_path($cluster_id, $version, $ascore);
//        $data["alignment_score"] = $ascore;
//        $ascore_dir = $data["dir"] . "/dicing-$ascore";
//        $full_dir = dirname(__FILE__) . "/$ascore_dir"; 
//        if (file_exists($full_dir))
//            $data["dir"] = $ascore_dir;
//    }

    return $data;
}






function get_network_info_sfld_sql($extra_cols = "", $extra_join = "") {
    if ($extra_cols)
        $extra_cols = ", $extra_cols";
    $sql = "SELECT network.cluster_id AS cluster_id, network.title AS title, network.name AS name, network.desc AS desc, sfld_map.sfld_id AS sfld_id, sfld_desc.sfld_desc AS sfld_desc "
        . $extra_cols 
        . " FROM network"
        . " LEFT JOIN sfld_map ON network.cluster_id = sfld_map.cluster_id"
        . " LEFT JOIN sfld_desc ON sfld_map.sfld_id = sfld_desc.sfld_id"
        . " " . $extra_join
        ;
    return $sql;
}
function get_network_info_title($row, $sfld_only = false, $child_cluster_id = "") {
    $title = !$sfld_only ? $row["name"] : "";
    if ($child_cluster_id)
        $title .= ' / Mega' . $child_cluster_id;
    if ($row["title"] && $row["sfld_id"]) {
        $sfld_id_repl = "";
        if ($sfld_only) {
            $sfld_id_repl = " [" . $row["sfld_id"] . "]";
            $title .= $row["title"]; 
        } else {
            $sfld_id_repl = " / ";
            $title .= ": SFLD Subgroup " . $row["sfld_id"];
            $title .= " / " . $row["title"];
        }
        if (preg_match("/<SFLD>/", $title)) {
            $title = preg_replace("/<SFLD>/", $row["sfld_desc"] . $sfld_id_repl, $title);
        }
    } elseif ($row["sfld_id"]) {
        if (!$sfld_only)
            $title .= ": SFLD Subgroup " . $row["sfld_id"] . " / ";
        $title .= $row["sfld_desc"];
    } elseif ($row["title"]) {
        $title .= (!$sfld_only ? ": " : "") . $row["title"];
    }
    return $title;
}
function get_network_info($db, $cluster_id, $is_child, $parent_cluster_id) {
    $sql = get_network_info_sfld_sql() . " WHERE network.cluster_id = :id";
    $sth = $db->prepare($sql);
    if ($is_child)
        $sth->bindValue("id", $parent_cluster_id);
    else
        $sth->bindValue("id", $cluster_id);
    $sth->execute();
    $row = $sth->fetch();
    if ($row) {
        $child_cluster_id = $is_child ? $cluster_id : "";
        $title = get_network_info_title($row, false, $child_cluster_id);
        return array("cluster_id" => $row["cluster_id"], "name" => $row["name"], "title" => $title, "desc" => $row["desc"]);
    } else {
        return array("cluster_id" => $cluster_id, "name" => "", "title" => "", "desc" => "");
    }
}
function get_all_network_names($db) {
    $sql = get_network_info_sfld_sql("uniprot, uniref50, uniref90", "LEFT JOIN size ON network.cluster_id = size.cluster_id");
    //$sql = "SELECT network.cluster_id, name, uniprot, uniref50, uniref90 FROM network LEFT JOIN size on network.cluster_id = size.cluster_id";
    $sth = $db->prepare($sql);
    $sth->execute();
    $data = array();
    $sfld_only = true;
    while ($row = $sth->fetch()) {
        $sfld_title = get_network_info_title($row, $sfld_only);
        $data[$row["cluster_id"]] = array("name" => $row["name"], "sfld_title" => $sfld_title, "size" => array("uniprot" => $row["uniprot"], "uniref90" => $row["uniref90"], "uniref50" => $row["uniref50"]));
    }
    return $data;
}

function get_sfld_map($db) {
    $sql = "SELECT * FROM sfld_map";
    $sth = $db->prepare($sql);
    $sth->execute();
    $data = array();
    while ($row = $sth->fetch()) {
        $cid = $row["cluster_id"];
        if (!isset($data[$cid]) || !is_array($data[$cid]))
            $data[$cid] = array();
        array_push($data[$cid], $row["sfld_id"]);
    }
    return $data;
}

function get_sfld_desc($db) {
    $sql = "SELECT * FROM sfld_desc";
    $sth = $db->prepare($sql);
    $sth->execute();
    $data = array();
    while ($row = $sth->fetch()) {
        $data[$row["sfld_id"]] = array("desc" => $row["sfld_desc"], "color" => $row["sfld_color"]);
    }
    return $data;
}

function get_display($db, $cluster_id, $version = "", $ascore = "", $child_id = "") {
    $cpath = functions::get_data_dir_path($cluster_id, $version, $ascore, $child_id);
    //$cpath = "$basepath/$cluster_id";

    $feat = array();
    if (file_exists("$cpath/weblogo.png"))
        array_push($feat, "weblogo");
    if (file_exists("$cpath/length_histogram_lg.png"))
        array_push($feat, "length_histogram");
    return $feat;
}

function get_consensus_residues($db, $cluster_id, $version = "", $ascore = "", $child_id = "") {
    $is_diced_parent = $ascore && !$child_id;
    if (!$is_diced_parent)
        return array();
    $cpath = functions::get_data_dir_path($cluster_id, $version, $ascore, $child_id);
    $files = glob("$cpath/consensus_residue_*_all.zip");
    $res = array();
    foreach ($files as $file) {
        preg_match("/^.*consensus_residue_([A-Z])_.*$/", $file, $matches);
        if (isset($matches[1]))
            array_push($res, $matches[1]);
    }
    return $res;
}

function get_download($db, $cluster_id, $version = "", $ascore = "", $child_id = "") {
    $cpath = functions::get_data_dir_path($cluster_id, $version, $ascore, $child_id);
    if ($ascore && $child_id)
        $parent_path = functions::get_data_dir_path2($db, $version, $ascore, $cluster_id);
    //$cpath = "$basepath/$cluster_id";

    $feat = array();

    $show_child_feat = $ascore && !$child_id;

    $ssn = functions::get_ssn_path($db, $cluster_id);
    if ($ssn)
        array_push($feat, "ssn");
    if (file_exists("$cpath/weblogo.png") || $show_child_feat)
        array_push($feat, "weblogo");
    if (file_exists("$cpath/msa.afa") || $show_child_feat)
        array_push($feat, "msa");
    if (file_exists("$cpath/hmm.hmm") || $show_child_feat)
        array_push($feat, "hmm");
    if (file_exists("$cpath/ssn.zip") || $show_child_feat)
        array_push($feat, "ssn");
    else if ($parent_path && (file_exists("$parent_path/ssn.zip") || $show_child_feat))
        array_push($feat, "ssn");
    if (file_exists("$cpath/uniprot.txt") || $show_child_feat)
        array_push($feat, "id_fasta");
    if (file_exists("$cpath/swissprot.txt") || $show_child_feat)
        array_push($feat, "misc");

    return $feat;
    //return array("ssn", "weblogo", "msa", "hmm", "id_fasta", "misc");
}

function get_generic_fetch($db, $cluster_id, $sql, $handle_row_fn, $check_only = false) {
    return functions::get_generic_fetch($db, $cluster_id, $sql, $handle_row_fn, $check_only);
    //$sth = $db->prepare($sql);
    //if (!$sth)
    //    return $check_only ? 0 : array();
    //$sth->bindValue("id", $cluster_id);
    //$sth->execute();
    //$data = array();
    //while ($row = $sth->fetch()) {
    //    if ($check_only)
    //        return $handle_row_fn($row);
    //    array_push($data, $handle_row_fn($row));
    //}
    //return $check_only ? 0 : $data;
}

function get_generic_join_sql($table, $parm, $extra_where = "", $ascore = "", $check_only = false, $extra_join = "") {
    if (USE_UNIPROT_ID_JOIN) {
        $join_table = $ascore ? DICED_ID_MAPPING : "id_mapping";
        $sql = "SELECT $parm FROM $table INNER JOIN $join_table ON $table.uniprot_id = $join_table.uniprot_id $extra_join WHERE $join_table.cluster_id = :id";
        if ($ascore)
            $sql .= " AND $join_table.ascore = '$ascore'";
        if ($extra_where)
            $sql .= " $extra_where";
        if ($check_only)
            $sql .= " LIMIT 1";
        return $sql;
    } else {
        return functions::get_generic_sql($table, $parm, $extra_where, $check_only);
    }

    //$check_parm = $check_only ? "COUNT(*) AS $parm" : "$parm";
    //$sql = "SELECT $check_parm FROM $table LEFT JOIN id_mapping ON $table.uniprot_id = id_mapping.uniprot_id WHERE id_mapping.cluster_id = :id";
    
    //return functions::get_generic_sql($table, $parm, $extra_where, $check_only);
    //if ($check_only)
    //    $sql = "SELECT COUNT(*) AS $parm FROM $table WHERE cluster_id = :id $extra_where";
    //else
    //    $sql = "SELECT $parm FROM $table WHERE cluster_id = :id $extra_where";
    //return $sql;
}

function get_kegg($db, $cluster_id, $ascore = "", $check_only = false) {
    $sql = get_generic_join_sql("kegg", "kegg", "", $ascore, $check_only);
    $row_fn = function($row) { return $row["kegg"]; };
    return get_generic_fetch($db, $cluster_id, $sql, $row_fn, $check_only);
}

function get_swissprot($db, $cluster_id, $ascore = "", $check_only = false) {
    $sql = get_generic_join_sql("swissprot", "function, GROUP_CONCAT(swissprot.uniprot_id) AS ids", "GROUP BY function ORDER BY function", $ascore, $check_only);
    $row_fn = function($row) { return ($row["function"] && $row["ids"]) ? array($row["function"], $row["ids"]) : false; };
    return get_generic_fetch($db, $cluster_id, $sql, $row_fn);
}

function get_pdb($db, $cluster_id, $ascore = "", $check_only = false) {
    $sql = get_generic_join_sql("pdb", "pdb, pdb.uniprot_id", "", $ascore, $check_only);
    $row_fn = function($row) { return array($row["pdb"], $row["uniprot_id"]); };
    return get_generic_fetch($db, $cluster_id, $sql, $row_fn);
}

function get_tigr($db, $cluster_id, $check_only = false) {
    $sql = "SELECT families.family, family_info.description FROM families LEFT JOIN family_info ON families.family = family_info.family WHERE cluster_id = :id AND family_type = 'TIGR'";
    $row_fn = function($row) { return array($row["family"], $row["description"]); };
    return get_generic_fetch($db, $cluster_id, $sql, $row_fn);
}

function get_enzyme_codes($db) {
    $sql = "SELECT * FROM enzymecode";
    $sth = $db->prepare($sql);
    $sth->execute();
    $data = array();
    while ($row = $sth->fetch()) {
        $data[$row["code_id"]] = $row["desc"];
    }
    return $data;
}

function get_regions($db, $cluster_id) {
    $sql = functions::get_generic_sql("region", "*", "ORDER BY region_index");
    //cluster_id TEXT, region_id TEXT, region_index INT, name TEXT, number TEXT, coords TEXT
    $row_fn = function($row) {
        $data = array();
        $data["id"] = $row["region_id"];
        $data["name"] = $row["name"];
        $data["number"] = $row["number"];
        $data["coords"] = array_map(function($c) { return floatval($c); }, explode(",", $row["coords"]));
        return $data;
    };
    return get_generic_fetch($db, $cluster_id, $sql, $row_fn);
}


function get_dicing_children($db, $cluster_id, $ascore = "") {
    $ascore_col = ASCORE_COL;
    $diced_net_table = DICED_NETWORK;
    $diced_size_table = DICED_SIZE;
    $sql = <<<SQL
    SELECT $diced_net_table.cluster_id AS cluster_id,
        $diced_size_table.uniprot AS uniprot,
        $diced_size_table.uniref90 AS uniref90,
        $diced_size_table.uniref50 AS uniref50
    FROM $diced_net_table
    LEFT JOIN $diced_size_table ON $diced_net_table.cluster_id = $diced_size_table.cluster_id
    WHERE $diced_net_table.parent_id = :id
SQL;
    //DEBUG TODO
    $has_legacy_bug = false;
    //$has_legacy_bug = USE_UNIPROT_ID_JOIN == false;
    if ($ascore)
        $sql .= " AND $diced_net_table.$ascore_col = :ascore"
            . " AND $diced_size_table.$ascore_col = :ascore";
    $sth = $db->prepare($sql);
    if (!$sth)
        return array();
    $row_fn = function($row) {
        return array("id" => $row["cluster_id"],
            "size" => array("uniprot" => $row["uniprot"], "uniref50" => $row["uniref50"], "uniref90" => $row["uniref90"]));
    };

    $sth->bindValue("id", $cluster_id);
    if (!$has_legacy_bug && $ascore)
        $sth->bindValue("$ascore_col", $ascore);
    $sth->execute();
    $data = array();
    while ($row = $sth->fetch()) {
        array_push($data, $row_fn($row));
    }

    return $data;
}


//function get_dicing_children($db, $cluster_id, $ascore = "") {
//    $sql = "SELECT diced_network.cluster_id AS cluster_id, diced_size.uniprot AS uniprot, diced_size.uniref90 AS uniref90, diced_size.uniref50 AS uniref50 FROM diced_network LEFT JOIN diced_size ON diced_network.cluster_id = diced_size.cluster_id WHERE parent_id = :id";
//    if ($ascore)
//        $sql .= " AND diced_network.ascore = :ascore";
//    $sth = $db->prepare($sql);
//    if (!$sth)
//        return array();
//    
//    $row_fn = function($row) {
//        return array("id" => $row["cluster_id"],
//            "size" => array("uniprot" => $row["uniprot"], "uniref50" => $row["uniref50"], "uniref90" => $row["uniref90"]));
//    };
//
//    $sth->bindValue("id", $cluster_id);
//    if ($ascore)
//        $sth->bindValue("ascore", $ascore);
//    $sth->execute();
//    $data = array();
//    while ($row = $sth->fetch()) {
//        array_push($data, $row_fn($row));
//    }
//
//    return $data;
//}


function get_alt_ssns($db, $cluster_id) {
    $table = DICED_SSN;
    $ascore_col = ASCORE_COL;
    $sql = "SELECT * FROM $table WHERE cluster_id = :id AND $ascore_col != ''";
    $row_fn = function($row) {
        return array($row[ASCORE_COL]);
    };
    return get_generic_fetch($db, $cluster_id, $sql, $row_fn);
}
function get_default_alignment_score($db, $cluster_id) {
    //TODO:
    //HACK:
    //LEGENDARYHACK:
    //WORSTHACKIVEEVERMADE:
    // Fix this by storing the default AS in the db...
    if ($cluster_id == "cluster-1-1")
        return "11";
    elseif ($cluster_id == "cluster-2-1")
        return "15";
    else
        return "";
}

function get_sizes($db, $id, $ascore = "", $is_child = false) {
    $table = $is_child ? DICED_SIZE : "size";
    $sql = "SELECT * FROM $table WHERE cluster_id = :id";
    if ($ascore && $is_child)
        $sql .= " AND ascore = '$ascore'";
    $row_fn = function($row) {
        return array("uniprot" => $row["uniprot"], "uniref90" => $row["uniref90"], "uniref50" => $row["uniref50"]);
    };
    $result = get_generic_fetch($db, $id, $sql, $row_fn);
    return (count($result) > 0 ? $result[0] : array());
    /*
    $sql = "SELECT * FROM size WHERE cluster_id = :id";
    $sth = $db->prepare($sql);
    $sth->bindValue("id", $id);
    $sth->execute();
    $row = $sth->fetch();
    if (!$row)
        return array();
    else
        return array("uniprot" => $row["uniprot"], "uniref90" => $row["uniref90"], "uniref50" => $row["uniref50"]);
     */
}

function get_tax_data($db, $cluster_id, $ascore) {
    $uniref_join = "LEFT JOIN uniref_map ON taxonomy.uniprot_id = uniref_map.uniprot_id";
    $uniref_parm = ", uniref_map.uniref50_id, uniref_map.uniref90_id";
    $sql = get_generic_join_sql("taxonomy", "taxonomy.* $uniref_parm", "", $ascore, false, $uniref_join);
    //$sql = get_generic_join_sql("taxonomy", "*", "", $ascore);
    $sth = $db->prepare($sql);
    if (!$sth)
        return array();
    $sth->bindValue("id", $cluster_id);
    $sth->execute();

    $tree = array();
    #$mk_struct_fn = function($name) { return array("numSpecies" => 0, "node" => $name, "children" => array()); };
    $add_data_fn = function($domain, $kingdom, $phylum, $class, $taxorder, $family, $genus, $species, $uniprot, $uniref50, $uniref90) use (&$tree) {
        if (!isset($tree[$domain]))
            $tree[$domain] = array();
        if (!isset($tree[$domain][$kingdom]))
            $tree[$domain][$kingdom] = array();
        if (!isset($tree[$domain][$kingdom][$phylum]))
            $tree[$domain][$kingdom][$phylum] = array();
        if (!isset($tree[$domain][$kingdom][$phylum][$class]))
            $tree[$domain][$kingdom][$phylum][$class] = array();
        if (!isset($tree[$domain][$kingdom][$phylum][$class][$taxorder]))
            $tree[$domain][$kingdom][$phylum][$class][$taxorder] = array();
        if (!isset($tree[$domain][$kingdom][$phylum][$class][$taxorder][$family]))
            $tree[$domain][$kingdom][$phylum][$class][$taxorder][$family] = array();
        if (!isset($tree[$domain][$kingdom][$phylum][$class][$taxorder][$family][$genus]))
            $tree[$domain][$kingdom][$phylum][$class][$taxorder][$family][$genus] = array();
        if (!isset($tree[$domain][$kingdom][$phylum][$class][$taxorder][$family][$genus][$species]))
            $tree[$domain][$kingdom][$phylum][$class][$taxorder][$family][$genus][$species] = array("sequences" => array());
        $leaf_data = array("numDomains" => 0, "seedSeq" => 0, "seqAcc" => $uniprot, "sa50" => $uniref50, "sa90" => $uniref90);
        //$leaf_data = array("numDomains" => 0, "seedSeq" => 0, "seqAcc" => $uniprot);
        array_push($tree[$domain][$kingdom][$phylum][$class][$taxorder][$family][$genus][$species]["sequences"], $leaf_data);
    };

    while ($row = $sth->fetch()) {
        $add_data_fn($row["domain"], $row["kingdom"], $row["phylum"], $row["class"], $row["taxorder"], $row["family"], $row["genus"], $row["species"], $row["uniprot_id"], $row["uniref50_id"], $row["uniref90_id"]);
        //$add_data_fn($row["domain"], $row["kingdom"], $row["phylum"], $row["class"], $row["taxorder"], $row["family"], $row["genus"], $row["species"], $row["uniprot_id"]);
    }

    # Convert tree into something that the sunburst libraries like
    $data = array("numSequences" => 0, "numSpecies" => 0, "node" => "Root", "children" => array());
    $species_map = array();

    list($kids, $num_seq, $num_species) = traverse_tree($tree, "root", $species_map);
    $data["children"] = $kids;
    $data["numSequences"] = $num_seq;
    $data["numSpecies"] = $num_species;

    return $data;
}
function traverse_tree($tree, $parent_name, $species_map) {
    $num_species = 0;
    $num_seq = 0;
    $data = array();
    foreach ($tree as $name => $group) {
        if ($name == "sequences") {
            if (!isset($species_map[$parent_name])) {
                $num_species += 1; //TODO: figure out why the pfam website sometimes returns numSpecies = 0???a
                $species_map[$parent_name] = 1;
            }
            $num_seq += count($group);
        } else {
            $struct = array("node" => $name);
            list($kids, $num_seq_next, $num_species_next) = traverse_tree($group, strtolower($name), $species_map);
            $struct["numSequences"] = $num_seq_next;
            $struct["numSpecies"] = $num_species_next;

            if (isset($group["sequences"]))
                $struct["sequences"] = $group["sequences"];

            $num_seq += $num_seq_next;
            $num_species += $num_species_next;
            $kids = array_map(function($x) use ($name) { $x["parent"] = $name; return $x; }, $kids);
            if (count($kids))
                $struct["children"] = $kids;
            #var_dump($struct);
            #print "<br>";
            array_push($data, $struct);
        }
    }
    return array($data, $num_seq, $num_species);
};


function validate_action($action) {
    return ($action == "cluster" || $action == "kegg" || $action == "netinfo" || $action == "tax");
}

