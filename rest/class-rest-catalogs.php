<?php

if (!defined('ABSPATH')) {
    exit;
}

class Altego_REST_Catalogs extends WP_REST_Controller
{
    private $ns = 'altego/v1';

    public function register_routes()
    {
        register_rest_route($this->ns, '/services', [
            'methods' => 'GET',
            'callback' => [$this, 'services'],
            'permission_callback' => '__return_true'
        ]);
        register_rest_route($this->ns, '/staff', [
            'methods' => 'GET',
            'callback' => [$this, 'staff'],
            'permission_callback' => '__return_true'
        ]);
    }

    public function services(WP_REST_Request $r)
    {
        global $wpdb;
        $rows = $wpdb->get_results("SELECT id, name, duration, price FROM {$wpdb->prefix}altego_services WHERE active = 1 ORDER BY name ASC", ARRAY_A);
        return rest_ensure_response(['items' => $rows]);
    }

    public function staff(WP_REST_Request $r) {
        global $wpdb;
        $service_id = intval($r->get_param('service_id'));

        if ($service_id) {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT st.id, st.name, st.email, st.phone
       FROM {$wpdb->prefix}altego_staff st
       INNER JOIN {$wpdb->prefix}altego_service_staff ss ON ss.staff_id = st.id
       WHERE ss.service_id = %d AND st.active = 1
       ORDER BY st.name ASC", $service_id
            ), ARRAY_A);

            if (empty($rows)) {
                // Fallback если связей нет
                $rows = $wpdb->get_results(
                    "SELECT id, name, email, phone
         FROM {$wpdb->prefix}altego_staff
         WHERE active = 1
         ORDER BY name ASC", ARRAY_A
                );
            }
        } else {
            $rows = $wpdb->get_results(
                "SELECT id, name, email, phone
       FROM {$wpdb->prefix}altego_staff
       WHERE active = 1
       ORDER BY name ASC", ARRAY_A
            );
        }

        return rest_ensure_response(['items' => $rows]);
    }

}
