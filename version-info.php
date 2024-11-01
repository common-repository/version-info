<?php
/**
 * Plugin Name: Version Info
 * Plugin URI: https://wordpress.org/plugins/version-info
 * Description: Show current WordPress, PHP, Web Server, and MySQL versions optionally in the admin footer, WP-Admin bar, or dashboard widget.
 * Author: Gaucho Plugins
 * Author URI: https://gauchoplugins.com
 * Version: 1.3.2
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: version-info
 */

namespace GauchoPlugins\VersionInfo;

use wpdb;

class VersionInfo {
    private $db;

    /**
     * Constructor to initialize the plugin.
     */
    public function __construct(wpdb $wpdb) {
        $this->db = $wpdb;
        add_action('plugins_loaded', [$this, 'load_text_domain']);
        add_filter('update_footer', [$this, 'version_in_footer'], 11);
        add_action('admin_bar_menu', [$this, 'add_version_info_to_admin_bar'], 100);
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('wp_dashboard_setup', [$this, 'conditionally_add_dashboard_widget']);
    }

    /**
     * Load the plugin's text domain for translation.
     */
    public function load_text_domain() {
        load_plugin_textdomain('version-info', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    /**
     * Add version info to the WP-Admin bar (admin-only, controlled by settings).
     */
    public function add_version_info_to_admin_bar($wp_admin_bar) {
        if (!get_option('version_info_show_admin_bar', false) || !current_user_can('administrator')) {
            return; 
        }

        $wp_admin_bar->add_node([
            'id' => 'version_info_admin_bar',
            'title' => $this->get_admin_bar_version_details(),
            'parent' => 'top-secondary',
        ]);
    }

    /**
     * Display version info in the admin footer (admin-only, controlled by settings).
     */
    public function version_in_footer() {
        if (!get_option('version_info_show_footer', true) || !current_user_can('administrator')) {
            return ''; 
        }
        return $this->get_footer_version_details();
    }

    /**
     * Retrieve version details for display in the WP-Admin bar (without update link).
     */
    private function get_admin_bar_version_details() {
        $wp_version = get_bloginfo('version');
        $server_software = sanitize_text_field($_SERVER['SERVER_SOFTWARE'] ?? __('Unknown', 'version-info'));
        $mysql_version = $this->db->get_var('SELECT VERSION()');
        if (is_wp_error($mysql_version)) {
            $mysql_version = __('Error fetching version', 'version-info');
        }

        return sprintf(
            __('WordPress %s | PHP %s | Web Server %s | MySQL %s', 'version-info'),
            esc_html($wp_version),
            esc_html(phpversion()),
            esc_html($server_software),
            esc_html($mysql_version)
        );
    }

    /**
     * Retrieve version details for display in the footer (with update link if available).
     */
    private function get_footer_version_details() {
        // Get the current WordPress version.
        $wp_version = get_bloginfo('version');
        $update_message = '';

        // Check if there are any WordPress core updates available.
        $updates = get_core_updates();
        if (!empty($updates) && !is_wp_error($updates)) {
            foreach ($updates as $update) {
                // If there's an update and it's a new version, add the update message.
                if (version_compare($wp_version, $update->version, '<')) {
                    $update_message = sprintf(
                        ' (<a href="%s">%s %s</a>)',
                        esc_url(admin_url('update-core.php')),
                        __('Get Version', 'version-info'),
                        esc_html($update->version)
                    );
                    break; // We only need to display the first update found.
                }
            }
        }

        // Fetch the server and MySQL version details.
        $server_software = sanitize_text_field($_SERVER['SERVER_SOFTWARE'] ?? __('Unknown', 'version-info'));
        $mysql_version = $this->db->get_var('SELECT VERSION()');
        if (is_wp_error($mysql_version)) {
            $mysql_version = __('Error fetching version', 'version-info');
        }

        // Combine the details into the final version string.
        return sprintf(
            __('WordPress %s%s | PHP %s | Web Server %s | MySQL %s', 'version-info'),
            esc_html($wp_version),
            $update_message,  // Add the update message if available.
            esc_html(phpversion()),
            esc_html($server_software),
            esc_html($mysql_version)
        );
    }

    /**
     * Add a settings page under Settings > Version Info.
     */
    public function add_settings_page() {
        add_options_page(
            __('Version Info Settings', 'version-info'),
            __('Version Info', 'version-info'),
            'manage_options',
            'version-info-settings',
            [$this, 'render_settings_page']
        );
    }

    /**
     * Register plugin settings with validation.
     */
    public function register_settings() {
        register_setting('version_info_settings_group', 'version_info_show_footer', [
            'sanitize_callback' => [$this, 'validate_boolean_option'],
        ]);
        register_setting('version_info_settings_group', 'version_info_show_admin_bar', [
            'sanitize_callback' => [$this, 'validate_boolean_option'],
        ]);
        register_setting('version_info_settings_group', 'version_info_show_dashboard_widget', [
            'sanitize_callback' => [$this, 'validate_boolean_option'],
            'default' => 0,  // Set widget disabled by default
        ]);
    }

    /**
     * Validate boolean options.
     */
    public function validate_boolean_option($input) {
        return filter_var($input, FILTER_VALIDATE_BOOLEAN); // Better boolean validation
    }

    /**
     * Conditionally add the dashboard widget based on the settings.
     */
    public function conditionally_add_dashboard_widget() {
        // Only register the widget if the setting is enabled
        if (get_option('version_info_show_dashboard_widget', false) && current_user_can('administrator')) {
            $this->add_dashboard_widget();
        }
    }

    /**
     * Add the dashboard widget (admin-only, controlled by settings).
     */
    public function add_dashboard_widget() {
        wp_add_dashboard_widget(
            'version_info_dashboard_widget',
            __('Version Info', 'version-info'),
            [$this, 'display_dashboard_widget']
        );
    }

    /**
     * Display content for the dashboard widget.
     */
    public function display_dashboard_widget() {
        global $wpdb;

        echo '<ul>';
        echo '<li><strong>' . esc_html__('WordPress Version:', 'version-info') . '</strong> ' . esc_html(get_bloginfo('version')) . '</li>';
        echo '<li><strong>' . esc_html__('PHP Version:', 'version-info') . '</strong> ' . esc_html(phpversion()) . '</li>';
        echo '<li><strong>' . esc_html__('Web Server:', 'version-info') . '</strong> ' . esc_html(sanitize_text_field($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown')) . '</li>';
        echo '<li><strong>' . esc_html__('MySQL Version:', 'version-info') . '</strong> ' . esc_html($wpdb->db_version()) . '</li>';
        echo '</ul>';
    }

    /**
     * Render the settings page with proper nonce verification for security.
     */
    public function render_settings_page() {
        // Handle POST request and verify the nonce.
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!isset($_POST['version_info_settings_nonce']) || 
                !wp_verify_nonce($_POST['version_info_settings_nonce'], 'version_info_settings_action')) {
                wp_die(__('Security check failed.', 'version-info')); // CSRF protection
            }
        }
        ?>
        <div class="wrap">
            <h1><?php _e('Version Info Settings', 'version-info'); ?></h1>
            <form method="post" action="options.php">
                <?php 
                // Output nonce, action, and option group fields for the settings form.
                settings_fields('version_info_settings_group'); 
                wp_nonce_field('version_info_settings_action', 'version_info_settings_nonce'); 
                do_settings_sections('version-info-settings'); 
                ?>
                <table class="form-table">
                    <tr>
                        <th><?php _e('Show Version Info in Admin Bar', 'version-info'); ?></th>
                        <td>
                            <input type="checkbox" name="version_info_show_admin_bar" value="1" 
                            <?php checked(1, get_option('version_info_show_admin_bar', false)); ?> />
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Show Version Info as Dashboard Widget', 'version-info'); ?></th>
                        <td>
                            <input type="checkbox" name="version_info_show_dashboard_widget" value="1" 
                            <?php checked(1, get_option('version_info_show_dashboard_widget', false)); ?> />
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Show Version Info in Footer', 'version-info'); ?></th>
                        <td>
                            <input type="checkbox" name="version_info_show_footer" value="1" 
                            <?php checked(1, get_option('version_info_show_footer', true)); ?> />
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}

// Initialize the plugin
global $wpdb;
new VersionInfo($wpdb);
