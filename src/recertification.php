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
        <h4>Certification Date: <?php echo $cert_start ?></h4>
        <h4>Expiry Date: <?php echo $cert_end ?></h4>
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