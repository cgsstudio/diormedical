<?php

if (!defined('ABSPATH')) die('Access denied.');

if (!class_exists('Updraft_Task_1_2')) require_once(WPO_PLUGIN_MAIN_PATH . 'vendor/team-updraft/common-libs/src/updraft-tasks/class-updraft-task.php');

if (!class_exists('WPO_Webp_Convert_Image_Task')) :

class WPO_Webp_Convert_Image_Task extends Updraft_Task_1_2 {

	/**
	 * Meta key used to mark attachments as WebP-converted.
	 */
	const WEBP_CONVERSION_META_KEY = '_wpo-webp-conversion-complete';

	/**
	 * Returns the default task options.
	 *
	 * @return array<mixed>
	 */
	public function get_default_options(): array {
		return array();
	}

	/**
	 * Runs WebP conversion for a compressed attachment's images.
	 *
	 * Converts all registered image sizes (including the original) to WebP,
	 * then marks the attachment as converted when the original succeeds.
	 *
	 * @return bool
	 */
	public function run(): bool {
		$options = $this->get_task_options();
		if (!$this->is_valid_task_options($options)) {
			return false;
		}

		$this->switch_to_blog($options['blog_id']);

		$images = $this->get_all_attachment_images($options['attachment_id'], $options['source']);
		$this->convert_images($images);

		if ($this->webp_file_exists($options['source'])) {
			update_post_meta($options['attachment_id'], self::WEBP_CONVERSION_META_KEY, true);
		}

		$this->restore_blog();

		return true;
	}

	/**
	 * Extracts and normalizes task options into typed values.
	 *
	 * @return array{blog_id: int, attachment_id: int, source: string}
	 */
	private function get_task_options(): array {
		$blog_id = $this->get_option('blog_id');
		$attachment_id = $this->get_option('attachment_id');
		$source = $this->get_option('attachment_source');

		return array(
			'blog_id' => is_numeric($blog_id) ? (int) $blog_id : 0,
			'attachment_id' => is_numeric($attachment_id) ? (int) $attachment_id : 0,
			'source' => is_string($source) ? $source : '',
		);
	}

	/**
	 * Checks whether all task options contain valid, non-empty values.
	 *
	 * @param array{blog_id: int, attachment_id: int, source: string} $options
	 *
	 * @return bool
	 */
	private function is_valid_task_options(array $options): bool {
		return !empty($options['blog_id'])
			&& !empty($options['attachment_id'])
			&& !empty($options['source']);
	}

	/**
	 * Switches to the target blog on multisite installations.
	 *
	 * @param int $blog_id The blog ID to switch to
	 *
	 * @return void
	 */
	private function switch_to_blog(int $blog_id): void {
		if (is_multisite()) {
			switch_to_blog($blog_id);
		}
	}

	/**
	 * Restores the previous blog on multisite installations.
	 *
	 * @return void
	 */
	private function restore_blog(): void {
		if (is_multisite()) {
			restore_current_blog();
		}
	}

	/**
	 * Collects all image file paths for an attachment, including the original.
	 *
	 * @param int    $attachment_id The attachment post ID.
	 * @param string $source        The path to the original attachment file.
	 *
	 * @return array<string, string> Associative array of image size => file path.
	 */
	private function get_all_attachment_images(int $attachment_id, string $source): array {
		$images = WPO_Image_Utils::get_attachment_files($attachment_id);
		$images['original'] = $source;

		return $images;
	}

	/**
	 * Converts each image in the list to WebP format.
	 *
	 * @param array<string, string> $images Associative array of image size => file path.
	 *
	 * @return void
	 */
	private function convert_images(array $images): void {
		foreach ($images as $image) {
			WPO_WebP_Utils::do_webp_conversion($image);
		}
	}

	/**
	 * Checks whether a WebP version of the source file exists on disk.
	 *
	 * @param string $source The original image file path.
	 *
	 * @return bool
	 */
	private function webp_file_exists(string $source): bool {
		return file_exists(WPO_WebP_Utils::get_destination_path($source));
	}
}
endif;
