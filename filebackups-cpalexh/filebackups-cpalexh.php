<?php
// TODO: update the links
/*
Plugin Name: FileBackups - CP Alexh
Plugin URI: https://webwentwicklung-huxel.de
Description: This plugin creates backups of the your files and db.
Author: Alexander Huxel
Author URI: https://webwentwicklung-huxel.de
Version: 1.0.0
Text Domain: filebackups-cpalexh
Requires at least: 4.7
Requires PHP: 7.4
*/

namespace cpalexh\filebackups;
if(!defined('ABSPATH')) die;


if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/../vendor/autoload.php')) {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/../vendor/autoload.php';
}

    class FilebackupPlugin {
    
        private $message;
        private $mysql_file;
        private $plugin;
    
        public function __construct()
        {
            $this->message = "";
            $this->mysql_file = null;
            $this->plugin = plugin_basename(__FILE__);
            $this->generateBackup();
        }
    

        function activation(){
            $this->getBackupFolder();
        }
    
        function deactivation(){}
    
        function uninstall(){}

        function register(){
            add_action('admin_menu', array($this , 'add_admin_pages'));
            add_filter("plugin_action_links_$this->plugin" , array($this, 'settings_link'));
        }

        function add_admin_pages(){
                add_menu_page(
                    'FileBackups',
                    'FileBackups',
                    'manage_options',
                    'filebackups_cpalexh',
                    array($this, 'admin_index'),
                    'dashicons-backup',
                    100
                );
        }

        function admin_index(){
            require_once plugin_dir_path(__FILE__) . 'templates/admin.php';
        }

        function settings_link($links){
            $settings_link = '<a href="options-general.php?page=filebackups_cpalexh">Settings</a>';
            array_push($links, $settings_link);
            return $links;
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
            if (!filter_var(get_option('admin_email'), FILTER_VALIDATE_EMAIL)) {
                $this->writeToLog("ERROR: Invalid email address provided for backup notification.");
                return;
            }
            // Use wp_mail function to send email^
            $subject = 'DB Backup [' . date('d.m.Y H:i') . ']';
            $headers = array('Content-Type: text/html; charset=UTF-8');
            $result = wp_mail(filter_var(get_option('admin_email'), FILTER_VALIDATE_EMAIL), $subject, $this->message, $headers);
    
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
            $this->mysql_file = $this->getBackupFolder() . DB_NAME ."_". date('m_d_Y') . ".sql";
    
            if (file_exists($this->mysql_file . '.gz')) {
                $this->writeToLog("Backup file already exists. Skipping creation.");
                return false;
            }
    
            // Use PDO or MySQLi to create a database dump securely here.
            $command = sprintf(
                "mysqldump --user=%s --password=%s --single-transaction --allow-keywords --complete-insert --insert-ignore --routines --events --force %s > %s",
                escapeshellarg(DB_NAME),
                escapeshellarg(DB_PASSWORD),
                escapeshellarg(DB_NAME),
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
            $upload_dir = wp_upload_dir()['basedir'];
            $media_backup_file = $this->getBackupFolder() . "media_" . date('m_d_Y') . ".tar.gz";
    
            exec("tar -czf " . escapeshellarg($media_backup_file) . " -C " . escapeshellarg($upload_dir) . " .");
            $this->writeToLog("Media files backed up to: $media_backup_file");
    
            return $media_backup_file;
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
                DB_NAME,
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

            file_put_contents(wp_upload_dir()['basedir'] . '/logs/backup-script.log', $log_message, FILE_APPEND);
        }
    }
 

        $filebackupPlugin = new FilebackupPlugin();
        $filebackupPlugin->register();


    // to ensure the wp_mail() function is available
    add_action('init', function () {
    
        // Schedule the events if they're not already scheduled
        add_action('wp', function () {
            if (!wp_next_scheduled('backup_script_weekly')) {
                wp_schedule_event(time(), 'weekly', 'backup_script_weekly');
            }
        });
    
        add_action('backup_script_weekly', function () {
            new FilebackupPlugin();
        });
    
        // allow the user to create a manual backup
        if (isset($_GET['backup']) && $_GET['backup'] === 'makemanually') {
            new FilebackupPlugin();
        }

        new FilebackupPlugin();
    
        // Clear scheduled events on plugin deactivation
        register_deactivation_hook(__FILE__, function () {
            $timestamp = wp_next_scheduled('backup_script_weekly');
            wp_unschedule_event($timestamp, 'backup_script_weekly');
        });
    });
