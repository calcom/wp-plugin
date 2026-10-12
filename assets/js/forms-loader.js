/**
 * Cal.com Forms Integration
 *
 * Displays a Cal.com booking prompt after successful submissions.
 *
 * Supported:
 * - Contact Form 7
 * - WPForms
 * - Fluent Forms
 */
(function () {
    'use strict';

    // Cached Cal.com API instance.
    var calApi = null;

    // Promise used while the Cal.com embed script is loading.
    var calLoading = null;

    /**
     * Load and initialize the Cal.com embed API.
     *
     * Reuses an existing API instance if available.
     * Otherwise, loads https://cal.com/embed.js dynamically.
     */
    function getCalApi() {
        // Cal.com has already been initialized.
        if (calApi) {
            return Promise.resolve(calApi);
        }

        // Cal.com is already available globally.
        if (typeof window.Cal === 'function') {
            calApi = window.Cal('init', 'cal_core');
            return Promise.resolve(calApi);
        }

        // The script is already being loaded.
        // Return the existing promise rather than loading it again.
        if (calLoading) {
            return calLoading;
        }

        calLoading = new Promise(function (resolve, reject) {
            var script = document.querySelector(
                'script[src*="cal.com/embed.js"]'
            );

            if (script) {
                // A Cal.com script already exists on the page.
                script.addEventListener('load', load);
                script.addEventListener('error', reject);
            } else {
                // Load the Cal.com embed script dynamically.
                script = document.createElement('script');
                script.src = 'https://cal.com/embed.js';
                script.async = true;

                script.onload = load;
                script.onerror = reject;

                document.head.appendChild(script);
            }

            /**
             * Called once the Cal.com script has loaded.
             */
            function load() {
                if (typeof window.Cal !== 'function') {
                    reject(new Error('Cal.com API unavailable.'));
                    return;
                }

                calApi = window.Cal('init', 'cal_core');
                resolve(calApi);
            }
        });

        return calLoading;
    }

    /**
     * Turn a button/link into a Cal.com booking modal trigger.
     *
     * The element must have a data-url attribute containing
     * the Cal.com booking link.
     */
    function initButton(button) {
        // Ignore missing buttons or buttons already initialized.
        if (!button || button.dataset.calInitialized) {
            return;
        }

        var calUrl = button.dataset.url;

        // No Cal.com URL means there is nothing to initialize.
        if (!calUrl) {
            return;
        }

        // Prevent duplicate initialization.
        button.dataset.calInitialized = 'true';

        getCalApi()
            .then(function (api) {
                // Open the Cal.com booking page in a modal
                // instead of navigating away from the current page.
                button.addEventListener('click', function (event) {
                    event.preventDefault();

                    api('modal', {
                        calLink: calUrl
                    });
                });
            })
            .catch(function (error) {
                // Allow the button to be initialized again if loading failed.
                button.dataset.calInitialized = '';

                console.error('Cal.com:', error);
            });
    }

    /**
     * Display a booking prompt and initialize its Cal.com button.
     */
    function showPrompt(prompt) {
        if (!prompt) {
            return;
        }

        // The prompt is initially hidden and is shown after
        // a form submission succeeds.
        prompt.style.display = 'block';

        // Find the Cal.com button inside the prompt.
        var button = prompt.querySelector(
            '#calcom-embed-link, .calcom-embed-link'
        );

        initButton(button);
    }

    /**
     * Contact Form 7 integration.
     *
     * Runs after Contact Form 7 reports a successful submission.
     */
    document.addEventListener('wpcf7mailsent', function (event) {
        var form = event.target;
    
        if (!form) {
            return;
        }
    
        setTimeout(function () {
            var prompt = form.querySelector('.calcom-cf7-prompt');
            var response = form.querySelector('.wpcf7-response-output');
    
            if (!prompt || !response) {
                return;
            }
    
            // Move the Cal.com prompt into the CF7 success message area.
            response.appendChild(prompt);
    
            // Show the prompt.
            prompt.style.display = 'block';
    
            // If the main Cal.com embed has already initialized this
            // button, do not initialize it again from CF7.
            if (window.calcomData) {
                return;
            }
    
            // Otherwise initialize the CF7 button normally.
            initButton(prompt);
        }, 100);
    });

    /**
     * WPForms integration.
     *
     * WPForms exposes its AJAX success event through jQuery.
     */
    if (window.jQuery) {
        jQuery(document).on(
            'wpformsAjaxSubmitSuccess',
            function () {
                setTimeout(function () {
                    document
                        .querySelectorAll('.calcom-wpforms-prompt')
                        .forEach(showPrompt);
                }, 100);
            }
        );
    }

    /**
     * Fluent Forms integration.
     *
     * Runs after a successful Fluent Forms submission.
     */
    document.addEventListener(
        'fluentform_submission_success',
        function () {
            setTimeout(function () {
                document
                    .querySelectorAll('.calcom-fluentforms-prompt')
                    .forEach(showPrompt);
            }, 100);
        }
    );

})();