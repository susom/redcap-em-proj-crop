<?php
namespace Stanford\ProjCROP;
/** @var \Stanford\ProjCROP\ProjCROP $module */


use DateTime;
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
<style>
.header-global nav a.som-logo{
    background-image:url(<?=$module->getUrl("img/web_logo.png")?>);
}
</style>
</head>
<body>
<header class="header-global">
    <nav class="container">
        <a class="som-logo" href="http://med.stanford.edu">Stanford Medicine</a> <b>Completed <span id='completed_certs'></span> of <span id='total_certs'></span></b>
    </nav>
</header>
<div class="container">
    <h3 style="text-align: center">Recertification Form for <?php echo $first_name. " ". $last_name ?> </h3>
    <br>

    <div id="recert_forms">
        <h4 class="p-3 rounded-top ">Section A: General Information</h4>
        <div class="form-row p-3">
            <div class="form-group col-md-5">
                <h6>Certification Date: <?php echo $cert_start ?></h6>
            </div>
            <div class="form-group col-md-5">
                <h6>Expiry Date: <?php echo $cert_end ?></h6>
            </div>

            <?php echo $module->getRecertificationGenInfo($instance); ?>
        </div>
        

        <h4 class="p-3">Section B: Seminars / Trainings</h4>
        <div class="form-row p-3">
            <h5 class="col-md-4">Please enter the following information in the following table</h5>
            <ol class="col-md-5 offset-md-1">
                <li>Completion date of most recent CITI and Privacy trainings</li>
                <li>3 Core Classes from Stanford Clinical Research Operations Program</li>
                <li>7 Additional classes</li>
            </ol>
        </div>
        <br>
        <form method="POST" id="recertification_form" action="">
            <div class="col-md-12">
                <table class="table">
                    <thead>
                    <tr>
                        <th>Date</th>
                        <th>Program Sponsor</th>
                        <th>Class Title</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php echo $module->getRecertification($instance); ?>
                    </tbody>
                </table>
            </div>

            <div class="btns">
                <button type="submit" id="save_form" class="btn btn-primary float-left" value="true">Save Form</button>
                <?php if (new DateTime() < new DateTime($cert_end_6_month)) { ?>
                    <span class="float-right">Submissions will be accepted after <?php echo $cert_end_6_month ?>.</span>
                <?php } else {?>
                    <button type="submit" id="recertify" class="btn btn-info float-right" value="true">Ready to Validate Recertification</button>
                <?php }?>
            </div>
        </form>
    </div>
</div>
</body>
</html>