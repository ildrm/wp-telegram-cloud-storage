=== Telegram Cloud Storage ===
Contributors: shahinilderemi
Donate link: https://ildrm.com/donate/
Tags: telegram, cloud storage, media upload, file management, wordpress media
Requires at least: 5.0
Tested up to: 6.6
Stable tag: 1.1.5
Requires PHP: 7.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.txt

Store your WordPress media uploads in Telegram instead of the local server, saving disk space and leveraging Telegram's cloud storage.

== Description ==

**Telegram Cloud Storage** is a powerful WordPress plugin that allows you to store your media uploads (images, documents, etc.) directly in Telegram's cloud storage instead of your server's disk. By utilizing Telegram's secure and unlimited cloud storage, this plugin helps you save server space, reduce hosting costs, and streamline media management.

The plugin uploads files to a specified Telegram chat or channel and serves them via a secure proxy URL, ensuring that your bot token remains private. It seamlessly integrates with WordPress's media library, making it easy to use for both new and existing uploads.

### Key Features
- **Cloud Storage**: Store media files in Telegram's cloud instead of your server.
- **Secure Proxy**: Serve files via a proxy URL to hide the Telegram bot token.
- **Seamless Integration**: Works with WordPress media library and existing attachments.
- **Easy Setup**: Configure with your Telegram bot token and chat ID in minutes.
- **File Type Support**: Supports images, documents, and other file types (up to 2GB).
- **Metadata Management**: Stores Telegram file IDs and URLs in attachment metadata.
- **Diagnostic Tools**: Includes tools to test chat ID, file ID, and update existing URLs.
- **Multilingual**: Translation-ready with support for multiple languages.

Developed by [Shahin Ilderemi](https://ildrm.com).

== Installation ==

1. **Install the Plugin**:
   - Download the plugin ZIP file from the WordPress Plugin Repository or [ildrm.com](https://ildrm.com/telegram-cloud-storage).
   - In your WordPress admin dashboard, go to **Plugins > Add New > Upload Plugin**.
   - Upload the ZIP file and click **Install Now**.

2. **Activate the Plugin**:
   - After installation, click **Activate Plugin**.

3. **Configure Telegram Settings**:
   - Go to **Settings > Telegram Storage** in your WordPress admin dashboard.
   - Enter your **Telegram Bot Token** (obtained from [@BotFather](https://t.me/BotFather)).
   - Enter the **Telegram Chat ID** (e.g., your Saved Messages chat ID or a private channel ID).
   - Click **Save Changes**.

4. **Test Configuration**:
   - Click the **Test Chat ID** button to verify that the bot can access the chat.
   - Use the **Test File ID** tool to diagnose issues with specific file IDs.

5. **Update Existing Media (Optional)**:
   - Click **Update Existing Telegram URLs** to rewrite URLs for previously uploaded media.

6. **Flush Permalinks**:
   - Go to **Settings > Permalinks** and click **Save Changes** to ensure proxy URLs work correctly.

== Frequently Asked Questions ==

= How do I get a Telegram Bot Token? =
Create a bot using [@BotFather](https://t.me/BotFather) on Telegram:
1. Send `/start` to @BotFather.
2. Send `/newbot` and follow the prompts to create a bot.
3. Copy the bot token provided by @BotFather.

= How do I find my Chat ID? =
- For personal chats, start a conversation with your bot (send `/start`) and use a service like [@GetIDsBot](https://t.me/GetIDsBot) to get your chat ID.
- For channels, add the bot as an admin and send a message to the channel, then use @GetIDsBot to retrieve the channel ID.
- Alternatively, test the chat ID using the **Test Chat ID** button in the plugin settings.

= Why do I see a "File not found" error? =
This error may occur due to:
- Missing or invalid attachment metadata.
- An invalid or expired bot token.
- Cache issues (browser, plugin, or CDN).
- Incorrect rewrite rules.
Use the **Test File ID** tool to diagnose the issue, check the plugin logs (`telegram-cloud-storage.log`), and ensure your bot token is valid. Clear caches and flush permalinks if needed.

= Can I use this plugin with existing media? =
Yes! Use the **Update Existing Telegram URLs** tool to rewrite URLs for existing media files stored in Telegram.

= What file types are supported? =
The plugin supports any file type up to 2GB, including images (JPEG, PNG, GIF), documents (PDF, DOCX), and more, as long as Telegram accepts them.

= Is my bot token secure? =
Yes, the plugin uses a proxy endpoint (`/telegram-file/<file_id>`) to serve files, ensuring that your bot token is never exposed in URLs.

= Where are the logs stored? =
Logs are stored in `wp-content/plugins/telegram-cloud-storage/telegram-cloud-storage.log`. Check this file for debugging information.

= How can I get support? =
Contact the developer at [ildrm@hotmail.com](mailto:ildrm@hotmail.com) or visit [ildrm.com](https://ildrm.com) for support.

== Screenshots ==

1. **Settings Page**: Configure your Telegram bot token and chat ID.
2. **Media Library**: Uploaded files are stored in Telegram and served via proxy URLs.
3. **Diagnostic Tools**: Test chat ID and file ID to troubleshoot issues.

== Changelog ==

= 1.1.5 =
* Added **Test File ID** tool to diagnose and fix issues with specific file IDs.
* Improved proxy request handling with better fallback for missing attachments.
* Enhanced logging for proxy requests and metadata updates.
* Fixed issues with missing `original_telegram_url` in metadata.

= 1.1.4 =
* Improved proxy endpoint to handle missing metadata with fallback to Telegram API.
* Added detailed logging for file retrieval and proxy requests.
* Optimized attachment metadata updates for reliability.

= 1.1.3 =
* Fixed issues with URL rewriting for existing attachments.
* Improved error handling for Telegram API requests.

= 1.1.2 =
* Added support for image dimensions in metadata.
* Fixed cURL timeout issues for large file uploads.

= 1.1.1 =
* Improved compatibility with WordPress 6.0+.
* Fixed minor bugs in settings validation.

= 1.1.0 =
* Added proxy endpoint for secure file serving.
* Introduced **Update Existing Telegram URLs** tool.
* Enhanced logging for debugging.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.1.5 =
This update includes a new **Test File ID** tool to help diagnose issues with file IDs, improved proxy handling, and enhanced logging. After upgrading, run **Test File ID** and **Update Existing Telegram URLs** to ensure all media files are correctly configured.

== Support ==

For support, please contact [ildrm@hotmail.com](mailto:ildrm@hotmail.com) or visit [ildrm.com](https://ildrm.com). Check the plugin logs (`telegram-cloud-storage.log`) for debugging information before reaching out.

== Credits ==

Developed by [Shahin Ilderemi](https://ildrm.com). Special thanks to the WordPress and Telegram communities for their support and feedback.