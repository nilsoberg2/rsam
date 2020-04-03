<?php

require_once(__DIR__ . "/../libs/settings.class.inc.php");
require_once(__DIR__ . "/../libs/functions.class.inc.php");

$db = functions::get_database();

// Available actions:
//   cluster    get cluster data
//   kegg       get kegg ids
//
$action = filter_input(INPUT_GET, "a", FILTER_SANITIZE_STRING);
$cluster_id = filter_input(INPUT_GET, "cid", FILTER_SANITIZE_STRING);

if (!validate_action($action) || ($cluster_id && !validate_cluster_id($db, $cluster_id))) {
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
    $cluster = get_cluster($db, $cluster_id);
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
    if ($cluster === false) {
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






function get_cluster($db, $cluster_id) {
    $data = array(
        "size" => array(
            "uniprot" => 0,
            "uniref90" => 0,
            "uniref50" => 0,
        ),
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
    );

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
    $data["display"] = get_display($db, $cluster_id);
    $data["download"] = get_download($db, $cluster_id);
    $data["regions"] = get_regions($db, $cluster_id);

    return $data;
}






function get_network_info($db, $cluster_id) {
    $sql = "SELECT * FROM network WHERE cluster_id = :id";
    $sth = $db->prepare($sql);
    $sth->bindValue("id", $cluster_id);
    $sth->execute();
    $row = $sth->fetch();
    if ($row)
        return $row;
    else
        return array("cluster_id" => $cluster_id, "name" => "", "title" => "", "desc" => "");
}
function get_all_network_names($db) {
    $sql = "SELECT network.cluster_id, name, uniprot, uniref50, uniref90 FROM network LEFT JOIN size on network.cluster_id = size.cluster_id";
    $sth = $db->prepare($sql);
    $sth->execute();
    $data = array();
    while ($row = $sth->fetch()) {
        $data[$row["cluster_id"]] = array("name" => $row["name"], "size" => array("uniprot" => $row["uniprot"], "uniref90" => $row["uniref90"], "uniref50" => $row["uniref50"]));
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
        if (!is_array($data[$cid]))
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

function get_display($db, $cluster_id) {
    return array("weblogo", "length_histogram");
}

function get_download($db, $cluster_id) {
    return array("weblogo", "msa", "hmm", "id_fasta", "misc");
}

function get_generic_fetch($db, $cluster_id, $sql, $handle_row_fn, $check_only = false) {
    $sth = $db->prepare($sql);
    if (!$sth)
        return $check_only ? 0 : array();
    $sth->bindValue("id", $cluster_id);
    $sth->execute();
    $data = array();
    while ($row = $sth->fetch()) {
        if ($check_only)
            return $handle_row_fn($row);
        array_push($data, $handle_row_fn($row));
    }
    return $check_only ? 0 : $data;
}

function get_generic_sql($table, $parm, $extra_where = "", $check_only = false) {
    if ($check_only)
        $sql = "SELECT COUNT(*) AS $parm FROM $table WHERE cluster_id = :id $extra_where";
    else
        $sql = "SELECT $parm FROM $table WHERE cluster_id = :id $extra_where";
    return $sql;
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
    $sql = get_generic_sql("families", "family", "AND family_type = 'TIGR'", $check_only);
    $row_fn = function($row) { return $row["family"]; };
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

function validate_cluster_id($db, $id) {
    $sql = "SELECT name FROM network WHERE cluster_id = :id";
    $sth = $db->prepare($sql);
    $sth->bindValue("id", $id);
    if (!$sth->execute())
        return false;
    if ($sth->fetch())
        return true;
    else
        return false;
}



