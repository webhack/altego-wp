<?php
if (!defined('ABSPATH')) { exit; }

class Altego_Import {
    public static function init() {
        add_action('admin_post_altego_import_csv', [__CLASS__, 'handle']);
        add_action('admin_menu', function(){
            add_submenu_page('altego', __('Import', 'altego-wp'), __('Import', 'altego-wp'), 'altego_schedule_edit', 'altego-import', [__CLASS__, 'render']);
        });
    }

    public static function render() {
        if (!current_user_can('altego_schedule_edit')) wp_die(__('Access denied', 'altego-wp'));
        echo '<div class="wrap"><h1>CSV import</h1>';
        echo '<form method="post" enctype="multipart/form-data" action="'.esc_url(admin_url('admin-post.php')).'">';
        wp_nonce_field('altego_import');
        echo '<input type="hidden" name="action" value="altego_import_csv" />';
        echo '<p><label>Type <select name="type"><option value="services">Services</option><option value="clients">Clients</option><option value="staff">Staff</option></select></label></p>';
        echo '<p><input type="file" name="csv" accept=".csv" required></p>';
        submit_button('Upload');
        echo '</form></div>';
    }

    public static function handle() {
        if (!current_user_can('altego_schedule_edit')) wp_die(__('Access denied', 'altego-wp'));
        check_admin_referer('altego_import');
        if (empty($_FILES['csv']['tmp_name'])) wp_die('No file');
        $type = sanitize_text_field($_POST['type']);
        $fh = fopen($_FILES['csv']['tmp_name'], 'r');
        $rows = 0;
        global $wpdb;
        while (($data = fgetcsv($fh, 0, ',')) !== false) {
            $rows++;
            if ($rows === 1) continue;
            if ($type === 'services') {
                $wpdb->insert("{$wpdb->prefix}altego_services", [
                    'name' => sanitize_text_field($data[0]),
                    'duration' => intval($data[1]),
                    'price' => floatval($data[2]),
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql'),
                ]);
            } elseif ($type === 'clients') {
                $wpdb->insert("{$wpdb->prefix}altego_clients", [
                    'name' => sanitize_text_field($data[0]),
                    'email' => sanitize_email($data[1]),
                    'phone' => sanitize_text_field($data[2]),
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql'),
                ]);
            } elseif ($type === 'staff') {
                $wpdb->insert("{$wpdb->prefix}altego_staff", [
                    'location_id' => intval($data[0]),
                    'name' => sanitize_text_field($data[1]),
                    'email' => sanitize_email($data[2]),
                    'phone' => sanitize_text_field($data[3]),
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql'),
                ]);
            }
        }
        fclose($fh);
        wp_safe_redirect(admin_url('admin.php?page=altego-import&imported=1'));
        exit;
    }
}
