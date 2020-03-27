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
            <div class="form-group col-md-4">
                <select id="st_requested_exam" class="form-control select" autocomplete="off">
                    <option value="" selected="" disabled="">Requested Exam (Please select)</option>
                    <option value="1"> Spring </option>
                    <option value=" 2"> Fall</option>
                </select>
            </div>
            <button type="submit" id="schedule" class="btn btn-primary" value="true">Start Verification /  Schedule Exam</button>
        </form>

    </div>

    </div>
    </body>
</html>