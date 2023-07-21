<?php

use ImportWP\Common\Filesystem\Filesystem;
use ImportWP\Common\Importer\ImporterManager;
use ImportWP\Common\Model\ImporterModel;
use ImportWP\Container;

if (!defined('ABSPATH')) exit; // Exit if accessed directly

function iwp_multiple_files_has_available_files($source, $raw_source)
{

    $files = glob($source);
    $exclude_files = array('.', '..', $raw_source);

    $files = array_filter($files, function ($item) use ($exclude_files) {
        return !in_array($item, $exclude_files);
    });

    return $files;
}

function iwp_multiple_files_get_config($file_path)
{

    if (!file_exists($file_path)) {
        return false;
    }

    $config = require $file_path;

    if (!isset($config['pattern'])) {
        return false;
    }

    return $config;
}

/**
 * Check for most recent files in folder
 * 
 * @param string $source
 * @param string $raw_source
 * @param ImporterModel $importer_model
 * @return string 
 */
function iwp_multiple_files_get_next_file($source, $raw_source, $importer_model)
{
    // FIX: On first attach the importer does not have a parser and cant check the extension
    if (preg_match('/datafeed\.[a-z]{3}\.php/', basename($raw_source)) !== 1) {
        return $source;
    }

    $config = iwp_multiple_files_get_config($raw_source);
    if (!$config) {
        return $source;
    }

    $files = iwp_multiple_files_has_available_files(trailingslashit(dirname($source)) . $config['pattern'], $raw_source);

    if (!empty($files)) {

        $order = isset($config['order']) ? $config['order'] : 'ASC';
        $order_by = isset($config['order_by']) ? $config['order_by'] : 'filemtime';

        switch ($order_by) {
            case 'filemtime':

                // Sort files by modified time, latest to earliest
                // Use SORT_ASC in place of SORT_DESC for earliest to latest
                array_multisort(
                    array_map('filemtime', $files),
                    SORT_NUMERIC,
                    $order == 'ASC' ? SORT_ASC : SORT_DESC,
                    $files
                );

                break;
            default:

                $files = apply_filters('iwp/multiple_files/order_files', $files, $order, $order_by, $config);
                break;
        }

        update_post_meta($importer_model->getId(), '_import_file_mf', $files[0]);

        return $files[0];
    }

    return false;
}

add_filter('iwp/importer/datasource/local', 'iwp_multiple_files_get_next_file', 10, 3);

/**
 * After import move files to processed folder
 * 
 * @param ImportWP\Common\Model\ImporterModel $importer_model
 * @return ImportWP\Common\Model\ImporterModel 
 */
function iwp_multiple_files_move_to_processed($importer_model)
{
    $state = ImportWP\Common\Importer\State\ImporterState::get_state($importer_model->getId());
    if ($state['status'] != 'complete') {
        return $importer_model;
    }

    switch ($importer_model->getDatasource()) {
        case 'local':

            $local_url = $importer_model->getDatasourceSetting('local_url');

            if (basename($local_url) !== 'datafeed.' . $importer_model->getParser() . '.php') {
                return $importer_model;
            }

            $config = iwp_multiple_files_get_config($local_url);
            if (!$config) {
                return $importer_model;
            }

            $dir = trailingslashit(dirname($local_url));
            $dir .= trailingslashit($config['destination']);
            if (!file_exists($dir) && !mkdir($dir)) {
                return $importer_model;
            }

            /**
             * @var Filesystem $filesystem
             */
            $filesystem = Container::getInstance()->get('filesystem');

            $file_path = get_post_meta($importer_model->getId(), '_import_file_mf', true);
            if (file_exists($file_path) && $filesystem->copy($file_path, $dir . basename($file_path))) {
                @unlink($file_path);
            }

            delete_post_meta($importer_model->getId(), '_import_file_mf', $file_path);

            do_action('iwp/multiple_files/after_file_processed', $file_path, $dir, $importer_model);
            break;
    }

    return $importer_model;
}

add_action('iwp/register_events', function ($event_handler) {

    /**
     * @var EventHandler $event_handler
     */
    $event_handler->listen('importer_manager.import_shutdown', 'iwp_multiple_files_move_to_processed');
});

/**
 * Add new wp cli command to process a queue
 */
add_action('plugins_loaded', function () {
    // register with wp-cli if it's running, and command hasn't already been defined elsewhere
    if (defined('WP_CLI') && \WP_CLI) {
        $command = function ($args, $assoc_args) {

            $importer_id = $args[0];

            $assoc_args['action'] = 'importwp';

            /**
             * @var ImporterManager $importer_manager
             */
            $importer_manager = Container::getInstance()->get('importer_manager');
            $importer_model = $importer_manager->get_importer($importer_id);

            if (!$importer_model) {
                \WP_CLI::error('Invalid importer');
                return;
            }

            if ($importer_model->getDatasource() !== 'local') {
                \WP_CLI::error('Importer does not have a local file source');
                return;
            }

            do {

                $source = $raw_source = $importer_model->getDatasourceSetting('local_url');

                $config = iwp_multiple_files_get_config($raw_source);
                if (!$config) {
                    return $source;
                }

                $files = iwp_multiple_files_has_available_files(trailingslashit(dirname($source)) . $config['pattern'], $raw_source);

                $old_file_count = count($files);

                if (empty($files)) {
                    \WP_CLI::log("All files imported");
                } else {

                    \WP_CLI::runcommand('importwp import ' . $importer_model->getId() . ' --start');

                    $files = iwp_multiple_files_has_available_files(trailingslashit(dirname($source)) . $config['pattern'], $raw_source);
                    \WP_CLI::log("Files left: " . count($files));
                }
            } while (!empty($files) && $old_file_count != count($files));
        };

        \WP_CLI::add_command('importwp-process', $command);
    }
}, 20);
