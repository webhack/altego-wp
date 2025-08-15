<?php
if (!defined('ABSPATH')) { exit; }
global $wpdb;

$appointment_id = isset($_GET['a']) ? intval($_GET['a']) : 0;
$token = isset($_GET['t']) ? sanitize_text_field($_GET['t']) : '';

$ok = false;
if ($appointment_id && $token) {
    $saved = get_transient("altego_token_$appointment_id");
    if ($saved && hash_equals($saved, $token)) $ok = true;
}

get_header();
echo '<div class="wrap">';
if (!$ok) {
    echo '<h2>Link invalid or expired</h2><p>Please request a new link from the administrator.</p>';
} else {
    $apt = $wpdb->get_row($wpdb->prepare(
        "SELECT id, date, time_start AS start, time_end AS end, status
     FROM {$wpdb->prefix}altego_appointments WHERE id = %d", $appointment_id
    ));
    if ($apt) {
        echo '<h2>Manage appointment</h2>';
        echo '<p>ID: ' . esc_html($apt->id) . '</p>';
        echo '<p>Date: ' . esc_html($apt->date) . '</p>';
        echo '<p>Time: ' . esc_html($apt->start) . ' - ' . esc_html($apt->end) . '</p>';
        echo '<p>Status: ' . esc_html($apt->status) . '</p>';
        echo '<p><a href="'.esc_url(home_url('/altego-ics/'.$apt->id)).'">Add to calendar</a></p>';

        echo '<form method="post">';
        wp_nonce_field('altego_cancel_'.$apt->id);
        echo '<input type="hidden" name="altego_action" value="cancel">';
        echo '<p><button type="submit" class="button button-primary">Cancel appointment</button></p>';
        echo '</form>';
    } else {
        echo '<p>Appointment not found.</p>';
    }
}
echo '</div>';
get_footer();
