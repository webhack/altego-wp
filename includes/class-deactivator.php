<?php
if (!defined('ABSPATH')) { exit; }
class Altego_Deactivator {
    public static function deactivate() {
        wp_clear_scheduled_hook('altego_reminders_cron');
        flush_rewrite_rules();
    }
}
