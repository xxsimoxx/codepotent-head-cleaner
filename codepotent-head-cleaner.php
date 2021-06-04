<?php

/**
 * -----------------------------------------------------------------------------
 * Plugin Name: Head Cleaner
 * Description: Remove specific tags from the ClassicPress head section to reduce server requests and improve site performance.
 * Version: 1.1.0
 * Author: Simone Fioravanti
 * Author URI: https://software.gieffeedizioni.it
 * Plugin URI: https://software.gieffeedizioni.it
 * Text Domain: codepotent-head-cleaner
 * Domain Path: /languages
 * -----------------------------------------------------------------------------
 * This is free software released under the terms of the General Public License,
 * version 2, or later. It is distributed WITHOUT ANY WARRANTY; without even the
 * implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. Full
 * text of the license is available at https://www.gnu.org/licenses/gpl-2.0.txt.
 * -----------------------------------------------------------------------------
 * Copyright 2021, John Alarcon (Code Potent)
 * -----------------------------------------------------------------------------
 * Adopted by Simone Fioravanti, 06/01/2021
 * -----------------------------------------------------------------------------
 */

// Declare the namespace.
namespace CodePotent\HeadCleaner;

// Prevent direct access.
if (!defined('ABSPATH')) {
	die();
}

class HeadCleaner {

	/**
	 * Constructor.
	 *
	 * No properties to set; move straight to initialization.
	 *
	 * @author John Alarcon
	 *
	 * @since 1.0.0
	 */
	public function __construct() {

		// Setup all the things.
		$this->init();

	}

	/**
	 * Plugin initialization.
	 *
	 * Register actions and filters to hook the plugin into the system.
	 *
	 * @author John Alarcon
	 *
	 * @since 1.0.0
	 */
	public function init() {

		// Load constants.
		require_once plugin_dir_path(__FILE__).'includes/constants.php';

		// Load plugin update class.
		require_once(PATH_CLASSES.'/UpdateClient.class.php');

		// Get plugin options just early enough.
		$this->options = get_option(PLUGIN_PREFIX.'_settings');

		// Unhook functions that output tags in the <head>, per settings.
		$this->remove_hooked_functions();

		// Register settings.
		add_action('admin_init', [$this, 'register_settings']);

		// Register admin page and menu item.
		add_action('admin_menu', [$this, 'register_admin_menu']);

		// Enqueue backend scripts.
		add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);

		// Replace footer text with plugin name and version info.
		add_filter('admin_footer_text', [$this, 'filter_footer_text'], 10000);

		// Add a "Settings" link to core's plugin admin row.
		add_filter('plugin_action_links_'.PLUGIN_IDENTIFIER, [$this, 'register_action_links']);

		// Register activation method.
		register_activation_hook(__FILE__, [$this, 'activate_plugin']);

		// Register deactivation method.
		register_deactivation_hook(__FILE__, [$this, 'deactivate_plugin']);

		// Register deletion method. This is a static method; use __CLASS__.
		register_uninstall_hook(__FILE__, [__CLASS__, 'uninstall_plugin']);

		// POST-ADOPTION: Remove these actions before pushing your next update.
		add_action('upgrader_process_complete', [$this, 'enable_adoption_notice'], 10, 2);
		add_action('admin_notices', [$this, 'display_adoption_notice']);

	}

	// POST-ADOPTION: Remove this method before pushing your next update.
	public function enable_adoption_notice($upgrader_object, $options) {
		if ($options['action'] === 'update') {
			if ($options['type'] === 'plugin') {
				if (!empty($options['plugins'])) {
					if (in_array(plugin_basename(__FILE__), $options['plugins'])) {
						set_transient(PLUGIN_PREFIX.'_adoption_complete', 1);
					}
				}
			}
		}
	}

	// POST-ADOPTION: Remove this method before pushing your next update.
	public function display_adoption_notice() {
		if (get_transient(PLUGIN_PREFIX.'_adoption_complete')) {
			delete_transient(PLUGIN_PREFIX.'_adoption_complete');
			echo '<div class="notice notice-success is-dismissible">';
			echo '<h3 style="margin:25px 0 15px;padding:0;color:#e53935;">IMPORTANT <span style="color:#aaa;">information about the <strong style="color:#333;">'.PLUGIN_NAME.'</strong> plugin</h3>';
			echo '<p style="margin:0 0 15px;padding:0;font-size:14px;">The <strong>'.PLUGIN_NAME.'</strong> plugin has been officially adopted and is now managed by <a href="'.PLUGIN_AUTHOR_URL.'" rel="noopener" target="_blank" style="text-decoration:none;">'.PLUGIN_AUTHOR.'<span class="dashicons dashicons-external" style="display:inline;font-size:98%;"></span></a>, a longstanding and trusted ClassicPress developer and community member. While it has been wonderful to serve the ClassicPress community with free plugins, tutorials, and resources for nearly 3 years, it\'s time that I move on to other endeavors. This notice is to inform you of the change, and to assure you that the plugin remains in good hands. I\'d like to extend my heartfelt thanks to you for making my plugins a staple within the community, and wish you great success with ClassicPress!</p>';
			echo '<p style="margin:0 0 15px;padding:0;font-size:14px;font-weight:600;">All the best!</p>';
			echo '<p style="margin:0 0 15px;padding:0;font-size:14px;">~ John Alarcon <span style="color:#aaa;">(Code Potent)</span></p>';
			echo '</div>';
		}
	}

	/**
	 * Register admin page.
	 *
	 * Register an options page under Dashboard > Settings > Head Cleaner
	 *
	 * @author John Alarcon
	 *
	 * @since 1.0.0
	 */
	public function register_admin_menu() {

		add_options_page(
			PLUGIN_NAME,
			PLUGIN_NAME,
			'manage_options',
			PLUGIN_SHORT_SLUG,
			[$this, 'render_settings_page']
		);

	}

	/**
	 * Register settings section and its options.
	 *
	 * @author John Alarcon
	 *
	 * @since 1.0.0
	 *
	 */
	public function register_settings() {

		// Register settings variable.
		register_setting(PLUGIN_PREFIX.'_settings', PLUGIN_PREFIX.'_settings');

		// Register a settings section.
		add_settings_section(PLUGIN_PREFIX.'_section', null, null, PLUGIN_PREFIX.'_settings');

		// Add settings fields to the section.
		foreach ($this->get_hook_properties() as $action) {
			add_settings_field(
				$action['hook'].'-'.$action['function'],
				'<label for="'.$action['hook'].'-'.$action['function'].'">'.$action['label'].'</label>',
				[$this, 'render_settings_checkbox'],
				PLUGIN_PREFIX.'_settings',
				PLUGIN_PREFIX.'_section',
				$action
			);
		}

	}

	/**
	 * Enqueue JavaScript and CSS.
	 *
	 * @author John Alarcon
	 *
	 * @since 1.0.0
	 */
	public function enqueue_admin_scripts() {

		if (get_current_screen()->base === 'settings_page_'.PLUGIN_SHORT_SLUG) {
			wp_enqueue_script(PLUGIN_SLUG.'-admin', URL_SCRIPTS.'/admin.js', [], time());
			wp_enqueue_style(PLUGIN_SLUG.'-admin', URL_STYLES.'/admin.css', [], time());
			wp_localize_script(PLUGIN_SLUG.'-admin', 'plugin_slug', PLUGIN_SLUG);
		}

	}

	/**
	 * Get hook properties
	 *
	 * This method returns an array of data related to the relevant hooks.
	 *
	 * @author John Alarcon
	 *
	 * @since 1.0.0
	 *
	 * @return array[][] Array of hook properties.
	 */
	public function get_hook_properties() {
		return [
			[
				'hook'        => 'wp_head',
				'function'    => 'wp_generator',
				'label'       => esc_html__('Generator Tag', 'codepotent-head-cleaner'),
				'short_desc'  => esc_html__('Remove generator tag.', 'codepotent-head-cleaner'),
				'long_desc'   => esc_html__('This tag references the platform and version on which the site it built. Note that it is not a security risk to leave this tag in the head section. Leaving this tag intact helps ClassicPress. The following is an example of the tag removed.', 'codepotent-head-cleaner'),
				'examples'    => [
					'<meta name="generator" content="WordPress 4.9.15 (compatible; ClassicPress 1.2.0)">',
				],
			],
			[
				'hook'        => 'wp_head',
				'function'    => 'rest_output_link_wp_head',
				'label'       => esc_html__('REST Tag', 'codepotent-head-cleaner'),
				'short_desc'  => esc_html__('Remove WordPress API call tag.', 'codepotent-head-cleaner'),
				'long_desc'   => esc_html__('If your site is not using the REST API, this tag can be removed. It is unclear whether this tag is used for tracking purposes. The following is an example of the tag removed.', 'codepotent-head-cleaner'),
				'examples'    => [
					'<link rel="https://api.w.org/" href="{Site URL}/index.php?rest_route=/" />',
				],
			],
			[
				'hook'        => 'wp_head',
				'function'    => 'wp_oembed_add_discovery_links',
				'label'       => esc_html__('oEmbed Tags', 'codepotent-head-cleaner'),
				'short_desc'  => esc_html__('Remove oEmbed discovery tags.', 'codepotent-head-cleaner'),
				'long_desc'   => esc_html__('oEmbed is a format for allowing an embedded representation of a URL on third party sites. The API allows a website to display embedded content (such as photos or videos) when a user posts a link to that resource, without having to parse the resource directly. The following is an example of the tags removed.', 'codepotent-head-cleaner'),
				'examples'    => [
					'<link rel="alternate" type="application/json+oembed" href="{Site URL}/wp-json/oembed/1.0/embed?url={Site URL}%2Fpost-title%2F" />',
					'<link rel="alternate" type="text/xml+oembed" href="{Site URL}/wp-json/oembed/1.0/embed?url={Site URL}%2Fpost-title%2F&#038;format=xml" />',
				],
			],
			[
				'hook'        => 'pingback',
				'function'    => '__return_false',
				'label'       => esc_html__('Pingback Tag', 'codepotent-head-cleaner'),
				'short_desc'  => esc_html__('Remove the pingback tag.', 'codepotent-head-cleaner'),
				'long_desc'   => esc_html__('Used for pingbacks and trackbacks. If you are not using XMLRPC, pingbacks, or trackbacks, you can remove this tag. Note that this tag is usually added directly to your theme\'s header.php file, rather than being added by ClassicPress.', 'codepotent-head-cleaner'),
				'examples'    => [
					'<link rel="pingback" href="{Site URL}/xmlrpc.php">',
				],
			],
			[
				'hook'        => 'wp_head',
				'function'    => 'adjacent_posts_rel_link_wp_head',
				'label'       => esc_html__('Relation Tags', 'codepotent-head-cleaner'),
				'short_desc'  => esc_html__('Remove previous and next relation tags.', 'codepotent-head-cleaner'),
				'long_desc'   => esc_html__('For sequential items (think posts, not pages) tags are injected to indicate the URL of the previous and next posts. These tags can help search engines to understand that the items are in a sequence. The following is an example of the tags removed.', 'codepotent-head-cleaner'),
				'examples'    => [
					'<link rel="prev" title="{Title of Previous Article}" href="{Site URL}/title-of-previous-article/" />',
					'<link rel="next" title="{Title of Next Article}" href="{Site URL}/title-of-next-article/" />',
				],
			],
			[
				'hook'        => 'wp_head',
				'function'    => 'feed_links',
				'label'       => esc_html__('Feed Tags', 'codepotent-head-cleaner'),
				'short_desc'  => esc_html__('Remove RSS feed tags.', 'codepotent-head-cleaner'),
				'long_desc'   => esc_html__('RSS feed tags allow your content to be more easily discovered by feed readers. This setting removes the feed tags for posts and comments. The following is an example of the tags removed.', 'codepotent-head-cleaner'),
				'examples'    => [
					'<link rel="alternate" type="application/rss+xml" title="{Site Name} &raquo; Feed" href="{Site URL}/feed/" />',
					'<link rel="alternate" type="application/rss+xml" title="{Site Name} &raquo; Comments Feed" href="{Site URL}/comments/feed/" />',
					'<link rel="alternate" type="application/rss+xml" title="{Site Name} &raquo; {Post Name} Comments Feed" href="{Site URL}/post-name/feed/" />',
				],
			],
			[
				'hook'        => 'wp_head',
				'function'    => 'wp_shortlink_wp_head',
				'label'       => esc_html__('Shortlink Tag', 'codepotent-head-cleaner'),
				'short_desc'  => esc_html__('Remove shortlink tag.', 'codepotent-head-cleaner'),
				'long_desc'   => esc_html__('When you have short URLs enabled, a tag is injected into the head section that indicates the direct URL to the page by its post id. This value is already used in the canonical URL tag. The following is an example of the tag removed.', 'codepotent-head-cleaner'),
				'examples'    => [
					'<link rel="shortlink" href="{Site URL}/?p=1591" />',
				],
			],
			[
				'hook'        => 'wp_head',
				'function'    => 'rsd_link',
				'label'       => esc_html__('RSD Tag', 'codepotent-head-cleaner'),
				'short_desc'  => esc_html__('Remove "Really Simple Discovery" tag.', 'codepotent-head-cleaner'),
				'long_desc'   => esc_html__('Used in conjunction with xmlrpc.php, Really Simple Discovery is an XML publishing convention for exposing services on the web. If you are not using xmlrpc, this tag can be removed. The following is an example of the tag removed.', 'codepotent-head-cleaner'),
				'examples'    => [
					'<link rel="EditURI" type="application/rsd+xml" title="RSD" href="{Site URL}/xmlrpc.php?rsd" />',
				],
			],
			[
				'hook'        => 'wp_head',
				'function'    => 'wlwmanifest_link',
				'label'       => esc_html__('WLW Manifest Tag', 'codepotent-head-cleaner'),
				'short_desc'  => esc_html__('Remove "Windows Live Writer Manifest" tag.', 'codepotent-head-cleaner'),
				'long_desc'   => esc_html__('If you are not composing posts with Windows Live Writer (WLW), this tag can go. As a safeguard, be sure to consult with all those who author posts on your site before removing this tag. The following is an example of the tag removed.', 'codepotent-head-cleaner'),
				'examples'    => [
					'<link rel="wlwmanifest" type="application/wlwmanifest+xml" href="{Site URL}/wp-includes/wlwmanifest.xml" />',
				],
			],
			[
				'hook'        => 'emoji',
				'function'    => '__return_false',
				'label'       => esc_html__('Emoji Tags', 'codepotent-head-cleaner'),
				'short_desc'  => esc_html__('Remove emoji tags.', 'codepotent-head-cleaner'),
				'long_desc'   => esc_html__('Emoji functionality injects JavaScript and CSS into the header as well as doing a DNS prefetch. If your site does not use emoji, it can be removed. The following is an example of the tags, scripts, and styles removed.', 'codepotent-head-cleaner'),
				'examples'    => [
					'<link rel="dns-prefetch" href="//twemoji.classicpress.net" />',
					'<script type="text/javascript">
window._wpemojiSettings = {"baseUrl":"https:\/\/twemoji.classicpress.net\/12\/72x72\/","ext":".png","svgUrl":"https:\/\/twemoji.classicpress.net\/12\/svg\/","svgExt":".svg","source":{"concatemoji":"https:\/\/{Site URL}\/wp-includes\/js\/wp-emoji-release.min.js?ver=cp_ca570ce6"}};
!function(e,t,a){var r,n,o,i,p=t.createElement("canvas"),s=p.getContext&&p.getContext("2d");function c(e,t){var a=String.fromCharCode;s.clearRect(0,0,p.width,p.height),s.fillText(a.apply(this,e),0,0);var r=p.toDataURL();return s.clearRect(0,0,p.width,p.height),s.fillText(a.apply(this,t),0,0),r===p.toDataURL()}function l(e){if(!s||!s.fillText)return!1;switch(s.textBaseline="top",s.font="600 32px Arial",e){case"flag":return!c([55356,56826,55356,56819],[55356,56826,8203,55356,56819])&&!c([55356,57332,56128,56423,56128,56418,56128,56421,56128,56430,56128,56423,56128,56447],[55356,57332,8203,56128,56423,8203,56128,56418,8203,56128,56421,8203,56128,56430,8203,56128,56423,8203,56128,56447]);case"emoji":return!c([55357,56424,55356,57342,8205,55358,56605,8205,55357,56424,55356,57340],[55357,56424,55356,57342,8203,55358,56605,8203,55357,56424,55356,57340])}return!1}function d(e){var a=t.createElement("script");a.src=e,a.defer=a.type="text/javascript",t.getElementsByTagName("head")[0].appendChild(a)}for(i=Array("flag","emoji"),a.supports={everything:!0,everythingExceptFlag:!0},o=0;o<i.length;o++)a.supports[i[o]]=l(i[o]),a.supports.everything=a.supports.everything&&a.supports[i[o]],"flag"!==i[o]&&(a.supports.everythingExceptFlag=a.supports.everythingExceptFlag&&a.supports[i[o]]);a.supports.everythingExceptFlag=a.supports.everythingExceptFlag&&!a.supports.flag,a.DOMReady=!1,a.readyCallback=function(){a.DOMReady=!0},a.supports.everything||(n=function(){a.readyCallback()},t.addEventListener?(t.addEventListener("DOMContentLoaded",n,!1),e.addEventListener("load",n,!1)):(e.attachEvent("onload",n),t.attachEvent("onreadystatechange",(function(){"complete"===t.readyState&&a.readyCallback()}))),(r=a.source||{}).concatemoji?d(r.concatemoji):r.wpemoji&&r.twemoji&&(d(r.twemoji),d(r.wpemoji)))}(window,document,window._wpemojiSettings);
</script>',
					'<style type="text/css">
img.wp-smiley,
img.emoji {
	display: inline !important;
	border: none !important;
	box-shadow: none !important;
	height: 1em !important;
	width: 1em !important;
	margin: 0 .07em !important;
	vertical-align: -0.1em !important;
	background: none !important;
	padding: 0 !important;
}
</style>',
				],
			],

		];
	}

	/**
	 * Remove hooked functions
	 *
	 * This method unhooks all the functions that output tags in the <head> of a
	 * page, per the settings made on the admin page.
	 *
	 * @author John Alarcon
	 *
	 * @since 1.0.0
	 */
	public function remove_hooked_functions() {

		// Remove RSD tag.
		if (!empty($this->options['wp_head-rsd_link'])) {
			remove_action('wp_head', 'rsd_link');
		}

		// Remove WLW Manifest tag.
		if (!empty($this->options['wp_head-wlwmanifest_link'])) {
			remove_action('wp_head', 'wlwmanifest_link');
		}

		// Remove shortlink tag.
		if (!empty($this->options['wp_head-wp_shortlink_wp_head'])) {
			remove_action('wp_head', 'wp_shortlink_wp_head');
		}

		// Remove generator tag.
		if (!empty($this->options['wp_head-wp_generator'])) {
			remove_action('wp_head', 'wp_generator');
		}

		// Remove feed tags.
		if (!empty($this->options['wp_head-feed_links'])) {
			remove_action('wp_head', 'feed_links', 2);
			remove_action('wp_head', 'feed_links_extra', 3);
		}

		// Remove prev/next post tags.
		if (!empty($this->options['wp_head-adjacent_posts_rel_link_wp_head'])) {
			remove_action('wp_head', 'adjacent_posts_rel_link_wp_head');
		}

		// Remove REST tag.
		if (!empty($this->options['wp_head-rest_output_link_wp_head'])) {
			remove_action('wp_head', 'rest_output_link_wp_head');
		}

		// Remove oEmbed discovery tags.
		if (!empty($this->options['wp_head-wp_oembed_add_discovery_links'])) {
			remove_action('wp_head', 'wp_oembed_add_discovery_links');
		}

		// Remove emojis tags; there's quite a few things to unhook.
		if (!empty($this->options['emoji-__return_false'])) {
			remove_action('wp_head', 'print_emoji_detection_script', 7);
			remove_action('admin_print_scripts', 'print_emoji_detection_script');
			remove_action('wp_print_styles', 'print_emoji_styles');
			remove_action('admin_print_styles', 'print_emoji_styles');
			add_filter('emoji_svg_url' , '__return_false');
			remove_filter('the_content', 'convert_smilies', 20);
		}

		// Removing pingback is different; must be removed via output buffer.
		if (!empty($this->options['pingback-__return_false'])) {
			add_action('template_redirect', [$this, 'pingback_buffer_start'], -1);
			add_action('get_header', [$this, 'pingback_buffer_start']);
			add_action('wp_head', [$this, 'pingback_buffer_end'], 999);
		}

	}

	/**
	 * Output buffer callback
	 *
	 * Because pingback tags are added directly to the themes's header.php file,
	 * the tag can only be removed via the output buffer. This method strips the
	 * tag if it is present and returns the resulting string.
	 *
	 * @author John Alarcon
	 *
	 * @since 1.0.0
	 *
	 * @param string $buffer Content of output buffer.
	 *
	 * @return string Possibly-amended output buffer.
	 */
	public function pingback_buffer_callback($buffer) {

		return preg_replace('/(<link.*?rel=("|\')pingback("|\').*?href=("|\')(.*?)("|\')(.*?)?\/?>|<link.*?href=("|\')(.*?)("|\').*?rel=("|\')pingback("|\')(.*?)?\/?>)/i', '', $buffer);

	}

	/**
	 * Start output buffer
	 *
	 * For removing pingback tag.
	 *
	 * @author John Alarcon
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function pingback_buffer_start() {

		ob_start([$this, 'pingback_buffer_callback']);

	}

	/**
	 * Flush output buffer
	 *
	 * For removing pingback tag.
	 *
	 * @author John Alarcon
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function pingback_buffer_end() {

		ob_flush();

	}

	/**
	 * Render settings page
	 *
	 * This is the admin settings page.
	 *
	 * @author John Alarcon
	 *
	 * @since 1.0.0
	 *
	 */
	public function render_settings_page() {

		// Open the container.
		echo '<div class="wrap">';

		// Add the title heading.
		echo '<h1 class="wp-heading">'.esc_html__('Head Cleaner Settings', 'codepotent-head-cleaner').'</h1>';

		// Wrap.
		echo '<div class="'.PLUGIN_SLUG.'-settings-main">';

		// Start up a form.
		echo '<form action="options.php" method="post">';

		// Print the settings fields.
		settings_fields(PLUGIN_PREFIX.'_settings');

		// Print the sections.
		do_settings_sections(PLUGIN_PREFIX.'_settings');

		// Add a button.
		submit_button();

		// Close the form.
		echo '</form>';

		// Close the inner wrapper.
		echo '</div><!-- .'.PLUGIN_SLUG.'-settings-main -->';

		// Close the outer container.
		echo '</div><!-- .wrap -->';
	}

	/**
	 * Render settings checkbox
	 *
	 * All admin settings use a checkbox with near-identical properties. This is
	 * how we can get away with only using a single callback to handle them all.
	 *
	 * @author John Alarcon
	 *
	 * @since 1.0.0
	 *
	 * @param array $action The properties of a hooked filter or action.
	 */
	public function render_settings_checkbox($action) {

		// The id and name of the checkbox input.
		$id = $action['hook'].'-'.$action['function'];

		// Check or uncheck input.
		$checked = !empty($this->options[$id]) ? checked(1, 1, 0) : '';

		// Output the checkbox.
		echo '<label>';
		echo '<input type="checkbox" id="'.$id.'" name="'.PLUGIN_PREFIX.'_settings['.$id.']" value="1" '.$checked.'>';
		echo '<span class="description">'.$action['short_desc'].'</span>';
		echo '</label>';
		echo ' <label><a href="#" class="'.PLUGIN_SLUG.'-details-link" data-id="'.$id.'">'.esc_html__('Details', 'codepotent-head-cleaner').'</a></label>';

		// Output code examples after the checkbox and label.
		echo $this->render_example_tags($id, $action);

	}

	/**
	 * Render settings examples
	 *
	 * This method renders the markup for the examples that are shown and hidden
	 * when a user clicks a "Details" link.
	 *
	 * @author John Alarcon
	 *
	 * @since 1.0.0
	 *
	 * @param string $id
	 * @param array $action
	 *
	 * @return void
	 */
	public function render_example_tags($id, $action) {

		// Initialization.
		$examples = '';

		// Markup any examples.
		if (!empty($action['examples'])) {
			$rows = count($action['examples']);
			if ($action['hook'] === 'emoji' && $action['function'] === '__return_false') {
				$rows = 10; // Because the emoji example is much larger.
			}
			$examples .= '<div id="'.$id.'-example"  class="'.PLUGIN_SLUG.'-example">';
			$examples .= '<p class="description">'.$action['long_desc'].'</p>';
			$examples .= '<textarea rows="'.$rows.'" disabled>';
			foreach ($action['examples'] as $example) {
				$examples .= htmlentities($example)."\n";
			}
			$examples .= '</textarea>';
			$examples .= '</div>';
		}

		// Return whatever we've got.
		return $examples;

	}

	/**
	 * Register action links
	 *
	 * Add a direct link to the plugin's primary admin view.
	 *
	 * @author John Alarcon
	 *
	 * @since 1.0.0
	 *
	 * @param array $links Administration links for the plugin.
	 *
	 * @return array $links Updated administration links.
	 */
	public function register_action_links($links) {

		// Prepend link in plugin row; for admins only.
		if (current_user_can('manage_options')) {
			$link = '<a href="'.admin_url('options-general.php?page='.PLUGIN_SHORT_SLUG).'">'.esc_html__('Settings', 'codepotent-head-cleaner').'</a>';
			array_unshift($links, $link);
		}

		// Return the maybe-updated $links array.
		return $links;

	}

	/**
	 * Filter footer text.
	 *
	 * @author John Alarcon
	 *
	 * @since 1.0.0
	 *
	 * @param string $text The original footer text.
	 *
	 * @return void|string Branded footer text if in this plugin's admin.
	 */
	public function filter_footer_text($text) {

		// On this plugin's settings screen? If so, change the footer text.
		if (strpos(get_current_screen()->base, PLUGIN_SHORT_SLUG)) {
			$text = '<span id="footer-thankyou" style="vertical-align:text-bottom;"><a href="'.PLUGIN_AUTHOR_URL.'/" title="'.PLUGIN_DESCRIPTION.'">'.PLUGIN_NAME.'</a> '.PLUGIN_VERSION.' &#8211; by <a href="'.PLUGIN_AUTHOR_URL.'" title="'.VENDOR_TAGLINE.'">'.PLUGIN_AUTHOR.'</a></span>';
		}

		// Return the string.
		return $text;

	}

	/**
	 * Plugin activation.
	 *
	 * @author John Alarcon
	 *
	 * @since 1.0.0
	 */
	public function activate_plugin() {

		// No permission to activate plugins? Bail.
		if (!current_user_can('activate_plugins')) {
			return;
		}

		// Get existing options; in case of temp deactivation, else default.
		$options = get_option(PLUGIN_PREFIX.'_settings', []);

		// Update the record.
		update_option(PLUGIN_PREFIX.'_settings', $options);

	}

	/**
	 * Plugin deactivation.
	 *
	 * @author John Alarcon
	 *
	 * @since 1.0.0
	 */
	public function deactivate_plugin() {

		// No permission to activate plugins? None to deactivate either. Bail.
		if (!current_user_can('activate_plugins')) {
			return;
		}

		// Not that there was anything to do here anyway. :)

	}

	/**
	 * Plugin deletion.
	 *
	 * @author John Alarcon
	 *
	 * @since 1.0.0
	 */
	public static function uninstall_plugin() {

		// No permission to delete plugins? Bail.
		if (!current_user_can('delete_plugins')) {
			return;
		}

		// Delete options related to the plugin.
		delete_option(PLUGIN_PREFIX.'_settings');

	}

}

// Go!
new HeadCleaner;