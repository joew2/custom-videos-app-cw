<?php
/**
 * Plugin Name: Custom Video App CW
 * Description: Displays public Vimeo videos in a responsive shortcode grid with popup playback.
 * Version: 1.0.0
 * Author: CW
 * License: GPL-2.0-or-later
 * Text Domain: custom-videos-app-cw
 */

if (!defined('ABSPATH')) {
	exit;
}

final class Custom_Videos_App_CW {
	private const OPTION_NAME = 'custom_videos_app_cw_settings';
	private const TRANSIENT_PREFIX = 'cvacw_videos_';
	private const DEFAULT_PER_PAGE = 500;

	private static $instance = null;

	public static function instance(): Custom_Videos_App_CW {
		if (null === self::$instance) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		add_action('admin_menu', array($this, 'add_settings_page'));
		add_action('admin_init', array($this, 'register_settings'));
		add_action('wp_enqueue_scripts', array($this, 'register_assets'));
		add_shortcode('custom_video_app_cw', array($this, 'render_shortcode'));
		add_shortcode('custom_videos_app_cw', array($this, 'render_shortcode'));
	}

	public function add_settings_page(): void {
		add_options_page(
			__('Custom Video App CW', 'custom-videos-app-cw'),
			__('Custom Video App CW', 'custom-videos-app-cw'),
			'manage_options',
			'custom-videos-app-cw',
			array($this, 'render_settings_page')
		);
	}

	public function register_settings(): void {
		register_setting(
			'custom_videos_app_cw',
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array($this, 'sanitize_settings'),
				'default'           => array(),
			)
		);

		add_settings_section(
			'custom_videos_app_cw_vimeo',
			__('Vimeo API Connection', 'custom-videos-app-cw'),
			function (): void {
				echo '<p>' . esc_html__('Add a Vimeo API access token that can read videos from your Vimeo account.', 'custom-videos-app-cw') . '</p>';
			},
			'custom-videos-app-cw'
		);

		add_settings_field(
			'access_token',
			__('Vimeo access token', 'custom-videos-app-cw'),
			array($this, 'render_access_token_field'),
			'custom-videos-app-cw',
			'custom_videos_app_cw_vimeo'
		);

		add_settings_field(
			'cache_minutes',
			__('Cache duration', 'custom-videos-app-cw'),
			array($this, 'render_cache_minutes_field'),
			'custom-videos-app-cw',
			'custom_videos_app_cw_vimeo'
		);

		add_settings_field(
			'hide_non_public',
			__('Video visibility', 'custom-videos-app-cw'),
			array($this, 'render_hide_non_public_field'),
			'custom-videos-app-cw',
			'custom_videos_app_cw_vimeo'
		);
	}

	public function sanitize_settings(array $input): array {
		$old_settings    = $this->get_settings();
		$access_token    = isset($input['access_token']) ? sanitize_text_field(wp_unslash($input['access_token'])) : '';
		$cache_minutes   = isset($input['cache_minutes']) ? absint($input['cache_minutes']) : 60;
		$hide_non_public = !empty($input['hide_non_public']) ? 1 : 0;

		if ($access_token === '********' && !empty($old_settings['access_token'])) {
			$access_token = $old_settings['access_token'];
		}

		if ($cache_minutes < 5) {
			$cache_minutes = 5;
		}

		if ($cache_minutes > 1440) {
			$cache_minutes = 1440;
		}

		$this->clear_video_cache();

		return array(
			'access_token'    => $access_token,
			'cache_minutes'   => $cache_minutes,
			'hide_non_public' => $hide_non_public,
		);
	}

	public function render_access_token_field(): void {
		$settings = $this->get_settings();
		$value    = !empty($settings['access_token']) ? '********' : '';

		printf(
			'<input type="password" class="regular-text" name="%1$s[access_token]" value="%2$s" autocomplete="off" placeholder="%3$s" />',
			esc_attr(self::OPTION_NAME),
			esc_attr($value),
			esc_attr__('Paste your Vimeo access token', 'custom-videos-app-cw')
		);
		echo '<p class="description">' . esc_html__('Create a Vimeo app token with the Public and Private scopes if the account contains videos that need authenticated listing. The token is stored in WordPress options.', 'custom-videos-app-cw') . '</p>';
	}

	public function render_cache_minutes_field(): void {
		$settings = $this->get_settings();
		$value    = isset($settings['cache_minutes']) ? absint($settings['cache_minutes']) : 60;

		printf(
			'<input type="number" min="5" max="1440" step="5" name="%1$s[cache_minutes]" value="%2$d" /> <span>%3$s</span>',
			esc_attr(self::OPTION_NAME),
			$value,
			esc_html__('minutes', 'custom-videos-app-cw')
		);
		echo '<p class="description">' . esc_html__('Video API responses are cached to keep pages fast and avoid unnecessary Vimeo API requests.', 'custom-videos-app-cw') . '</p>';
	}

	public function render_hide_non_public_field(): void {
		printf(
			'<label><input type="checkbox" name="%1$s[hide_non_public]" value="1" %2$s /> %3$s</label>',
			esc_attr(self::OPTION_NAME),
			checked($this->should_hide_non_public_videos(), true, false),
			esc_html__('Hide unlisted and private videos', 'custom-videos-app-cw')
		);
		echo '<p class="description">' . esc_html__('When checked, only publicly listed Vimeo videos are shown. When unchecked, the grid can include any videos returned by the authenticated Vimeo account that are embeddable.', 'custom-videos-app-cw') . '</p>';
	}

	public function render_settings_page(): void {
		if (!current_user_can('manage_options')) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html__('Custom Video App CW', 'custom-videos-app-cw'); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields('custom_videos_app_cw');
				do_settings_sections('custom-videos-app-cw');
				submit_button();
				?>
			</form>
			<hr />
			<h2><?php echo esc_html__('Shortcode', 'custom-videos-app-cw'); ?></h2>
			<p><code>[custom_video_app_cw]</code></p>
			<p><?php echo esc_html__('Optional attributes: per_page, columns, show_description. The default per_page value is 500.', 'custom-videos-app-cw'); ?></p>
			<p><code>[custom_video_app_cw per_page="12" columns="3" show_description="false"]</code></p>
		</div>
		<?php
	}

	public function register_assets(): void {
		$css_path = plugin_dir_path(__FILE__) . 'assets/css/custom-videos-app-cw.css';
		$js_path  = plugin_dir_path(__FILE__) . 'assets/js/custom-videos-app-cw.js';

		wp_register_style(
			'custom-videos-app-cw',
			plugins_url('assets/css/custom-videos-app-cw.css', __FILE__),
			array(),
			file_exists($css_path) ? (string) filemtime($css_path) : '1.0.0'
		);

		wp_register_script(
			'custom-videos-app-cw',
			plugins_url('assets/js/custom-videos-app-cw.js', __FILE__),
			array(),
			file_exists($js_path) ? (string) filemtime($js_path) : '1.0.0',
			true
		);
	}

	public function render_shortcode(array $atts = array()): string {
		$atts = shortcode_atts(
			array(
				'per_page'         => self::DEFAULT_PER_PAGE,
				'columns'          => 3,
				'show_description' => 'false',
			),
			$atts,
			'custom_video_app_cw'
		);

		$per_page         = max(1, min(500, absint($atts['per_page'])));
		$columns          = max(1, min(6, absint($atts['columns'])));
		$tablet_columns   = min(2, $columns);
		$show_description = filter_var($atts['show_description'], FILTER_VALIDATE_BOOLEAN);
		$videos           = $this->get_videos($per_page);

		$this->enqueue_assets();

		if (is_wp_error($videos)) {
			if (current_user_can('manage_options')) {
				return '<div class="cvacw-video-app"><div class="cvacw-notice">' . esc_html($videos->get_error_message()) . '</div></div>';
			}

			return '<div class="cvacw-video-app"><div class="cvacw-notice">' . esc_html__('Videos are temporarily unavailable.', 'custom-videos-app-cw') . '</div></div>';
		}

		if (empty($videos)) {
			return '<div class="cvacw-video-app"><div class="cvacw-notice">' . esc_html__('No public Vimeo videos are available right now.', 'custom-videos-app-cw') . '</div></div>';
		}

		ob_start();
		?>
		<div class="cvacw-video-app">
			<div class="cvacw-video-grid" style="<?php echo esc_attr('--cvacw-columns:' . $columns . ';--cvacw-tablet-columns:' . $tablet_columns); ?>">
				<?php foreach ($videos as $video) : ?>
					<article class="cvacw-video-card">
						<button
							type="button"
							class="cvacw-video-trigger cvacw-video-trigger-thumb"
							data-vimeo-url="<?php echo esc_url($video['embed_url']); ?>"
							data-vimeo-title="<?php echo esc_attr($video['title']); ?>"
							aria-label="<?php echo esc_attr(sprintf(__('Play %s', 'custom-videos-app-cw'), $video['title'])); ?>"
						>
							<span class="cvacw-thumb-wrap">
								<?php if (!empty($video['thumbnail'])) : ?>
									<img class="cvacw-thumbnail" src="<?php echo esc_url($video['thumbnail']); ?>" alt="" loading="lazy" />
								<?php else : ?>
									<span class="cvacw-thumb-placeholder" aria-hidden="true"></span>
								<?php endif; ?>
								<span class="cvacw-play" aria-hidden="true"></span>
							</span>
						</button>
						<h4 class="cvacw-title">
							<button
								type="button"
								class="cvacw-video-trigger cvacw-video-trigger-title"
								data-vimeo-url="<?php echo esc_url($video['embed_url']); ?>"
								data-vimeo-title="<?php echo esc_attr($video['title']); ?>"
							>
								<?php echo esc_html($video['title']); ?>
							</button>
						</h4>
						<?php if ($show_description && !empty($video['description'])) : ?>
							<p class="cvacw-description"><?php echo esc_html($video['description']); ?></p>
						<?php endif; ?>
					</article>
				<?php endforeach; ?>
			</div>
		</div>
		<?php

		return (string) ob_get_clean();
	}

	private function get_settings(): array {
		$settings = get_option(self::OPTION_NAME, array());

		return is_array($settings) ? $settings : array();
	}

	private function get_videos(int $limit) {
		$settings     = $this->get_settings();
		$access_token = isset($settings['access_token']) ? trim((string) $settings['access_token']) : '';

		if (empty($access_token)) {
			return new WP_Error(
				'cvacw_missing_token',
				__('Custom Video App CW needs a Vimeo access token. Add it under Settings > Custom Video App CW.', 'custom-videos-app-cw')
			);
		}

		$hide_non_public = $this->should_hide_non_public_videos();
		$cache_key        = self::TRANSIENT_PREFIX . md5((string) $limit . '_' . (int) $hide_non_public);
		$cached           = get_transient($cache_key);

		if (false !== $cached) {
			return $cached;
		}

		$videos = array();
		$page   = 1;

		do {
			$url = add_query_arg(
				array(
					'page'      => $page,
					'per_page'  => min(100, $limit),
					'sort'      => 'date',
					'direction' => 'desc',
					'fields'    => 'uri,name,description,pictures.sizes,player_embed_url,privacy.view,paging.next',
				),
				'https://api.vimeo.com/me/videos'
			);

			$response = wp_remote_get(
				$url,
				array(
					'timeout' => 15,
					'headers' => array(
						'Authorization' => 'Bearer ' . $access_token,
						'Accept'        => 'application/vnd.vimeo.*+json;version=3.4',
					),
				)
			);

			if (is_wp_error($response)) {
				return $response;
			}

			$status_code = wp_remote_retrieve_response_code($response);
			$body        = json_decode(wp_remote_retrieve_body($response), true);

			if ($status_code < 200 || $status_code >= 300) {
				$message = isset($body['error']) ? $body['error'] : __('Vimeo API request failed. Check the access token and app permissions.', 'custom-videos-app-cw');
				$message = isset($body['message']) ? $body['message'] : $message;

				return new WP_Error('cvacw_vimeo_error', $message);
			}

			if (!is_array($body) || empty($body['data']) || !is_array($body['data'])) {
				break;
			}

			foreach ($body['data'] as $video) {
				if ($hide_non_public && !$this->is_public_video($video)) {
					continue;
				}

				$embed_url = isset($video['player_embed_url']) ? esc_url_raw($video['player_embed_url']) : '';

				if (empty($embed_url)) {
					continue;
				}

				$videos[] = array(
					'title'       => isset($video['name']) ? sanitize_text_field($video['name']) : __('Untitled video', 'custom-videos-app-cw'),
					'description' => isset($video['description']) ? wp_strip_all_tags($video['description']) : '',
					'thumbnail'   => $this->get_best_thumbnail($video),
					'embed_url'   => $embed_url,
				);

				if (count($videos) >= $limit) {
					break 2;
				}
			}

			$has_next_page = !empty($body['paging']['next']);
			$page++;
		} while ($has_next_page && $page <= 25);

		set_transient($cache_key, $videos, $this->get_cache_seconds());

		return $videos;
	}

	private function is_public_video(array $video): bool {
		$privacy = $video['privacy']['view'] ?? '';

		return 'anybody' === $privacy;
	}

	private function should_hide_non_public_videos(): bool {
		$settings = $this->get_settings();

		if (!array_key_exists('hide_non_public', $settings)) {
			return true;
		}

		return !empty($settings['hide_non_public']);
	}

	private function get_best_thumbnail(array $video): string {
		$sizes = $video['pictures']['sizes'] ?? array();

		if (!is_array($sizes) || empty($sizes)) {
			return '';
		}

		usort(
			$sizes,
			static function (array $a, array $b): int {
				return (int) ($b['width'] ?? 0) <=> (int) ($a['width'] ?? 0);
			}
		);

		return isset($sizes[0]['link']) ? esc_url_raw($sizes[0]['link']) : '';
	}

	private function get_cache_seconds(): int {
		$settings = $this->get_settings();
		$minutes  = isset($settings['cache_minutes']) ? absint($settings['cache_minutes']) : 60;

		return max(5, $minutes) * MINUTE_IN_SECONDS;
	}

	private function clear_video_cache(): void {
		global $wpdb;

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like('_transient_' . self::TRANSIENT_PREFIX) . '%',
				$wpdb->esc_like('_transient_timeout_' . self::TRANSIENT_PREFIX) . '%'
			)
		);
	}

	private function enqueue_assets(): void {
		wp_enqueue_style('custom-videos-app-cw');
		wp_enqueue_script('custom-videos-app-cw');
	}
}

Custom_Videos_App_CW::instance();
