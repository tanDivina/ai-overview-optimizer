<?php
/**
 * Plugin Name: AI Overview Optimizer
 * Plugin URI: https://github.com/divinavibecodes/ai-overview-optimizer
 * Description: Generate WordPress articles optimized for Google's AI overviews with structured data and schema markup
 * Version: 1.0.0
 * Author: Divina Vibecodes
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ai-overview-optimizer
 * Requires at least: 5.0
 * Tested up to: 6.8
 * Requires PHP: 7.4
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('AI_OVERVIEW_OPTIMIZER_VERSION', '1.0.0');
define('AI_OVERVIEW_OPTIMIZER_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AI_OVERVIEW_OPTIMIZER_PLUGIN_PATH', plugin_dir_path(__FILE__));

// Include required files
require_once AI_OVERVIEW_OPTIMIZER_PLUGIN_PATH . 'includes/class-content-generator.php';
require_once AI_OVERVIEW_OPTIMIZER_PLUGIN_PATH . 'includes/class-schema-generator.php';

/**
 * Main AI Overview Optimizer Plugin Class
 */
class AIOverviewOptimizer {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init_hooks();
    }

    private function init_hooks() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));

        // AJAX handlers
        add_action('wp_ajax_generate_overview_post', array($this, 'generate_overview_post'));
        add_action('wp_ajax_test_overview_api', array($this, 'test_api_connection'));

        // Schema output
        add_action('wp_head', array($this, 'output_schema_markup'));
    }

    public function add_admin_menu() {
        add_menu_page(
            'AI Overview Optimizer',
            'AI Overview Optimizer',
            'manage_options',
            'ai-overview-optimizer',
            array($this, 'render_admin_page'),
            'dashicons-search',
            25
        );
    }

    public function register_settings() {
        register_setting('ai_overview_optimizer_settings', 'ai_overview_provider');
        register_setting('ai_overview_optimizer_settings', 'ai_overview_gemini_key');
        register_setting('ai_overview_optimizer_settings', 'ai_overview_openai_key');
        register_setting('ai_overview_optimizer_settings', 'ai_overview_post_status');
        register_setting('ai_overview_optimizer_settings', 'ai_overview_content_type');
        register_setting('ai_overview_optimizer_settings', 'ai_overview_category');
        register_setting('ai_overview_optimizer_settings', 'ai_overview_author_name');
        register_setting('ai_overview_optimizer_settings', 'ai_overview_schema_types');
    }

    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'ai-overview-optimizer') === false) {
            return;
        }

        wp_enqueue_script('jquery');
        wp_localize_script('jquery', 'aiOverviewOptimizer', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ai_overview_optimizer_nonce')
        ));
    }

    public function render_admin_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        $current_provider = get_option('ai_overview_provider', 'gemini');
        ?>
        <div class="wrap">
            <h1>AI Overview Optimizer</h1>

            <div class="notice notice-info">
                <p><strong>About:</strong> This plugin generates content specifically optimized for Google's AI overviews, including structured data markup for rich snippets.</p>
            </div>

            <div class="notice notice-success" style="display: none;" id="success-notice">
                <p id="success-message"></p>
            </div>

            <div class="notice notice-error" style="display: none;" id="error-notice">
                <p id="error-message"></p>
            </div>

            <form method="post" action="options.php">
                <?php settings_fields('ai_overview_optimizer_settings'); ?>

                <h2>AI Provider Settings</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">AI Provider</th>
                        <td>
                            <select name="ai_overview_provider" id="ai_provider_select">
                                <option value="gemini" <?php selected($current_provider, 'gemini'); ?>>Google Gemini</option>
                                <option value="openai" <?php selected($current_provider, 'openai'); ?>>OpenAI (ChatGPT)</option>
                            </select>
                        </td>
                    </tr>
                </table>

                <h3>API Keys</h3>
                <table class="form-table">
                    <tr class="api-key-row" data-provider="gemini">
                        <th scope="row">Gemini API Key</th>
                        <td>
                            <input type="password" name="ai_overview_gemini_key"
                                   value="<?php echo esc_attr(get_option('ai_overview_gemini_key', '')); ?>"
                                   class="regular-text api-key-input" />
                            <p class="description">Get your key from <a href="https://makersuite.google.com/app/apikey" target="_blank">Google AI Studio</a></p>
                        </td>
                    </tr>
                    <tr class="api-key-row" data-provider="openai">
                        <th scope="row">OpenAI API Key</th>
                        <td>
                            <input type="password" name="ai_overview_openai_key"
                                   value="<?php echo esc_attr(get_option('ai_overview_openai_key', '')); ?>"
                                   class="regular-text api-key-input" />
                            <p class="description">Get your key from <a href="https://platform.openai.com/api-keys" target="_blank">OpenAI Platform</a></p>
                        </td>
                    </tr>
                </table>

                <p>
                    <button type="button" id="test-api-btn" class="button button-secondary">Test API Connection</button>
                    <span id="test-result" style="margin-left: 10px;"></span>
                </p>

                <h2>Content Settings</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">Post Status</th>
                        <td>
                            <select name="ai_overview_post_status">
                                <option value="draft" <?php selected(get_option('ai_overview_post_status', 'draft'), 'draft'); ?>>Draft</option>
                                <option value="publish" <?php selected(get_option('ai_overview_post_status'), 'publish'); ?>>Publish</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Content Type</th>
                        <td>
                            <select name="ai_overview_content_type">
                                <option value="faq" <?php selected(get_option('ai_overview_content_type', 'faq'), 'faq'); ?>>FAQ Article</option>
                                <option value="howto" <?php selected(get_option('ai_overview_content_type'), 'howto'); ?>>How-To Guide</option>
                                <option value="comparison" <?php selected(get_option('ai_overview_content_type'), 'comparison'); ?>>Comparison Article</option>
                                <option value="listicle" <?php selected(get_option('ai_overview_content_type'), 'listicle'); ?>>Listicle</option>
                            </select>
                            <p class="description">Type of content optimized for AI overviews</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Default Category</th>
                        <td>
                            <?php
                            wp_dropdown_categories(array(
                                'name' => 'ai_overview_category',
                                'selected' => get_option('ai_overview_category', 1),
                                'show_option_none' => 'Select Category',
                                'option_none_value' => 1
                            ));
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Author Name</th>
                        <td>
                            <input type="text" name="ai_overview_author_name"
                                   value="<?php echo esc_attr(get_option('ai_overview_author_name', get_bloginfo('name'))); ?>"
                                   class="regular-text" />
                        </td>
                    </tr>
                </table>

                <h2>Schema Markup Settings</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">Schema Types</th>
                        <td>
                            <label><input type="checkbox" name="ai_overview_schema_types[]" value="faq" <?php checked(in_array('faq', get_option('ai_overview_schema_types', array('faq')))); ?> /> FAQ Schema</label><br>
                            <label><input type="checkbox" name="ai_overview_schema_types[]" value="howto" <?php checked(in_array('howto', get_option('ai_overview_schema_types', array('faq')))); ?> /> How-To Schema</label><br>
                            <label><input type="checkbox" name="ai_overview_schema_types[]" value="article" <?php checked(in_array('article', get_option('ai_overview_schema_types', array('faq')))); ?> /> Article Schema</label><br>
                            <label><input type="checkbox" name="ai_overview_schema_types[]" value="breadcrumb" <?php checked(in_array('breadcrumb', get_option('ai_overview_schema_types', array('faq')))); ?> /> Breadcrumb Schema</label>
                            <p class="description">Structured data types to include for better AI overview visibility</p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>

            <h2>Generate AI Overview Optimized Content</h2>
            <div class="postbox">
                <div class="inside">
                    <p>
                        <label for="overview-topic">Topic/Question:</label><br>
                        <input type="text" id="overview-topic" class="regular-text" placeholder="Enter a topic or question for AI overview optimization" />
                    </p>
                    <p>
                        <button type="button" id="generate-overview-btn" class="button button-primary">Generate Optimized Post</button>
                        <span id="generate-result" style="margin-left: 10px;"></span>
                    </p>
                </div>
            </div>

            <script>
            jQuery(document).ready(function($) {
                // Show/hide API key rows based on selected provider
                function toggleApiKeyRows() {
                    var selectedProvider = $('#ai_provider_select').val();
                    $('.api-key-row').hide();
                    $('.api-key-row[data-provider="' + selectedProvider + '"]').show();
                }

                $('#ai_provider_select').change(toggleApiKeyRows);
                toggleApiKeyRows();

                // Test API Connection
                $('#test-api-btn').click(function() {
                    var $btn = $(this);
                    var $result = $('#test-result');

                    $btn.prop('disabled', true).text('Testing...');
                    $result.html('<span style="color: #666;">Testing connection...</span>');

                    var provider = $('#ai_provider_select').val();
                    var apiKey = $('.api-key-row[data-provider="' + provider + '"] .api-key-input').val();

                    if (!apiKey) {
                        $result.html('<span style="color: red;">Please enter an API key first</span>');
                        $btn.prop('disabled', false).text('Test API Connection');
                        return;
                    }

                    $.ajax({
                        url: aiOverviewOptimizer.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'test_overview_api',
                            api_key: apiKey,
                            provider: provider,
                            nonce: aiOverviewOptimizer.nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                $result.html('<span style="color: green;">✓ Connection successful!</span>');
                            } else {
                                $result.html('<span style="color: red;">✗ ' + (response.data || 'Connection failed') + '</span>');
                            }
                        },
                        error: function() {
                            $result.html('<span style="color: red;">✗ Network error occurred</span>');
                        },
                        complete: function() {
                            $btn.prop('disabled', false).text('Test API Connection');
                        }
                    });
                });

                // Generate Post
                $('#generate-overview-btn').click(function() {
                    var $btn = $(this);
                    var $result = $('#generate-result');
                    var topic = $('#overview-topic').val();

                    if (!topic) {
                        $result.html('<span style="color: red;">Please enter a topic</span>');
                        return;
                    }

                    $btn.prop('disabled', true).text('Generating...');
                    $result.html('<span style="color: #666;">Generating AI overview optimized content...</span>');

                    var provider = $('#ai_provider_select').val();
                    var apiKey = $('.api-key-row[data-provider="' + provider + '"] .api-key-input').val();

                    if (!apiKey) {
                        $result.html('<span style="color: red;">Please enter an API key first</span>');
                        $btn.prop('disabled', false).text('Generate Optimized Post');
                        return;
                    }

                    $.ajax({
                        url: aiOverviewOptimizer.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'generate_overview_post',
                            topic: topic,
                            api_key: apiKey,
                            nonce: aiOverviewOptimizer.nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                var data = response.data;
                                $result.html('<span style="color: green;">✓ Post created! <a href="' + data.edit_url + '" target="_blank">Edit Post</a></span>');
                                $('#overview-topic').val('');
                            } else {
                                $result.html('<span style="color: red;">✗ ' + (response.data || 'Generation failed') + '</span>');
                            }
                        },
                        error: function() {
                            $result.html('<span style="color: red;">✗ Network error occurred</span>');
                        },
                        complete: function() {
                            $btn.prop('disabled', false).text('Generate Optimized Post');
                        }
                    });
                });
            });
            </script>

            <style>
            .api-key-row { display: none; }
            .postbox { margin-top: 20px; }
            .postbox .inside { padding: 15px; }
            </style>
        </div>
        <?php
    }

    public function test_api_connection() {
        check_ajax_referer('ai_overview_optimizer_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        $api_key = sanitize_text_field($_POST['api_key']);
        $provider = sanitize_text_field($_POST['provider']);

        if (empty($api_key) || empty($provider)) {
            wp_send_json_error('API key and provider are required');
        }

        $test_result = $this->make_test_request($provider, $api_key);

        if ($test_result) {
            wp_send_json_success('API connection successful');
        } else {
            wp_send_json_error('API connection failed - please check your key');
        }
    }

    public function generate_overview_post() {
        check_ajax_referer('ai_overview_optimizer_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        $topic = sanitize_text_field($_POST['topic']);
        if (empty($topic)) {
            wp_send_json_error('Topic is required');
        }

        // Get API key from form (similar to test function)
        $provider = get_option('ai_overview_provider', 'gemini');
        $api_key = sanitize_text_field($_POST['api_key'] ?? '');

        // If no API key in POST, try to get from saved options
        if (empty($api_key)) {
            $api_key_option = 'ai_overview_' . $provider . '_key';
            $api_key = get_option($api_key_option, '');
        }

        if (empty($api_key)) {
            wp_send_json_error('API key not configured for ' . $provider . '. Please enter your API key and save settings first.');
        }

        $generator = new AIOverview_Content_Generator();
        $result = $generator->generate_overview_post($topic, $api_key);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        $post = get_post($result);
        wp_send_json_success(array(
            'post_id' => $result,
            'title' => $post->post_title,
            'edit_url' => admin_url('post.php?post=' . $result . '&action=edit'),
            'view_url' => $post->post_status === 'publish' ? get_permalink($result) : null
        ));
    }

    public function output_schema_markup() {
        if (!is_single()) {
            return;
        }

        global $post;
        if (!$post) {
            return;
        }

        $schema_types = get_post_meta($post->ID, '_ai_overview_schema_types', true);
        if (empty($schema_types)) {
            return;
        }

        $schema_generator = new AIOverview_Schema_Generator();
        $schema_data = $schema_generator->generate_schema($post, $schema_types);

        if ($schema_data) {
            echo '<script type="application/ld+json">' . wp_json_encode($schema_data) . '</script>' . "\n";
        }
    }

    private function make_test_request($provider, $api_key) {
        try {
            switch ($provider) {
                case 'gemini':
                    return $this->test_gemini_connection($api_key);
                case 'openai':
                    return $this->test_openai_connection($api_key);
                default:
                    return false;
            }
        } catch (Exception $e) {
            return false;
        }
    }

    private function test_gemini_connection($api_key) {
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-exp:generateContent?key=' . $api_key;

        $body = wp_json_encode(array(
            'contents' => array(
                array(
                    'parts' => array(
                        array('text' => 'Say "test successful" if you can read this.')
                    )
                )
            )
        ));

        $response = wp_remote_post($url, array(
            'timeout' => 15,
            'headers' => array('Content-Type' => 'application/json'),
            'body' => $body
        ));

        if (is_wp_error($response)) {
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        return $response_code >= 200 && $response_code < 300;
    }

    private function test_openai_connection($api_key) {
        $url = 'https://api.openai.com/v1/chat/completions';

        $body = wp_json_encode(array(
            'model' => 'gpt-3.5-turbo',
            'messages' => array(
                array('role' => 'user', 'content' => 'Say "test successful"')
            ),
            'max_tokens' => 10
        ));

        $response = wp_remote_post($url, array(
            'timeout' => 15,
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => $body
        ));

        if (is_wp_error($response)) {
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        return $response_code >= 200 && $response_code < 300;
    }

    public static function activate() {
        // Set default options
        add_option('ai_overview_provider', 'gemini');
        add_option('ai_overview_post_status', 'draft');
        add_option('ai_overview_content_type', 'faq');
        add_option('ai_overview_category', 1);
        add_option('ai_overview_author_name', get_bloginfo('name'));
        add_option('ai_overview_schema_types', array('faq'));
    }

    public static function deactivate() {
        // Clean up if needed
    }
}

// Initialize the plugin
AIOverviewOptimizer::get_instance();

// Activation hooks
register_activation_hook(__FILE__, array('AIOverviewOptimizer', 'activate'));
register_deactivation_hook(__FILE__, array('AIOverviewOptimizer', 'deactivate'));