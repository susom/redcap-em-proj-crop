<?php

namespace Stanford\ProjCROP;
/** @var \Stanford\ProjCROP\ProjCROP $module */

require_once("RepeatingForms.php");

use REDCap;
use DateTime;

$record = isset($_REQUEST['recid']) ? $_REQUEST['recid'] : "";
$instance = isset($_REQUEST['instance']) ? $_REQUEST['instance'] : "";
$event_name = isset($_REQUEST['event']) ? $_REQUEST['event'] : "";
$num = isset($_REQUEST['num']) ? $_REQUEST['num'] : "";

$module->emDebug("Starting CROP GetNextSurveyLink for project $pid and record $record and instance $instance");

if (empty($record)) {
    $module->emDebug("Record id not passed through EM link.");
    die("Unable to locate your followup survey. Please notify your admin");
}

$url = $module->getAnnualUrl($record, $event_name, $instance, $num);

//redirect to the annual survey
header("Location: " . $url);
exit;
