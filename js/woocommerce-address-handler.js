jQuery(document).ready(function($) {
    // console.log("Script loaded successfully");

    // Function to handle the state change event for both billing and shipping
    function handleStateChange(field) {
        var province = $(field).val(); // Get the state/province value from the field (billing or shipping)
        // console.log("Handling state change for: ", province);
        if (province) {
            setCookie('province', province, 7); // Set the cookie for 7 days
            // console.log("Province cookie set to: ", province);
            $(document.body).trigger('update_checkout'); // Trigger WooCommerce checkout update
        }
    }

    // Function to set a cookie
    function setCookie(name, value, days) {
        var expires = "";
        if (days) {
            var date = new Date();
            date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
            expires = "; expires=" + date.toUTCString();
        }
        // Set cookie with CDN compatibility
        document.cookie = name + "=" + (value || "") + expires + "; path=/; SameSite=Lax";
        
        // Also try to set via AJAX for CDN compatibility
        if (name === 'province') {
            setProvinceViaAJAX(value);
        }
        
        // console.log("Cookie set:", name, value);
    }
    
    // Function to set province via AJAX for CDN compatibility
    function setProvinceViaAJAX(province) {
        $.ajax({
            url: wc_checkout_params.ajax_url || '/wp-admin/admin-ajax.php',
            type: 'POST',
            data: {
                action: 'set_province_cookie',
                province: province,
                nonce: wc_checkout_params.nonce || ''
            },
            success: function(response) {
                console.log('Province set via AJAX:', response);
            },
            error: function(xhr, status, error) {
                console.log('AJAX province set failed:', error);
            }
        });
    }

    // Monitor changes to the state fields (billing and shipping) using a MutationObserver
    function observeStateField(field) {
        if (field) {
            var observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    // console.log("State field changed by plugin:", mutation);
                    handleStateChange(field);
                });
            });

            // Observe the field for changes in attributes and child nodes
            observer.observe(field, {
                attributes: true,
                childList: true,
                subtree: false
            });
        }
    }

    // Function to apply observers on state fields
    function applyStateFieldObservers() {
        var billingStateField = document.getElementById('billing_state');
        var shippingStateField = document.getElementById('shipping_state');

        if (billingStateField) {
            // Observe changes by plugin and user input
            observeStateField(billingStateField);
            $(billingStateField).on('change', function() {
                handleStateChange(billingStateField); // Trigger when user changes input
            });
        }

        if (shippingStateField) {
            // Observe changes by plugin and user input
            observeStateField(shippingStateField);
            $(shippingStateField).on('change', function() {
                handleStateChange(shippingStateField); // Trigger when user changes input
            });
        }
    }

  

    // Attach event listener for WooCommerce's updated_checkout event
    $(document.body).on('updated_checkout', function() {
        // console.log("Checkout updated");
        applyStateFieldObservers(); // Reapply observers after WooCommerce checkout update
    });

    // Detect when any WooCommerce AJAX call is completed
    $(document).ajaxComplete(function(event, xhr, settings) {
        if (settings.url.indexOf('wc-ajax=update_order_review') !== -1) {
            // console.log("WooCommerce AJAX completed");
            applyStateFieldObservers(); // Reapply observers after WooCommerce checkout is updated via AJAX
        }
    });

    // Initialize event listeners on first load
    applyStateFieldObservers();
});
