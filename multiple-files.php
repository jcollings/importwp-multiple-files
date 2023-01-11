<?php

/**
 * Plugin Name: Import WP - Multiple Files Addon
 * Plugin URI: https://www.importwp.com
 * Description: Allow Import WP to import multiple Files.
 * Author: James Collings <james@jclabs.co.uk>
 * Version: 0.0.1 
 * Author URI: https://www.importwp.com
 * Network: True
 */

if (!defined('ABSPATH')) exit; // Exit if accessed directly

add_action('admin_init', 'iwp_multiple_files_check');

function iwp_multiple_files_requirements_met()
{
    return false === (is_admin() && current_user_can('activate_plugins') &&  (!function_exists('import_wp') || version_compare(IWP_VERSION, '2.6.2', '<')));
}

function iwp_multiple_files_check()
{
    if (!iwp_multiple_files_requirements_met()) {

        add_action('admin_notices', 'iwp_multiple_files_notice');

        deactivate_plugins(plugin_basename(__FILE__));

        if (isset($_GET['activate'])) {
            unset($_GET['activate']);
        }
    }
}

function iwp_multiple_files_setup()
{
    if (!iwp_multiple_files_requirements_met()) {
        return;
    }

    $base_path = dirname(__FILE__);

    require_once $base_path . '/setup.php';

    // Install updater
    if (file_exists($base_path . '/updater.php') && !class_exists('IWP_Updater')) {
        require_once $base_path . '/updater.php';
    }

    if (class_exists('IWP_Updater')) {
        $updater = new IWP_Updater(__FILE__, 'importwp-multiple-files');
        $updater->initialize();
    }
}
add_action('plugins_loaded', 'iwp_multiple_files_setup', 9);

function iwp_multiple_files_notice()
{
    echo '<div class="error">';
    echo '<p><strong>Import WP - multiple_files File Addon</strong> requires that you have <strong>Import WP v2.6.2 or newer</strong> installed.</p>';
    echo '</div>';
}
