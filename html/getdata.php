<?php

require_once(__DIR__ . "/../libs/settings.class.inc.php");
require_once(__DIR__ . "/../libs/functions.class.inc.php");

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
    $kegg = get_kegg($db, $cluster_id);
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
    $tax = get_tax_data($db, $cluster_id);
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
        "name" => "",
        "desc" => "",
        "image" => "",
        "title" => "",
        "display" => array(),
        "download" => array(),
        "regions" => array(),
        "subgroups" => array(),
        "public" => array(
            "kegg_count" => 0,
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
        "dir" => "",
    );

    $data["dicing"]["parent"] = get_dicing_parent($db, $cluster_id);
    $data["dicing"]["children"] = get_dicing_children($db, $cluster_id);
    $parent_cluster_id = $data["dicing"]["parent"];
    $child_cluster_id = "";
    $is_child = $parent_cluster_id ? true : false;
    if ($is_child) {
        $child_cluster_id = $cluster_id;
    } else {
        $parent_cluster_id = $cluster_id;
    }

    $info = get_network_info($db, $cluster_id);
    $data["name"] = $info["name"];
    $data["title"] = $info["title"];
    $data["desc"] = $info["desc"];
    $data["image"] = $cluster_id;
    $data["public"]["kegg_count"] = get_kegg($db, $cluster_id, true);
    $data["size"] = get_sizes($db, $cluster_id);
    $data["public"]["swissprot"] = get_swissprot($db, $cluster_id);
    $data["public"]["pdb"] = get_pdb($db, $cluster_id);
    $data["families"]["tigr"] = get_tigr($db, $cluster_id);
    $data["display"] = get_display($db, $parent_cluster_id, $version, $ascore, $child_cluster_id);
    $data["download"] = get_download($db, $parent_cluster_id, $version, $ascore, $child_cluster_id);
    $data["regions"] = get_regions($db, $cluster_id);
    $data["alt_ssn"] = get_alt_ssns($db, $cluster_id);

    $data["dir"] = functions::get_rel_data_dir_path($parent_cluster_id, $version, $ascore, $child_cluster_id);
    if ($ascore)
        $data["alignment_score"] = $ascore;
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
function get_network_info_title($row, $sfld_only = false) {
    $title = !$sfld_only ? $row["name"] : "";
    if ($row["title"] && $row["sfld_id"]) {
        if ($sfld_only) {
            $title .= $row["title"];
        } else {
            $title .= ": SFLD Subgroup " . $row["sfld_id"];
            $title .= " / " . $row["title"];
        }
        if (preg_match("/<SFLD>/", $title)) {
            $title = preg_replace("/<SFLD>/", $row["sfld_desc"], $title);
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
function get_network_info($db, $cluster_id) {
    $sql = get_network_info_sfld_sql() . " WHERE network.cluster_id = :id";
    $sth = $db->prepare($sql);
    $sth->bindValue("id", $cluster_id);
    $sth->execute();
    $row = $sth->fetch();
    if ($row) {
        $title = get_network_info_title($row);
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

function get_download($db, $cluster_id, $version = "", $ascore = "", $child_id = "") {
    $cpath = functions::get_data_dir_path($cluster_id, $version, $ascore, $child_id);
    //$cpath = "$basepath/$cluster_id";

    $feat = array();

    $ssn = functions::get_ssn_path($db, $cluster_id);
    if ($ssn)
        array_push($feat, "ssn");
    if (file_exists("$cpath/weblogo.png"))
        array_push($feat, "weblogo");
    if (file_exists("$cpath/msa.afa"))
        array_push($feat, "msa");
    if (file_exists("$cpath/hmm.hmm"))
        array_push($feat, "hmm");
    if (file_exists("$cpath/uniprot.txt"))
        array_push($feat, "id_fasta");
    if (file_exists("$cpath/swissprot.txt"))
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

function get_generic_sql($table, $parm, $extra_where = "", $check_only = false) {
    return functions::get_generic_sql($table, $parm, $extra_where, $check_only);
    //if ($check_only)
    //    $sql = "SELECT COUNT(*) AS $parm FROM $table WHERE cluster_id = :id $extra_where";
    //else
    //    $sql = "SELECT $parm FROM $table WHERE cluster_id = :id $extra_where";
    //return $sql;
}

function get_kegg($db, $cluster_id, $check_only = false) {
    $sql = get_generic_sql("kegg", "kegg", "", $check_only);
    $row_fn = function($row) { return $row["kegg"]; };
    return get_generic_fetch($db, $cluster_id, $sql, $row_fn, $check_only);
}

function get_swissprot($db, $cluster_id, $check_only = false) {
    $sql = get_generic_sql("swissprot", "function, GROUP_CONCAT(uniprot_id) AS ids", "GROUP BY function ORDER BY function", $check_only);
    #$sql = get_generic_sql("swissprot", "DISTINCT(function)", "ORDER BY function", $check_only);
    $row_fn = function($row) { return array($row["function"], $row["ids"]); };
    return get_generic_fetch($db, $cluster_id, $sql, $row_fn);
}

function get_pdb($db, $cluster_id, $check_only = false) {
    $sql = get_generic_sql("pdb", "pdb, uniprot_id", "", $check_only);
    $row_fn = function($row) { return array($row["pdb"], $row["uniprot_id"]); };
    return get_generic_fetch($db, $cluster_id, $sql, $row_fn);
}

function get_tigr($db, $cluster_id, $check_only = false) {
    $sql = "SELECT families.family, family_info.description FROM families LEFT JOIN family_info ON families.family = family_info.family WHERE cluster_id = :id AND family_type = 'TIGR'";
    //$sql = get_generic_sql("families", "family", "AND family_type = 'TIGR'", $check_only);
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
    $sql = get_generic_sql("region", "*", "ORDER BY region_index", $check_only);
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

function get_dicing_parent($db, $cluster_id) {
    $sql = get_generic_sql("dicing", "parent_id");
    $row_fn = function($row) {
        return $row["parent_id"];
    };
    $data = get_generic_fetch($db, $cluster_id, $sql, $row_fn, true); // true = return only first row in this case;
    return isset($data) ? $data : "";
}

function get_dicing_children($db, $cluster_id) {
    $sql = "SELECT cluster_id FROM dicing WHERE parent_id = :id";
    $row_fn = function($row) {
        return $row["cluster_id"];
    };
    return get_generic_fetch($db, $cluster_id, $sql, $row_fn); // true = return only first row in this case;
}

function get_alt_ssns($db, $cluster_id) {
    $sql = "SELECT * FROM ssn WHERE cluster_id = :id AND alignment_score != ''";
    $row_fn = function($row) {
        return array($row["alignment_score"]);
    };
    return get_generic_fetch($db, $cluster_id, $sql, $row_fn);
}

function get_sizes($db, $id) {
    $sql = "SELECT * FROM size WHERE cluster_id = :id";
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

function get_tax_data($db, $cluster_id) {
    $sql = "SELECT * FROM taxonomy WHERE cluster_id = :id";
    $sth = $db->prepare($sql);
    if (!$sth)
        return array();
    $sth->bindValue("id", $cluster_id);
    $sth->execute();

    $tree = array();
    #$mk_struct_fn = function($name) { return array("numSpecies" => 0, "node" => $name, "children" => array()); };
    $add_data_fn = function($domain, $kingdom, $phylum, $class, $taxorder, $family, $genus, $species, $uniprot) use (&$tree) {
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
        array_push($tree[$domain][$kingdom][$phylum][$class][$taxorder][$family][$genus][$species]["sequences"], array("numDomains" => 0, "seedSeq" => 0, "seqAcc" => $uniprot));
    };
    while ($row = $sth->fetch()) {
        $add_data_fn($row["domain"], $row["kingdom"], $row["phylum"], $row["class"], $row["taxorder"], $row["family"], $row["genus"], $row["species"], $row["uniprot_id"]);
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

