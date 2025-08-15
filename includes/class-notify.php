<?php
if (!defined('ABSPATH')) { exit; }

class Altego_Notify {

    public static function init() {
        // крон каждые 5 минут
        add_filter('cron_schedules', [__CLASS__, 'cron_schedules']);
        add_action('altego_reminders_cron', [__CLASS__, 'run_cron']);
        add_action('admin_init', [__CLASS__, 'ensure_cron']);
    }

    public static function cron_schedules($s) {
        if (!isset($s['altego_5min'])) {
            $s['altego_5min'] = ['interval' => 300, 'display' => 'Altego every 5 minutes'];
        }
        return $s;
    }

    public static function ensure_cron() {
        if (!wp_next_scheduled('altego_reminders_cron')) {
            wp_schedule_event(time() + 60, 'altego_5min', 'altego_reminders_cron');
        }
    }

    public static function send($type, $appointment_id) {
        do_action('altego_notify_log', $type, $appointment_id);
    }

    public static function render_settings() {
        if (!current_user_can('altego_notifications_edit')) wp_die(__('Access denied', 'altego-wp'));
        $opt = Altego_Settings::get();
        echo '<div class="wrap"><h1>Notifications</h1>';
        echo '<form method="post" action="'.esc_url(admin_url('options.php')).'">';
        settings_fields('altego_settings_group');

        echo '<h2 class="title">Verification code and anti spam</h2>';
        $otp_checked = !empty($opt['otp_enabled']) ? 'checked' : '';
        echo '<p><label><input type="checkbox" name="altego_settings[otp_enabled]" value="1" '.$otp_checked.'> Require phone verification code</label></p>';
        $otp_lt = esc_attr($opt['otp_lifetime']);
        echo '<p><label>OTP lifetime minutes <input type="number" min="1" max="60" name="altego_settings[otp_lifetime]" value="'.$otp_lt.'"></label></p>';

        $rc_checked = !empty($opt['recaptcha_enabled']) ? 'checked' : '';
        echo '<hr><p><label><input type="checkbox" name="altego_settings[recaptcha_enabled]" value="1" '.$rc_checked.'> Enable Google reCAPTCHA</label></p>';
        $site = esc_attr($opt['recaptcha_site_key']);
        $sec  = esc_attr($opt['recaptcha_secret_key']);
        echo '<p><label>reCAPTCHA site key <input class="regular-text" name="altego_settings[recaptcha_site_key]" value="'.$site.'"></label></p>';
        echo '<p><label>reCAPTCHA secret key <input class="regular-text" name="altego_settings[recaptcha_secret_key]" value="'.$sec.'"></label></p>';

        echo '<hr><h2 class="title">Reminders</h2>';
        $rem_on = !empty($opt['reminder_email_enabled']) ? 'checked' : '';
        echo '<p><label><input type="checkbox" name="altego_settings[reminder_email_enabled]" value="1" '.$rem_on.'> Send email reminders</label></p>';

        $lead = esc_attr($opt['reminder_email_lead_min']);
        echo '<p><label>Lead time minutes before start <input type="number" min="5" step="5" name="altego_settings[reminder_email_lead_min]" value="'.$lead.'"></label></p>';

        $subj = esc_attr($opt['reminder_email_subject']);
        echo '<p><label>Reminder subject <input class="regular-text" name="altego_settings[reminder_email_subject]" value="'.$subj.'"></label></p>';

        $tpl  = esc_textarea($opt['reminder_email_template']);
        echo '<p><label>Reminder message<br><textarea name="altego_settings[reminder_email_template]" rows="10" class="large-text code">'.$tpl.'</textarea></label></p>';

        echo '<p>Placeholders: {client_name} {client_phone} {client_email} {service_name} {staff_name} {date} {start} {end} {manage_url} {site_name}</p>';

        submit_button('Save settings');
        echo '</form>';
        echo '</div>';
    }

    public static function run_cron() {
        $opt = Altego_Settings::get();
        if (empty($opt['reminder_email_enabled'])) return;

        $lead = intval($opt['reminder_email_lead_min']);
        if ($lead < 5) $lead = 5;

        global $wpdb;
        $now = current_time('mysql');

        // due_time = start_time - lead minutes
        $sql = "
      SELECT a.id
      FROM {$wpdb->prefix}altego_appointments a
      LEFT JOIN {$wpdb->prefix}altego_reminders_log r
        ON r.appointment_id = a.id AND r.type = 'email'
      WHERE a.status = 'confirmed'
        AND r.id IS NULL
        AND TIMESTAMPADD(MINUTE, -%d, CONCAT(a.date,' ', a.time_start)) <= %s
        AND CONCAT(a.date,' ', a.time_start) > %s
      ORDER BY a.date ASC, a.time_start ASC
      LIMIT 200
    ";
        $ids = $wpdb->get_col($wpdb->prepare($sql, $lead, $now, $now));

        if (!$ids) return;

        foreach ($ids as $id) {
            $ok = Altego_Mailer::send_reminder(intval($id));
            if ($ok) {
                $wpdb->insert("{$wpdb->prefix}altego_reminders_log", [
                    'appointment_id' => intval($id),
                    'type' => 'email',
                    'sent_at' => current_time('mysql'),
                ]);
            }
        }
    }
}
