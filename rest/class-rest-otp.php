<?php
if (!defined('ABSPATH')) { exit; }

class Altego_REST_OTP extends WP_REST_Controller {
    private $ns = 'altego/v1';

    public function register_routes() {
        register_rest_route($this->ns, '/otp/send', [
            'methods'  => 'POST',
            'callback' => [$this, 'send'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($this->ns, '/otp/check', [
            'methods'  => 'POST',
            'callback' => [$this, 'check'],
            'permission_callback' => '__return_true',
        ]);
    }

    private function keys_from($email, $phone) {
        $keys = [];
        $p = preg_replace('~\D+~', '', (string) $phone);
        $e = strtolower(trim((string) $email));
        if ($e) $keys[] = 'e:' . $e;
        if ($p) $keys[] = 'p:' . $p;
        return $keys; // array of 0-2 keys
    }

    public function send(WP_REST_Request $r) {
        if (!Altego_Settings::get('otp_enabled')) {
            // OTP is disabled in settings. Return 200 so the frontend doesn't fail.
            return rest_ensure_response(['ok' => true, 'disabled' => true]);
        }

        $p = $r->get_json_params();
        $email = sanitize_email($p['email'] ?? '');
        $phone = sanitize_text_field($p['phone'] ?? '');
        $recaptcha = sanitize_text_field($p['recaptcha'] ?? '');

        // reCAPTCHA per settings
        if (!Altego_Guards::verify_recaptcha($recaptcha)) {
            return new WP_Error('captcha', 'reCAPTCHA validation failed', ['status' => 400]);
        }

        $keys = $this->keys_from($email, $phone);
        if (!$keys) {
            return new WP_Error('bad_request', 'Email or phone is required', ['status' => 400]);
        }

        $code = (string) random_int(100000, 999999);
        $ttl_min = max(1, intval(Altego_Settings::get('otp_lifetime')));
        foreach ($keys as $k) {
            set_transient('altego_otp_' . md5($k), $code, $ttl_min * MINUTE_IN_SECONDS);
        }

        // send code via email
        if ($email) {
            if (method_exists('Altego_Mailer', 'send_otp')) {
                Altego_Mailer::send_otp($email, $code, $ttl_min);
            } else {
                $subj = __('Your verification code', 'altego-wp');
                $msg  = sprintf(__('Your code is %s. It will expire in %d minutes.', 'altego-wp'), $code, $ttl_min);
                $headers = ['Content-Type: text/plain; charset=UTF-8'];
                wp_mail($email, $subj, $msg, $headers);
            }
        }

        // if you want SMS, add integration here

        return rest_ensure_response(['ok' => true, 'ttl' => $ttl_min * 60]);
    }

    public function check(WP_REST_Request $r) {
        $p = $r->get_json_params();
        $email = sanitize_email($p['email'] ?? '');
        $phone = sanitize_text_field($p['phone'] ?? '');
        $code  = sanitize_text_field($p['code']  ?? '');

        $keys = $this->keys_from($email, $phone);
        if (!$keys || !$code) {
            return new WP_Error('bad_request', 'Code and email or phone are required', ['status' => 400]);
        }

        $ok = false;
        foreach ($keys as $k) {
            $tkey = 'altego_otp_' . md5($k);
            $stored = get_transient($tkey);
            if ($stored && hash_equals((string)$stored, (string)$code)) {
                $ok = true;
                delete_transient($tkey);
                set_transient('altego_otp_ok_' . md5($k), 1, 15 * MINUTE_IN_SECONDS);
            }
        }

        if (!$ok) {
            return new WP_Error('otp_bad', 'Invalid or expired code', ['status' => 400]);
        }
        return rest_ensure_response(['ok' => true]);
    }

}
