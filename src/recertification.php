<?php
namespace Stanford\ProjCROP;
/** @var \Stanford\ProjCROP\ProjCROP $module */

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

    <div class="container">
        <h3 style="text-align: center">Recertification Form for <?php echo $first_name. " ". $last_name ?> </h3>
        <hr>
        <h4>Section A: General Information</h4>
        <div class="form-row">
            <div class="form-group col-md-6">
                <h6>Certification Date: <?php echo $cert_start ?></h6>
            </div>
            <div class="form-group col-md-6">
                <h6>Expiry Date: <?php echo $cert_end ?></h6>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group col-md-6"><h6>Have you changed positions since certification?</h6>
            </div>
            <div class="form-group col-md-6">
                <label><input name="rf_position_change" id="rf_position_change" type="radio" value="1"> Yes</label><br>
                <label><input name="rf_position_change" id="rf_position_change" type="radio" value="0"> No</label>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group col-md-6"><p>If yes, what was your title/department at initial certification date?</p>
            </div>
            <div class="form-group col-md-4">
                <input name="rf_title_at_cert" id='rf_title_at_cert' type='text' class="form-control" autocomplete="off"/>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group col-md-2">
                <label for="last_name">Signature</label>
            </div>
            <div class="form-group col-md-6">
                <input type="text" class="form-control" id="rf_signature" placeholder="">
            </div>
        </div>
        <hr>
        <h4>Section B: Seminars / Trainings</h4>
        <h5>Please enter the following information in the following table</h5>
        <ol>
            <li>Completion date of most recent CITI and Privacy trainings</li>
            <li>3 Core Classes from Stanford Clinical Research Operations Program</li>
            <li>7 Additional classes</li>
        </ol>

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
            <button type="submit" id="save_form" class="btn btn-primary" value="true">Save Form</button>
            <br>
            <hr>
            <button type="submit" id="recertify" class="btn btn-primary" value="true">Ready to Validate Recertification</button>
        </form>
    </div>
    </body>
</html>