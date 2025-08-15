// JavaScript code to handle the form submission
jQuery(document).ready(function($) {

    // Listen for change on the billing and shipping province select fields
    $('form.checkout').on('change', '#billing_state, #shipping_state', function() {
        // Trigger update event on checkout form
        $('form.checkout').trigger('update');
    });

    
    $('#d-age-verification-form').on('submit', function(e) {
       // e.preventDefault();
        console.log("Form submitted");
        var day = $('#day').val();
        var month = $('#month').val();
        var year = $('#year').val();

        // Verify the age
        if (!verifyAge(day, month, year)) {
            e.preventDefault();
            $('#error-message').show();
        } else {
            $('#error-message').hide();
        }

    });
});

// Verify the age
function verifyAge(day, month, year) {
    var birthDate = new Date(year, month - 1, day);
    var ageDifMs = Date.now() - birthDate.getTime();
    var ageDate = new Date(ageDifMs);
    return Math.abs(ageDate.getUTCFullYear() - 1970) > 18;
}