/**
 * Customizer admin JS — complements admin-customizer.min.js.
 *
 * When the event-type <select> changes, syncs the selected option's
 * data-booking-url to the hidden #calLink input and triggers an input
 * event so the minified script's updateState() picks up the new URL.
 */
(function($) {
    'use strict';

    function initEventTypeSelector() {
        var select = document.querySelector('select[data-cal-link]');
        if (!select) return;

        // Pre-populate calLink on load.
        var calLink = document.getElementById('calLink');
        if (calLink && select.value) {
            var opt = select.querySelector('option[value="' + select.value + '"]');
            if (opt) {
                calLink.value = opt.getAttribute('data-booking-url') || '';
            }
        }

        select.addEventListener('change', function() {
            var val = this.value;
            var url = '';
            if (val) {
                var opt = this.querySelector('option[value="' + val + '"]');
                if (opt) {
                    url = opt.getAttribute('data-booking-url') || '';
                }
            }
            if (calLink) {
                calLink.value = url;
                calLink.dispatchEvent(new Event('input', { bubbles: true }));
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initEventTypeSelector);
    } else {
        initEventTypeSelector();
    }
})(jQuery);
