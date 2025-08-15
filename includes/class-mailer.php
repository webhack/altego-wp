<?php

if (!defined('ABSPATH')) {
    exit;
}

class Altego_Mailer
{
    public static function send_confirmation($appointment_id)
    {
        $a = self::get_data($appointment_id);
        if (!$a) return;
        $to = $a['client_email'] ?: Altego_Settings::get('from_email');
        $subj = sprintf(__('Appointment confirmed %s', 'altego-wp'), $a['service']);
        $body = sprintf(
            "%s, %s %s. %s: %s. %s: %s",
            $a['client_name'] ?: __('Client', 'altego-wp'),
            $a['date'], $a['start'],
            __('Service', 'altego-wp'), $a['service'],
            __('Manage', 'altego-wp'), $a['manage_url']
        );
        wp_mail($to, $subj, $body);
    }

    private static function get_data($id)
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT a.*, s.name as service_name, c.name as client_name, c.email as client_email
       FROM {$wpdb->prefix}altego_appointments a
       LEFT JOIN {$wpdb->prefix}altego_services s ON s.id = a.service_id
       LEFT JOIN {$wpdb->prefix}altego_clients c ON c.id = a.client_id
       WHERE a.id = %d", $id
        ), ARRAY_A);
        if (!$row) return null;
        $token = get_transient("altego_token_$id");
        if (!$token) $token = wp_generate_password(32, false, false);
        $manage = add_query_arg(['a' => $id, 't' => $token], site_url('/booking-manage'));
        return [
            'date' => $row['date'],
            'start' => $row['start'],
            'service' => $row['service_name'],
            'client_name' => $row['client_name'],
            'client_email' => $row['client_email'],
            'manage_url' => $manage
        ];
    }

    public static function send_reminder($appointment_id) {
        global $wpdb;
        $a = $wpdb->get_row($wpdb->prepare("
      SELECT a.id, a.date, a.time_start AS start, a.time_end AS end,
             s.name AS service_name, st.name AS staff_name,
             c.name AS client_name, c.email AS client_email, c.phone AS client_phone
      FROM {$wpdb->prefix}altego_appointments a
      LEFT JOIN {$wpdb->prefix}altego_services s ON s.id = a.service_id
      LEFT JOIN {$wpdb->prefix}altego_staff st ON st.id = a.staff_id
      LEFT JOIN {$wpdb->prefix}altego_clients c ON c.id = a.client_id
      WHERE a.id = %d
    ", $appointment_id), ARRAY_A);
        if (!$a) return false;

        $opts = Altego_Settings::get();
        $to   = $a['client_email'] ?: $opts['from_email'];
        if (!$to) return false;

        $manage_url = add_query_arg(['a' => $appointment_id, 't' => get_transient("altego_token_$appointment_id")], site_url('/booking-manage'));

        $vars = [
            '{client_name}'   => $a['client_name'] ?: '',
            '{client_phone}'  => $a['client_phone'] ?: '',
            '{client_email}'  => $a['client_email'] ?: '',
            '{service_name}'  => $a['service_name'] ?: '',
            '{staff_name}'    => $a['staff_name'] ?: '',
            '{date}'          => $a['date'],
            '{start}'         => $a['start'],
            '{end}'           => $a['end'],
            '{manage_url}'    => $manage_url,
            '{site_name}'     => get_bloginfo('name'),
        ];

        $subject = strtr($opts['reminder_email_subject'], $vars);
        $body    = strtr($opts['reminder_email_template'], $vars);

        $headers = ['Content-Type: text/plain; charset=UTF-8'];
        return wp_mail($to, $subject, $body, $headers);
    }

    public static function send_otp($email, $code, $ttl_min = 10) {
        if (!$email || !$code) return false;
        $subject = __('Your verification code', 'altego-wp');
        $message = sprintf(__('Your code is %s. It will expire in %d minutes.', 'altego-wp'), $code, $ttl_min);
        $headers = ['Content-Type: text/plain; charset=UTF-8'];
        $from = Altego_Settings::get('from_email');
        if ($from) $headers[] = 'From: '. get_bloginfo('name') .' <'.$from.'>';
        return wp_mail($email, $subject, $message, $headers);
    }
}
