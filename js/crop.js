
    $(document).ready(function () {
        crop.init();
    });

    var crop = crop || {};

    crop.init = function() {

        // turn off cached entries
        $("form :input").attr("autocomplete", "off");

        var allEntered = true;

        //for each date field add datepicker
        $('.form-control.dt').each(function () {
            //check that there aren't any empty values
            if ($(this).val() == '') {
                allEntered = false;
            }

            //reposition the datepicker
            $(this).datepicker({
                format: 'yyyy-mm-dd',
                orientation: 'auto top left'
            });
        });

        //if all the dates are entered, display the Verify button
        if (allEntered === true) {
            $('#complete').show();
        }

        //bind button to save Form
        $('#save_form').on('click', crop.saveForm);

        //bind button to save Form
        $('#schedule').on('click', crop.schedule);

        //bind button to save Form
        $('#recertify').on('click', crop.recertify);
    };

    crop.saveForm = function() {

        console.log("saving form...");

        let dateValues = {};
        $('.form-control.dt').each(function () {
            dateValues[$(this).attr("id")] = $(this).val();
        });

        let textValues = {};
        $('.form-control.elective').each(function () {
            textValues[$(this).attr("id")] = $(this).val();
        });

        let codedValues = {};
        $('.form-control.select').each(function () {
            codedValues[$(this).attr("id")] = $(this).val();
        });

        let formValues = {
            "save_form": true,
            "date_values": dateValues,
            "text_values": textValues,
            "coded_values": codedValues
        };


        $.ajax({
            data: formValues,
            method: "POST"
        })
            .done(function (data) {
                console.log("SUCCESS DATA: ", data);
                if (data.result === 'success') {
                    alert(data.msg);
                    location.reload();
                } else {
                    alert(data.msg);
                }

            })
            .fail(function (data) {
                console.log("FAIL DATA: ", data);
                alert("error:", data);
            })
            .always(function () {
            });

        return false;
    };

    crop.schedule = function() {
        console.log("starting verification...");

        let codedValues = {
            "st_requested_exam"     : $("#st_requested_exam").val()
        };

        var now = new Date();
        var d2 = now.toString("yyyy-MM-dd");

        var d = new Date();
        date = [
            d.getFullYear(),
            ('0' + (d.getMonth() + 1)).slice(-2),
            ('0' + d.getDate()).slice(-2)
        ].join('-');

        let dateValues = {
            "date_training_completion" : date
        };


        let formValues = {
            "schedule" : true,
            "coded_values" : codedValues,
            "date_values"  : dateValues
        };

        $.ajax({
            data: formValues,
            method: "POST"
        })
            .done(function (data) {
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
    };

      crop.recertify = function() {
        console.log("starting recertification...");

        let formValues = {
            "recertify": true
        };

        $.ajax({
            data: formValues,
            method: "POST"
        })
            .done(function (data) {
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
    };
