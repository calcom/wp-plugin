<?php

namespace CalCom\Integrations;

defined('ABSPATH') || exit;

/**
 * Central user meta field management for Cal.com booking URLs.
 *
 * Provides shared WordPress user profile field rendering and saving for
 * all LMS/membership integrations, using a single `_calcom_booking_url`
 * meta key plus an optional `_calcom_booking_button_text` meta key.
 */
class UserMeta
{

	const META_KEY        = '_calcom_booking_url';
	const BUTTON_TEXT_KEY = '_calcom_booking_button_text';

	/**
	 * Register WordPress core user profile hooks for configuration.
	 * Called once per integration (they are idempotent).
	 */
	public function hooks()
	{
		add_action('show_user_profile', array($this, 'render_user_profile_field'));
		add_action('edit_user_profile', array($this, 'render_user_profile_field'));
		add_action('personal_options_update', array($this, 'save_user_profile_field'));
		add_action('edit_user_profile_update', array($this, 'save_user_profile_field'));
	}

	/**
	 * Validate a Cal.com booking URL.
	 *
	 * @param string|null $url
	 * @return string Validated URL or empty string.
	 */
	public function validate_booking_url($url)
	{
		if (empty($url) || ! is_string($url)) {
			return '';
		}

		$url = trim($url);

		if (empty($url)) {
			return '';
		}

		$parsed = wp_parse_url($url);

		if (! isset($parsed['scheme']) || ! isset($parsed['host'])) {
			return '';
		}

		$scheme = strtolower($parsed['scheme']);
		if ($scheme !== 'https') {
			return '';
		}

		$host = strtolower($parsed['host']);

		$allowed_hosts = array('cal.com', 'cal.dev');
		$host_matches  = false;
		foreach ($allowed_hosts as $allowed) {
			if ($host === $allowed || substr($host, -strlen('.' . $allowed)) === '.' . $allowed) {
				$host_matches = true;
				break;
			}
		}

		if (! $host_matches) {
			return '';
		}

		$path = isset($parsed['path']) ? $parsed['path'] : '';
		if (empty($path) || $path === '/') {
			return '';
		}

		return esc_url_raw($url);
	}

	/**
	 * Validate and return the booking button text.
	 *
	 * @param string|null $text
	 * @return string Sanitized button text.
	 */
	public function validate_button_text($text)
	{
		if (empty($text) || ! is_string($text)) {
			return '';
		}

		$text = trim($text);
		if (empty($text)) {
			return '';
		}

		return sanitize_text_field($text);
	}

	/**
	 * Get the stored booking URL for a user.
	 *
	 * @param int $user_id
	 * @return string Validated booking URL or empty string.
	 */
	public function get_user_booking_url($user_id)
	{
		if (empty($user_id)) {
			return '';
		}

		$url = get_user_meta($user_id, self::META_KEY, true);
		return $this->validate_booking_url($url);
	}

	/**
	 * Get the stored booking button text for a user.
	 *
	 * @param int $user_id
	 * @return string Button text or default.
	 */
	public function get_user_booking_button_text($user_id)
	{
		if (empty($user_id)) {
			return '';
		}

		$text = get_user_meta($user_id, self::BUTTON_TEXT_KEY, true);
		return $this->validate_button_text($text);
	}

	/**
	 * Render the Cal.com Booking fields on the WordPress user profile screen.
	 *
	 * @param WP_User $user
	 */
	public function render_user_profile_field($user)
	{
		$booking_url = $this->get_user_booking_url($user->ID);
		$button_text = $this->get_user_booking_button_text($user->ID);
?>
		<h2><?php esc_html_e('Cal.com Booking', 'cal-com'); ?></h2>
		<table class="form-table">
			<tr>
				<th scope="row"><label for="calcom_booking_url"><?php esc_html_e('Cal.com Booking URL', 'cal-com'); ?></label></th>
				<td>
					<input type="url"
						name="calcom_booking_url"
						id="calcom_booking_url"
						value="<?php echo esc_url($booking_url); ?>"
						class="regular-text"
						placeholder="https://cal.com/example/consultation" />
					<p class="description"><?php esc_html_e('The public Cal.com booking URL for this user.', 'cal-com'); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="calcom_booking_button_text"><?php esc_html_e('Button Text', 'cal-com'); ?></label></th>
				<td>
					<input type="text"
						name="calcom_booking_button_text"
						id="calcom_booking_button_text"
						value="<?php echo esc_attr($button_text); ?>"
						class="regular-text"
						placeholder="<?php esc_attr_e('Schedule a session', 'cal-com'); ?>" />
					<p class="description"><?php esc_html_e('The text displayed on the booking button. If left empty, the Cal.com default text is used.', 'cal-com'); ?></p>
				</td>
			</tr>
		</table>
<?php
	}

	/**
	 * Save the Cal.com booking URL and button text from the WordPress user profile screen.
	 *
	 * @param int $user_id
	 */
	public function save_user_profile_field($user_id)
	{
		if (! $user_id) {
			return;
		}

		if (! current_user_can('edit_user', $user_id)) {
			return;
		}

		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
			return;
		}
		if (isset($GLOBALS['DOING_AUTOSAVE']) && $GLOBALS['DOING_AUTOSAVE']) {
			return;
		}

		// Nonce check: WordPress uses update-user_<user_id> for profile saves.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Nonce verified below.
		if (! isset($_POST['_wpnonce']) || ! wp_verify_nonce(wp_unslash($_POST['_wpnonce']), 'update-user_' . $user_id)) {
			return;
		}

		// Save booking URL.
		if (isset($_POST['calcom_booking_url'])) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Validated via custom validate_booking_url method.
			$validated = $this->validate_booking_url(wp_unslash($_POST['calcom_booking_url']));

			if (empty($validated)) {
				delete_user_meta($user_id, self::META_KEY);
			} else {
				update_user_meta($user_id, self::META_KEY, $validated);
			}
		}

		// Save button text.
		if (isset($_POST['calcom_booking_button_text'])) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Validated via custom validate_button_text method.
			$button_text = $this->validate_button_text(wp_unslash($_POST['calcom_booking_button_text']));

			if (empty($button_text)) {
				delete_user_meta($user_id, self::BUTTON_TEXT_KEY);
			} else {
				update_user_meta($user_id, self::BUTTON_TEXT_KEY, $button_text);
			}
		}
	}

	/**
	 * Generate the Cal.com embed HTML using the [cal] shortcode.
	 *
	 * @param string $url  The Cal.com booking URL.
	 * @param string $button_text Optional button text.
	 * @return string HTML embed.
	 */
	public function get_embed_html($url, $button_text = '')
	{
		$text_attr = '';
		if (! empty($button_text)) {
			$text_attr = ' text="' . esc_attr($button_text) . '"';
		}

		return do_shortcode(sprintf('[cal type="2" url="%s"%s]', esc_url($url), $text_attr));
	}
}
