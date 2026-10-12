<?php

namespace CalCom\Integrations\Admin;

defined('ABSPATH') || exit;

class IntegrationsPage
{


	private $registry;

	private $integration_instances;

	public function __construct(\CalCom\Integrations\Integrations $registry, $integration_instances = array())
	{
		$this->registry              = $registry;
		$this->integration_instances = $integration_instances;
	}

	private function get_integration_instance($key)
	{
		return isset($this->integration_instances[$key])
			? $this->integration_instances[$key]
			: null;
	}

	public function hooks()
	{
		add_action('admin_menu', array($this, 'add_submenu_page'));
		add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
		add_action('wp_ajax_calcom_toggle_integration', array($this, 'handle_toggle'));
	}

	public function add_submenu_page()
	{
		add_submenu_page(
			'calcom',
			__('Integrations', 'cal-com'),
			__('Integrations', 'cal-com'),
			'manage_options',
			'calcom-integrations',
			array($this, 'render'),
			31
		);
	}

	public function enqueue_assets($hook_suffix)
	{
		if (! isset($hook_suffix) || strpos($hook_suffix, 'calcom-integrations') === false) {
			return;
		}

		wp_register_script(
			'calcom-integrations-js',
			CALCOM_ASSETS_URL . 'js/admin-integrations.min.js',
			array(),
			file_exists(CALCOM_ASSETS_PATH . 'js/admin-integrations.min.js')
				? filemtime(CALCOM_ASSETS_PATH . 'js/admin-integrations.min.js')
				: '2.2.0',
			true
		);

		wp_localize_script(
			'calcom-integrations-js',
			'calcomIntegrations',
			array(
				'ajaxUrl' => admin_url('admin-ajax.php'),
				'nonce'   => wp_create_nonce('calcom_toggle_integration'),
			)
		);

		wp_register_style(
			'calcom-integrations-css',
			CALCOM_ASSETS_URL . 'css/admin-integrations.min.css',
			array(),
			file_exists(CALCOM_ASSETS_PATH . 'css/admin-integrations.min.css')
				? filemtime(CALCOM_ASSETS_PATH . 'css/admin-integrations.min.css')
				: '2.2.0'
		);

		wp_enqueue_script('calcom-integrations-js');
		wp_enqueue_style('calcom-integrations-css');
	}

	private function is_postback()
	{
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Validated via isset check.
		$request_method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper(wp_unslash($_SERVER['REQUEST_METHOD'])) : '';
		return $request_method === 'POST';
	}

	public function render()
	{
		// Process settings form submissions before rendering.
		foreach ($this->integration_instances as $key => $instance) {
			if ($this->is_postback() && method_exists($instance, 'handle_form_post')) {
				$instance->handle_form_post();
			}
		}

		$integrations = $this->registry->get_integrations();
		$enabled      = $this->registry->get_enabled();
?>
		<div class="cal-admin wrap">
			<h1><?php esc_html_e('Integrations', 'cal-com'); ?></h1>

			<p class="description">
				<?php esc_html_e('Enable or disable integrations for your Cal.com plugin.', 'cal-com'); ?>
			</p>

			<?php if (empty($integrations)) : ?>
				<p><?php esc_html_e('No integrations available.', 'cal-com'); ?></p>
			<?php else : ?>
				<?php $first_enabled = null; ?>
				<?php foreach ($enabled as $e_key) : ?>
					<?php
					if ($first_enabled === null) :
						$first_enabled = $e_key;
					endif;
					?>
				<?php endforeach; ?>

				<?php if ($first_enabled === null && ! empty($integrations)) : ?>
					<?php $first_enabled = array_key_first($integrations); ?>
				<?php endif; ?>

				<div class="calcom-integrations-grid">
					<?php foreach ($integrations as $key => $data) : ?>
						<?php $is_enabled = in_array($key, $enabled, true); ?>
						<div class="calcom-integration-card" data-integration-key="<?php echo esc_attr($key); ?>">
							<div class="calcom-integration-card-header">
								<?php if (! empty($data['icon'])) : ?>
									<span class="calcom-integration-icon <?php echo esc_attr($data['icon']); ?>"></span>
								<?php endif; ?>
								<h2 class="calcom-integration-name"><?php echo esc_html($data['name']); ?></h2>
							</div>

							<p class="calcom-integration-description">
								<?php echo esc_html($data['description']); ?>
							</p>

							<div class="calcom-integration-toggle">
								<label class="calcom-toggle">
									<input
										type="checkbox"
										class="calcom-integration-switch"
										data-integration="<?php echo esc_attr($key); ?>"
										<?php checked($is_enabled); ?> />
									<span class="calcom-toggle-slider"></span>
								</label>
							</div>
						</div>
					<?php endforeach; ?>
				</div>

				<!-- Tabbed settings section -->
				<div class="calcom-integration-tabs-container">
					<div class="calcom-integration-tabs" role="tablist">
						<?php foreach ($integrations as $key => $data) : ?>
							<?php $is_enabled = in_array($key, $enabled, true); ?>
							<button type="button"
								class="calcom-integration-tab <?php echo ($key === $first_enabled) ? 'calcom-integration-tab--active' : ''; ?>"
								data-integration="<?php echo esc_attr($key); ?>"
								role="tab"
								aria-selected="<?php echo ($key === $first_enabled) ? 'true' : 'false'; ?>">
								<?php if (! empty($data['icon'])) : ?>
									<span class="calcom-integration-icon <?php echo esc_attr($data['icon']); ?>"></span>
								<?php endif; ?>
								<span class="calcom-integration-name"><?php echo esc_html($data['name']); ?></span>
							</button>
						<?php endforeach; ?>
					</div>

					<div class="calcom-integration-tab-panels">
						<?php foreach ($integrations as $key => $data) : ?>
							<?php
							$instance = $this->get_integration_instance($key);
							$is_first = ($key === $first_enabled);
							?>
							<div class="calcom-integration-panel <?php echo $is_first ? 'calcom-integration-panel--active' : ''; ?>"
								data-integration="<?php echo esc_attr($key); ?>">
								<?php if ($instance && method_exists($instance, 'render_admin_settings')) : ?>
									<?php
									// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- render_admin_settings() intentionally returns admin form HTML.
									echo $instance->render_admin_settings();
									?>
								<?php else : ?>
									<div class="calcom-integration-placeholder">
										<p><?php esc_html_e('Settings will display here.', 'cal-com'); ?></p>
									</div>
								<?php endif; ?>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>
		</div>
<?php
	}

	public function handle_toggle()
	{
		check_ajax_referer('calcom_toggle_integration', 'nonce');

		if (! current_user_can('manage_options')) {
			wp_send_json_error(__('You do not have permission to manage integrations.', 'cal-com'), 403);
		}

		$key   = isset($_POST['key']) ? sanitize_key(wp_unslash($_POST['key'])) : '';
		$state = isset($_POST['state']) ? (bool) filter_var(wp_unslash($_POST['state']), FILTER_VALIDATE_BOOLEAN) : false;

		if (! $this->registry->is_registered($key)) {
			wp_send_json_error(__('Invalid integration key.', 'cal-com'));
		}

		$this->registry->toggle($key, $state);

		$enabled = $this->registry->get_enabled();

		wp_send_json_success(
			array(
				'enabled'   => $enabled,
				'isEnabled' => $state,
				'key'       => $key,
			)
		);
	}
}
