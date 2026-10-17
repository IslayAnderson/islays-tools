<?php

/**
 * Self-updater backed by GitHub releases.
 *
 * @link       https://islayanderson.co.uk
 * @since      1.0.1
 *
 * @package    Islays_Tools
 * @subpackage Islays_Tools/includes
 */

/**
 * Checks the plugin's GitHub repository for new releases and feeds them into
 * the standard WordPress plugin update flow.
 *
 * A release is picked up when its tag (e.g. "v1.2.0" or "1.2.0") is a higher
 * version than the installed one. The package is the release asset named
 * "islays-tools.zip" if present, otherwise GitHub's source zipball.
 *
 * Private repositories are supported by defining ISLAYS_TOOLS_GITHUB_TOKEN
 * in wp-config.php.
 *
 * @since      1.0.1
 * @package    Islays_Tools
 * @subpackage Islays_Tools/includes
 * @author     islay <me@islayanderson.co.uk>
 */
class Islays_Tools_Updater
{

    /**
     * How long a release lookup is cached for.
     */
    const CACHE_TTL = 6 * HOUR_IN_SECONDS;

    /**
     * The name of the release asset to install, if attached.
     */
    const ASSET_NAME = 'islays-tools.zip';

    /**
     * @var string Absolute path to the main plugin file.
     */
    private $plugin_file;

    /**
     * @var string Plugin basename, e.g. "islays-tools/islays-tools.php".
     */
    private $basename;

    /**
     * @var string Plugin slug (directory name).
     */
    private $slug;

    /**
     * @var string GitHub repository in "owner/name" form.
     */
    private $repo;

    /**
     * @var string The installed plugin version.
     */
    private $version;

    /**
     * @var string Optional GitHub token for private repositories.
     */
    private $token;

    /**
     * @var string Transient key for the cached release.
     */
    private $cache_key;

    /**
     * @param string $plugin_file Absolute path to the main plugin file.
     * @param string $repo        GitHub repository in "owner/name" form.
     * @param string $version     The installed plugin version.
     * @param string $token       Optional GitHub token.
     * @since    1.0.1
     */
    public function __construct($plugin_file, $repo, $version, $token = '')
    {
        $this->plugin_file = $plugin_file;
        $this->basename = plugin_basename($plugin_file);
        $this->slug = dirname($this->basename);
        $this->repo = $repo;
        $this->version = $version;
        $this->token = $token;
        $this->cache_key = 'islays_tools_gh_release_' . md5($repo);
    }

    /**
     * Inject an update into the update_plugins transient when a newer release exists.
     *
     * @param object $transient The update_plugins transient.
     * @return object
     * @since    1.0.1
     */
    public function check_for_update($transient)
    {
        if (empty($transient->checked)) {
            return $transient;
        }

        $release = $this->get_latest_release();
        if (!$release) {
            return $transient;
        }

        $item = (object)array(
            'id' => $this->basename,
            'slug' => $this->slug,
            'plugin' => $this->basename,
            'new_version' => $release['version'],
            'url' => $release['html_url'],
            'package' => $release['package'],
            'icons' => array(),
            'banners' => array(),
            'tested' => '',
            'requires_php' => '',
        );

        if (version_compare($release['version'], $this->version, '>')) {
            $transient->response[$this->basename] = $item;
        } else {
            $transient->no_update[$this->basename] = $item;
        }

        return $transient;
    }

    /**
     * Provide the "View details" modal content for this plugin.
     *
     * @param false|object|array $result
     * @param string $action
     * @param object $args
     * @return false|object|array
     * @since    1.0.1
     */
    public function plugin_info($result, $action, $args)
    {
        if ('plugin_information' !== $action || empty($args->slug) || $this->slug !== $args->slug) {
            return $result;
        }

        $release = $this->get_latest_release();
        if (!$release) {
            return $result;
        }

        $plugin = get_plugin_data($this->plugin_file, false, false);

        return (object)array(
            'name' => $plugin['Name'],
            'slug' => $this->slug,
            'version' => $release['version'],
            'author' => $plugin['Author'],
            'homepage' => 'https://github.com/' . $this->repo,
            'download_link' => $release['package'],
            'last_updated' => $release['published_at'],
            'sections' => array(
                'description' => $plugin['Description'],
                'changelog' => $this->format_notes($release['body']),
            ),
        );
    }

    /**
     * Rename the extracted folder to the plugin slug.
     *
     * GitHub zipballs extract to "owner-repo-<sha>", which would install the
     * plugin into the wrong directory and deactivate it.
     *
     * @param string $source        Path to the extracted package.
     * @param string $remote_source Path to the working directory.
     * @param WP_Upgrader $upgrader
     * @param array $hook_extra
     * @return string|WP_Error
     * @since    1.0.1
     */
    public function fix_source_dir($source, $remote_source, $upgrader, $hook_extra = array())
    {
        global $wp_filesystem;

        if (empty($hook_extra['plugin']) || $this->basename !== $hook_extra['plugin']) {
            return $source;
        }

        $desired = trailingslashit($remote_source) . $this->slug . '/';
        if (trailingslashit($source) === $desired) {
            return $source;
        }

        if (!$wp_filesystem->move($source, $desired, true)) {
            return new WP_Error('islays_tools_rename_failed', __('Could not rename the update folder.', 'islays-tools'));
        }

        return $desired;
    }

    /**
     * Add auth headers when downloading a private release package.
     *
     * @param array $args
     * @param string $url
     * @return array
     * @since    1.0.1
     */
    public function authorize_download($args, $url)
    {
        if (!$this->token || 0 !== strpos($url, 'https://api.github.com/repos/' . $this->repo . '/')) {
            return $args;
        }

        $args['headers']['Authorization'] = 'Bearer ' . $this->token;
        if (false !== strpos($url, '/releases/assets/')) {
            $args['headers']['Accept'] = 'application/octet-stream';
        }

        return $args;
    }

    /**
     * Drop the cached release after this plugin is updated.
     *
     * @param WP_Upgrader $upgrader
     * @param array $options
     * @since    1.0.1
     */
    public function purge_cache($upgrader, $options)
    {
        if ('update' === $options['action'] && 'plugin' === $options['type']) {
            delete_transient($this->cache_key);
        }
    }

    /**
     * Fetch (and cache) the latest published release.
     *
     * @return array|null Normalised release data, or null on failure.
     * @since    1.0.1
     */
    private function get_latest_release()
    {
        $cached = get_transient($this->cache_key);
        if (false !== $cached) {
            return $cached ?: null;
        }

        $headers = array(
            'Accept' => 'application/vnd.github+json',
            'User-Agent' => 'islays-tools/' . $this->version,
        );
        if ($this->token) {
            $headers['Authorization'] = 'Bearer ' . $this->token;
        }

        $response = wp_remote_get(
            'https://api.github.com/repos/' . $this->repo . '/releases/latest',
            array('headers' => $headers, 'timeout' => 10)
        );

        $release = null;
        if (!is_wp_error($response) && 200 === wp_remote_retrieve_response_code($response)) {
            $data = json_decode(wp_remote_retrieve_body($response), true);
            if (is_array($data) && !empty($data['tag_name'])) {
                $release = array(
                    'version' => ltrim($data['tag_name'], 'vV'),
                    'html_url' => $data['html_url'],
                    'body' => isset($data['body']) ? $data['body'] : '',
                    'published_at' => isset($data['published_at']) ? $data['published_at'] : '',
                    'package' => $this->find_package($data),
                );
            }
        }

        // Cache failures too (as an empty string) so a GitHub outage or rate
        // limit doesn't trigger a request on every admin page load.
        set_transient($this->cache_key, $release ?: '', $release ? self::CACHE_TTL : HOUR_IN_SECONDS);

        return $release;
    }

    /**
     * Pick the download URL for a release.
     *
     * @param array $data Raw release data from the GitHub API.
     * @return string
     * @since    1.0.1
     */
    private function find_package($data)
    {
        if (!empty($data['assets'])) {
            foreach ($data['assets'] as $asset) {
                if (self::ASSET_NAME === $asset['name']) {
                    // Private repos must download assets through the API URL.
                    return $this->token ? $asset['url'] : $asset['browser_download_url'];
                }
            }
        }

        return $data['zipball_url'];
    }

    /**
     * Turn markdown-ish release notes into simple HTML for the details modal.
     *
     * @param string $body
     * @return string
     * @since    1.0.1
     */
    private function format_notes($body)
    {
        if ('' === trim($body)) {
            return '<p>' . esc_html__('No release notes.', 'islays-tools') . '</p>';
        }

        $html = '';
        $in_list = false;
        foreach (preg_split('/\r\n|\r|\n/', $body) as $line) {
            $line = trim($line);
            if (preg_match('/^[-*]\s+(.*)$/', $line, $m)) {
                if (!$in_list) {
                    $html .= '<ul>';
                    $in_list = true;
                }
                $html .= '<li>' . esc_html($m[1]) . '</li>';
                continue;
            }
            if ($in_list) {
                $html .= '</ul>';
                $in_list = false;
            }
            if (preg_match('/^#{1,6}\s+(.*)$/', $line, $m)) {
                $html .= '<h4>' . esc_html($m[1]) . '</h4>';
            } elseif ('' !== $line) {
                $html .= '<p>' . esc_html($line) . '</p>';
            }
        }
        if ($in_list) {
            $html .= '</ul>';
        }

        return $html;
    }

}
