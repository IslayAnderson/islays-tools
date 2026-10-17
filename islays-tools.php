<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://islayanderson.co.uk
 * @since             1.0.0
 * @package           Islays_Tools
 *
 * @wordpress-plugin
 * Plugin Name:       Islays tools
 * Plugin URI:        https://islayanderson.co.uk
 * Description:       Collection of random wp functions
 * Version:           1.0.1
 * Update URI:        https://github.com/IslayAnderson/islays-tools
 * Author:            islay
 * Author URI:        https://islayanderson.co.uk/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       islays-tools
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define('ISLAYS_TOOLS_VERSION', '1.0.1');

/**
 * Path to this file, used by the GitHub release updater.
 */
define('ISLAYS_TOOLS_FILE', __FILE__);

/**
 * GitHub repository that releases are pulled from ("owner/name").
 * Define ISLAYS_TOOLS_GITHUB_TOKEN in wp-config.php if the repository is private.
 */
if (!defined('ISLAYS_TOOLS_GITHUB_REPO')) {
    define('ISLAYS_TOOLS_GITHUB_REPO', 'IslayAnderson/islays-tools');
}

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-islays-tools-activator.php
 */
function activate_islays_tools()
{
    require_once plugin_dir_path(__FILE__) . 'includes/class-islays-tools-activator.php';
    Islays_Tools_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-islays-tools-deactivator.php
 */
function deactivate_islays_tools()
{
    require_once plugin_dir_path(__FILE__) . 'includes/class-islays-tools-deactivator.php';
    Islays_Tools_Deactivator::deactivate();
}

register_activation_hook(__FILE__, 'activate_islays_tools');
register_deactivation_hook(__FILE__, 'deactivate_islays_tools');

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path(__FILE__) . 'includes/class-islays-tools.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_islays_tools()
{

    $plugin = new Islays_Tools();
    $plugin->run();

}

run_islays_tools();
