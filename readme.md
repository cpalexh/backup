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
- PHP 7.2 or higher
- Composer
- MySQL/MariaDB
- PHPMailer
- php-dotenv

## Installation

1. Install the package via Composer: 
2. Configure your environment variables in a `.env` file in your WordPress root directory:

    DB_NAME=your_database_name
   
    DB_PASSWORD=your_database_password
   
    BACKUP_MAIL=your_email@example.com
   
    MAIL_HOST=your_smtp_host
   
    MAIL_USERNAME=your_smtp_username
   
    MAIL_PASSWORD=your_smtp_password


## Usage

Once installed and activated, the plugin will automatically run daily backups. You don't need to do anything else.

## Customization

You can modify the backup schedule by editing the `wp_schedule_event` function call in the main plugin file.

## Logging

The plugin logs its operations to `wp-content/backup-script-log.txt`. Check this file for detailed information about each backup run.

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
