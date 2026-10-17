<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://islayanderson.co.uk
 * @since      1.0.0
 *
 * @package    Islays_Tools
 * @subpackage Islays_Tools/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Islays_Tools
 * @subpackage Islays_Tools/includes
 * @author     islay <me@islayanderson.co.uk>
 */
class Islays_Tools
{

    /**
     * The loader that's responsible for maintaining and registering all hooks that power
     * the plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      Islays_Tools_Loader $loader Maintains and registers all hooks for the plugin.
     */
    protected $loader;

    /**
     * The unique identifier of this plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string $plugin_name The string used to uniquely identify this plugin.
     */
    protected $plugin_name;

    /**
     * The current version of the plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string $version The current version of the plugin.
     */
    protected $version;

    /**
     * Define the core functionality of the plugin.
     *
     * Set the plugin name and the plugin version that can be used throughout the plugin.
     * Load the dependencies, define the locale, and set the hooks for the admin area and
     * the public-facing side of the site.
     *
     * @since    1.0.0
     */
    public function __construct()
    {
        if (defined('ISLAYS_TOOLS_VERSION')) {
            $this->version = ISLAYS_TOOLS_VERSION;
        } else {
            $this->version = '1.1.0';
        }
        $this->plugin_name = 'islays-tools';

        $this->load_dependencies();
        $this->set_locale();
        $this->define_admin_hooks();
        $this->define_public_hooks();
        $this->define_updater_hooks();

        $this->sort_by_size();
        $this->buffer_filter();
        $this->prerender_pages();
    }

    public function sort_by_size()
    {
        /* Add custom column to post list */
        function add_content_size_column($columns)
        {
            return array_merge($columns, array('content_size' => __('Size', 'your_text_domain')));
        }

        add_filter('manage_posts_columns', 'add_content_size_column');


        /* Display custom column */
        function display_posts_content_size($column, $post_id)
        {
            if ('content_size' == $column) {
                echo mb_strlen(trim(get_post_field('post_content', $post_id)));
            }
        }

        add_action('manage_posts_custom_column', 'display_posts_content_size', 10, 2);

        /* Set custom column as sortable */
        function set_content_size_column_as_sortable($columns)
        {
            $columns['content_size'] = 'content_size';

            return $columns;
        }

        add_filter('manage_edit-post_sortable_columns', 'set_content_size_column_as_sortable');


        function my_content_size_orderby($orderby)
        {
            global $wpdb;

            // Run this filter only once, when needed (it will be added by pre_get_posts)
            remove_filter('posts_orderby', 'my_content_size_orderby');

            $orderby = str_replace("{$wpdb->posts}.post_date ", "CHAR_LENGTH( TRIM({$wpdb->posts}.post_content) ) ", $orderby);

            return $orderby;
        }
    }

    public function buffer_filter()
    {

        function replace_deadname()
        {
            ob_start(str_replace("MrAnderson64", "IslayAnderson", $buffer));
            ob_end_flush();
        }

        add_action('template_redirect', 'replace_deadname');

        function prepend_variant()
        {
            $re = '/src\s*=\s*[""\']?([^\'"" >]+?)[ \'""]/';
            $subst = "src=\"$1/public\"";
            ob_start(str_replace($re, $subst, $buffer));
            ob_end_flush();
        }

        add_action('template_redirect', 'prepend_variant');

    }

    public function prerender_pages()
    {
        if (class_exists('Simply_Static')) {
            function ssp_run_static_export_cron()
            {

                // Full static export
                $simply_static = Simply_Static\Plugin::instance();
                $simply_static->run_static_export();


            }

            add_action('my_simply_static_cron_event', 'ssp_run_static_export_cron');
        }
    }


    /**
     * Load the required dependencies for this plugin.
     *
     * Include the following files that make up the plugin:
     *
     * - Islays_Tools_Loader. Orchestrates the hooks of the plugin.
     * - Islays_Tools_i18n. Defines internationalization functionality.
     * - Islays_Tools_Admin. Defines all hooks for the admin area.
     * - Islays_Tools_Public. Defines all hooks for the public side of the site.
     *
     * Create an instance of the loader which will be used to register the hooks
     * with WordPress.
     *
     * @since    1.0.0
     * @access   private
     */
    private function load_dependencies()
    {

        /**
         * The class responsible for orchestrating the actions and filters of the
         * core plugin.
         */
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-islays-tools-loader.php';

        /**
         * The class responsible for defining internationalization functionality
         * of the plugin.
         */
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-islays-tools-i18n.php';

        /**
         * The class responsible for defining all actions that occur in the admin area.
         */
        require_once plugin_dir_path(dirname(__FILE__)) . 'admin/class-islays-tools-admin.php';

        /**
         * The class responsible for defining all actions that occur in the public-facing
         * side of the site.
         */
        require_once plugin_dir_path(dirname(__FILE__)) . 'public/class-islays-tools-public.php';

        /**
         * The class responsible for pulling plugin updates from GitHub releases.
         */
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-islays-tools-updater.php';

        $this->loader = new Islays_Tools_Loader();

    }

    /**
     * Define the locale for this plugin for internationalization.
     *
     * Uses the Islays_Tools_i18n class in order to set the domain and to register the hook
     * with WordPress.
     *
     * @since    1.0.0
     * @access   private
     */
    private function set_locale()
    {

        $plugin_i18n = new Islays_Tools_i18n();

        $this->loader->add_action('plugins_loaded', $plugin_i18n, 'load_plugin_textdomain');

    }

    /**
     * Register all of the hooks related to the admin area functionality
     * of the plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_admin_hooks()
    {

        $plugin_admin = new Islays_Tools_Admin($this->get_plugin_name(), $this->get_version());

        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');

    }

    /**
     * Register all of the hooks related to the public-facing functionality
     * of the plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_public_hooks()
    {

        $plugin_public = new Islays_Tools_Public($this->get_plugin_name(), $this->get_version());

        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_styles');
        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_scripts');

    }

    /**
     * Register the hooks that let WordPress update the plugin from GitHub releases.
     *
     * These run outside the admin too, since update checks happen in wp-cron.
     *
     * @since    1.0.1
     * @access   private
     */
    private function define_updater_hooks()
    {

        if (!defined('ISLAYS_TOOLS_FILE') || !defined('ISLAYS_TOOLS_GITHUB_REPO')) {
            return;
        }

        $updater = new Islays_Tools_Updater(
            ISLAYS_TOOLS_FILE,
            ISLAYS_TOOLS_GITHUB_REPO,
            $this->get_version(),
            defined('ISLAYS_TOOLS_GITHUB_TOKEN') ? ISLAYS_TOOLS_GITHUB_TOKEN : ''
        );

        $this->loader->add_filter('pre_set_site_transient_update_plugins', $updater, 'check_for_update');
        $this->loader->add_filter('plugins_api', $updater, 'plugin_info', 20, 3);
        $this->loader->add_filter('upgrader_source_selection', $updater, 'fix_source_dir', 10, 4);
        $this->loader->add_filter('http_request_args', $updater, 'authorize_download', 10, 2);
        $this->loader->add_action('upgrader_process_complete', $updater, 'purge_cache', 10, 2);

    }

    /**
     * Run the loader to execute all of the hooks with WordPress.
     *
     * @since    1.0.0
     */
    public function run()
    {
        $this->loader->run();
    }

    /**
     * The name of the plugin used to uniquely identify it within the context of
     * WordPress and to define internationalization functionality.
     *
     * @return    string    The name of the plugin.
     * @since     1.0.0
     */
    public function get_plugin_name()
    {
        return $this->plugin_name;
    }

    /**
     * The reference to the class that orchestrates the hooks with the plugin.
     *
     * @return    Islays_Tools_Loader    Orchestrates the hooks of the plugin.
     * @since     1.0.0
     */
    public function get_loader()
    {
        return $this->loader;
    }

    /**
     * Retrieve the version number of the plugin.
     *
     * @return    string    The version number of the plugin.
     * @since     1.0.0
     */
    public function get_version()
    {
        return $this->version;
    }

}
