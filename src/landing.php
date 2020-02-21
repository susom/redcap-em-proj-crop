<?php

namespace Stanford\ProjCROP;
/** @var \Stanford\ProjCROP\ProjCROP $module */

require_once("RepeatingForms.php");
use REDCap;

$module->emDebug("Starting CROP landing page for project $pid");

$sunet_id = $_SERVER['WEBAUTH_USER'];
//$sunet_id = 'bam';

//if sunet ID not set leave
if (!isset($sunet_id)) {
    die("SUNet ID was not available. Please webauth in and try again!");
}

$module->emDebug($_POST);
//look up the sunet id in the project and present the available status in the data table.
//1. if none, create new form
//2. If exists,
$dem_array = $module->findRecordFromSUNet($sunet_id);

$record = $dem_array[REDCap::getRecordIdField()];
$first_name = $dem_array['first_name'];
$last_name = $dem_array['last_name'];
$module->emDebug("Record is $record");


//todo: use config property
$repeating_seminar_instrument = 'seminars_trainings';

//get the latest seminar form
$rf = new RepeatingForms($pid, $repeating_seminar_instrument);
$rf->loadData($record, null, null);
//$data = $rf->getAllInstances($record, null);
$last_instance = $rf->getLastInstanceId($record);
$instance = $rf->getInstanceById($record, $last_instance, null);

//there is instance yet
if ($last_instance === false) {
    $module->emDebug("No instance yet, creating a new one");
    $last_instance = 1;
}
//$module->emDebug("Last instance is $last_instance", $instance);
//$latest_instance = getLatestSeminar($record);


if (isset($_POST['schedule'])) {
    $module->emDebug("Handling schedule");

    //$schedule_data = $module->scheduleExam($record);
    $schedule_data['ready_for_exam___1'] = '1';

    $module->emDebug($schedule_data);
    $save_status = $rf->saveInstance($record, $schedule_data, $last_instance, null);
    if ($save_status == false) {
        $module->emDebug("Error saving : ". $rf->last_error_message);
        $result = array(
            'result' => 'fail',
            'msg' => 'There was an error starting verification. Please notify your admin.'
        );
    } else {

        $result = array(
            'result' => 'success',
            'msg' => 'Verification has been started. You will be contacted soon on its status.'
        );
    }

    header('Content-Type: application/json');
    print json_encode($result);
    exit();

}

if (isset($_POST['save_form'])) {
    $module->emDebug("Handling Save Form");

    $save_data = $module->setupSaveData($_POST['date_values'],$_POST['text_values'],  $_POST['checked_values']);

    $module->emDebug($save_data);
    $save_status = $rf->saveInstance($record, $save_data, $last_instance, null);
    if ($save_status == false) {
        $module->emDebug("Error saving : ". $rf->last_error_message);
           $result = array(
                'result' => 'fail',
                'msg' => 'There was an error saving the form. Please notify your admin.'
            );
    } else {
        $result = array(
            'result' => 'success',
            'msg' => 'Your form has been saved. If ready to start verification, click the Start Verification button below to schedule an exam.'
        );
    }

    header('Content-Type: application/json');
    print json_encode($result);
    exit();

}


?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <title>CROP</title>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=yes">

        <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/v/dt/dt-1.10.20/datatables.min.css"/>
        <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">
        <link rel="stylesheet" type="text/css" href="<?php echo $module->getUrl("css/crop.css") ?>" />

        <script type="text/javascript" src="https://code.jquery.com/jquery-3.3.1.js"></script>

        <script type="text/javascript" src="https://cdn.datatables.net/v/dt/dt-1.10.20/datatables.min.js"></script>

        <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.4.1/js/bootstrap-datepicker.min.js"></script>

    </head>
    <body>

    <div class="container">
        <h3 style="text-align: center">Seminar / Training for <?php echo $first_name. " ". $last_name ?> </h3>
        <br>
        <form method="POST" id="seminar_update_form" action="">
            <div class="col-md-12">
                <table class="table">
                    <thead>
                    <tr>
                        <th>Class Title</th>
                        <th>Completion Date</th>
                    </tr>
                    </thead>
                    <tbody>
                        <?php echo $module->getLatestSeminars($instance); ?>
                    </tbody>
                </table>
            </div>
            <button type="submit" id="save_form" class="btn btn-primary" value="true">Save Form</button>
            <br>
            <hr>
            <button type="submit" id="schedule" class="btn btn-primary" value="true">Start Verification /  Schedule Exam</button>
        </form>

    </div>

    </div>
    </body>
</html>

<script type="text/javascript">

    $(document).ready(function () {

        // turn off cached entries
        $("form :input").attr("autocomplete", "off");

        $('#st_date_citi').datepicker({
            format: 'yyyy-mm-dd',
            orientation: 'auto top left'
        });
        $('#st_date_clin_trials').datepicker({
            format: 'yyyy-mm-dd',
            orientation: 'auto top left'
        });

        //bind button to save Form
        $('#save_form').on('click', function () {

            console.log("saving form...");

            //todo: this is actually text data sa rename to textValues
            let dateValues = {};
            $('.form-control.dt').each(function () {
                dateValues[$(this).attr("id")] = $(this).val();
            });

            let formValues = {
                "save_form" : true,
                "date_values" : dateValues
            };


            $.ajax({
                data: formValues,
                method: "POST"
            })
                .done(function (data){
                    if (data.result === 'success') {
                        alert(data.msg);
                        location.reload();
                    } else {
                        alert(data.msg);
                    }

                })
                .fail(function (data) {
                    //console.log("DATA: ", data);
                    alert("error:", data);
                })
                .always(function () {
                });

            return false;

        });

        //bind button to save Form
        $('#schedule').on('click', function () {
            console.log("starting verification...");

            let formValues = {
                "schedule" : true
            };

            $.ajax({
                data: formValues,
                method: "POST"
            })
                .done(function (data){
                    if (data.result === 'success') {
                        alert(data.msg);
                        location.reload();
                    } else {
                        alert(data.msg);
                    }

                })
                .fail(function (data) {
                    //console.log("DATA: ", data);
                    alert("error:", data);
                })
                .always(function () {
                });

            return false;
        });
    });
</script>