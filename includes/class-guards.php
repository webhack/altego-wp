<?php
if (!defined('ABSPATH')) {
    exit;
}

class Altego_Guards
{
    public static function rate_limit($key, $limit, $window)
    {
        $now = time();
        $bucket = get_transient($key);
        if (!$bucket) $bucket = ['count' => 0, 'reset' => $now + $window];
        if ($now > $bucket['reset']) $bucket = ['count' => 0, 'reset' => $now + $window];
        if ($bucket['count'] >= $limit) return false;
        $bucket['count']++;
        set_transient($key, $bucket, $bucket['reset'] - $now);
        return true;
    }

    public static function verify_recaptcha($response) {
        $enabled = !empty(Altego_Settings::get('recaptcha_enabled'));
        $secret  = trim((string)Altego_Settings::get('recaptcha_secret_key'));
        if (!$enabled) return true;
        if (!$secret) return false;
        if (!$response) return false;

        $res = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', [
            'timeout' => 8,
            'body' => [
                'secret' => $secret,
                'response' => $response,
                'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
            ]
        ]);
        if (is_wp_error($res)) return false;
        $body = json_decode(wp_remote_retrieve_body($res), true);
        return !empty($body['success']);
    }

}
