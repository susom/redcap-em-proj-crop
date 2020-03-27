<?php

namespace Stanford\ProjCROP;
/** @var \Stanford\ProjCROP\ProjCROP $module */

require_once("RepeatingForms.php");

use REDCap;

$module->emDebug("Starting CROP landing page for project $pid");

$sunet_id = $_SERVER['WEBAUTH_USER'];
//$sunet_id = 'foo';

//if sunet ID not set leave
if (!isset($sunet_id)) {
    die("SUNet ID was not available. Please webauth in and try again!");
}

//$module->emDebug($_POST);
//look up the sunet id in the project and present the available status in the data table.
//1. if none, create new form
//2. If exists,
$dem_array = $module->findRecordFromSUNet($sunet_id, $module->getProjectSetting('application-event'));

if (!isset($dem_array)) {
    die("Record was not found for $sunet_id. Please contact CROP admin.");
}

$record = $dem_array[REDCap::getRecordIdField()];

//$module->emDebug($dem_array, $record);

$first_name = $dem_array['first_name'];
$last_name = $dem_array['last_name'];
$cert_status = $dem_array['cert_status'];

//if blank or certification, then recertifyy is false
$recertify = false;
if ($cert_status) {
    $recertify = true;
}
$cert_start      = $dem_array["cert_start"];
$cert_end        = $dem_array["cert_end"];

$module->emDebug("Record is $record / status is $cert_status ");

//todo: use config property
$repeating_seminar_instrument = 'seminars_trainings';

//get the latest seminar form
$rf = new RepeatingForms($pid, $repeating_seminar_instrument);
$rf->loadData($record, null, null);
//$data = $rf->getAllInstances($record, null);

$exam_event = $module->getProjectSetting('exam-event');
$last_instance = $rf->getLastInstanceId($record,$exam_event);


$module->emDebug("last instance id is $last_instance");

//there is instance yet
if ($last_instance === null) {
    $module->emDebug("No instance yet, creating a new one");
    $last_instance = 1;
}
$instance = $rf->getInstanceById($record, $last_instance, $exam_event);
//$module->emDebug("Last instance is $last_instance", $instance);
//$latest_instance = getLatestSeminar($record);


if (isset($_POST['schedule'])) {
    $module->emDebug("Verification requested... ");
    $module->sendAdminVerifyEmail($record, $exam_event, $last_instance);
    $result = array(
        'result' => 'success',
        'msg' => 'Verification has been started. You will be contacted soon on its status.'
    );
/*
    //$schedule_data = $module->scheduleExam($record);
    $schedule_data['ready_for_exam___1'] = '1';

    $module->emDebug($schedule_data);
    $save_status = $rf->saveInstance($record, $schedule_data, $last_instance, $exam_event);
    if ($save_status == false) {
        $module->emDebug("Error saving : ". $rf->last_error_message);
        $result = array(
            'result' => 'fail',
            'msg' => 'There was an error starting verification. Please notify your admin.'
        );
    } else {
        $module->sendAdminVerifyEmail($record, $exam_event, $last_instance);
        $result = array(
            'result' => 'success',
            'msg' => 'Verification has been started. You will be contacted soon on its status.'
        );
    }
*/
    header('Content-Type: application/json');
    print json_encode($result);
    exit();

}

if (isset($_POST['recertify'])) {
    $module->emDebug("Handling recertification");
    $rf_ready_for_verification_field = "rf_ready_for_verification";  //checkbox from recertification form

    $status = $module->sendAdminVerifyRecertificationEmail($record, $exam_event, $last_instance);
    //if () {
        $result = array(
            'result' => 'success',
            'msg' => 'Recertification validation has been started. You will be contacted soon on its status.'
        );
    //}

    /*
    $recertify_data = array(); //reset
    //$schedule_data = $module->scheduleExam($record);
    $recertify_data[$rf_ready_for_verification_field."___1"] = '1';

    $module->emDebug($recertify_data);

    $save_status = $rf->saveInstance($record, $recertify_data, $last_instance, $exam_event);
    if ($save_status == false) {
        $module->emDebug("Error saving : ". $rf->last_error_message);
        $result = array(
            'result' => 'fail',
            'msg' => 'There was an error recertification. Please notify your admin.'
        );
    } else {
        $module->sendAdminVerifyRecertificationEmail($record, $exam_event, $last_instance);
        $result = array(
            'result' => 'success',
            'msg' => 'Recertification validation has been started. You will be contacted soon on its status.'
        );
    }
*/

    header('Content-Type: application/json');
    print json_encode($result);
    exit();

}

if (isset($_POST['save_form'])) {
    $module->emDebug("Handling Save Form");

    $save_data = $module->setupSaveData($_POST['date_values'],$_POST['text_values'],  $_POST['coded_values']);


    $save_status = $rf->saveInstance($record, $save_data, $last_instance, $exam_event);

    //$module->emDebug($save_data, $save_status);

    if ($save_status == false) {
        $module->emDebug("Error saving : ". $rf->last_error_message);
           $result = array(
                'result' => 'fail',
                'msg' => 'There was an error saving the form. Please notify your admin.'
            );
    } else {
        $module->emDebug("Success saving : ". $rf->last_error_message);
        $result = array(
            'result' => 'success',
            'msg' => 'Your form has been saved. If ready to start verification, click the Start Verification button below to schedule an exam.'
        );
    }

    header('Content-Type: application/json');
    print json_encode($result);
    exit();

}


if ($recertify === true) {
    // RENDERING THE SUMMARY PAGE WITH TABLE
    include("recertification.php");
} else {
    include("certification.php");
}
?>




