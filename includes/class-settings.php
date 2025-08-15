<?php

if (!defined('ABSPATH')) {
    exit;
}

class Altego_Settings
{
    const OPTION = 'altego_settings';

    public static function init()
    {
        add_action('admin_menu', [__CLASS__, 'menu']);
        add_action('admin_init', [__CLASS__, 'register']);
    }

    public static function defaults() {
        return [
            'timezone' => 'Europe/Kyiv',
            'slot_step' => 15,
            'otp_lifetime' => 10,
            'token_lifetime_hours' => 48,
            'reminder_email_hrs' => 24,
            'reminder_sms_hrs' => 2,
            'country' => 'UA',
            'from_email' => get_bloginfo('admin_email'),
            'otp_enabled' => '1',
            'recaptcha_enabled' => '0',
            'recaptcha_site_key' => '',
            'recaptcha_secret_key' => '',
            'reschedule_url' => '',
            // напоминания
            'reminder_email_enabled' => '1',
            'reminder_email_lead_min' => 1440, // 24h
            'reminder_email_subject' => 'Reminder: your appointment on {date} at {start}',
            'reminder_email_template' =>
                "Hello {client_name},\n\n" .
                "This is a reminder about your appointment:\n" .
                "Service: {service_name}\n" .
                "Staff: {staff_name}\n" .
                "Date: {date}\n" .
                "Time: {start} to {end}\n\n" .
                "Manage: {manage_url}\n\n" .
                "Regards,\n{site_name}",
        ];
    }


    public static function get($key = null)
    {
        $opts = wp_parse_args(get_option(self::OPTION, []), self::defaults());
        return $key ? ($opts[$key] ?? null) : $opts;
    }

    public static function register()
    {
        register_setting('altego_settings_group', self::OPTION, [
            'type' => 'array',
            'sanitize_callback' => function ($v) {
                return is_array($v) ? array_map('sanitize_text_field', $v) : [];
            },
            'default' => self::defaults()
        ]);

        add_settings_section('altego_main', __('Main', 'altego-wp'), '__return_false', 'altego_settings');

        add_settings_field('timezone', __('Timezone', 'altego-wp'), function () {
            $v = esc_attr(self::get('timezone'));
            echo "<input name='" . self::OPTION . "[timezone]' value='$v' class='regular-text' />";
        }, 'altego_settings', 'altego_main');

        add_settings_field('slot_step', __('Slot step minutes', 'altego-wp'), function () {
            $v = esc_attr(self::get('slot_step'));
            echo "<input type='number' name='" . self::OPTION . "[slot_step]' value='$v' min='5' max='60' />";
        }, 'altego_settings', 'altego_main');

        add_settings_field('token_lifetime_hours', __('Client link lifetime hours', 'altego-wp'), function () {
            $v = esc_attr(self::get('token_lifetime_hours'));
            echo "<input type='number' name='" . self::OPTION . "[token_lifetime_hours]' value='$v' min='1' max='168' />";
        }, 'altego_settings', 'altego_main');

        add_settings_field('from_email', __('From email', 'altego-wp'), function () {
            $v = esc_attr(self::get('from_email'));
            echo "<input type='email' name='" . self::OPTION . "[from_email]' value='$v' class='regular-text' />";
        }, 'altego_settings', 'altego_main');
    }

    public static function menu()
    {
        add_menu_page('Altego', 'Altego', 'altego_schedule_view', 'altego', [__CLASS__, 'render_dashboard'], 'dashicons-calendar-alt', 26);
        add_submenu_page('altego', __('Schedule', 'altego-wp'), __('Schedule', 'altego-wp'), 'altego_schedule_view', 'altego', [__CLASS__, 'render_dashboard']);
        add_submenu_page('altego', __('Catalogs', 'altego-wp'), __('Catalogs', 'altego-wp'), 'altego_schedule_edit', 'altego-catalogs', ['Altego_Catalogs', 'render']);
        add_submenu_page('altego', __('Notifications', 'altego-wp'), __('Notifications', 'altego-wp'), 'altego_notifications_edit', 'altego-notify', ['Altego_Notify', 'render_settings']);
        add_submenu_page('altego', __('Settings', 'altego-wp'), __('Settings', 'altego-wp'), 'altego_settings_edit', 'altego-settings', [__CLASS__, 'render_settings']);
    }

    public static function render_dashboard() {
        echo '<div class="wrap"><h1>Schedule</h1>
  <div id="altego-admin-app"></div></div>';
        wp_enqueue_style('altego-admin', ALTEGO_WP_URL . 'admin/index.css', [], ALTEGO_WP_VERSION);
        wp_enqueue_script('altego-admin', ALTEGO_WP_URL . 'admin/index.js', [], ALTEGO_WP_VERSION, true);
        wp_localize_script('altego-admin', 'AltegoAdmin', [
            'rest' => esc_url_raw(rest_url('altego/v1')),
            'nonce' => wp_create_nonce('wp_rest')
        ]);
    }


    public static function render_settings()
    {
        echo '<div class="wrap"><h1>Altego Settings</h1><form method="post" action="options.php">';
        settings_fields('altego_settings_group');
        do_settings_sections('altego_settings');
        echo '<h2 class="title">Booking manage</h2>';
        $val = esc_attr(Altego_Settings::get('reschedule_url'));
        echo '<p><label>Reschedule URL <input class="regular-text" name="altego_settings[reschedule_url]" value="'.$val.'" placeholder="https://your-site.com/reschedule"></label></p>';
        echo '<p class="description">Можно указать любую страницу. Мы добавим параметры a, t, date, start, end, staff_id, service_id.</p>';
        submit_button();
        echo '</form></div>';

    }
}
