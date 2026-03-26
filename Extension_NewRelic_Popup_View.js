jQuery(function($) {
    function w3tcnr_update_apply_state() {
        var $apply = $('.w3tcnr_apply_configuration');
        if (!$apply.length) {
            return;
        }

        var hasApm = $('.w3tcnr_apm option').length > 0;
        var hasBrowser = $('.w3tcnr_browser option').length > 0;

        // No apps at all, keep disabled.
        if (!hasApm && !hasBrowser) {
            $apply.prop('disabled', true);
            return;
        }

        var monitoring = $('input[name="monitoring_type"]:checked').val();
        var selected = '';
        if (monitoring === 'apm') {
            selected = $('.w3tcnr_apm').val();
        } else if (monitoring === 'browser') {
            selected = $('.w3tcnr_browser').val();
        }

        $apply.prop('disabled', !(monitoring && selected));
    }

    // Initialize button state on load.
    w3tcnr_update_apply_state();

    $('body')
        .on('click', '.w3tcnr_configure', function() {
            W3tc_Lightbox.open({
                id:'w3tc-overlay',
                close: '',
                width: 800,
                height: 400,
                url: ajaxurl + '?action=w3tc_ajax&_wpnonce=' + w3tc_nonce +
                    '&w3tc_action=newrelic_popup',
				callback: function(lightbox) {
					lightbox.resize();
				}
            });
        })



        .on('click', '.w3tcnr_list_applications', function() {
            var url = ajaxurl + '?action=w3tc_ajax&_wpnonce=' + w3tc_nonce +
                '&w3tc_action=newrelic_list_applications&api_key=' +
                encodeURIComponent($('.w3tcnr_api_key').val());
            W3tc_Lightbox.load(url);
        })



        .on('change', 'input[name="monitoring_type"], .w3tcnr_apm, .w3tcnr_browser', function() {
            w3tcnr_update_apply_state();
        })

        .on('click', '.w3tcnr_apply_configuration', function() {
            // Prevent submit if disabled.
            if ($(this).prop('disabled')) {
                return;
            }

            var url = ajaxurl + '?action=w3tc_ajax&_wpnonce=' + w3tc_nonce +
                '&w3tc_action=newrelic_apply_configuration';
            $('.w3tcnr_form').find('input').each(function(i) {
                var name = $(this).attr('name');
                var type = $(this).attr('type');
                if (type == 'radio') {
                    if (!$(this).prop('checked'))
                        return;
                }

                if (name)
                    url += '&' + encodeURIComponent(name) + '=' +
                        encodeURIComponent($(this).val());
            });
            $('.w3tcnr_form').find('select').each(function(i) {
                var name = $(this).attr('name');
                url += '&' + encodeURIComponent(name) + '=' +
                    encodeURIComponent($(this).val());
            });

            W3tc_Lightbox.load(url);
        });
});
