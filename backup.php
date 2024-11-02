<?php

/**
 * Plugin Name: Backup Script
 * Description: Automatic DB backup generator
 * Version: 1.6.4
 * Author: Alexander Huxel
 * Author URI: https://webentwicklung-huxel.de
 * License: MIT License
 */

namespace cpalexh\backup;

if (!defined('ABSPATH')) exit;


if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/../vendor/autoload.php')) {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/../vendor/autoload.php';
}

class BackupScript
{
    private $db_name;
    private $db_passwd;
    private $backup_folder;
    private $mail;
    private $message;
    private $mysql_file;
    private $log_file;

    public function __construct()
    {
        $this->db_name = DB_NAME;
        $this->db_passwd = DB_PASSWORD;
        $this->backup_folder = $this->getBackupFolder();
        $this->mail = filter_var(get_option('admin_email'), FILTER_VALIDATE_EMAIL);
        $this->message = "";
        $this->mysql_file = null;
        $this->log_file = wp_upload_dir()['basedir'] . '/logs/backup-script.log';
        $this->generateBackup();
    }

    private function getBackupFolder()
    {
        $folder = wp_upload_dir()['basedir'] . '/backups/' . date('Y-m-d') . '/';
        if (!file_exists($folder)) {
            mkdir($folder, 0755, true);
        }
        return $folder;
    }

    private function sendMail()
    {
        if (!$this->mail) {
            $this->writeToLog("ERROR: Invalid email address provided for backup notification.");
            return;
        }
        // Use wp_mail function to send email
        $subject = 'DB Backup [' . date('d.m.Y H:i') . ']';
        $headers = array('Content-Type: text/html; charset=UTF-8');
        $result = wp_mail($this->mail, $subject, $this->message, $headers);

        if ($result) {
            $this->writeToLog("Mail sent successfully.");
        } else {
            $this->writeToLog("ERROR: Email could not be sent.");
        }
    }

    private function getCalculatedMysqlSize($inputfile)
    {
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

    private function generateMysqlFile()
    {
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

    private function backupMediaFiles()
    {

        $dirs = wp_upload_dir()['basedir'];
        $listOfdirs = array();

        foreach (scandir($dirs) as  $dir) {
            if (!is_numeric($dir)) continue;

            $listOfdirs[] = $dir;
        }

        $backup_file = $this->backup_folder . "media_" . date('m_d_Y') . ".tar.gz";
        $dirs_to_backup = implode(' ', array_map('escapeshellarg', $listOfdirs));
        $command = "tar -czf " . escapeshellarg($backup_file) . " -C " . escapeshellarg($dirs) . " $dirs_to_backup";
        exec($command, $output, $return_var);

        if ($return_var === 0) {
            $this->writeToLog("Directories backed up to: $backup_file");
        } else {
            $this->writeToLog("Failed to backup directories. Command output: " . implode("\n", $output));
        }

        return $backup_file;
    }

    private function generateBackup()
    {
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

    private function writeToLog($message)
    {
        if (!file_exists(wp_upload_dir()['basedir'] . '/logs')) {
            mkdir(wp_upload_dir()['basedir'] . '/logs', 0755, true);
        }

        $timestamp = date('[Y-m-d H:i:s]');
        $log_message = $timestamp . ' ' . $message . PHP_EOL;
        file_put_contents($this->log_file, $log_message, FILE_APPEND);
    }
}

// Main initialization function
function initialize_backup_script()
{
    // Check if it's a cron job or a manual admin request
    if (defined('DOING_CRON') && DOING_CRON) {
        add_action('backup_script_weekly', 'cpalexh\backup\run_backup');
    } elseif (is_admin() && current_user_can('manage_options')) {
        if (isset($_GET['backup']) && $_GET['backup'] === 'makemanually') {
            run_backup();
            exit;
        }
    }

    // Schedule the events if they're not already scheduled
    if (!wp_next_scheduled('backup_script_weekly')) {
        wp_schedule_event(time(), 'weekly', 'backup_script_weekly');
    }
}

// Function to run the backup
function run_backup()
{
    new BackupScript();
}

// Hook the initialization function to 'init'
add_action('init', 'cpalexh\backup\initialize_backup_script');

// Clear scheduled events on plugin deactivation
register_deactivation_hook(__FILE__, function () {
    $timestamp = wp_next_scheduled('backup_script_weekly');
    wp_unschedule_event($timestamp, 'backup_script_weekly');
});
