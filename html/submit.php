<?php
require_once(__DIR__ . "/../libs/settings.class.inc.php");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    die("Invalid request");
}

$to = settings::get_submit_email();

$inputName = filter_input(INPUT_POST, "inputName", FILTER_SANITIZE_STRING);
$inputEmail = filter_input(INPUT_POST, "inputEmail", FILTER_SANITIZE_EMAIL);
$inputClusterId = filter_input(INPUT_POST, "inputClusterId", FILTER_SANITIZE_STRING);
$inputFunction = filter_input(INPUT_POST, "inputFunction", FILTER_SANITIZE_STRING);
$inputAccession = filter_input(INPUT_POST, "inputAccession", FILTER_SANITIZE_STRING);
$inputSequence = isset($_POST["inputSequence"]) ? filter_input(INPUT_POST, "inputSequence", FILTER_CALLBACK, array("options" => "checkFasta")) : "";
$inputDoi = filter_input(INPUT_POST, "inputDoi", FILTER_SANITIZE_STRING);
$inputDetails = filter_input(INPUT_POST, "inputDetails", FILTER_SANITIZE_STRING);
$inputTos = filter_input(INPUT_POST, "inputTos", FILTER_VALIDATE_BOOLEAN);

$valid = true;
$message = array();
if (!$inputName) {
    array_push($message, "Invalid name input.");
    $valid = false;
}
if (!filter_var($inputEmail, FILTER_VALIDATE_EMAIL)) {
    array_push($message, "Invalid email input.");
    $valid = false;
}
if (!$inputFunction) {
    array_push($message, "Invalid function/annotation.");
    $valid = false;
}
if (!$inputSequence) {
    array_push($message, "Invalid sequence.");
    $valid = false;
}
if (!$inputDetails) {
    array_push($message, "Invalid details input.");
    $valid = false;
}
if (!$inputTos) {
    array_push($message, "Terms of service must be acknowledged.");
    $valid = false;
}

$result = array("message" => $message, "valid" => $valid);


//TODO: twigify email

if (!$valid) {
    echo json_encode($result);
    exit(0);
}

$message = <<<MAIL
<html>
<head>
<style>
body { font-family: -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,"Noto Sans",sans-serif,"Apple Color Emoji","Segoe UI Emoji","Segoe UI Symbol","Noto Color Emoji"; }
td { padding: 5px;  padding-left: 30px; }
.out-label { font-weight: bold; }
</style>
<body>
<p>A new community annotation was submitted.</p>
<table border="0">
    <tr>
        <td class="out-label">Name</td>
        <td>$inputName</td>
    </tr>
    <tr>
        <td class="out-label">Email</td>
        <td>$inputEmail</td>
    </tr>
    <tr>
        <td class="out-label">Cluster ID</td>
        <td>$inputClusterId</td>
    </tr>
    <tr>
        <td class="out-label">Annotation/Function</td>
        <td>$inputFunction</td>
    </tr>
    <tr>
        <td class="out-label">Accession</td>
        <td>$inputAccession</td>
    </tr>
    <tr>
        <td style="vertical-align: top" class="out-label">Sequence</td>
        <td>$inputSequence</td>
    </tr>
    <tr>
        <td class="out-label">DOI</td>
        <td>$inputDoi</td>
    </tr>
    <tr>
        <td style="vertical-align: top" class="out-label">Details</td>
        <td>$inputDetails</td>
    </tr>
</table>
</body>
</html>
MAIL;

$headers[] = "MIME-Version: 1.0";
$headers[] = "Content-type: text/html; charset=iso-8859-1";
$headers[] = "From: RadicalSAM.org Community Annotation Submission <noreply@efi.igb.illinois.edu>";

mail($to, "RadicalSAM.org Community Annotation Submission", $message, implode("\r\n", $headers));


echo json_encode($result);





function checkFasta($val) {
    if (substr_count($val, ">") > 1)
        return "";
    $pos = strpos($val, ">");
    $val = preg_replace('/^.*>[^\r\n].*[\r\n]+/', "", $val);
    $val = preg_replace('/[\r\n ]+/', "", $val);
    if (!$val)
        return "";
    if (preg_match('/[^A-Z]/i', $val))
        return "";
    return $val;
}

