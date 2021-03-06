<?php

// Copyright (c) 2016 Interfacelab LLC. All rights reserved.
//
// Released under the GPLv3 license
// http://www.gnu.org/licenses/gpl-3.0.html
//
// **********************************************************************
// This program is distributed in the hope that it will be useful, but
// WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
// **********************************************************************


namespace ILAB\MediaCloud\Tasks;

use ILAB\MediaCloud\Tools\ToolsManager;
use ILAB\MediaCloud\Utilities\Logger;

if (!defined( 'ABSPATH')) { header( 'Location: /'); die; }

/**
 * Class ILABRekognizerProcess
 *
 * Background processing job for processing existing media with AWS Rekognizer
 */
class RekognizerProcess extends BackgroundProcess {
	protected $action = 'ilab_rekognizer_import_process';

	protected function shouldHandle() {
		$result = !get_option('ilab_rekognizer_should_cancel', false);
		return $result;
	}

	public function task($item) {
		Logger::info( 'Start Task', $item);
		if (!$this->shouldHandle()) {
			Logger::info( 'Task cancelled', $item);
			return false;
		}

		$index = $item['index'];
		$post_id = $item['post'];

		update_option('ilab_rekognizer_current', $index+1);
		$data = wp_get_attachment_metadata($post_id);

		if (empty($data)) {
			Logger::info( 'Missing metadata', $item);
			return false;
		}

		if (!isset($data['s3'])) {
			Logger::info( 'Missing s3 metadata', $item);
			return false;
		}

		$fileName = basename($data['file']);
		update_option('ilab_rekognizer_current_file', $fileName);


		$rekognizerTool = ToolsManager::instance()->tools['rekognition'];
		$data = $rekognizerTool->processImageMeta($post_id, $data);
		wp_update_attachment_metadata($post_id, $data);

		return false;
	}

	public function dispatch() {
		Logger::info( 'Task dispatch');
		parent::dispatch();
	}

	protected function complete() {
		Logger::info( 'Task complete');
		delete_option('ilab_rekognizer_status');
		delete_option('ilab_rekognizer_total_count');
		delete_option('ilab_rekognizer_current');
		delete_option('ilab_rekognizer_current_file');
		parent::complete();
	}

	public function cancel_process() {
		Logger::info( 'Cancel process');

		parent::cancel_process();

		delete_option('ilab_rekognizer_status');
		delete_option('ilab_rekognizer_total_count');
		delete_option('ilab_rekognizer_current');
		delete_option('ilab_rekognizer_current_file');
	}

	public static function cancelAll() {
		Logger::info( 'Cancel all processes');

		wp_clear_scheduled_hook('wp_ilab_rekognizer_import_process_cron');

		global $wpdb;

		$res = $wpdb->get_results("select * from {$wpdb->options} where option_name like 'wp_ilab_rekognizer_import_process_batch_%'");
		foreach($res as $batch) {
			Logger::info( "Deleting batch {$batch->option_name}");
			delete_option($batch->option_name);
		}

		delete_option('ilab_rekognizer_status');
		delete_option('ilab_rekognizer_total_count');
		delete_option('ilab_rekognizer_current');
		delete_option('ilab_rekognizer_current_file');

		Logger::info( "Current cron", get_option( 'cron', []));
		Logger::info( 'End cancel all processes');
	}
}
