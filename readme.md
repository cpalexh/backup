# WordPress Backup Script

## Description

This WordPress plugin provides an automatic database and media backup generator. It creates daily backups of your WordPress database and media files, compresses them, and sends an email notification with the backup details.

## Features

- Automatic daily database backups
- Media files backup
- Compressed backups (gzip for database, tar.gz for media)
- Email notifications with backup details
- Logging of backup operations

## Requirements

- WordPress
- PHP 7.3 or higher

## Installation (bedrock)

1. Install the package via Composer:
2. Ensure your mail server is properly configured:
3. Ensure the admin email is correnct:

## Installation (non-bedrock)

1. Move the backup.php file in your mu-plugins folder:
2. Ensure your mail server is properly configured:
3. Ensure the admin email is correnct:

## Usage

Once installed and activated, the plugin will automatically run weekly backups. You don't need to do anything else.

## Customization

You can modify the backup schedule by editing the `wp_schedule_event` function call in the main plugin file.

## Logging

The plugin logs its operations to `uploads/logs/backup-script.log`. Check this file for detailed information about each backup run.

## License

This project is licensed under the MIT License.

## Author

Alexander Huxel

- Website: https://webentwicklung-huxel.de

## Support

For support, please open an issue on the GitHub repository or contact the author directly.

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## Disclaimer

Please ensure you have proper backups before using this plugin. The author is not responsible for any data loss or damage caused by the use of this plugin.
