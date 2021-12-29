<?php declare(strict_types = 1);
/*
Plugin Name: Piwigo2WP
Plugin URI: https://github.com/dougherty-dev/Piwigo2WP
Description: Include random images from an open API Piwigo gallery in your WP blog.
Version: 1.0.1
Author: Niklas Dougherty
Author URI: https://github.com/dougherty-dev
License: GNU General Public License v3.0
Copyright: 2021– Niklas Dougherty
Based on PiwigoPress (2009-2012	VDigital, 2014-2015 Norbert Preining).
*/

version_compare(PHP_VERSION, '8.0', '>=') or exit;
defined('ABSPATH') or exit;
define('PWG2WP_NAME', 'Piwigo2WP');
define('PWG2WP_VERSION', '1.0.1');

final class Piwigo2WP extends WP_Widget {
	function __construct() {
		$options = ['classname' => PWG2WP_NAME, 'description' => __("Adds a picture to your sidebar.", 'piwigo2wp')];
		WP_Widget::__construct(mb_strtolower(PWG2WP_NAME), PWG2WP_NAME, $options);
	}

	function widget($args, $gallery) {
		if (empty($gallery['url'])) return;
		extract($args);

		$piwigo_url = $gallery['url'];
		if (!str_ends_with($piwigo_url, '/')) $piwigo_url .= '/';

		$title = apply_filters('widget_title', empty($gallery['title']) ? '&nbsp;' : $gallery['title']);
		if ($title !== '') $title = $before_title . $title . $after_title;
		echo $before_widget . PHP_EOL . $title . PHP_EOL;

		$image_size = empty($gallery['image_size']) ? 'xlarge' : $gallery['image_size'];
		$number = empty($gallery['number']) ? 1 : intval($gallery['number']);

		$callstr = $piwigo_url . 'ws.php?method=pwg.categories.getImages&format=json&order=random&per_page=' . $number;
		$response = wp_remote_get($callstr);
		if (!is_wp_error($response)) {
			$thumbc = json_decode($response['body'], true);
			if (isset($thumbc["result"]["images"])) {
				foreach ($thumbc["result"]["images"] as $picture) {
					$image_url = $picture['derivatives'][$image_size]['url'];
					$image_width = $picture['derivatives'][$image_size]['width'];
					$image_height = $picture['derivatives'][$image_size]['height'];
					$image_comment = isset($picture['comment']) ? wp_strip_all_tags($picture['comment']) : '';
					$image_name = isset($picture['name']) ? wp_strip_all_tags($picture['name']) . ' – ' . $image_comment : '';

					echo <<< EOT
			<div style="max-width: {$image_width}px">
				<a title="$image_name" href="{$piwigo_url}picture.php?/{$picture['id']}">
					<img class="PWG2WP_thumb" width="$image_width" height="$image_height" src="$image_url" alt="$image_name"/>
					<span class="PWG2WP_caption">$image_name</span>
				</a>
			</div>

EOT;
				}
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

		echo '<div class="PWG2WP_widget">
	<table>
		<tr>
			<td>
				<fieldset class="edge">
					<legend><span> ' . __('Gallery', 'piwigo2wp') . ' </span></legend>
					<label>' . __('Title', 'piwigo2wp') . '<input type="text" name="' . $this->get_field_name('title') .
						'" value="' . esc_attr($gallery['title']) . '"/></label>
					<label>' . __('Gallery URL', 'piwigo2wp') . '<input type="url" name="' . $this->get_field_name('url') .
						'" value="' . esc_attr($gallery['url']) . '" placeholder="https://piwigo.org/" pattern="https://.*" size="30" required/></label>
					<label>' . __('Number of pictures', 'piwigo2wp') . '<input type="number" min="1" max="20" name="' . $this->get_field_name('number') .
						'" value="' . esc_attr($gallery['number']) . '"/></label>
				</fieldset>
			</td>
			<td>
				<fieldset class="right edge">
					<legend><span> ' . __('Size', 'piwigo2wp') . ' </span></legend>';

		$image_size = esc_attr($gallery['image_size']);
		$image_size_field_name = $this->get_field_name('image_size');
		$sizes = ['square', 'thumb', '2small', 'xsmall', 'small', 'medium', 'large', 'xlarge', 'xxlarge'];
		$strings = ['Square', 'Thumbnail', 'XXS – Tiny', 'XS – Extra small', 'S – Small', 'M – Medium', 'L – Large', 'XL – Extra large', 'XXL – Huge'];

		foreach ($sizes as $i => $size) echo '
					<label>' . __($strings[$i], 'piwigo2wp') . ' <input type="radio" value="' . $sizes[$i] .'" name="' .
						$image_size_field_name .'" ' . checked($image_size, $sizes[$i], false) . '></label><br>';

		echo '				</fieldset>
			</td>
		</tr>
	</table>
</div>';
	}
}

function piwigo2wp_init(): void {
	register_widget('Piwigo2WP');
}
add_action('widgets_init', 'piwigo2wp_init');

function piwigo2wp_load_in_head(): void {
	echo '<link media="all" type="text/css" href="' .
		plugins_url('piwigo2wp/piwigo2wp.css?ver=') . PWG2WP_VERSION . '" id="piwigo2wp-css" rel="stylesheet">';
}
add_action('wp_head', 'piwigo2wp_load_in_head');

function piwigo2wp_register_plugin(): void {
	if (current_user_can('edit_posts') || current_user_can('edit_pages')) {
		add_action('admin_head', 'piwigo2wp_load_in_head');
	}
}
add_action('init', 'piwigo2wp_register_plugin');

function piwigo2wp_plugin_links(array $links, string $file): array {
	if ($file === plugin_basename(__FILE__)) {
		return array_merge($links, ['<a href="https://piwigo.org/">' . __('Piwigo') . '</a>']);
	}
	return $links;
}
add_filter('plugin_row_meta', 'piwigo2wp_plugin_links', 10, 2);
