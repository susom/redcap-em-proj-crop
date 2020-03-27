
    $(document).ready(function () {
        crop.init();
    });

    var crop = crop || {};

    crop.init = function() {

        // turn off cached entries
        $("form :input").attr("autocomplete", "off");

        //for each date field add datepicker
        $('.form-control.dt').each(function () {
            $(this).datepicker({
                format: 'yyyy-mm-dd',
                orientation: 'auto top left'
            });
        });

        // $('#st_date_citi').datepicker({
        //     format: 'yyyy-mm-dd',
        //     orientation: 'auto top left'
        // });

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

        let formValues = {
            "schedule": true
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
