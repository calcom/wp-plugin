<?php

namespace CalCom\Integrations;

defined('ABSPATH') || exit;

class LearnPress
{
    /** CSS selector targeting the LearnPress single instructor info section. */
    const PROFILE_SELECTOR = '.lp-single-instructor__info__right';

    /** @var Integrations */
    private $integrations;

    /** @var UserMeta */
    private $user_meta;

    public function __construct(Integrations $integrations)
    {
        $this->integrations = $integrations;
        $this->user_meta = new UserMeta();
    }

    public function hooks()
    {
        if (!$this->is_enabled()) {
            return;
        }

        // Front-end profile rendering via JS.
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function is_enabled()
    {
        return $this->integrations->is_enabled('learnpress');
    }

    public function render_admin_settings()
    {
        ob_start();
?>
        <div class="calcom-learnpress-settings-section">
            <h2><?php esc_html_e('LearnPress', 'cal-com'); ?></h2>

            <p class="description"><?php esc_html_e('This integration uses per-user Cal.com booking URLs stored in the WordPress user profile. Configure a Cal.com URL for each instructor on their profile edit screen.', 'cal-com'); ?></p>
            <?php if (!defined('LEARNPRESS_VERSION')): ?>
                <div class="notice notice-warning inline">
                    <p><?php esc_html_e('LearnPress is not active. Booking URL configuration will appear on user profile screens once LearnPress is installed and activated.', 'cal-com'); ?></p>
                </div>
            <?php endif; ?>

            <div class="calcom-learnpress-info">
                <p><?php esc_html_e('To configure:', 'cal-com'); ?></p>
                <ol>
                    <li><?php esc_html_e('Go to Users &rarr; All Users', 'cal-com'); ?></li>
                    <li><?php esc_html_e('Edit any instructor user', 'cal-com'); ?></li>
                    <li><?php esc_html_e('Scroll to the "Cal.com Booking" section in the profile', 'cal-com'); ?></li>
                    <li><?php esc_html_e('Enter the Cal.com booking URL and optional button text', 'cal-com'); ?></li>
                    <li><?php esc_html_e('Update the user &mdash; their LearnPress instructor profile will show the booking button', 'cal-com'); ?></li>
                </ol>
            </div>
        </div>
<?php
        $template = ob_get_clean();
        return $template;
    }

    /**
     * Get the instructor user ID from the LearnPress single instructor page context.
     */
    public function get_instructor_id()
    {
        // LearnPress single instructor URL: /instructor/username/
        // The query var 'instructor_name' contains the user_nicename (slug).
        $instructor_name = get_query_var('instructor_name');
        if ($instructor_name && 'page' !== $instructor_name) {
            $user = get_user_by('slug', $instructor_name);
            if (!$user) {
                $user = get_user_by('login', $instructor_name);
            }
            if ($user) {
                return $user->ID;
            }
        }

        // Fallback to LearnPress's own user detection.
        if (function_exists('learn_press_get_page_id')) {
            $user_id = get_query_var('user_id');
            if ($user_id) {
                return (int) $user_id;
            }
        }

        // Last resort: current user (only on own profile).
        if (is_user_logged_in()) {
            $user = \wp_get_current_user();
            if ($user && $user->ID) {
                return $user->ID;
            }
        }

        return 0;
    }


    /**
     * Inject Cal.com booking button into LearnPress instructor profile via JS.
     *
     * Uses JavaScript to reliably inject the button inside the
     * ".lp-single-instructor__info__right" section, ensuring consistent
     * rendering regardless of template loading order.
     */
    public function enqueue_assets()
    {
        if (is_admin()) {
            return;
        }

        // Only proceed on LearnPress single instructor pages.
        if (!function_exists('learn_press_get_page_id')) {
            return;
        }

        if (learn_press_get_page_id('single_instructor') !== get_queried_object_id()) {
            return;
        }

        $user_id = $this->get_instructor_id();
        if (!$user_id) {
            return;
        }

        $url = $this->user_meta->get_user_booking_url($user_id);
        if (empty($url)) {
            return;
        }

        $button_text = $this->user_meta->get_user_booking_button_text($user_id);

        // Enqueue required Cal.com scripts.
        wp_enqueue_script('calcom-loader-js');
        wp_enqueue_script('calcom-embed-js');
        wp_enqueue_style('calcom-embed-css');

        // Generate Cal.com embed HTML server-side via the existing [cal] shortcode.
        $embed_html = $this->user_meta->get_embed_html($url, $button_text);

        // Build the booking button div
        $booking_html = sprintf(
            '<div class="calcom-learnpress-instructor-booking calcom-js-injected">%s</div>',
            $embed_html
        );

        // JSON-encode the HTML for safe JS insertion.
        $encoded_html = json_encode($booking_html);

        // JS that waits for the selector to appear, then injects the button.
        $selector = self::PROFILE_SELECTOR;
        $js = sprintf(
            "var calcomInjectBtn = function() { var el = document.querySelector('%s'); if (!el) { setTimeout(calcomInjectBtn, 300); return; } if (el.querySelector('.calcom-learnpress-instructor-booking')) { return; } var div = document.createElement('div'); div.innerHTML = %s; el.appendChild(div); if (window.calcomInitButtons) { window.calcomInitButtons(); } }; setTimeout(calcomInjectBtn, 100);",
            $selector,
            $encoded_html
        );

        wp_add_inline_script('calcom-embed-js', $js, 'before');
    }
}
