jQuery(document).ready(function($) {
    'use strict';
    
    // Handle province form submission
    $(document).on('submit', '#d-age-verification-form', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $submitBtn = $form.find('input[type="submit"]');
        var province = $form.find('select[name="province"]').val();
        var month = $form.find('select[name="month"]').val();
        var day = $form.find('select[name="day"]').val();
        var year = $form.find('select[name="year"]').val();
        
        if (!province) {
            alert('Please select a province');
            return false;
        }
        
        if (!month || !day || !year) {
            alert('Please select your complete birthdate');
            return false;
        }
        
        // Calculate age
        var birthDate = new Date(year, month - 1, day);
        var today = new Date();
        var age = today.getFullYear() - birthDate.getFullYear();
        var monthDiff = today.getMonth() - birthDate.getMonth();
        
        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
            age--;
        }
        
        // Check if user is 19 or older
        if (age < 19) {
            $('#error-message').show();
            return false;
        }
        
        // Hide error message if it was shown before
        $('#error-message').hide();
        
        // Disable submit button to prevent double submission
        $submitBtn.prop('disabled', true).val('Processing...');
        
        // Set cookie for the selected province
        document.cookie = 'province_cache=' + province + '; path=/; max-age=' + (86400 * 30) + '; SameSite=Lax';
        
        // Get current URL and add province parameter
        var currentUrl = window.location.href.split('?')[0]; // Remove existing query string
        var redirectUrl = currentUrl + '?province=' + province;
        
        // Small delay to ensure cookie is set
        setTimeout(function() {
            // Redirect to the new URL
            window.location.href = redirectUrl;
        }, 100);
    });
    
    // Handle province change without form submission (if needed)
    $(document).on('change', 'select[name="province"]', function() {
        var province = $(this).val();
        if (province) {
            // Update cookie immediately when province changes
            document.cookie = 'province_cache=' + province + '; path=/; max-age=' + (86400 * 30) + '; SameSite=Lax';
            
            // Update URL without page reload
            var currentUrl = window.location.href.split('?')[0];
            var newUrl = currentUrl + '?province=' + province;
            
            // Use history.pushState to update URL without reload
            if (window.history && window.history.pushState) {
                window.history.pushState({province: province}, '', newUrl);
                
                // Trigger a custom event for other scripts to listen to
                $(document).trigger('provinceChanged', [province]);
            }
        }
    });
    
    // Handle province removal (if needed)
    function clearProvinceCookie() {
        document.cookie = 'province_cache=; path=/; expires=Thu, 01 Jan 1970 00:00:00 GMT';
    }
    
    // Expose clear function globally if needed
    window.clearProvinceCookie = clearProvinceCookie;
    
    // Debug: Log cookie value on page load
    console.log('Province cookie value:', getCookie('province_cache'));
    
    // Helper function to get cookie value
    function getCookie(name) {
        var value = "; " + document.cookie;
        var parts = value.split("; " + name + "=");
        if (parts.length == 2) return parts.pop().split(";").shift();
        return null;
    }
});
