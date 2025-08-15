<?php

if (!defined('ABSPATH')) {
    exit;
}

class Altego_OTP
{
    public static function init_routes()
    {
        add_action('rest_api_init', function () {
            register_rest_route('altego/v1', '/otp/request', [
                'methods' => 'POST',
                'callback' => [__CLASS__, 'request_code'],
                'permission_callback' => '__return_true'
            ]);
            register_rest_route('altego/v1', '/otp/verify', [
                'methods' => 'POST',
                'callback' => [__CLASS__, 'verify_code'],
                'permission_callback' => '__return_true'
            ]);
        });
    }

    private static function key($phone)
    {
        return 'altego_otp_' . md5($phone);
    }

    public static function request_code(WP_REST_Request $r) {
        $params = $r->get_json_params();
        $phone = sanitize_text_field($params['phone'] ?? '');
        $recaptcha = sanitize_text_field($params['recaptcha'] ?? '');

        // если reCAPTCHA включена то требуем валидный токен
        if (!Altego_Guards::verify_recaptcha($recaptcha)) {
            return new WP_Error('captcha', 'reCAPTCHA validation failed', ['status' => 400]);
        }

        if (empty(Altego_Settings::get('otp_enabled'))) {
            return new WP_Error('otp_disabled', 'OTP disabled by settings', ['status' => 400]);
        }

        if (!$phone) return new WP_Error('bad_request', 'Phone required', ['status' => 400]);

        $code = wp_rand(100000, 999999);
        $ttl = intval(Altego_Settings::get('otp_lifetime')) * MINUTE_IN_SECONDS;
        set_transient(self::key($phone), (string)$code, $ttl);

        $to = Altego_Settings::get('from_email');
        wp_mail($to, 'OTP code', "Phone: $phone Code: $code");

        return ['ok' => true];
    }


    public static function verify_code(WP_REST_Request $r)
    {
        $phone = sanitize_text_field($r['phone']);
        $code = sanitize_text_field($r['code']);
        $saved = get_transient(self::key($phone));
        if ($saved && hash_equals($saved, $code)) {
            delete_transient(self::key($phone));
            return ['ok' => true];
        }
        return new WP_Error('otp_invalid', 'Invalid code', ['status' => 400]);
    }
}
