<?php

if (!defined('ABSPATH')) {
    exit;
}

class Altego_ICS
{
    public static function init()
    {
        add_action('init', [__CLASS__, 'rewrite']);
        add_action('template_redirect', [__CLASS__, 'serve']);
    }

    public static function rewrite()
    {
        add_rewrite_rule('^altego-ics/([0-9]+)/?', 'index.php?altego_ics=$matches[1]', 'top');
        add_filter('query_vars', function ($vars) {
            $vars[] = 'altego_ics';
            return $vars;
        });
    }

    public static function serve()
    {
        $id = get_query_var('altego_ics');
        if (!$id) return;
        global $wpdb;
        $a = $wpdb->get_row($wpdb->prepare(
            "SELECT id, date, time_start AS start, time_end AS end
   FROM {$wpdb->prefix}altego_appointments WHERE id = %d", $id
        ), ARRAY_A);


        if (!$a) {
            status_header(404);
            exit;
        }
        $dtstart = gmdate('Ymd\THis\Z', strtotime($a['date'] . ' ' . $a['start']));
        $dtend = gmdate('Ymd\THis\Z', strtotime($a['date'] . ' ' . $a['end']));
        $uid = 'apt-' . $a['id'] . '@' . parse_url(home_url(), PHP_URL_HOST);
        $ics = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VEVENT\r\nUID:$uid\r\nDTSTART:$dtstart\r\nDTEND:$dtend\r\nSUMMARY:Appointment\r\nEND:VEVENT\r\nEND:VCALENDAR";
        nocache_headers();
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="appointment-' . $a['id'] . '.ics"');
        echo $ics;
        exit;
    }
}
