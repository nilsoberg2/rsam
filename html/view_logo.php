<?php 

require_once(__DIR__ . "/../libs/settings.class.inc.php");
require_once(__DIR__ . "/../libs/functions.class.inc.php");

//$version = filter_input(INPUT_GET, "v", FILTER_SANITIZE_NUMBER_INT);
$version = functions::validate_version();

$db = functions::get_database($version);

$cluster_id = filter_input(INPUT_GET, "cid", FILTER_SANITIZE_STRING);
$ascore = filter_input(INPUT_GET, "as", FILTER_SANITIZE_NUMBER_INT);

if (!$cluster_id || !functions::validate_cluster_id($db, $cluster_id)) {
    echo json_encode(array("valid" => false, "message" => "Invalid request."));
    exit(0);
}

$basepath = functions::get_data_dir_path2($db, $version, $ascore, $cluster_id);
$hmm_path = "$basepath/hmm.json";
$json = file_get_contents($hmm_path);

$title = isset($_GET["title"]) ? $_GET["title"] : "";

?>

<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<link rel="stylesheet" type="text/css" href="../css/hmm_logo.min.css">
<script src="js/jquery-3.4.1.min.js" type="text/javascript"></script>
<script src="js/hmm_logo.js" type="text/javascript"></script>
    <title>Logo</title>
</head>
<body>

<div><big><b><?php echo $title; ?></b></big></div>


<div id="logo" class="logo" data-logo='<?php echo $json; ?>'></div>

<script>
$(document).ready(function () {
    var data = <?php echo $json; ?>;
    $("#logo").hmm_logo({height_toggle: true}).toggle_scale("obs");
});
</script>

</body>
</html>

