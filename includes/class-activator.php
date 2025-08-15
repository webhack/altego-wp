<?php
if (!defined('ABSPATH')) { exit; }

class Altego_Activator {
    public static function activate() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        $tables = [];

        $tables[] = "CREATE TABLE {$wpdb->prefix}altego_locations(
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(190) NOT NULL,
  logo_url VARCHAR(255) NULL,
  subtitle VARCHAR(190) NULL,
  address VARCHAR(255) NULL,
  phone1 VARCHAR(64) NULL,
  phone2 VARCHAR(64) NULL,
  website_url VARCHAR(255) NULL,
  telegram VARCHAR(190) NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL
) $charset;";


        $tables[] = "CREATE TABLE {$wpdb->prefix}altego_staff(
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  location_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(190) NOT NULL,
  title VARCHAR(190) NULL,
  email VARCHAR(190),
  phone VARCHAR(64),
  avatar_url VARCHAR(255) NULL,
  color VARCHAR(16),
  rating DECIMAL(3,2) NOT NULL DEFAULT 0,
  reviews_count INT NOT NULL DEFAULT 0,
  work_rules LONGTEXT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  INDEX(location_id)
) $charset;";

        $tables[] = "CREATE TABLE {$wpdb->prefix}altego_services(
      id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      name VARCHAR(190) NOT NULL,
      duration INT NOT NULL,
      price DECIMAL(12,2) NOT NULL DEFAULT 0,
      category VARCHAR(190),
      created_at DATETIME NOT NULL,
      updated_at DATETIME NOT NULL
    ) $charset;";

        $tables[] = "CREATE TABLE {$wpdb->prefix}altego_service_staff(
      service_id BIGINT UNSIGNED NOT NULL,
      staff_id BIGINT UNSIGNED NOT NULL,
      duration INT NOT NULL,
      price DECIMAL(12,2) NOT NULL,
      PRIMARY KEY(service_id, staff_id)
    ) $charset;";

        $tables[] = "CREATE TABLE {$wpdb->prefix}altego_clients(
      id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      name VARCHAR(190),
      email VARCHAR(190),
      phone VARCHAR(64),
      consent_sms TINYINT(1) DEFAULT 0,
      consent_email TINYINT(1) DEFAULT 0,
      created_at DATETIME NOT NULL,
      updated_at DATETIME NOT NULL,
      UNIQUE KEY uniq_email_phone (email, phone)
    ) $charset;";

        $tables[] = "CREATE TABLE {$wpdb->prefix}altego_appointments(
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  location_id BIGINT UNSIGNED NOT NULL,
  staff_id BIGINT UNSIGNED NOT NULL,
  service_id BIGINT UNSIGNED NOT NULL,
  client_id BIGINT UNSIGNED NOT NULL,
  date DATE NOT NULL,
  time_start TIME NOT NULL,
  time_end TIME NOT NULL,
  status ENUM('new','confirmed','canceled','completed','no_show') NOT NULL DEFAULT 'confirmed',
  source VARCHAR(64),
  utm LONGTEXT NULL,
  notes TEXT,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  INDEX(location_id, date),
  INDEX(staff_id, date),
  INDEX(client_id)
) $charset;";

        $tables[] = "CREATE TABLE {$wpdb->prefix}altego_staff_schedule(
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  staff_id BIGINT UNSIGNED NOT NULL,
  weekday TINYINT NOT NULL,          /* 1 Mon .. 7 Sun */
  start TIME NOT NULL,
  end TIME NOT NULL,
  break_start TIME NULL,
  break_end TIME NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_staff_day (staff_id, weekday),
  INDEX(staff_id)
) $charset;";

        $tables[] = "CREATE TABLE {$wpdb->prefix}altego_reminders_log(
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  appointment_id BIGINT UNSIGNED NOT NULL,
  type VARCHAR(16) NOT NULL, /* email or sms */
  sent_at DATETIME NOT NULL,
  UNIQUE KEY uniq_apptype (appointment_id, type),
  INDEX(appointment_id)
) $charset;";



        if (!wp_next_scheduled('altego_reminders_cron')) {
            if (!has_filter('cron_schedules', ['Altego_Notify','cron_schedules'])) {
                add_filter('cron_schedules', ['Altego_Notify','cron_schedules']);
            }
            wp_schedule_event(time() + 60, 'altego_5min', 'altego_reminders_cron');
        }

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        foreach ($tables as $sql) { dbDelta($sql); }

        // создаем страницу booking manage если не существует
        $page = get_page_by_path('booking-manage');
        if (!$page) {
            $page_id = wp_insert_post([
                'post_title'   => 'Booking manage',
                'post_name'    => 'booking-manage',
                'post_status'  => 'publish',
                'post_type'    => 'page',
                'post_content' => '[altego_booking_manage]',
            ]);
            if ($page_id && !is_wp_error($page_id)) {
                update_option('altego_manage_page_id', intval($page_id));
            }
        }

        flush_rewrite_rules();
    }
}
