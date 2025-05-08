<?php
/**
 * Plugin Name: Telegram Cloud Storage
 * Plugin URI: https://ildrm.com/wordpress-telegram-cloud-storage
 * Description: A WordPress plugin to store media uploads in Telegram instead of the local server.
 * Version: 1.1.5
 * Author: Shahin Ilderemi
 * Author URI: https://ildrm.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: telegram-cloud-storage
 */

// Prevent direct access to this file
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Telegram_Cloud_Storage
 * Main plugin class to handle Telegram uploads and settings
 */
class Telegram_Cloud_Storage {
    private $telegram_api_url = 'https://api.telegram.org/bot';
    private $log_file = __DIR__ . '/telegram-cloud-storage.log';

    /**
     * Constructor
     * Initialize hooks and settings
     */
    public function __construct() {
        // Register settings page
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
        
        // Handle file uploads
        add_filter('wp_handle_upload', [$this, 'handle_upload_to_telegram'], 10, 2);
        add_filter('wp_generate_attachment_metadata', [$this, 'update_attachment_metadata'], 10, 3);
        
        // Load text domain for translations
        add_action('plugins_loaded', [$this, 'load_textdomain']);
        
        // Handle test chat ID button
        add_action('admin_post_test_chat_id', [$this, 'handle_test_chat_id']);
        
        // Proxy endpoint for images
        add_action('init', [$this, 'register_proxy_endpoint']);
        
        // Rewrite image URLs
        add_filter('wp_get_attachment_url', [$this, 'rewrite_attachment_url'], 10000, 2);
        add_filter('wp_get_attachment_image_src', [$this, 'rewrite_image_src'], 10000, 4);
        add_filter('wp_get_attachment_image_attributes', [$this, 'rewrite_image_attributes'], 10000, 3);
        add_filter('the_content', [$this, 'rewrite_content_urls'], 10000);
        add_filter('wp_get_attachment_image', [$this, 'rewrite_attachment_image'], 10000, 5);
        
        // Emergency URL rewrite for output buffer
        add_action('template_redirect', [$this, 'start_output_buffer'], 0);
        
        // Admin actions
        add_action('admin_post_update_telegram_urls', [$this, 'update_existing_attachments']);
        add_action('admin_post_test_file_id', [$this, 'test_file_id']);
        
        // Flush rewrite rules on activation/deactivation
        register_activation_hook(__FILE__, [$this, 'flush_rewrite_rules']);
        register_deactivation_hook(__FILE__, [$this, 'flush_rewrite_rules']);
    }
    
    /**
     * Load plugin text domain for translations
     */
    public function load_textdomain() {
        load_plugin_textdomain('telegram-cloud-storage', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    /**
     * Custom logging function
     *
     * @param string $message Log message
     */
    private function log($message) {
        $timestamp = gmdate('Y-m-d H:i:s');
        $log_message = "[$timestamp] $message\n";
        
        // Try WordPress error_log
        error_log($log_message);
        
        // Also write to custom log file
        if (is_writable(__DIR__)) {
            file_put_contents($this->log_file, $log_message, FILE_APPEND);
        }
    }
    
    /**
     * Flush rewrite rules
     */
    public function flush_rewrite_rules() {
        $this->register_proxy_endpoint();
        flush_rewrite_rules();
        $this->log("Rewrite rules flushed");
    }
    
    /**
     * Add settings page under Settings menu
     */
    public function add_settings_page() {
        add_options_page(
            __('Telegram Cloud Storage Settings', 'telegram-cloud-storage'),
            __('Telegram Storage', 'telegram-cloud-storage'),
            'manage_options',
            'telegram-cloud-storage',
            [$this, 'render_settings_page']
        );
    }
    
    /**
     * Register plugin settings
     */
    public function register_settings() {
        register_setting('telegram_cloud_storage_options', 'telegram_bot_token', [
            'sanitize_callback' => [$this, 'sanitize_bot_token']
        ]);
        register_setting('telegram_cloud_storage_options', 'telegram_chat_id', [
            'sanitize_callback' => [$this, 'sanitize_chat_id']
        ]);
        
        add_settings_section(
            'telegram_cloud_storage_main',
            __('Telegram Configuration', 'telegram-cloud-storage'),
            null,
            'telegram-cloud-storage'
        );
        
        add_settings_field(
            'telegram_bot_token',
            __('Telegram Bot Token', 'telegram-cloud-storage'),
            [$this, 'render_bot_token_field'],
            'telegram-cloud-storage',
            'telegram_cloud_storage_main'
        );
        
        add_settings_field(
            'telegram_chat_id',
            __('Telegram Chat ID', 'telegram-cloud-storage'),
            [$this, 'render_chat_id_field'],
            'telegram-cloud-storage',
            'telegram_cloud_storage_main'
        );
    }
    
    /**
     * Sanitize and validate bot token
     *
     * @param string $token Bot token
     * @return string Sanitized token
     */
    public function sanitize_bot_token($token) {
        $token = sanitize_text_field($token);
        if (!empty($token)) {
            $this->log("Validating bot token");
            $response = wp_remote_get($this->telegram_api_url . $token . '/getMe', [
                'timeout' => 30,
                'sslverify' => true
            ]);
            if (is_wp_error($response)) {
                $error = $response->get_error_message();
                $this->log("Bot token validation failed: $error");
                add_settings_error(
                    'telegram_bot_token',
                    'invalid_bot_token',
                    __('Failed to validate bot token: ', 'telegram-cloud-storage') . $error
                );
            } else {
                $result = json_decode(wp_remote_retrieve_body($response), true);
                $this->log("Bot token validation response: " . wp_json_encode($result));
                if (!$result['ok']) {
                    $error = $result['description'] ?? 'Unknown error';
                    $this->log("Invalid bot token: $error");
                    add_settings_error(
                        'telegram_bot_token',
                        'invalid_bot_token',
                        __('Invalid bot token: ', 'telegram-cloud-storage') . $error
                    );
                }
            }
        }
        return $token;
    }
    
    /**
     * Sanitize and validate chat ID
     *
     * @param string $chat_id Chat ID
     * @return string Sanitized chat ID
     */
    public function sanitize_chat_id($chat_id) {
        $chat_id = sanitize_text_field($chat_id);
        $bot_token = get_option('telegram_bot_token', '');
        if (!empty($chat_id) && !empty($bot_token)) {
            $this->log("Validating chat ID: $chat_id");
            $response = wp_remote_post($this->telegram_api_url . $bot_token . '/sendMessage', [
                'timeout' => 30,
                'body' => [
                    'chat_id' => $chat_id,
                    'text' => 'Test message from Telegram Cloud Storage plugin'
                ],
                'sslverify' => true
            ]);
            if (is_wp_error($response)) {
                $error = $response->get_error_message();
                $this->log("Chat ID validation failed: $error");
                add_settings_error(
                    'telegram_chat_id',
                    'invalid_chat_id',
                    __('Failed to validate chat ID: ', 'telegram-cloud-storage') . $error
                );
            } else {
                $result = json_decode(wp_remote_retrieve_body($response), true);
                $this->log("Chat ID validation response: " . wp_json_encode($result));
                if (!$result['ok']) {
                    $error = $result['description'] ?? 'Unknown error';
                    $this->log("Invalid chat ID: $error");
                    if (strpos($error, 'chat not found') !== false) {
                        $error .= __(' Please start a chat with the bot by sending a message to it (e.g., /start).', 'telegram-cloud-storage');
                    }
                    add_settings_error(
                        'telegram_chat_id',
                        'invalid_chat_id',
                        __('Invalid chat ID: ', 'telegram-cloud-storage') . $error
                    );
                }
            }
        }
        return $chat_id;
    }
    
    /**
     * Render bot token input field
     */
    public function render_bot_token_field() {
        $bot_token = get_option('telegram_bot_token', '');
        ?>
        <input type="text" name="telegram_bot_token" value="<?php echo esc_attr($bot_token); ?>" class="regular-text" />
        <p class="description">
            <?php _e('Enter the bot token obtained from @BotFather.', 'telegram-cloud-storage'); ?>
        </p>
        <?php
    }
    
    /**
     * Render chat ID input field
     */
    public function render_chat_id_field() {
        $chat_id = get_option('telegram_chat_id', '');
        ?>
        <input type="text" name="telegram_chat_id" value="<?php echo esc_attr($chat_id); ?>" class="regular-text" />
        <p>
            <a href="<?php echo esc_url(admin_url('admin-post.php?action=test_chat_id')); ?>" class="button"><?php _e('Test Chat ID', 'telegram-cloud-storage'); ?></a>
        </p>
        <p class="description">
            <?php _e('Enter the chat ID where files will be uploaded (e.g., your Saved Messages chat ID or a private channel ID). Click "Test Chat ID" to verify access. For personal chats, start a chat with the bot first.', 'telegram-cloud-storage'); ?>
        </p>
        <?php
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Telegram Cloud Storage Settings', 'telegram-cloud-storage'); ?></h1>
            <?php if (isset($_GET['settings-updated'])) : ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php _e('Settings saved successfully.', 'telegram-cloud-storage'); ?></p>
                </div>
            <?php endif; ?>
            <?php if (isset($_GET['error'])) : ?>
                <div class="notice notice-error is-dismissible">
                    <p><?php echo esc_html(urldecode($_GET['error'])); ?></p>
                </div>
            <?php endif; ?>
            <?php if (isset($_GET['success'])) : ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php echo esc_html(urldecode($_GET['success'])); ?></p>
                </div>
            <?php endif; ?>
            <form method="post" action="options.php">
                <?php
                settings_fields('telegram_cloud_storage_options');
                do_settings_sections('telegram-cloud-storage');
                submit_button();
                ?>
            </form>
            <h2><?php _e('Tools', 'telegram-cloud-storage'); ?></h2>
            <p>
                <a href="<?php echo esc_url(admin_url('admin-post.php?action=update_telegram_urls')); ?>" class="button"><?php _e('Update Existing Telegram URLs', 'telegram-cloud-storage'); ?></a>
                <a href="<?php echo esc_url(admin_url('admin-post.php?action=test_file_id')); ?>" class="button"><?php _e('Test File ID', 'telegram-cloud-storage'); ?></a>
            </p>
            <p>
                <?php
                printf(
                    __('Developed by <a href="%s">Shahin Ilderemi</a>. For support, contact <a href="mailto:%s">%s</a>.', 'telegram-cloud-storage'),
                    'https://ildrm.com',
                    'ildrm@hotmail.com',
                    'ildrm@hotmail.com'
                );
                ?>
            </p>
        </div>
        <?php
    }
    
    /**
     * Handle test chat ID action
     */
    public function handle_test_chat_id() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to perform this action.', 'telegram-cloud-storage'));
        }
        
        $bot_token = get_option('telegram_bot_token', '');
        $chat_id = get_option('telegram_chat_id', '');
        
        if (empty($bot_token) || empty($chat_id)) {
            wp_redirect(admin_url('options-general.php?page=telegram-cloud-storage&error=' . urlencode(__('Bot token or chat ID not configured.', 'telegram-cloud-storage'))));
            exit;
        }
        
        $this->log("Testing chat ID: $chat_id");
        $response = wp_remote_post($this->telegram_api_url . $bot_token . '/sendMessage', [
            'timeout' => 30,
            'body' => [
                'chat_id' => $chat_id,
                'text' => 'Test message from Telegram Cloud Storage plugin'
            ],
            'sslverify' => true
        ]);
        
        if (is_wp_error($response)) {
            $error = $response->get_error_message();
            $this->log("Chat ID test failed: $error");
            wp_redirect(admin_url('options-general.php?page=telegram-cloud-storage&error=' . urlencode(__('Failed to test chat ID: ', 'telegram-cloud-storage') . $error)));
            exit;
        }
        
        $result = json_decode(wp_remote_retrieve_body($response), true);
        $this->log("Chat ID test response: " . wp_json_encode($result));
        
        if (!$result['ok']) {
            $error = $result['description'] ?? 'Unknown error';
            $this->log("Chat ID test failed: $error");
            if (strpos($error, 'chat not found') !== false) {
                $error .= __(' Please start a chat with the bot by sending a message to it (e.g., /start).', 'telegram-cloud-storage');
            }
            wp_redirect(admin_url('options-general.php?page=telegram-cloud-storage&error=' . urlencode(__('Invalid chat ID: ', 'telegram-cloud-storage') . $error)));
            exit;
        }
        
        wp_redirect(admin_url('options-general.php?page=telegram-cloud-storage&success=' . urlencode(__('Chat ID is valid and accessible.', 'telegram-cloud-storage'))));
        exit;
    }
    
    /**
     * Test file ID action
     */
    public function test_file_id() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to perform this action.', 'telegram-cloud-storage'));
        }
        
        $file_id = 'BQACAgQAAxkDAAMSaBzg7PaJyXQzqf_EC4H5LJn9oVcAAvIWAAKWeuFQzOFnTyBykKU2BA';
        $this->log("Testing file_id: $file_id");
        
        // Check if attachment exists
        $args = [
            'post_type' => 'attachment',
            'posts_per_page' => 1,
            'post_status' => 'any',
            'meta_query' => [
                [
                    'key' => '_wp_attachment_metadata',
                    'value' => $file_id,
                    'compare' => 'LIKE'
                ]
            ]
        ];
        $query = new WP_Query($args);
        
        if ($query->have_posts()) {
            $attachment = $query->posts[0];
            $metadata = wp_get_attachment_metadata($attachment->ID);
            $this->log("Found attachment ID: {$attachment->ID}, Metadata: " . wp_json_encode($metadata));
            
            if (empty($metadata['original_telegram_url'])) {
                $telegram_url = $this->get_file_url_from_file_id($file_id);
                if ($telegram_url) {
                    $metadata['original_telegram_url'] = $telegram_url;
                    wp_update_attachment_metadata($attachment->ID, $metadata);
                    $this->log("Updated metadata with Telegram URL: $telegram_url");
                    wp_redirect(admin_url('options-general.php?page=telegram-cloud-storage&success=' . urlencode(__('File ID found and metadata updated.', 'telegram-cloud-storage'))));
                    exit;
                } else {
                    $this->log("Failed to retrieve Telegram URL for file_id: $file_id");
                    wp_redirect(admin_url('options-general.php?page=telegram-cloud-storage&error=' . urlencode(__('File ID found but failed to retrieve Telegram URL.', 'telegram-cloud-storage'))));
                    exit;
                }
            }
            
            wp_redirect(admin_url('options-general.php?page=telegram-cloud-storage&success=' . urlencode(__('File ID found with valid metadata.', 'telegram-cloud-storage'))));
            exit;
        }
        
        // Try to fetch file directly
        $telegram_url = $this->get_file_url_from_file_id($file_id);
        if ($telegram_url) {
            // Create new attachment
            $attachment_id = wp_insert_attachment([
                'post_mime_type' => 'image/jpeg',
                'post_title' => 'Telegram File ' . $file_id,
                'post_content' => '',
                'post_status' => 'inherit'
            ]);
            
            if ($attachment_id) {
                $metadata = [
                    'file' => home_url('/telegram-file/' . $file_id),
                    'telegram_file_id' => $file_id,
                    'original_telegram_url' => $telegram_url
                ];
                wp_update_attachment_metadata($attachment_id, $metadata);
                $this->log("Created new attachment ID: $attachment_id for file_id: $file_id");
                wp_redirect(admin_url('options-general.php?page=telegram-cloud-storage&success=' . urlencode(__('File ID accessible, new attachment created.', 'telegram-cloud-storage'))));
                exit;
            }
        }
        
        $this->log("No attachment found and failed to retrieve Telegram URL for file_id: $file_id");
        wp_redirect(admin_url('options-general.php?page=telegram-cloud-storage&error=' . urlencode(__('File ID not found and could not retrieve file.', 'telegram-cloud-storage'))));
        exit;
    }
    
    /**
     * Handle file upload to Telegram
     *
     * @param array $upload Upload data
     * @param string $context Upload context
     * @return array Modified upload data
     */
    public function handle_upload_to_telegram($upload, $context) {
        $this->log("Starting file upload to Telegram: " . $upload['file']);
        
        $bot_token = get_option('telegram_bot_token', '');
        $chat_id = get_option('telegram_chat_id', '');
        
        if (empty($bot_token) || empty($chat_id)) {
            $this->log("Missing bot token or chat ID");
            wp_die(__('Telegram bot token or chat ID not configured.', 'telegram-cloud-storage'));
        }
        
        $file_path = $upload['file'];
        if (!file_exists($file_path)) {
            $this->log("File does not exist: $file_path");
            wp_die(__('Upload file not found on server.', 'telegram-cloud-storage'));
        }
        
        if (!is_readable($file_path)) {
            $this->log("File is not readable: $file_path");
            wp_die(__('Upload file is not readable.', 'telegram-cloud-storage'));
        }
        
        $file_size = filesize($file_path);
        $max_size = 2000000000; // 2GB limit for Telegram
        if ($file_size > $max_size) {
            $this->log("File size too large: $file_size bytes");
            wp_die(__('File size exceeds Telegram limit of 2GB.', 'telegram-cloud-storage'));
        }
        
        $file_name = basename($file_path);
        $mime_type = mime_content_type($file_path);
        if (!$mime_type) {
            $this->log("Failed to detect MIME type for file: $file_path");
            wp_die(__('Failed to detect file MIME type.', 'telegram-cloud-storage'));
        }
        
        $this->log("File details - Name: $file_name, MIME: $mime_type, Size: $file_size bytes, Path: $file_path");
        
        // Get image dimensions if it's an image
        $image_dimensions = [];
        if (in_array($mime_type, ['image/jpeg', 'image/png', 'image/gif'])) {
            $image_info = @getimagesize($file_path);
            if ($image_info) {
                $image_dimensions = [
                    'width' => $image_info[0],
                    'height' => $image_info[1]
                ];
                $this->log("Image dimensions - Width: {$image_info[0]}, Height: {$image_info[1]}");
            }
        }
        
        // Test chat ID accessibility before uploading
        $this->log("Testing chat ID accessibility: $chat_id");
        $test_response = wp_remote_post($this->telegram_api_url . $bot_token . '/sendMessage', [
            'timeout' => 30,
            'body' => [
                'chat_id' => $chat_id,
                'text' => 'Pre-upload test from Telegram Cloud Storage plugin'
            ],
            'sslverify' => true
        ]);
        
        if (is_wp_error($test_response)) {
            $error = $test_response->get_error_message();
            $this->log("Chat ID test failed: $error");
            wp_die(__('Failed to access chat ID: ', 'telegram-cloud-storage') . $error);
        }
        
        $test_result = json_decode(wp_remote_retrieve_body($test_response), true);
        $this->log("Chat ID test response: " . wp_json_encode($test_result));
        
        if (!$test_result['ok']) {
            $error = $test_result['description'] ?? 'Unknown error';
            $this->log("Chat ID inaccessible: $error");
            if (strpos($error, 'chat not found') !== false) {
                $error .= __(' Please start a chat with the bot by sending a message to it (e.g., /start).', 'telegram-cloud-storage');
            }
            wp_die(__('Chat ID is invalid or bot lacks permissions: ', 'telegram-cloud-storage') . $error);
        }
        
        // Prepare file for Telegram upload using cURL directly
        $api_url = $this->telegram_api_url . $bot_token . '/sendDocument';
        $this->log("Sending request to: $api_url");
        
        $post_fields = [
            'chat_id' => $chat_id,
            'document' => new CURLFile($file_path, $mime_type, $file_name)
        ];
        $this->log("Post fields: " . wp_json_encode([
            'chat_id' => $chat_id,
            'document' => [
                'name' => $file_name,
                'mime_type' => $mime_type,
                'path' => $file_path
            ]
        ]));
        
        // Use cURL directly instead of wp_remote_post
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api_url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_HEADER, true); // Include headers in output
        
        $response = curl_exec($ch);
        $curl_error = curl_error($ch);
        $curl_errno = curl_errno($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        // Split headers and body
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $response_headers = substr($response, 0, $header_size);
        $response_body = substr($response, $header_size);
        
        curl_close($ch);
        
        $this->log("cURL Response - Code: $http_code, Headers: " . $response_headers);
        if ($curl_error) {
            $this->log("cURL Error - Number: $curl_errno, Message: $curl_error");
            wp_die(__('Failed to upload file to Telegram: cURL error ', 'telegram-cloud-storage') . $curl_error);
        }
        
        $result = json_decode($response_body, true);
        
        if ($http_code !== 200 || !$result || !isset($result['ok']) || !$result['ok']) {
            $error_description = isset($result['description']) ? $result['description'] : 'Bad request (check file format, size, or server configuration)';
            $this->log("API Error - Code: $http_code, Description: $error_description");
            wp_die(__('Telegram API error: ', 'telegram-cloud-storage') . $error_description);
        }
        
        if (empty($result['result']['document']['file_id'])) {
            $this->log("No file_id in response: " . wp_json_encode($result));
            wp_die(__('Telegram API error: No file ID returned.', 'telegram-cloud-storage'));
        }
        
        $file_id = $result['result']['document']['file_id'];
        $this->log("File uploaded successfully, file_id: $file_id");
        
        // Get file URL from Telegram
        $file_url = $this->get_telegram_file_url($bot_token, $file_id);
        
        if (!$file_url) {
            $this->log("Failed to retrieve file URL for file_id: $file_id");
            wp_die(__('Failed to retrieve Telegram file URL.', 'telegram-cloud-storage'));
        }
        
        $this->log("File URL retrieved: $file_url");
        
        // Delete local file
        if (file_exists($file_path)) {
            @unlink($file_path);
            $this->log("Local file deleted: $file_path");
        }
        
        // Update upload data
        $upload['url'] = home_url('/telegram-file/' . $file_id);
        $upload['file'] = '';
        $upload['telegram_file_id'] = $file_id;
        $upload['original_telegram_url'] = $file_url;
        $upload['image_dimensions'] = $image_dimensions;
        $this->log("Upload data updated: " . wp_json_encode($upload));
        
        return $upload;
    }
    
    /**
     * Get Telegram file URL from file ID
     *
     * @param string $bot_token Bot token
     * @param string $file_id Telegram file ID
     * @return string|null File URL or null on failure
     */
    public function get_telegram_file_url($bot_token, $file_id) {
        $this->log("Retrieving file URL for file_id: $file_id");
        
        $api_url = $this->telegram_api_url . $bot_token . '/getFile';
        
        $response = wp_remote_post($api_url, [
            'timeout' => 30,
            'body' => [
                'file_id' => $file_id
            ],
            'sslverify' => true
        ]);
        
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            $this->log("getFile failed: $error_message");
            return null;
        }
        
        $response_body = wp_remote_retrieve_body($response);
        $this->log("getFile Response: $response_body");
        
        $result = json_decode($response_body, true);
        
        if (!$result || !isset($result['ok']) || !$result['ok'] || empty($result['result']['file_path'])) {
            $error_description = isset($result['description']) ? $result['description'] : 'Unknown error';
            $this->log("getFile error: $error_description");
            return null;
        }
        
        $file_url = 'https://api.telegram.org/file/bot' . $bot_token . '/' . $result['result']['file_path'];
        $this->log("File URL: $file_url");
        return $file_url;
    }
    
    /**
     * Update attachment metadata to ensure compatibility
     *
     * @param array $metadata Attachment metadata
     * @param int $attachment_id Attachment ID
     * @param string $context Context
     * @return array Modified metadata
     */
    public function update_attachment_metadata($metadata, $attachment_id, $context) {
        $this->log("Updating metadata for attachment ID: $attachment_id");
        
        // Get upload data
        $upload_data = get_post_meta($attachment_id, '_wp_attachment_metadata', true);
        $telegram_file_id = isset($upload_data['telegram_file_id']) ? $upload_data['telegram_file_id'] : '';
        $original_telegram_url = isset($upload_data['original_telegram_url']) ? $upload_data['original_telegram_url'] : '';
        $image_dimensions = isset($upload_data['image_dimensions']) ? $upload_data['image_dimensions'] : [];
        
        // Set file URL to proxy URL
        if (!empty($telegram_file_id)) {
            $proxy_url = home_url('/telegram-file/' . $telegram_file_id);
            $metadata['file'] = $proxy_url;
            $this->log("Set proxy URL for attachment ID: $attachment_id, URL: $proxy_url");
        } else {
            $metadata['file'] = wp_get_attachment_url($attachment_id);
            $this->log("No telegram_file_id found for attachment ID: $attachment_id, using default URL: {$metadata['file']}");
        }
        
        // Store additional metadata
        $metadata['telegram_file_id'] = $telegram_file_id;
        $metadata['original_telegram_url'] = $original_telegram_url;
        
        // Set image dimensions
        if (!empty($image_dimensions)) {
            $metadata['width'] = $image_dimensions['width'];
            $metadata['height'] = $image_dimensions['height'];
        }
        
        if (empty($metadata['sizes'])) {
            $metadata['sizes'] = [];
        }
        
        $this->log("Updated metadata: " . wp_json_encode($metadata));
        
        // Force update post meta
        update_post_meta($attachment_id, '_wp_attachment_metadata', $metadata);
        
        return $metadata;
    }
    
    /**
     * Register proxy endpoint for serving Telegram files
     */
    public function register_proxy_endpoint() {
        add_rewrite_rule(
            'telegram-file/([^/]+)/?$',
            'index.php?telegram_file_id=$matches[1]',
            'top'
        );
        
        add_filter('query_vars', function($vars) {
            $vars[] = 'telegram_file_id';
            return $vars;
        });
        
        add_action('template_redirect', [$this, 'handle_proxy_request']);
        
        // Force flush rewrite rules
        flush_rewrite_rules();
        $this->log("Rewrite rules registered and flushed");
    }
    
    /**
     * Handle proxy request for Telegram files
     */
    public function handle_proxy_request() {
        $file_id = get_query_var('telegram_file_id');
        if (empty($file_id)) {
            $this->log("No file_id provided in proxy request");
            return;
        }
        
        $this->log("Handling proxy request for file_id: $file_id");
        
        // Find attachment with this file_id
        $args = [
            'post_type' => 'attachment',
            'posts_per_page' => 1,
            'post_status' => 'any',
            'meta_query' => [
                [
                    'key' => '_wp_attachment_metadata',
                    'value' => $file_id,
                    'compare' => 'LIKE'
                ]
            ]
        ];
        $query = new WP_Query($args);
        
        if ($query->have_posts()) {
            $attachment = $query->posts[0];
            $metadata = wp_get_attachment_metadata($attachment->ID);
            $this->log("Found attachment ID: {$attachment->ID}, Metadata: " . wp_json_encode($metadata));
            
            $telegram_url = isset($metadata['original_telegram_url']) ? $metadata['original_telegram_url'] : '';
            
            if (empty($telegram_url)) {
                $this->log("No Telegram URL found for attachment ID: {$attachment->ID}, attempting fallback");
                $telegram_url = $this->get_file_url_from_file_id($file_id);
                if ($telegram_url) {
                    $metadata['original_telegram_url'] = $telegram_url;
                    wp_update_attachment_metadata($attachment->ID, $metadata);
                    $this->log("Updated metadata with Telegram URL: $telegram_url");
                }
            }
            
            if ($telegram_url) {
                $this->serve_telegram_file($telegram_url);
            }
            
            $this->log("Failed to retrieve Telegram URL for file_id: $file_id");
            wp_die(__('File URL not found.', 'telegram-cloud-storage'), 404);
        }
        
        // Fallback: Try to fetch file directly from Telegram
        $this->log("No attachment found for file_id: $file_id, attempting direct fetch");
        $telegram_url = $this->get_file_url_from_file_id($file_id);
        if ($telegram_url) {
            // Create new attachment
            $attachment_id = wp_insert_attachment([
                'post_mime_type' => 'image/jpeg',
                'post_title' => 'Telegram File ' . $file_id,
                'post_content' => '',
                'post_status' => 'inherit'
            ]);
            
            if ($attachment_id) {
                $metadata = [
                    'file' => home_url('/telegram-file/' . $file_id),
                    'telegram_file_id' => $file_id,
                    'original_telegram_url' => $telegram_url
                ];
                wp_update_attachment_metadata($attachment_id, $metadata);
                $this->log("Created new attachment ID: $attachment_id for file_id: $file_id");
            }
            
            $this->serve_telegram_file($telegram_url);
        }
        
        $this->log("Failed to retrieve file for file_id: $file_id");
        wp_die(__('File not found.', 'telegram-cloud-storage'), 404);
    }
    
    /**
     * Serve file from Telegram URL
     *
     * @param string $telegram_url Telegram file URL
     */
    private function serve_telegram_file($telegram_url) {
        $this->log("Fetching file from Telegram: $telegram_url");
        
        // Fetch file from Telegram
        $response = wp_remote_get($telegram_url, [
            'timeout' => 30,
            'sslverify' => true
        ]);
        
        if (is_wp_error($response)) {
            $error = $response->get_error_message();
            $this->log("Failed to fetch file: $error");
            wp_die(__('Failed to fetch file from Telegram: ', 'telegram-cloud-storage') . $error, 500);
        }
        
        $body = wp_remote_retrieve_body($response);
        $content_type = wp_remote_retrieve_header($response, 'content-type');
        $content_length = wp_remote_retrieve_header($response, 'content-length');
        
        // Serve file
        header('Content-Type: ' . $content_type);
        header('Content-Length: ' . $content_length);
        header('Cache-Control: max-age=31536000');
        echo $body;
        exit;
    }
    
    /**
     * Get Telegram file URL from file ID (fallback)
     *
     * @param string $file_id Telegram file ID
     * @return string|null File URL or null on failure
     */
    private function get_file_url_from_file_id($file_id) {
        $bot_token = get_option('telegram_bot_token', '');
        if (empty($bot_token)) {
            $this->log("No bot token available for fetching file_id: $file_id");
            return null;
        }
        
        return $this->get_telegram_file_url($bot_token, $file_id);
    }
    
    /**
     * Rewrite attachment URLs to use proxy
     *
     * @param string $url Original URL
     * @param int $attachment_id Attachment ID
     * @return string Rewritten URL
     */
    public function rewrite_attachment_url($url, $attachment_id) {
        $this->log("Checking URL rewrite for attachment ID: $attachment_id, Original URL: $url");
        
        $metadata = wp_get_attachment_metadata($attachment_id);
        if (isset($metadata['telegram_file_id']) && !empty($metadata['telegram_file_id'])) {
            $proxy_url = home_url('/telegram-file/' . $metadata['telegram_file_id']);
            $this->log("Rewriting URL for attachment ID: $attachment_id, Proxy URL: $proxy_url");
            return $proxy_url;
        }
        
        if (strpos($url, 'api.telegram.org') !== false) {
            $this->log("Detected Telegram URL for attachment ID: $attachment_id, attempting fallback rewrite");
            $metadata = $metadata ? $metadata : [];
            $metadata['telegram_file_id'] = 'fallback_' . $attachment_id . '_' . time();
            $metadata['original_telegram_url'] = $url;
            wp_update_attachment_metadata($attachment_id, $metadata);
            $proxy_url = home_url('/telegram-file/' . $metadata['telegram_file_id']);
            $this->log("Fallback rewrite for attachment ID: $attachment_id, Proxy URL: $proxy_url");
            return $proxy_url;
        }
        
        $this->log("No telegram_file_id found for attachment ID: $attachment_id, keeping URL: $url");
        return $url;
    }
    
    /**
     * Rewrite image source URLs
     *
     * @param array $image Image data
     * @param int $attachment_id Attachment ID
     * @param string|array $size Image size
     * @param bool $icon Whether the image is an icon
     * @return array Modified image data
     */
    public function rewrite_image_src($image, $attachment_id, $size, $icon) {
        if (!$image || empty($image[0])) {
            return $image;
        }
        
        $this->log("Checking image src rewrite for attachment ID: $attachment_id, Original src: {$image[0]}");
        
        $metadata = wp_get_attachment_metadata($attachment_id);
        if (isset($metadata['telegram_file_id']) && !empty($metadata['telegram_file_id'])) {
            $proxy_url = home_url('/telegram-file/' . $metadata['telegram_file_id']);
            $image[0] = $proxy_url;
            $this->log("Rewrote image src for attachment ID: $attachment_id, Proxy URL: $proxy_url");
            return $image;
        }
        
        if (strpos($image[0], 'api.telegram.org') !== false) {
            $this->log("Detected Telegram URL in image src for attachment ID: $attachment_id, attempting fallback rewrite");
            $metadata = $metadata ? $metadata : [];
            $metadata['telegram_file_id'] = 'fallback_' . $attachment_id . '_' . time();
            $metadata['original_telegram_url'] = $image[0];
            wp_update_attachment_metadata($attachment_id, $metadata);
            $proxy_url = home_url('/telegram-file/' . $metadata['telegram_file_id']);
            $image[0] = $proxy_url;
            $this->log("Fallback rewrite for image src, attachment ID: $attachment_id, Proxy URL: $proxy_url");
            return $image;
        }
        
        return $image;
    }
    
    /**
     * Rewrite image attributes
     *
     * @param array $attr Image attributes
     * @param WP_Post $attachment Attachment post object
     * @param string|array $size Image size
     * @return array Modified attributes
     */
    public function rewrite_image_attributes($attr, $attachment, $size) {
        if (isset($attr['src'])) {
            $this->log("Checking image attributes rewrite for attachment ID: {$attachment->ID}, Original src: {$attr['src']}");
            
            $metadata = wp_get_attachment_metadata($attachment->ID);
            if (isset($metadata['telegram_file_id']) && !empty($metadata['telegram_file_id'])) {
                $proxy_url = home_url('/telegram-file/' . $metadata['telegram_file_id']);
                $attr['src'] = $proxy_url;
                $this->log("Rewrote image attributes for attachment ID: {$attachment->ID}, Proxy URL: $proxy_url");
                return $attr;
            }
            
            if (strpos($attr['src'], 'api.telegram.org') !== false) {
                $this->log("Detected Telegram URL in image attributes for attachment ID: {$attachment->ID}, attempting fallback rewrite");
                $metadata = $metadata ? $metadata : [];
                $metadata['telegram_file_id'] = 'fallback_' . $attachment->ID . '_' . time();
                $metadata['original_telegram_url'] = $attr['src'];
                wp_update_attachment_metadata($attachment->ID, $metadata);
                $proxy_url = home_url('/telegram-file/' . $metadata['telegram_file_id']);
                $attr['src'] = $proxy_url;
                $this->log("Fallback rewrite for image attributes, attachment ID: {$attachment->ID}, Proxy URL: $proxy_url");
                return $attr;
            }
        }
        
        return $attr;
    }
    
    /**
     * Rewrite attachment image HTML
     *
     * @param string $html Image HTML
     * @param int $attachment_id Attachment ID
     * @param string|array $size Image size
     * @param bool $icon Whether the image is an icon
     * @param array $attr Image attributes
     * @return string Modified HTML
     */
    public function rewrite_attachment_image($html, $attachment_id, $size, $icon, $attr) {
        $this->log("Checking attachment image rewrite for attachment ID: $attachment_id, Original HTML: $html");
        
        $metadata = wp_get_attachment_metadata($attachment_id);
        if (isset($metadata['telegram_file_id']) && !empty($metadata['telegram_file_id'])) {
            $proxy_url = home_url('/telegram-file/' . $metadata['telegram_file_id']);
            $html = preg_replace('/src=["\'][^"\']+["\']/', 'src="' . esc_url($proxy_url) . '"', $html);
            $this->log("Rewrote attachment image HTML for attachment ID: $attachment_id, Proxy URL: $proxy_url");
            return $html;
        }
        
        if (strpos($html, 'api.telegram.org') !== false) {
            $this->log("Detected Telegram URL in attachment image HTML for attachment ID: $attachment_id, attempting fallback rewrite");
            $metadata = $metadata ? $metadata : [];
            $metadata['telegram_file_id'] = 'fallback_' . $attachment_id . '_' . time();
            $metadata['original_telegram_url'] = wp_get_attachment_url($attachment_id);
            wp_update_attachment_metadata($attachment_id, $metadata);
            $proxy_url = home_url('/telegram-file/' . $metadata['telegram_file_id']);
            $html = preg_replace('/src=["\'][^"\']+["\']/', 'src="' . esc_url($proxy_url) . '"', $html);
            $this->log("Fallback rewrite for attachment image HTML, attachment ID: $attachment_id, Proxy URL: $proxy_url");
            return $html;
        }
        
        return $html;
    }
    
    /**
     * Rewrite Telegram URLs in post content
     *
     * @param string $content Post content
     * @return string Modified content
     */
    public function rewrite_content_urls($content) {
        $pattern = '/https:\/\/api\.telegram\.org\/file\/bot[^\/]+\/([^\s"]+)/';
        $content = preg_replace_callback($pattern, function($matches) use ($content) {
            $file_path = $matches[1];
            $this->log("Found Telegram URL in content: {$matches[0]}");
            
            // Find attachment by Telegram URL
            $args = [
                'post_type' => 'attachment',
                'posts_per_page' => 1,
                'post_status' => 'any',
                'meta_query' => [
                    [
                        'key' => '_wp_attachment_metadata',
                        'value' => $file_path,
                        'compare' => 'LIKE'
                    ]
                ]
            ];
            $query = new WP_Query($args);
            
            if ($query->have_posts()) {
                $attachment = $query->posts[0];
                $metadata = wp_get_attachment_metadata($attachment->ID);
                if (isset($metadata['telegram_file_id']) && !empty($metadata['telegram_file_id'])) {
                    $proxy_url = home_url('/telegram-file/' . $metadata['telegram_file_id']);
                    $this->log("Rewriting content URL: {$matches[0]} to $proxy_url");
                    return $proxy_url;
                }
                
                // Fallback for attachments without telegram_file_id
                $metadata['telegram_file_id'] = 'fallback_' . $attachment->ID . '_' . time();
                $metadata['original_telegram_url'] = $matches[0];
                wp_update_attachment_metadata($attachment->ID, $metadata);
                $proxy_url = home_url('/telegram-file/' . $metadata['telegram_file_id']);
                $this->log("Fallback rewrite for content URL: {$matches[0]} to $proxy_url");
                return $proxy_url;
            }
            
            // Fallback for unmatched URLs
            $fallback_id = 'unmatched_' . md5($matches[0]) . '_' . time();
            $this->log("No attachment found for Telegram URL: {$matches[0]}, using fallback ID: $fallback_id");
            return home_url('/telegram-file/' . $fallback_id);
        }, $content);
        
        return $content;
    }
    
    /**
     * Start output buffer to catch any remaining Telegram URLs
     */
    public function start_output_buffer() {
        ob_start([$this, 'rewrite_output_buffer']);
    }
    
    /**
     * Rewrite Telegram URLs in final output buffer
     *
     * @param string $buffer Output buffer
     * @return string Modified buffer
     */
    public function rewrite_output_buffer($buffer) {
        $pattern = '/https:\/\/api\.telegram\.org\/file\/bot[^\/]+\/([^\s"]+)/';
        $buffer = preg_replace_callback($pattern, function($matches) {
            $file_path = $matches[1];
            $this->log("Found Telegram URL in output buffer: {$matches[0]}");
            
            // Try to find attachment
            $args = [
                'post_type' => 'attachment',
                'posts_per_page' => 1,
                'post_status' => 'any',
                'meta_query' => [
                    [
                        'key' => '_wp_attachment_metadata',
                        'value' => $file_path,
                        'compare' => 'LIKE'
                    ]
                ]
            ];
            $query = new WP_Query($args);
            
            if ($query->have_posts()) {
                $attachment = $query->posts[0];
                $metadata = wp_get_attachment_metadata($attachment->ID);
                if (isset($metadata['telegram_file_id']) && !empty($metadata['telegram_file_id'])) {
                    $proxy_url = home_url('/telegram-file/' . $metadata['telegram_file_id']);
                    $this->log("Rewriting output buffer URL: {$matches[0]} to $proxy_url");
                    return $proxy_url;
                }
                
                // Fallback for attachments without telegram_file_id
                $metadata['telegram_file_id'] = 'fallback_' . $attachment->ID . '_' . time();
                $metadata['original_telegram_url'] = $matches[0];
                wp_update_attachment_metadata($attachment->ID, $metadata);
                $proxy_url = home_url('/telegram-file/' . $metadata['telegram_file_id']);
                $this->log("Fallback rewrite for output buffer URL: {$matches[0]} to $proxy_url");
                return $proxy_url;
            }
            
            // Fallback for unmatched URLs
            $fallback_id = 'unmatched_' . md5($matches[0]) . '_' . time();
            $this->log("No attachment found for output buffer URL: {$matches[0]}, using fallback ID: $fallback_id");
            return home_url('/telegram-file/' . $fallback_id);
        }, $buffer);
        
        return $buffer;
    }
    
    /**
     * Update existing attachments to use proxy URLs
     */
    public function update_existing_attachments() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to perform this action.', 'telegram-cloud-storage'));
        }
        
        $this->log("Starting update of existing Telegram URLs");
        
        $args = [
            'post_type' => 'attachment',
            'posts_per_page' => -1,
            'post_status' => 'any',
        ];
        
        $query = new WP_Query($args);
        
        $updated = 0;
        while ($query->have_posts()) {
            $query->the_post();
            $attachment_id = get_the_ID();
            $metadata = wp_get_attachment_metadata($attachment_id);
            
            if (!$metadata) {
                $metadata = [];
            }
            
            $current_url = wp_get_attachment_url($attachment_id);
            if (strpos($current_url, 'api.telegram.org') !== false || empty($metadata['telegram_file_id']) || empty($metadata['original_telegram_url'])) {
                $this->log("Processing attachment ID: $attachment_id, Current URL: $current_url");
                
                // Store original Telegram URL if it exists
                if (strpos($current_url, 'api.telegram.org') !== false) {
                    $metadata['original_telegram_url'] = $current_url;
                }
                
                // Ensure telegram_file_id exists
                if (!isset($metadata['telegram_file_id']) || empty($metadata['telegram_file_id'])) {
                    $metadata['telegram_file_id'] = 'fallback_' . $attachment_id . '_' . time();
                    $this->log("Generated fallback telegram_file_id for attachment ID: $attachment_id: {$metadata['telegram_file_id']}");
                }
                
                // If original_telegram_url is empty, try to fetch it
                if (empty($metadata['original_telegram_url']) && !empty($metadata['telegram_file_id'])) {
                    $telegram_url = $this->get_file_url_from_file_id($metadata['telegram_file_id']);
                    if ($telegram_url) {
                        $metadata['original_telegram_url'] = $telegram_url;
                        $this->log("Fetched Telegram URL for attachment ID: $attachment_id: $telegram_url");
                    }
                }
                
                $proxy_url = home_url('/telegram-file/' . $metadata['telegram_file_id']);
                $metadata['file'] = $proxy_url;
                
                wp_update_attachment_metadata($attachment_id, $metadata);
                $this->log("Updated attachment ID: $attachment_id to use proxy URL: $proxy_url");
                $updated++;
            }
        }
        
        wp_reset_postdata();
        
        wp_redirect(admin_url('options-general.php?page=telegram-cloud-storage&success=' . urlencode(sprintf(__('Updated %d attachments to use proxy URLs.', 'telegram-cloud-storage'), $updated))));
        exit;
    }
}

// Initialize the plugin
new Telegram_Cloud_Storage();
?>