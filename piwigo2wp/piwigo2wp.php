<?php declare(strict_types = 1);
/*
Plugin Name: Piwigo2WP
Plugin URI: https://github.com/dougherty-dev
Description: Include random images from an open API Piwigo gallery in your WP blog.
Version: 1.0
Author: Niklas Dougherty
Author URI: https://github.com/dougherty-dev
License: GNU General Public License v3.0
Copyright: 2021– Niklas Dougherty
Based on PiwigoPress (2009-2012	VDigital, 2014-2015 Norbert Preining).
*/

version_compare(PHP_VERSION, '8.0', '>=') or exit;
defined('ABSPATH') or exit;
if (defined('PHPWG_ROOT_PATH')) return;

define('PWG2WP_NAME', 'piwigo2wp');
define('PWG2WP_VERSION', '1.0');

final class Piwigo2WP extends WP_Widget {
	function __construct() {
		$widget_ops = ['classname' => PWG2WP_NAME,
			'description' => __("Adds a picture to your sidebar.", 'piwigo2wp')];
		$control_ops = ['width' => 780, 'height' => 300];
		WP_Widget::__construct(PWG2WP_NAME, PWG2WP_NAME, $widget_ops, $control_ops);
	}

	function widget($args, $gallery) {
		extract($args);
		echo $before_widget . PHP_EOL;

		if (empty($gallery['url'])) return;
		$piwigo_url = $gallery['url'];
		if (substr($piwigo_url, -1) !== '/') $piwigo_url .= '/';

		$title = apply_filters('widget_title', empty($gallery['title']) ? '&nbsp;' : $gallery['title']);
		if ($title) $title = $before_title . $title . $after_title;
		echo $title . PHP_EOL;

		$image_size = empty($gallery['image_size']) ? 'xl' : $gallery['image_size'];
		$number = empty($gallery['number']) ? 1 : intval($gallery['number']);

		$callstr = $piwigo_url . 'ws.php?method=pwg.categories.getImages&format=json&per_page=' . intval($gallery['number']) . '&recursive=true&order=random';
		$response = wp_remote_get($callstr);
		if (!is_wp_error($response)) {
			$thumbc = json_decode($response['body'], true);
			if (!isset($thumbc["result"]["images"])) return;
			$pictures = $thumbc["result"]["images"];
			foreach ($pictures as $picture) {
				if (isset($picture['derivatives']['square']['url'])) {
					$picture['image_url'] = match($image_size) {
						'sq' => $picture['derivatives']['square']['url'],
						'sm' => $picture['derivatives']['small']['url'],
						'xs' => $picture['derivatives']['xsmall']['url'],
						'2s' => $picture['derivatives']['2small']['url'],
						'me' => $picture['derivatives']['medium']['url'],
						'la' => $picture['derivatives']['large']['url'],
						'xl' => $picture['derivatives']['xlarge']['url'],
						'xx' => $picture['derivatives']['xxlarge']['url'],
						default => $picture['derivatives']['thumb']['url']
					};
				}

				$alt = htmlspecialchars($picture['name']);
				if (isset($picture['comment'])) $alt .= (' -- ' . htmlspecialchars($picture['comment']));

				echo '<a title="' . htmlspecialchars($picture['name']) . '" href="'
					. $piwigo_url . 'picture.php?/' . $picture['id'] . '">
					<img class="PWG2WP_thumb" src="' . $picture['image_url'] . '" alt="' . $alt . '"/>' . PHP_EOL;

				if (isset($picture['comment'])) {
					$picture['comment'] = wp_strip_all_tags($picture['comment']);
					if (trim($picture['comment']) !== '')
						echo '<span class="PWG2WP_caption">' . $picture['comment'] . '</span>' . PHP_EOL;
				}
				echo '</a>' . PHP_EOL;
			}
		}

		echo $after_widget . PHP_EOL;
	}

	function update($new_gallery, $old_gallery): array {
		$gallery = $old_gallery;
		$gallery['title'] = isset($new_gallery['title']) ? wp_strip_all_tags($new_gallery['title']) : '';
		isset($new_gallery['image_size']) and $gallery['image_size'] = wp_strip_all_tags($new_gallery['image_size']);
		isset($new_gallery['url']) and $gallery['url'] = wp_strip_all_tags($new_gallery['url']);
		isset($new_gallery['number']) and $gallery['number'] = intval(wp_strip_all_tags($new_gallery['number']));
		return $gallery;
	}

	function form($gallery) {
		$gallery = wp_parse_args((array) $gallery, ['title' =>__('Random picture', 'piwigo2wp'),
			'image_size' => 'xl', 'url' => '', 'number' => 1]
		);

		$image_size = esc_attr($gallery['image_size']);

		echo '<div class="PWG2WP_widget">
	<table>
		<tr>
			<td>
				<fieldset class="edge">
					<legend><span> ' . __('Gallery', 'piwigo2wp') . ' </span></legend>
					<label for=">' . $this->get_field_id('title') . '">' . __('Title', 'piwigo2wp') . ' </label>
					<input id="' . $this->get_field_id('title') . '" name="' . $this->get_field_name('title')
						. '" type="text" value="' . esc_attr($gallery['title']) . '"/>
					<label for=">' . $this->get_field_id('url') . '">' . __('Gallery URL', 'piwigo2wp') . ' </label>
					<input id="' . $this->get_field_id('url') . '" name="' . $this->get_field_name('url')
						. '" type="text" value="' . esc_attr($gallery['url']) . '"/>
					<label for=">' . $this->get_field_id('number') . '">' . __('Number of pictures', 'piwigo2wp') . ' </label>
					<input id="' . $this->get_field_id('number') . '" name="' . $this->get_field_name('number')
						. '" type="text" value="' . esc_attr($gallery['number']) . '"/>
				</fieldset>
			</td>
			<td>
				<fieldset class="right edge">
					<legend><span> ' . __('Size', 'piwigo2wp') . ' </span></legend>
					<label for="'. $this->get_field_id('image_size') . '">' . __('Square', 'piwigo2wp') . ' </label>
					<input type="radio" value="sq" name="'. $this->get_field_name('image_size') .'" '
						. checked($image_size, 'sq', false) . '><br/>
					<label for="'. $this->get_field_id('image_size') . '">' . __('Thumbnail', 'piwigo2wp') . ' </label>
					<input type="radio" value="th" name="'. $this->get_field_name('image_size') .'" '
						. checked($image_size, 'th', false) . '><br/>
					<label for="'. $this->get_field_id('image_size') . '">' . __('XXS - tiny', 'piwigo2wp') . ' </label>
					<input type="radio" value="2s" name="'. $this->get_field_name('image_size') .'" '
						. checked($image_size, '2s', false) . '><br/>
					<label for="'. $this->get_field_id('image_size') . '">' . __('XS - extra small', 'piwigo2wp') . ' </label>
					<input type="radio" value="xs" name="'. $this->get_field_name('image_size') .'" '
						. checked($image_size, 'xs', false) . '><br/>
					<label for="'. $this->get_field_id('image_size') . '">' . __('S - small', 'piwigo2wp') . ' </label>
					<input type="radio" value="sm" name="'. $this->get_field_name('image_size') .'" '
						. checked($image_size, 'sm', false) . '><br/>
					<label for="'. $this->get_field_id('image_size') . '">' . __('M - medium', 'piwigo2wp') . ' </label>
					<input type="radio" value="me" name="'. $this->get_field_name('image_size') .'" '
						. checked($image_size, 'me', false) . '><br/>
					<label for="'. $this->get_field_id('image_size') . '">' . __('L - large', 'piwigo2wp') . ' </label>
					<input type="radio" value="la" name="'. $this->get_field_name('image_size') .'" '
						. checked($image_size, 'la', false) . '><br/>
					<label for="'. $this->get_field_id('image_size') . '">' . __('XL - extra large', 'piwigo2wp') . ' </label>
					<input type="radio" value="xl" name="'. $this->get_field_name('image_size') .'" '
						. checked($image_size, 'xl', false) . '><br/>
					<label for="'. $this->get_field_id('image_size') . '">' . __('XXL - huge', 'piwigo2wp') . ' </label>
					<input type="radio" value="xx" name="'. $this->get_field_name('image_size') .'" '
						. checked($image_size, 'xx', false) . '>
				</fieldset>
			</td>
		</tr>
	</table>
</div>';
	}
}

function piwigo2wp_init() {
	register_widget('Piwigo2WP');
}
add_action('widgets_init', PWG2WP_NAME . '_init');

function piwigo2wp_load_in_head() {
	echo '<link media="all" type="text/css" href="' .
		plugins_url('piwigo2wp/piwigo2wp.css?ver=') . PWG2WP_VERSION . '" id="piwigo2wp-css" rel="stylesheet">';
}
add_action('wp_head', PWG2WP_NAME . '_load_in_head');

function piwigo2wp_register_plugin() {
	if (!current_user_can('edit_posts') && !current_user_can('edit_pages'))
		return;
	add_action('admin_head', PWG2WP_NAME . '_load_in_head');
}
add_action('init', PWG2WP_NAME . '_register_plugin');

function piwigo2wp_plugin_links($links, $file) {
	$plugin = plugin_basename(__FILE__);

	if ($file === $plugin) {
		return array_merge($links,
			['<a href="https://piwigo.org/">' . __('Piwigo') . '</a>']
		);
	}
	return $links;
}
add_filter('plugin_row_meta', PWG2WP_NAME . '_plugin_links', 10, 2);
