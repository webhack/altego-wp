<?php
if (!defined('ABSPATH')) { exit; }

class Altego_Booking_Widget {
  public static function register() {
    add_shortcode('altego_booking', [__CLASS__, 'render']);
    add_action('wp_enqueue_scripts', [__CLASS__, 'assets']);
  }
    public static function assets() {
        wp_enqueue_style('altego-booking', ALTEGO_WP_URL . 'assets/css/booking.css', [], ALTEGO_WP_VERSION);
        wp_enqueue_script('altego-booking', ALTEGO_WP_URL . 'assets/js/booking.js', ['wp-element', 'wp-i18n'], ALTEGO_WP_VERSION, true);

        $cfg = [
            'rest'  => esc_url_raw(rest_url('altego/v1')),
            'nonce' => wp_create_nonce('wp_rest'),
            'otpEnabled' => !empty(Altego_Settings::get('otp_enabled')),
            'recaptcha' => [
                'enabled' => !empty(Altego_Settings::get('recaptcha_enabled')),
                'siteKey' => (string)Altego_Settings::get('recaptcha_site_key'),
            ],
        ];
        wp_localize_script('altego-booking', 'AltegoConfig', $cfg);

        if (!empty($cfg['recaptcha']['enabled']) && !empty($cfg['recaptcha']['siteKey'])) {
            wp_enqueue_script('google-recaptcha', 'https://www.google.com/recaptcha/api.js', [], null, true);
        }
    }

    public static function render($atts = []) {
        // подключаем стили и js
        wp_enqueue_style('altego-booking', ALTEGO_WP_URL.'assets/css/booking.css', [], ALTEGO_WP_VERSION);
        $deps = [];
        // reCAPTCHA v3 если включено
        $site_key = Altego_Settings::get('recaptcha_site_key');
        if (Altego_Settings::get('recaptcha_enabled') && $site_key) {
            wp_enqueue_script('grecaptcha', 'https://www.google.com/recaptcha/api.js?render='.$site_key, [], null, true);
        }
        wp_enqueue_script('altego-booking', ALTEGO_WP_URL.'assets/js/booking.js', $deps, ALTEGO_WP_VERSION, true);

        // прокидываем настройки во фронт
        wp_localize_script('altego-booking', 'AltegoBooking', [
            'api' => [
                'services' => rest_url('altego/v1/services'),
                // стафф для услуги. Поддержим оба варианта для совместимости
                'staff'    => rest_url('altego/v1/staff'),
                'slots'    => rest_url('altego/v1/slots'),
                'create'   => rest_url('altego/v1/appointments'),
                // если у вас есть отдельные эндпоинты для OTP то раскомментируйте и подставьте
                'otp_send' => rest_url('altego/v1/otp/send'),
                'otp_check'=> rest_url('altego/v1/otp/check'),
            ],
            'recaptcha' => [
                'enabled' => (bool)Altego_Settings::get('recaptcha_enabled'),
                'siteKey' => $site_key ? $site_key : '',
            ],
            'otp' => [
                'enabled' => (bool)Altego_Settings::get('otp_enabled'),
                'lifetimeMin' => intval(Altego_Settings::get('otp_lifetime')),
            ],
            'labels' => [
                'appointmentCreated' => __('Appointment created', 'altego-wp'),
                'manageLink' => __('Manage link', 'altego-wp'),
                'sendCode' => __('Send code', 'altego-wp'),
                'verify' => __('Verify', 'altego-wp'),
            ],
        ]);
    ob_start();
    include ALTEGO_WP_DIR . 'templates/booking-form.php';
    return ob_get_clean();
  }
}
