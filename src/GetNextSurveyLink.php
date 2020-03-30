<?php

namespace Stanford\ProjCROP;
/** @var \Stanford\ProjCROP\ProjCROP $module */

require_once("RepeatingForms.php");

use REDCap;

$record = isset($_REQUEST['recid']) ? $_REQUEST['recid'] : "";
$instance = isset($_REQUEST['instance']) ? $_REQUEST['instance'] : "";
$module->emDebug("Starting CROP GetNext Survey Link for project $pid and record $record and instance $instance");

if (empty($record)) {
    $module->emDebug("Record id not passed through EM link.");
    die("Unable to locate your followup survey. Please notify your admin");
}

$rf = RepeatingForms::byForm($pid, $module->getProjectSetting('fup-survey-form'));

$next_instance = $rf->getNextInstanceId($record, $module->getProjectSetting('application-event'));

$url =  $rf->getSurveyUrl($record,$next_instance);
$module->emDebug("next _instance is $next_instance", $url);

//redirect to teh survey
header("Location: " . $url);
exit;
