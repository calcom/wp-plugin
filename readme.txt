=== Cal.com ===
Contributors: calcom, turn2honey
Tags: appointment, appointment booking, appointment scheduling, booking calendar, calcom
Requires at least: 4.6
Tested up to: 6.9
Stable tag: 2.1.0
Requires PHP: 7.4
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.en.html

Embed Cal.com booking calendar in WordPress with custom UI and admin widget support.

== Description ==

Cal.com is an open-source alternative to Calendly that allows easy appointment booking and meeting scheduling.

This plugin enables you to:

- Embed your Cal.com booking calendar inline, as a popup, or as a floating widget.
- Customize UI with theme colors, layout, and event details visibility.
- Pre-fill user information and add UTM tracking parameters.
- Use the new admin widget customizer for real-time previews.

[Watch Demo](https://simpma.com/plugins/cal-com/)

== Installation ==

1. Install via the WordPress dashboard or upload the ZIP.
2. Activate the plugin.
3. Use the `[cal]` or `[cal_custom]` shortcode in any page, post, or widget.

== Shortcodes ==

**[cal url="/username/meetingid" type=1]**  

Embed inline calendar.

**[cal url="/username/meetingid" type=2 text="Schedule a call"]**  

Embed popup trigger button.

**[cal_custom url="/demo/30min" type=1 prefill="true" utm="source:localhost" ui='{"theme":"dark","cssVarsPerTheme":{"dark":{"cal-brand":"#a3ffcb"}},"hideEventTypeDetails":true,"layout":"week_view"}' config='{"layout":"week_view","useSlotsViewOnSmallScreen":true,"disableMobileScroll":true}']**  

Embed customizable widget with full UI control, prefill, and UTM support.

== Shortcode Attributes ==

- **url:** URL of the booking calendar.
- **type:** Embed type (1 = inline, 2 = popup, 3 = floating button for `[cal_custom]`).
- **text:** Button text for popup embeds.
- **prefill:** Set to `true` to prefill user info if available.
- **utm:** Comma-separated UTM tracking parameters (e.g., `source:newsletter, medium:email`).
- **ui:** JSON object for theme, layout, and visibility customization.
- **config:** JSON object for advanced widget configuration (slots view, scrolling, etc.).

== CSS Customization ==

Customize popup/button text via CSS targeting **#calcom-embed-link**:

`
#calcom-embed-link, .calcom-embed-link {
	background-color: #222222;
	padding: 15px;
	color: #fff;
	font-size: 16px;
	text-align: center;
	cursor: pointer;
}

`

== Use of  3rd Party Software ==

This plugin relies on [Cal.com embed](https://cal.com). See their [Privacy Policy](https://cal.com/privacy) and [Terms of use](https://cal.com/terms).


== Changelog ==

= 2.1.0 - 26-03-2026 =

- Script enqueue handle mismatch fix


= 2.0.0 - 21-03-2026 =

- Added widget customizer to admin page
- Introduced new shortcode [cal_custom]
- Support prefill with logged-in user info
- Support adding UTM parameters to shortcode
- Security improvements
- Ensured compatibility with lastest WordPress version

= 1.0.0 - 15-11-2022 =

- Initial release
- Supports inline & popup embed types