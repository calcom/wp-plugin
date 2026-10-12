<?php

namespace CalCom\Integrations;

defined('ABSPATH') || exit;

class UltimateMember
{
    /** CSS selector targeting the Ultimate Member profile main meta section. */
    const PROFILE_SELECTOR = '.um-main-meta';

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
        return $this->integrations->is_enabled('ultimate-member');
    }

    public function render_admin_settings()
    {
        ob_start();
?>
        <div class="calcom-ultimate-member-settings-section">
            <h2><?php esc_html_e('Ultimate Member', 'cal-com'); ?></h2>

            <p class="description"><?php esc_html_e('This integration uses per-user Cal.com booking URLs stored in the WordPress user profile. Configure a Cal.com URL for each member on their profile edit screen.', 'cal-com'); ?></p>
            <?php if (!class_exists('UM')): ?>
                <div class="notice notice-warning inline">
                    <p><?php esc_html_e('Ultimate Member is not active. Booking URL configuration will appear on user profile screens once Ultimate Member is installed and activated.', 'cal-com'); ?></p>
                </div>
            <?php endif; ?>

            <div class="calcom-ultimate-member-info">
                <p><?php esc_html_e('To configure:', 'cal-com'); ?></p>
                <ol>
                    <li><?php esc_html_e('Go to Users &rarr; All Users', 'cal-com'); ?></li>
                    <li><?php esc_html_e('Edit any member user', 'cal-com'); ?></li>
                    <li><?php esc_html_e('Scroll to the "Cal.com Booking" section in the profile', 'cal-com'); ?></li>
                    <li><?php esc_html_e('Enter the Cal.com booking URL and optional button text', 'cal-com'); ?></li>
                    <li><?php esc_html_e('Update the user &mdash; their Ultimate Member profile will show the booking button', 'cal-com'); ?></li>
                </ol>
            </div>
        </div>
<?php
        $template = ob_get_clean();
        return $template;
    }

    /**
     * Get the profile user ID from the Ultimate Member profile page context.
     */
    public function get_profile_user_id()
    {
        // Use Ultimate Member's um_profile_id() to get the displayed profile user ID,
        if (function_exists('um_profile_id')) {
            $user_id = um_profile_id();
            if ($user_id) {
                return (int) $user_id;
            }
        }

        // Fallback: check for UM user query var.
        $um_user = get_query_var('um_user');
        if ($um_user) {
            $user = get_user_by('slug', $um_user);
            if (!$user) {
                $user = get_user_by('login', $um_user);
            }
            if ($user) {
                return $user->ID;
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
     * Inject Cal.com booking button into Ultimate Member profile via JS.
     *
     * Uses JavaScript to reliably inject the button inside the ".um-main-meta"
     * section (below the user's name and navigation tabs).
     */
    public function enqueue_assets()
    {
        if (is_admin()) {
            return;
        }

        // Only proceed on Ultimate Member profile pages.
        if (!class_exists('UM')) {
            return;
        }

        if (!function_exists('um_profile_id')) {
            return;
        }

        if (!get_query_var('um_user')) {
            return;
        }

        $user_id = $this->get_profile_user_id();
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
            '<div class="calcom-ultimate-member-booking calcom-js-injected">%s</div>',
            $embed_html
        );

        // JSON-encode the HTML for safe JS insertion.
        $encoded_html = json_encode($booking_html);

        // JS that waits for the selector to appear, then injects the button.
        $selector = self::PROFILE_SELECTOR;
        $js = sprintf(
            "var calcomInjectBtn = function() { var el = document.querySelector('%s'); if (!el) { setTimeout(calcomInjectBtn, 300); return; } if (el.querySelector('.calcom-ultimate-member-booking')) { return; } var div = document.createElement('div'); div.innerHTML = %s; el.appendChild(div); if (window.calcomInitButtons) { window.calcomInitButtons(); } }; setTimeout(calcomInjectBtn, 100);",
            $selector,
            $encoded_html
        );

        wp_add_inline_script('calcom-embed-js', $js, 'before');
    }
}
