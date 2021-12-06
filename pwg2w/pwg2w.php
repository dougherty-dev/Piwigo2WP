<?php declare(strict_types = 1);
/*
Plugin Name: PWG2W
Plugin URI: https://github.com/dougherty-dev/PWG2W
Description: Include random images from an open API Piwigo gallery in your WP blog.
Version: 1.0.0
Author: Niklas Dougherty
Author URI: https://github.com/dougherty-dev
License: GNU General Public License v3.0
Copyright: 2021– Niklas Dougherty
Based on PiwigoPress (2009-2012	VDigital, 2014-2015 Norbert Preining).
*/

version_compare(PHP_VERSION, '8.0', '>=') or exit;
defined('ABSPATH') or exit;
define('PWG2W_NAME', 'PWG2W');
define('PWG2W_VERSION', '1.0.0');

final class PWG2W extends WP_Widget {
	function __construct() {
		$options = ['classname' => PWG2W_NAME, 'description' => __("Adds a picture to your sidebar.", 'pwg2w')];
		WP_Widget::__construct(mb_strtolower(PWG2W_NAME), PWG2W_NAME, $options);
	}

	function widget($args, $gallery) {
		if (empty($gallery['url'])) return;
		extract($args);
		echo $before_widget . PHP_EOL;

		$piwigo_url = $gallery['url'];
		if (!str_ends_with($piwigo_url, '/')) $piwigo_url .= '/';

		$title = apply_filters('widget_title', empty($gallery['title']) ? '&nbsp;' : $gallery['title']);
		if ($title !== '') $title = $before_title . $title . $after_title;
		echo $title . PHP_EOL;

		$image_size = empty($gallery['image_size']) ? 'xl' : $gallery['image_size'];
		$number = empty($gallery['number']) ? 1 : intval($gallery['number']);

		$callstr = $piwigo_url . 'ws.php?method=pwg.categories.getImages&format=json&order=random&per_page=' . $number;
		$response = wp_remote_get($callstr);
		if (!is_wp_error($response)) {
			$thumbc = json_decode($response['body'], true);
			if (isset($thumbc["result"]["images"])) {
				foreach ($thumbc["result"]["images"] as $picture) {
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

					$comment = isset($picture['comment']) ? wp_strip_all_tags($picture['comment']) : '';
					$name = isset($picture['name']) ? wp_strip_all_tags($picture['name']) . ' – ' . $comment : '';

					echo '<a title="' . $name . '" href="' . $piwigo_url . 'picture.php?/' . $picture['id'] . '">
						<img class="PWG2W_thumb" src="' . $picture['image_url'] . '" alt="' . $name . '"/>' . PHP_EOL;

					if ($name !== '') echo '<span class="PWG2W_caption">' . $name . '</span>' . PHP_EOL;
					echo '</a>' . PHP_EOL;
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
		$gallery = wp_parse_args((array) $gallery, ['title' =>__('Random picture', 'pwg2w'),
			'image_size' => 'xl', 'url' => '', 'number' => 1]
		);

		echo '<div class="PWG2W_widget">
	<table>
		<tr>
			<td>
				<fieldset class="edge">
					<legend><span> ' . __('Gallery', 'pwg2w') . ' </span></legend>
					<label>' . __('Title', 'pwg2w') . '<input type="text" name="' . $this->get_field_name('title') .
						'" value="' . esc_attr($gallery['title']) . '"/></label>
					<label>' . __('Gallery URL', 'pwg2w') . '<input type="text" name="' . $this->get_field_name('url') .
						'" value="' . esc_attr($gallery['url']) . '"/></label>
					<label>' . __('Number of pictures', 'pwg2w') . '<input type="text" name="' . $this->get_field_name('number') .
						'" value="' . esc_attr($gallery['number']) . '"/></label>
				</fieldset>
			</td>
			<td>
				<fieldset class="right edge">
					<legend><span> ' . __('Size', 'pwg2w') . ' </span></legend>';

		$image_size = esc_attr($gallery['image_size']);
		$image_size_field_name = $this->get_field_name('image_size');
		$sizes = ['sq', 'th', '2s', 'xs', 'sm', 'me', 'la', 'xl', 'xx'];
		$strings = ['Square', 'Thumbnail', 'XXS - tiny', 'XS - extra small', 'S - small',
			'M - medium', 'L - large', 'XL - extra large', 'XXL - huge'];

		foreach ($sizes as $i => $size) echo '
					<label>' . __($strings[$i], 'pwg2w') . '<input type="radio" value="' . $sizes[$i] .'" name="' .
						$image_size_field_name .'" ' . checked($image_size, $sizes[$i], false) . '></label><br>';

		echo '				</fieldset>
			</td>
		</tr>
	</table>
</div>';
	}
}

function pwg2w_init(): void {
	register_widget('pwg2w');
}
add_action('widgets_init', 'pwg2w_init');

function pwg2w_load_in_head(): void {
	echo '<link media="all" type="text/css" href="' .
		plugins_url('pwg2w/pwg2w.css?ver=') . PWG2W_VERSION . '" id="pwg2w-css" rel="stylesheet">';
}
add_action('wp_head', 'pwg2w_load_in_head');

function pwg2w_register_plugin(): void {
	if (current_user_can('edit_posts') || current_user_can('edit_pages')) {
		add_action('admin_head', 'pwg2w_load_in_head');
	}
}
add_action('init', 'pwg2w_register_plugin');

function pwg2w_plugin_links(array $links, string $file): array {
	if ($file === plugin_basename(__FILE__)) {
		return array_merge($links, ['<a href="https://piwigo.org/">' . __('Piwigo') . '</a>']);
	}
	return $links;
}
add_filter('plugin_row_meta', 'pwg2w_plugin_links', 10, 2);
