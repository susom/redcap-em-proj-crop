
    $(document).ready(function () {
        crop_recert.init();
    });

    var crop_recert = crop_recert || {};

    crop_recert.init = function() {
        // turn off cached entries
        $("form :input").attr("autocomplete", "off");

        //for each date field add datepicker
        $('.form-control.dt').each(function () {
            $(this).datepicker({
                format: 'yyyy-mm-dd',
                orientation: 'auto top left'
            });
        });
    }