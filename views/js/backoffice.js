$(function () {

    function init ()
    {
        $apiKey = $('#DIVIDO_API_KEY');
        if ($apiKey.val()) {
            showAll();
        } else {
            hideAll();
        }

        $("#DIVIDO_PLANS_OPTION").on('change', function () {
            togglePlans();
        });

        $("#DIVIDO_PROD_SELECTION").on('change', function () {
            toggleTreshold();
        });

        toggleTreshold();

        togglePlans();
    }

    function showAll ()
    {
        console.log('show');
        $('#configuration_form .form-group').show();
    }

    function hideAll ()
    {
        var fields = $('#configuration_form .form-group');
        fields.splice(0, 1);
        fields.hide();
    }

    function toggleTreshold ()
    {
        if ($('#DIVIDO_PROD_SELECTION').val() == 1) {
            $('#DIVIDO_PRICE_THRESHOLD').parent().parent().show();
        } else {
            $('#DIVIDO_PRICE_THRESHOLD').parent().parent().hide();
        }
    }

    function togglePlans () 
    {
        if ($('#DIVIDO_PLANS_OPTION').val() == 1) {
            $('#DIVIDO_PLANS\\[\\]').parent().parent().show();
        } else {
            $('#DIVIDO_PLANS\\[\\]').parent().parent().hide();
        }
    }
    init();
});
