<?php
if (!defined('ABSPATH')) {
    exit;
}

class Altego_Roles
{
    public static function add_caps()
    {
        $caps = [
            'altego_schedule_view',
            'altego_schedule_edit',
            'altego_clients_edit',
            'altego_notifications_edit',
            'altego_reports_view',
            'altego_settings_edit',
        ];
        if ($role = get_role('administrator')) {
            foreach ($caps as $c) {
                $role->add_cap($c);
            }
        }
        add_role('altego_manager', 'Altego Manager', array_fill_keys($caps, true));
        add_role('altego_staff', 'Altego Staff', [
            'altego_schedule_view' => true,
        ]);
        add_role('altego_marketer', 'Altego Marketer', [
            'altego_reports_view' => true,
            'altego_notifications_edit' => true,
        ]);
    }

    public static function remove_roles()
    {
        remove_role('altego_manager');
        remove_role('altego_staff');
        remove_role('altego_marketer');
    }
}
