# Telegram Cloud Storage

**A WordPress plugin to store media uploads in Telegram's cloud, saving server disk space and serving files securely via proxy URLs.**

[![License: GPL v2](https://img.shields.io/badge/License-GPL%20v2-blue.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![WordPress Version](https://img.shields.io/badge/WordPress-5.0%2B-blue)](https://wordpress.org)
[![PHP Version](https://img.shields.io/badge/PHP-7.2%2B-blue)](https://php.net)
[![GitHub Release](https://img.shields.io/github/v/release/ildrm/wp-telegram-cloud-storage)](https://github.com/ildrm/wp-telegram-cloud-storage/releases)

**Telegram Cloud Storage** is a robust WordPress plugin designed to offload media uploads (images, documents, etc.) to Telegram's secure and unlimited cloud storage. By leveraging Telegram's infrastructure, this plugin reduces server disk usage, lowers hosting costs, and provides a seamless integration with the WordPress media library. Files are served through a secure proxy endpoint, ensuring that your Telegram bot token remains private.

Developed by [Shahin Ilderemi](https://github.com/ildrm), a seasoned WordPress developer, this plugin is built with performance, security, and extensibility in mind.

## Features

- **Cloud Storage Integration**: Store media files in Telegram's cloud, eliminating local server storage needs.
- **Secure Proxy Endpoint**: Serve files via `/telegram-file/<file_id>` URLs to protect bot token exposure.
- **WordPress Media Library Compatibility**: Seamlessly integrates with new and existing media uploads.
- **Comprehensive Metadata Management**: Stores Telegram file IDs and URLs in attachment metadata.
- **Diagnostic Tools**: Includes tools to test chat ID, file ID, and update existing URLs.
- **Broad File Support**: Handles images (JPEG, PNG, GIF), documents (PDF, DOCX), and more (up to 2GB).
- **Extensive Logging**: Detailed logs for debugging and monitoring (`telegram-cloud-storage.log`).
- **Translation-Ready**: Supports multilingual setups with `.mo` and `.po` files.
- **Developer-Friendly**: Well-documented code with hooks and filters for customization.

## Installation

### From WordPress Admin
1. Download the latest release from the [GitHub repository](https://github.com/ildrm/wp-telegram-cloud-storage/releases).
2. In your WordPress admin dashboard, navigate to **Plugins > Add New > Upload Plugin**.
3. Upload the plugin ZIP file and click **Install Now**.
4. Activate the plugin from the **Plugins** page.

### Manual Installation
1. Clone or download the repository:
   ```bash
   git clone https://github.com/ildrm/wp-telegram-cloud-storage.git
   ```
2. Copy the `wp-telegram-cloud-storage` folder to `/wp-content/plugins/` in your WordPress installation.
3. Activate the plugin via the WordPress admin dashboard or WP-CLI:
   ```bash
   wp plugin activate wp-telegram-cloud-storage
   ```

### Configuration
1. Go to **Settings > Telegram Storage** in the WordPress admin dashboard.
2. Enter your **Telegram Bot Token** (obtained from [@BotFather](https://t.me/BotFather)).
3. Enter the **Telegram Chat ID** (e.g., Saved Messages or a private channel ID).
4. Save changes and use the **Test Chat ID** button to verify connectivity.
5. Optionally, run **Update Existing Telegram URLs** to rewrite URLs for existing media.
6. Flush permalinks (**Settings > Permalinks > Save Changes**) to enable proxy URLs.

## Getting Started

### Obtaining a Telegram Bot Token
1. Open Telegram and start a chat with [@BotFather](https://t.me/BotFather).
2. Send `/newbot` and follow the prompts to create a bot.
3. Copy the bot token provided by @BotFather.

### Finding Your Chat ID
- For personal chats: Start a conversation with your bot (send `/start`) and use [@GetIDsBot](https://t.me/GetIDsBot) to retrieve your chat ID.
- For channels: Add the bot as an admin, send a message, and use @GetIDsBot to get the channel ID.
- Verify the chat ID using the **Test Chat ID** tool in the plugin settings.

### Troubleshooting
If you encounter issues (e.g., "File not found" errors):
- Use the **Test File ID** tool to diagnose specific file IDs.
- Check the log file at `wp-content/plugins/wp-telegram-cloud-storage/telegram-cloud-storage.log`.
- Ensure your bot token is valid and the chat ID is accessible.
- Clear caches (browser, plugin, or CDN) and flush permalinks.
- Verify rewrite rules in `.htaccess` or Nginx configuration.

## Developer Guide

### Hooks and Filters
The plugin provides several hooks for customization:
- `wp_handle_upload`: Modify upload data before sending to Telegram.
- `wp_generate_attachment_metadata`: Adjust metadata for Telegram-stored files.
- `wp_get_attachment_url`, `wp_get_attachment_image_src`, `wp_get_attachment_image_attributes`: Rewrite URLs for proxy serving.

Example: Add custom metadata during upload
```php
add_filter('wp_generate_attachment_metadata', function($metadata, $attachment_id, $context) {
    $metadata['custom_field'] = 'custom_value';
    return $metadata;
}, 10, 3);
```

### Extending the Plugin
- **Custom Proxy Endpoints**: Modify the `register_proxy_endpoint` method to add custom rewrite rules.
- **Additional File Types**: Extend the `handle_upload_to_telegram` method to support specific MIME types.
- **Custom Logging**: Override the `log` method to integrate with external logging services.

### Contributing
Contributions are welcome! To contribute:
1. Fork the repository: [https://github.com/ildrm/wp-telegram-cloud-storage](https://github.com/ildrm/wp-telegram-cloud-storage).
2. Create a feature branch (`git checkout -b feature/your-feature`).
3. Commit your changes (`git commit -m "Add your feature"`).
4. Push to the branch (`git push origin feature/your-feature`).
5. Open a Pull Request.

Please follow the [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/) and include unit tests where applicable.

## Requirements
- WordPress: 5.0 or higher
- PHP: 7.2 or higher
- cURL: Enabled for Telegram API requests
- Telegram Bot Token and Chat ID

## Changelog

### 1.1.5 (2025-05-08)
- Added **Test File ID** tool for diagnosing and fixing file ID issues.
- Enhanced proxy request handling with automatic attachment creation for missing files.
- Improved logging for proxy requests, metadata updates, and diagnostic tools.
- Fixed issues with missing `original_telegram_url` in metadata.

### 1.1.4 (2025-05-07)
- Improved proxy endpoint with fallback to Telegram API for missing metadata.
- Added detailed logging for file retrieval and proxy requests.
- Optimized metadata updates for reliability.

### 1.1.3 (2025-04-15)
- Fixed URL rewriting for existing attachments.
- Improved Telegram API error handling.

### 1.1.2 (2025-03-20)
- Added support for image dimensions in metadata.
- Fixed cURL timeout issues for large files.

### 1.1.1 (2025-02-10)
- Enhanced compatibility with WordPress 6.0+.
- Fixed minor settings validation bugs.

### 1.1.0 (2025-01-05)
- Introduced secure proxy endpoint for file serving.
- Added **Update Existing Telegram URLs** tool.
- Enhanced logging for debugging.

### 1.0.0 (2024-12-01)
- Initial release.

## Support

For support, please:
- Email: [ildrm@hotmail.com](mailto:ildrm@hotmail.com)
- Visit: [ildrm.com](https://ildrm.com)
- Open an issue on GitHub: [https://github.com/ildrm/wp-telegram-cloud-storage/issues](https://github.com/ildrm/wp-telegram-cloud-storage/issues)

Before contacting support, check the log file (`telegram-cloud-storage.log`) and run the **Test File ID** and **Test Chat ID** tools to diagnose issues.

## License

This plugin is licensed under the [GNU General Public License v2.0 or later](https://www.gnu.org/licenses/gpl-2.0.html).

## Credits

Developed by [Shahin Ilderemi](https://github.com/ildrm), a WordPress developer with extensive experience in plugin development and cloud integrations. Special thanks to the WordPress and Telegram communities for their invaluable feedback and support.

---

⭐ **Star this repository** on [GitHub](https://github.com/ildrm/wp-telegram-cloud-storage) if you find it useful!  
💬 **Feedback and contributions** are greatly appreciated.