<?php
if (!defined('ABSPATH')) { exit; }

class Altego_Upgrade {
    public static function run() {
        self::maybe_migrate();
    }

    public static function maybe_migrate() {
        global $wpdb;
        $table = $wpdb->prefix . 'altego_appointments';
        // extra fields for locations
        $loc = $wpdb->prefix . 'altego_locations';
        $add_if_missing = function($name, $sql) use ($wpdb, $loc) {
            $col = $wpdb->get_var("SHOW COLUMNS FROM `$loc` LIKE '$name'");
            if (!$col) { $wpdb->query("ALTER TABLE `$loc` ADD $sql"); }
        };
        $add_if_missing('logo_url', "logo_url VARCHAR(255) NULL AFTER name");
        $add_if_missing('subtitle', "subtitle VARCHAR(190) NULL AFTER logo_url");
        $add_if_missing('phone1', "phone1 VARCHAR(64) NULL AFTER address");
        $add_if_missing('phone2', "phone2 VARCHAR(64) NULL AFTER phone1");
        $add_if_missing('website_url', "website_url VARCHAR(255) NULL AFTER phone2");
        $add_if_missing('telegram', "telegram VARCHAR(190) NULL AFTER website_url");

        $exists = $wpdb->get_var( $wpdb->prepare("SHOW TABLES LIKE %s", $table) );
        if ($exists) {
            $col_time_start = $wpdb->get_var("SHOW COLUMNS FROM `$table` LIKE 'time_start'");
            $col_time_end   = $wpdb->get_var("SHOW COLUMNS FROM `$table` LIKE 'time_end'");
            $col_start      = $wpdb->get_var("SHOW COLUMNS FROM `$table` LIKE 'start'");
            $col_end        = $wpdb->get_var("SHOW COLUMNS FROM `$table` LIKE 'end'");
            if (!$col_time_start && $col_start) { $wpdb->query("ALTER TABLE `$table` CHANGE `start` `time_start` TIME NOT NULL"); }
            if (!$col_time_end && $col_end)     { $wpdb->query("ALTER TABLE `$table` CHANGE `end` `time_end` TIME NOT NULL"); }
        }
        // reminders table
        $rem = $wpdb->prefix . 'altego_reminders_log';
        $rem_exists = $wpdb->get_var( $wpdb->prepare("SHOW TABLES LIKE %s", $rem) );
        if (!$rem_exists) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            $charset = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE $rem(
      id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      appointment_id BIGINT UNSIGNED NOT NULL,
      type VARCHAR(16) NOT NULL,
      sent_at DATETIME NOT NULL,
      UNIQUE KEY uniq_apptype (appointment_id, type),
      INDEX(appointment_id)
    ) $charset;";
            dbDelta($sql);
        }

        $staff = $wpdb->prefix . 'altego_staff';

// avatar_url
        $col = $wpdb->get_var("SHOW COLUMNS FROM `$staff` LIKE 'avatar_url'");
        if (!$col) { $wpdb->query("ALTER TABLE `$staff` ADD `avatar_url` VARCHAR(255) NULL AFTER `phone`"); }

// title
        $col = $wpdb->get_var("SHOW COLUMNS FROM `$staff` LIKE 'title'");
        if (!$col) { $wpdb->query("ALTER TABLE `$staff` ADD `title` VARCHAR(190) NULL AFTER `name`"); }

// rating
        $col = $wpdb->get_var("SHOW COLUMNS FROM `$staff` LIKE 'rating'");
        if (!$col) { $wpdb->query("ALTER TABLE `$staff` ADD `rating` DECIMAL(3,2) NOT NULL DEFAULT 0 AFTER `color`"); }

// reviews_count
        $col = $wpdb->get_var("SHOW COLUMNS FROM `$staff` LIKE 'reviews_count'");
        if (!$col) { $wpdb->query("ALTER TABLE `$staff` ADD `reviews_count` INT NOT NULL DEFAULT 0 AFTER `rating`"); }

        // services.active
        $svc = $wpdb->prefix . 'altego_services';
        $col = $wpdb->get_var("SHOW COLUMNS FROM `$svc` LIKE 'active'");
        if (!$col) { $wpdb->query("ALTER TABLE `$svc` ADD `active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `price`"); }

// locations.active
        $loc = $wpdb->prefix . 'altego_locations';
        $col = $wpdb->get_var("SHOW COLUMNS FROM `$loc` LIKE 'active'");
        if (!$col) { $wpdb->query("ALTER TABLE `$loc` ADD `active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `address`"); }

    }

}
