<?php

namespace Stanford\ProjCROP;
/** @var \Stanford\ProjCROP\ProjCROP $module */

require_once("RepeatingForms.php");

use REDCap;
use Project;



$sunet_id_wu = $_SERVER['WEBAUTH_USER'];
$module->emDebug("WEBAUTH user found is ". $sunet_id_wu);
$sunet_id_ru = $_SERVER['REMOTE_USER'];
$module->emDebug("REmote user found is ". $sunet_id_ru);
//use framework method
$sunet_id = $module->getUser()->getUsername();
$module->emDebug("User username is ". $sunet_id);
//$sunet_id = 'petunia';


 //We need endpoint for a webauthed user who is not a REDCap project user
//Use different parameter for PID: 'projectId', and then pass that project id to all the methods
//since we won't have project context.
$pid = $_GET['projectId'] ? $_GET['projectId']: $_GET['pid'];
$_GET['pid']=$pid;

$proj =  new Project($pid);
$module->emDebug("Starting CROP landing page for project $pid");

//if sunet ID not set leave
if (!isset($sunet_id)) {
    die("SUNet ID was not available. Please webauth in and try again!");
}

//look up the sunet id in the project and present the available status in the data table.
//1. if none, create new form
//2. If exists,

$dem_array = $module->findRecordFromSUNet($pid, $sunet_id, $module->getProjectSetting('application-event', $pid));

if (!isset($dem_array)) {
    die("Record was not found for $sunet_id. Please contact CROP admin.");
}

$record = $dem_array[$proj->table_pk];

$cert_mode = $dem_array['cert_mode'];

//if blank or certification, then recertifyy is false
$recertify = false;
$repeating_seminar_instrument = 'seminars_trainings';
$repeating_seminar_instrument = $module->getProjectSetting('training-survey-form', $pid);

if ($cert_mode) {
    $recertify = true;

    $repeating_seminar_instrument = 'recertification_form';
    $repeating_seminar_instrument = $module->getProjectSetting('recertification-form', $pid);
}

$module->emDebug("Record is $record / status is $cert_mode / instrument is  $repeating_seminar_instrument");

//get the latest seminar form
$rf = new RepeatingForms($pid, $repeating_seminar_instrument);
$rf->loadData($record, null, null);
//$data = $rf->getAllInstances($record, null);

$exam_event = $module->framework->getProjectSetting('exam-event');
$last_instance = $rf->getLastInstanceId($record,$exam_event);


//$module->emDebug("last instance id is $last_instance", $_POST);

//there is instance yet
if ($last_instance === null) {
    $module->emDebug("No instance yet, creating a new one");
    $last_instance = 1;
}
//$instance = $rf->getInstanceById($record, $last_instance, $exam_event);
$url = $rf->getSurveyUrlForInstrument($pid, $record, $last_instance, $repeating_seminar_instrument, $exam_event);
//$module->emDebug("Last instance is $last_instance", $instance);

if ($url == null) {
    die("The survey url was not available. Please notify your admin!");
}

if ($recertify === true ) {
    // RENDERING THE SUMMARY PAGE WITH TABLE
    //include("recertification.php");
    $module->redirect($url);
} else {
    //include("certification.php");
    $module->redirect($url);

}
?>




