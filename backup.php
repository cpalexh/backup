<?php
/**
 * Plugin Name: Backup Script
 * Description: Automatic DB backup generator
 * Version: 1.1.0
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
        $this->backup_folder = $_SERVER['DOCUMENT_ROOT'] . '/../backups/' . '/' . date('Y-m-d') . '/';
        $this->mail = env('BACKUP_MAIL') ? env('BACKUP_MAIL') : "admin@webentwicklung-huxel.de";
        $this->message = "";
        $this->mysql_file = null;
        $this->log_file = WP_CONTENT_DIR . '/backup-script-log.txt';

        $this->generateBackup();
    }

    private function createBackupFolder(){
        if (!file_exists($this->backup_folder)) {
            mkdir($this->backup_folder, 0755, true);
        }
    }

    private function sendMail()
    {
        if (filter_var($this->mail, FILTER_VALIDATE_EMAIL)) {
            $mail = new PHPMailer(true);
    
            try {
                // Server settings
                $mail->isSMTP();
                $mail->Host       = env('MAIL_HOST'); 
                $mail->SMTPAuth   = true;
                $mail->Username   = env('MAIL_USERNAME');
                $mail->Password   = env('MAIL_PASSWORD');
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                $mail->Port       = 465;

                $mail->setFrom(env('BACKUP_MAIL'), 'Backup Script');
                $mail->addAddress($this->mail);
    
                // Content
                $mail->isHTML(true); 
                $mail->Subject = 'DB Backup [' . date('d.m.Y H:i') .']';
                $mail->Body    = $this->message;
    
                $mail->send();
            } catch (Exception $e) {
                $this->writeToLog("ERROR: Email could not be sent. Mailer Error: {$mail->ErrorInfo}");
            }
        } else {
            $this->writeToLog("ERROR: Email could not be sent due to invalid address!");
        }
    }

    private function getCalculatedMysqlSize($inputfile){
        $file = $inputfile . ".gz";
        $size = filesize($file);
        $units = ["Bytes", "KB", "MB", "GB", "TB"];
        $size = round($size / pow(1024, ($i = floor(log($size, 1024)))), 2);
        $mysql_size = "$size {$units[$i]}";

        $this->writeToLog("Dump size: $mysql_size");
        return $mysql_size;
    }

    private function generateMysqlFile(){
        $this->mysql_file = $this->backup_folder . "{$this->db_name}_" . date('m_d_Y') . ".sql";

        $this->createBackupFolder();        

        if(!file_exists(dirname($this->mysql_file))) {
            mkdir(dirname($this->mysql_file), 0777, true);
        }

        if(!file_exists($this->mysql_file) && !file_exists($this->mysql_file.'.gz')) {
            exec("mysqldump -u {$this->db_name} -p'{$this->db_passwd}' --single-transaction --allow-keywords --complete-insert --insert-ignore --routines --events --force {$this->db_name} > $this->mysql_file");
        }else{
            return false;
        }

        if(!file_exists($this->mysql_file.'.gz')) {
            exec("gzip $this->mysql_file");
        }else{
            return false;
        }

        return true;
    }

    private function backupMediaFiles(){
        $upload_dir = wp_upload_dir()['basedir'];
        $media_backup_file = $this->backup_folder . "media_" . date('m_d_Y') . ".tar.gz";

        exec("tar -czf $media_backup_file -C $upload_dir .");

        // Log the location of the media backup
        return $media_backup_file;
    }

    private function generateBackup() {

        if(!$this->generateMysqlFile()){
            return false;
        }

        $this->writeToLog("Backup completed successfully.");

        $this->message =  '<h1>' . get_bloginfo("name") . '-Backup</h1>' . "<br>";
        $this->message .= "Backup of database <b>{$this->db_name}</b> completed.<br>";
        $this->message .= "Dump size: <b>" . $this->getCalculatedMysqlSize($this->mysql_file). "</b>.<br>";
        $this->message .= date('d.m.Y H:i:s') . "<br>";
        $this->message .= "Media files backed up to: " . $this->backupMediaFiles();

        $this->sendMail();
        $this->writeToLog("Mail sent successfully.");
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
