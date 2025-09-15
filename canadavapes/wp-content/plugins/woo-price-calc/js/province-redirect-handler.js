jQuery(document).ready(function($) {
    'use strict';

    // Check if we need to redirect based on province cookie
    function checkProvinceRedirect() {
        var provinceCookie = getCookie('province_cache');
        var currentUrl = window.location.href;
        var hasProvinceParam = currentUrl.indexOf('?province=') !== -1;
        
        console.log('Province Redirect Check:', {
            cookie: provinceCookie,
            hasParam: hasProvinceParam,
            currentUrl: currentUrl
        });

        // If no province in URL but cookie exists, redirect
        if (!hasProvinceParam && provinceCookie && provinceCookie !== 'no') {
            console.log('Redirecting to province:', provinceCookie);
            
            // Add flash message to show redirect is happening
            showFlashMessage('Redirecting to your province...', 'info');
            
            // Small delay to show flash message
            setTimeout(function() {
                var newUrl = currentUrl + (currentUrl.indexOf('?') !== -1 ? '&' : '?') + 'province=' + provinceCookie;
                window.location.href = newUrl;
            }, 1000);
            
            return;
        }

        // If no province in URL and no cookie, redirect to ?province=no
        if (!hasProvinceParam && (!provinceCookie || provinceCookie === 'no')) {
            console.log('Redirecting to no-province state');
            
            // Add flash message
            showFlashMessage('Setting up province selection...', 'info');
            
            setTimeout(function() {
                var newUrl = currentUrl + (currentUrl.indexOf('?') !== -1 ? '&' : '?') + 'province=no';
                window.location.href = newUrl;
            }, 1000);
            
            return;
        }

        // If province=no in URL but cookie exists, redirect to cookie province
        if (hasProvinceParam && currentUrl.indexOf('?province=no') !== -1 && provinceCookie && provinceCookie !== 'no') {
            console.log('Redirecting from no-province to cookie province:', provinceCookie);
            
            showFlashMessage('Redirecting to your province...', 'info');
            
            setTimeout(function() {
                var newUrl = currentUrl.replace('?province=no', '?province=' + provinceCookie);
                window.location.href = newUrl;
            }, 1000);
            
            return;
        }
    }

    // Show flash message
    function showFlashMessage(message, type) {
        // Remove existing flash messages
        $('.province-flash-message').remove();
        
        var flashClass = 'province-flash-message';
        if (type === 'info') flashClass += ' flash-info';
        if (type === 'success') flashClass += ' flash-success';
        if (type === 'error') flashClass += ' flash-error';
        
        var flashHtml = '<div class="' + flashClass + '">' + message + '</div>';
        
        // Add to body
        $('body').prepend(flashHtml);
        
        // Auto-remove after 3 seconds
        setTimeout(function() {
            $('.province-flash-message').fadeOut(500, function() {
                $(this).remove();
            });
        }, 3000);
    }

    // Helper function to get cookie value
    function getCookie(name) {
        var value = "; " + document.cookie;
        var parts = value.split("; " + name + "=");
        if (parts.length == 2) return parts.pop().split(";").shift();
        return null;
    }

    // Run redirect check on page load
    checkProvinceRedirect();

    // Also check when page becomes visible (in case of back/forward)
    $(window).on('focus', function() {
        setTimeout(checkProvinceRedirect, 100);
    });

    // Expose functions globally for debugging
    window.provinceRedirectHandler = {
        checkRedirect: checkProvinceRedirect,
        showFlash: showFlashMessage,
        getCookie: getCookie
    };
});
