<?php
/*
Plugin Name: Altego WP
Description: Online booking plugin inspired by Altegio. MVP without payments. Includes booking widget, schedule data model, REST API, notifications, client self-service links, admin catalogs and OTP verification.
Version: 0.2.0
Author: WHA
Requires at least: 6.0
Requires PHP: 7.4
*/

if (!defined('ABSPATH')) { exit; }

define('ALTEGO_WP_VERSION', '0.2.0');
define('ALTEGO_WP_DIR', plugin_dir_path(__FILE__));
define('ALTEGO_WP_URL', plugin_dir_url(__FILE__));

// ====== Includes ======
require_once ALTEGO_WP_DIR . 'includes/class-activator.php';
require_once ALTEGO_WP_DIR . 'includes/class-deactivator.php';
require_once ALTEGO_WP_DIR . 'includes/class-repo.php';
require_once ALTEGO_WP_DIR . 'includes/class-booking-widget.php';
require_once ALTEGO_WP_DIR . 'includes/class-notify.php';
require_once ALTEGO_WP_DIR . 'includes/class-roles.php';
require_once ALTEGO_WP_DIR . 'includes/class-settings.php';
require_once ALTEGO_WP_DIR . 'includes/class-catalogs.php';
require_once ALTEGO_WP_DIR . 'includes/class-otp.php';
require_once ALTEGO_WP_DIR . 'includes/class-mailer.php';
require_once ALTEGO_WP_DIR . 'includes/class-import.php';
require_once ALTEGO_WP_DIR . 'includes/class-ics.php';
require_once ALTEGO_WP_DIR . 'includes/class-guards.php';
require_once ALTEGO_WP_DIR . 'rest/class-rest-catalogs.php';
require_once ALTEGO_WP_DIR . 'includes/class-upgrade.php';
require_once ALTEGO_WP_DIR . 'includes/class-workhours.php';
require_once ALTEGO_WP_DIR . 'includes/class-manage-page.php';
require_once ALTEGO_WP_DIR . 'rest/class-rest-otp.php';
require_once ALTEGO_WP_DIR . 'rest/class-rest-appointments.php';

// ====== Activation & Deactivation ======
register_activation_hook(__FILE__, function(){
    Altego_Activator::activate();
    Altego_Roles::add_caps();
});
register_deactivation_hook(__FILE__, function(){
    Altego_Deactivator::deactivate();
    Altego_Roles::remove_roles();
});

// ====== Init ======
add_action('plugins_loaded', function(){
    load_plugin_textdomain('altego-wp', false, dirname(plugin_basename(__FILE__)) . '/languages');

    Altego_Settings::init();
    Altego_OTP::init_routes();
    Altego_Import::init();
    Altego_ICS::init();
    Altego_Workhours::init();
    Altego_Notify::init();
    Altego_Manage_Page::init();
    Altego_Upgrade::run();
});

// ====== REST API ======
add_action('rest_api_init', function(){
    (new Altego_REST_Appointments())->register_routes();
    (new Altego_REST_Catalogs())->register_routes();
    (new Altego_REST_OTP())->register_routes();
});

// ====== Shortcode and assets ======
add_action('init', ['Altego_Booking_Widget', 'register']);

// ====== Public page for client manage ======
add_action('init', function(){
    add_rewrite_rule('^booking-manage/?$', 'index.php?altego_manage=1', 'top');
});
add_filter('query_vars', function($vars){
    $vars[] = 'altego_manage';
    return $vars;
});
add_action('template_redirect', function(){
    if (get_query_var('altego_manage') && !get_page_by_path('booking-manage')) {
        status_header(200);
        include ALTEGO_WP_DIR . 'templates/manage.php';
        exit;
    }
});

// ====== Admin Menu ======
add_action('admin_menu', function(){
    // This is handled inside Altego_Settings::menu()
});
