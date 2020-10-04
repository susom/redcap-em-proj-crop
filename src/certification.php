<?php
namespace Stanford\ProjCROP;
/** @var \Stanford\ProjCROP\ProjCROP $module */

$first_name = "Irvin";
$last_name = "Szeto";

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
<script type='text/javascript' src='<?php echo $module->getUrl("js/crop.js")?>'></script>
</head>
<body>
<header class="header-global">
    <nav class="container">
        <a class="som-logo" href="http://med.stanford.edu">Stanford Medicine</a> <b>Completed <span id='completed_certs'></span> of <span id='total_certs'></span></b>
    </nav>
</header>
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
        <div class='btns'>
            <button type="submit" id="save_form" class="btn btn-primary btn-block" value="true">Save Form</button>
        </div>
        
        <div style="display:none;" id="complete">
            <br><br>
            <hr>
            <div class="form-group col-md-4">
                <select id="st_requested_exam" class="form-control select" autocomplete="off">
                    <option value="" selected="" disabled="">Requested Exam (Please select)</option>
                    <?php echo $module->getExamDates($instance); ?>
                </select>
            </div>
            <div class='btns'>
                <button type="submit" id="schedule" class="btn btn-primary btn-block" value="true">Start Verification /  Schedule Exam</button>
            </div>
            <br><br>
        </div>
    </form>
</div>
<script>
$(document).ready(function(){
    // Add Completed Count?    
    var count_dates = $("#seminar_update_form").find(".input-group").length;
    var count_files = $("#seminar_update_form").find(".file_uploaded_status").length;
    var date_hasval = 0;
    $("#seminar_update_form").find(".input-group :input").each(function(){
        if($(this).val()){
            date_hasval++;
        }
    });
    if($("#seminar_update_form").find(".file_uploaded_status").text() != "File not yet uploaded."){
        date_hasval++;
    }

    $("#total_certs").text(count_dates+count_files);
    $("#completed_certs").text(date_hasval);
    $(".header-global b").fadeIn("medium");
})
</script>
</body>
</html>