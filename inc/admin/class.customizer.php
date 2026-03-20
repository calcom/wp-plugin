<?php

namespace CalCom;

defined('ABSPATH') || exit;

class Customizer
{
    public function hooks()
    {
        add_action('in_admin_header', [$this, 'clear_unwanted_notices']);
        add_action('admin_menu', [$this, 'menu']);
    }

    public function clear_unwanted_notices()
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only reading admin page slug.
        if (!isset($_GET['page'])) return;

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only reading admin page slug.
        $page = sanitize_text_field(wp_unslash($_GET['page']));

        if ($page === 'calcom-customizer') {
            remove_all_actions('admin_notices');
            remove_all_actions('all_admin_notices');
        }
    }

    public function menu()
    {
        add_menu_page(
            'Cal Widget Customizer',
            'Cal Customizer',
            'manage_options',
            'calcom-customizer',
            [$this, 'render'],
            'dashicons-calendar',
            30
        );
    }

    public function render()
    {
        wp_enqueue_script('calcom-customizer-js');
        wp_enqueue_style('calcom-customizer-css');
?>
        <div class="cal-admin wrap">
            <h1>Customize Your Cal Widget</h1>

            <div id="calcom-customizer">
                <div id="cal-customizer">

                    <div class="customizer-controls">

                        <div class="shortcode-box">
                            <span class="shortcode-label">Generated Shortcode</span>
                            <textarea id="output" readonly></textarea>
                        </div>
                        <button id="generate" class="button button-primary">Generate Shortcode</button>

                        <div class="group">
                            <div class="section-title">General</div>

                            <label>Cal Link</label>
                            <input type="text" id="calLink" value="/demo/30min">

                            <label>Embed Type</label>
                            <select id="type">
                                <option value="1">Inline</option>
                                <option value="2">Modal</option>
                                <option value="3">Floating Button</option>
                            </select>

                            <label class="checkbox">
                                <input type="checkbox" id="prefill">
                                Prefill logged-in user
                            </label>

                            <label>UTM Parameters (comma-separated key:value)</label>
                            <input type="text" id="utm" placeholder="source:localhost,medium:web">
                        </div>

                        <div class="group">
                            <div class="section-title">Appearance</div>

                            <label>Theme</label>
                            <select id="theme">
                                <option value="light">Light</option>
                                <option value="dark">Dark</option>
                            </select>

                            <label>Brand Color</label>
                            <input type="color" id="brandColor" value="#000000">

                            <label>Layout</label>
                            <select id="layout">
                                <option value="month_view">Month</option>
                                <option value="week_view">Week</option>
                            </select>

                            <label class="checkbox">
                                <input type="checkbox" id="hideDetails">
                                Hide event details
                            </label>
                        </div>

                        <div class="group">
                            <div class="section-title">Behavior</div>

                            <label class="checkbox">
                                <input type="checkbox" id="slotsMobile">
                                Slots view on mobile
                            </label>

                            <label class="checkbox">
                                <input type="checkbox" id="disableScroll">
                                Disable mobile scroll
                            </label>
                        </div>
                    </div>

                    <div class="customizer-preview">
                        <div id="preview">
                            Start customizing
                        </div>
                    </div>
                </div>
            </div>
        </div>
<?php
    }
}
