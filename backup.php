<?php
/**
 * Plugin Name: Backup Script
 * Description: Automatic DB backup generator
 * Version: 1.2
 * Author: Alexander Huxel
 * Author URI: https://webentwicklung-huxel.de
 * License: MIT License
 */

if (!defined('ABSPATH')) exit;

use function Env\env;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require $_SERVER['DOCUMENT_ROOT'] . '/../vendor/autoload.php';

class BackupScript {
    private $db_name;
    private $db_passwd;
    private $backup_folder;
    private $mail;
    private $message;
    private $mysql_file;
    private $log_file;

    public function __construct() {
        $this->db_name = env('DB_NAME');
        $this->db_passwd = env('DB_PASSWORD');
        $this->backup_folder = $this->getBackupFolder();
        $this->mail = filter_var(env('BACKUP_MAIL'), FILTER_VALIDATE_EMAIL) ? env('BACKUP_MAIL') : "admin@webentwicklung-huxel.de";
        $this->message = "";
        $this->mysql_file = null;
        $this->log_file = WP_CONTENT_DIR . '/backup-script-log.log';

        $this->generateBackup();
    }

    private function getBackupFolder() {
        $folder = $_SERVER['DOCUMENT_ROOT'] . '/../backups/' . date('Y-m-d') . '/';
        if (!file_exists($folder)) {
            mkdir($folder, 0755, true);
        }
        return $folder;
    }

    private function sendMail() {
        if (!$this->mail) {
            $this->writeToLog("ERROR: Invalid email address provided for backup notification.");
            return;
        }

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = env('MAIL_HOST'); 
            $mail->SMTPAuth = true;
            $mail->Username = env('MAIL_USERNAME');
            $mail->Password = env('MAIL_PASSWORD');
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port = 465;

            $mail->setFrom(env('BACKUP_MAIL'), 'Backup Script');
            $mail->addAddress($this->mail);

            $mail->isHTML(true); 
            $mail->Subject = 'DB Backup [' . date('d.m.Y H:i') . ']';
            $mail->Body = $this->message;

            $mail->send();
            $this->writeToLog("Mail sent successfully.");
        } catch (Exception $e) {
            $this->writeToLog("ERROR: Email could not be sent. Mailer Error: {$mail->ErrorInfo}");
        }
    }

    private function getCalculatedMysqlSize($inputfile) {
        $file = $inputfile . ".gz";
        $size = @filesize($file);
        if ($size === false) {
            $this->writeToLog("ERROR: Could not calculate file size for $file.");
            return "Unknown size";
        }

        $units = ["Bytes", "KB", "MB", "GB", "TB"];
        $size = round($size / pow(1024, ($i = floor(log($size, 1024)))), 2);
        $mysql_size = "$size {$units[$i]}";
        $this->writeToLog("Dump size: $mysql_size");

        return $mysql_size;
    }

    private function generateMysqlFile() {
        $this->mysql_file = $this->backup_folder . "{$this->db_name}_" . date('m_d_Y') . ".sql";

        if (file_exists($this->mysql_file . '.gz')) {
            $this->writeToLog("Backup file already exists. Skipping creation.");
            return false;
        }

        // Use PDO or MySQLi to create a database dump securely here.
        $command = sprintf(
            "mysqldump --user=%s --password=%s --single-transaction --allow-keywords --complete-insert --insert-ignore --routines --events --force %s > %s",
            escapeshellarg($this->db_name),
            escapeshellarg($this->db_passwd),
            escapeshellarg($this->db_name),
            escapeshellarg($this->mysql_file)
        );

        exec($command, $output, $return_var);

        if ($return_var !== 0) {
            $this->writeToLog("ERROR: Failed to create MySQL dump. Command: $command");
            return false;
        }

        exec("gzip " . escapeshellarg($this->mysql_file));
        return true;
    }

    private function backupMediaFiles() {
        $upload_dir = wp_upload_dir()['basedir'];
        $media_backup_file = $this->backup_folder . "media_" . date('m_d_Y') . ".tar.gz";

        exec("tar -czf " . escapeshellarg($media_backup_file) . " -C " . escapeshellarg($upload_dir) . " .");
        $this->writeToLog("Media files backed up to: $media_backup_file");

        return $media_backup_file;
    }

    private function generateBackup() {
        if (!$this->generateMysqlFile()) {
            $this->writeToLog("Backup generation failed.");
            return false;
        }

        $this->message = sprintf(
            '<h1>%s Backup</h1><br>Backup of database <b>%s</b> completed.<br>Dump size: <b>%s</b>.<br>%s<br>Media files backed up to: %s',
            get_bloginfo("name"),
            $this->db_name,
            $this->getCalculatedMysqlSize($this->mysql_file),
            date('d.m.Y H:i:s'),
            $this->backupMediaFiles()
        );

        $this->sendMail();
    }

    private function writeToLog($message) {
        $timestamp = date('[Y-m-d H:i:s]');
        $log_message = $timestamp . ' ' . $message . PHP_EOL;
        file_put_contents($this->log_file, $log_message, FILE_APPEND);
    }
}

// Schedule the events if they're not already scheduled
add_action('wp', function() {
    if (!wp_next_scheduled('backup_script_daily')) {
        wp_schedule_event(time(), 'daily', 'backup_script_daily');
    }
});

add_action('backup_script_daily', function() {
    new BackupScript();
});

// Clear scheduled events on plugin deactivation
register_deactivation_hook(__FILE__, function() {
    $timestamp = wp_next_scheduled('backup_script_daily');
    wp_unschedule_event($timestamp, 'backup_script_daily');
});