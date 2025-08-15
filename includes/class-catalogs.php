<?php
if (!defined('ABSPATH')) { exit; }

class Altego_Catalogs {
    public static function init() {
        add_submenu_page('altego', 'Catalogs', 'Catalogs', 'altego_schedule_edit', 'altego-catalogs', [__CLASS__, 'render']);
    }

    public static function render() {
        if (!current_user_can('altego_schedule_edit')) wp_die(__('Access denied', 'altego-wp'));

        $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'services';
        $tabs = [
            'services'  => 'Services',
            'staff'     => 'Staff',
            'locations' => 'Locations',
            'links'     => 'Service–Staff',
        ];

        echo '<div class="wrap"><h1>Catalogs</h1><h2 class="nav-tab-wrapper">';
        foreach ($tabs as $k => $label) {
            $cls = $tab === $k ? ' nav-tab-active' : '';
            echo '<a class="nav-tab'.$cls.'" href="'.esc_url(add_query_arg(['tab'=>$k])).'">'.esc_html($label).'</a>';
        }
        echo '</h2>';

        switch ($tab) {
            case 'staff':     self::staff_ui(); break;
            case 'locations': self::locations_ui(); break;
            case 'links':     self::links_ui(); break;
            case 'services':
            default:          self::services_ui(); break;
        }

        echo '</div>';
    }

    /* ============ SERVICES ============ */

    private static function services_ui() {
        global $wpdb;
        $table = "{$wpdb->prefix}altego_services";

        // Save service
        if (isset($_POST['altego_service_save']) && check_admin_referer('altego_service_save')) {
            $id   = intval($_POST['id'] ?? 0);
            $data = [
                'name'      => sanitize_text_field($_POST['name'] ?? ''),
                'duration'  => intval($_POST['duration'] ?? 0),
                'price'     => floatval($_POST['price'] ?? 0),
                'active'    => empty($_POST['active']) ? 0 : 1,
                'updated_at'=> current_time('mysql'),
            ];
            if ($id) {
                $wpdb->update($table, $data, ['id' => $id]);
                echo '<div class="updated"><p>Service updated</p></div>';
            } else {
                $data['created_at'] = current_time('mysql');
                $wpdb->insert($table, $data);
                echo '<div class="updated"><p>Service added</p></div>';
            }
        }

        // Toggle active
        if (isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'toggle' && check_admin_referer('altego_srv_toggle_'.intval($_GET['id']))) {
            $id = intval($_GET['id']);
            $cur = $wpdb->get_var($wpdb->prepare("SELECT active FROM $table WHERE id=%d", $id));
            $wpdb->update($table, ['active' => $cur ? 0 : 1, 'updated_at' => current_time('mysql')], ['id' => $id]);
            echo '<div class="updated"><p>Status changed</p></div>';
        }

        // Delete safely
        if (isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'delete' && check_admin_referer('altego_srv_delete_'.intval($_GET['id']))) {
            $id = intval($_GET['id']);
            $used = intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}altego_appointments WHERE service_id=%d", $id)));
            if ($used > 0) {
                echo '<div class="error"><p>Cannot delete. There are appointments using this service. Deactivate it instead.</p></div>';
            } else {
                // remove links and delete
                $wpdb->delete("{$wpdb->prefix}altego_service_staff", ['service_id' => $id]);
                $wpdb->delete($table, ['id' => $id]);
                echo '<div class="updated"><p>Service deleted</p></div>';
            }
        }

        // Edit row or empty for new
        $edit = null;
        if (isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'edit') {
            $edit = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d", intval($_GET['id'])));
        }

        // Form
        echo '<h2>'.($edit?'Edit service':'Add service').'</h2>';
        echo '<form method="post" style="max-width:640px">';
        wp_nonce_field('altego_service_save');
        echo '<input type="hidden" name="altego_service_save" value="1">';
        echo '<input type="hidden" name="id" value="'.esc_attr($edit->id ?? 0).'">';
        echo '<p><label>Name <input class="regular-text" name="name" value="'.esc_attr($edit->name ?? '').'" required></label></p>';
        echo '<p><label>Duration minutes <input type="number" min="0" step="5" name="duration" value="'.esc_attr($edit->duration ?? 0).'"></label></p>';
        echo '<p><label>Price <input type="number" step="0.01" min="0" name="price" value="'.esc_attr($edit->price ?? 0).'"></label></p>';
        $checked = isset($edit) ? intval($edit->active) : 1;
        echo '<p><label><input type="checkbox" name="active" value="1" '.checked($checked,1,false).'> Active</label></p>';
        submit_button($edit?'Update service':'Add service');

        echo '</form>';

        // List
        $rows = $wpdb->get_results("SELECT * FROM $table ORDER BY active DESC, name ASC");
        echo '<h2>List</h2>';
        echo '<table class="widefat striped"><thead><tr><th>ID</th><th>Name</th><th>Duration</th><th>Price</th><th>Status</th><th style="width:220px">Actions</th></tr></thead><tbody>';
        foreach ($rows as $r) {
            $edit_link = wp_nonce_url(add_query_arg(['tab'=>'services','action'=>'edit','id'=>$r->id]), 'x');
            $toggle_link = wp_nonce_url(add_query_arg(['tab'=>'services','action'=>'toggle','id'=>$r->id]), 'altego_srv_toggle_'.$r->id);
            $delete_link = wp_nonce_url(add_query_arg(['tab'=>'services','action'=>'delete','id'=>$r->id]), 'altego_srv_delete_'.$r->id);
            echo '<tr>';
            echo '<td>'.esc_html($r->id).'</td>';
            echo '<td>'.esc_html($r->name).'</td>';
            echo '<td>'.esc_html($r->duration).' min</td>';
            echo '<td>'.esc_html(number_format_i18n(floatval($r->price),2)).'</td>';
            echo '<td>'.($r->active?'active':'inactive').'</td>';
            echo '<td><a class="button" href="'.esc_url($edit_link).'">Edit</a> ';
            echo '<a class="button" href="'.esc_url($toggle_link).'">'.($r->active?'Deactivate':'Activate').'</a> ';
            echo '<a class="button button-link-delete" href="'.esc_url($delete_link).'" onclick="return confirm(\'Delete permanently?\')">Delete</a></td>';
            echo '</tr>';
        }
        if (!$rows) echo '<tr><td colspan="6">No services</td></tr>';
        echo '</tbody></table>';
    }

    /* ============ STAFF ============ */

    private static function staff_ui() {
        global $wpdb;
        $table = "{$wpdb->prefix}altego_staff";
        $locs  = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}altego_locations ORDER BY name ASC");

        // Save
        if (isset($_POST['altego_staff_save']) && check_admin_referer('altego_staff_save')) {
            $id = intval($_POST['id'] ?? 0);
            $data = [
                'location_id'  => intval($_POST['location_id'] ?? 0),
                'name'         => sanitize_text_field($_POST['name'] ?? ''),
                'title'        => sanitize_text_field($_POST['title'] ?? ''),
                'email'        => sanitize_email($_POST['email'] ?? ''),
                'phone'        => sanitize_text_field($_POST['phone'] ?? ''),
                'avatar_url'   => esc_url_raw($_POST['avatar_url'] ?? ''),
                'rating'       => floatval($_POST['rating'] ?? 0),
                'reviews_count'=> intval($_POST['reviews_count'] ?? 0),
                'active'       => empty($_POST['active']) ? 0 : 1,
                'updated_at'   => current_time('mysql'),
            ];
            if ($id) {
                $wpdb->update($table, $data, ['id' => $id]);
                echo '<div class="updated"><p>Staff updated</p></div>';
            } else {
                $data['created_at'] = current_time('mysql');
                $wpdb->insert($table, $data);
                echo '<div class="updated"><p>Staff added</p></div>';
            }
        }

        // Toggle
        if (isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'toggle' && check_admin_referer('altego_st_toggle_'.intval($_GET['id']))) {
            $id = intval($_GET['id']);
            $cur = $wpdb->get_var($wpdb->prepare("SELECT active FROM $table WHERE id=%d", $id));
            $wpdb->update($table, ['active' => $cur ? 0 : 1, 'updated_at'=>current_time('mysql')], ['id'=>$id]);
            echo '<div class="updated"><p>Status changed</p></div>';
        }

        // Delete safely
        if (isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'delete' && check_admin_referer('altego_st_delete_'.intval($_GET['id']))) {
            $id = intval($_GET['id']);
            $used = intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}altego_appointments WHERE staff_id=%d", $id)));
            if ($used > 0) {
                echo '<div class="error"><p>Cannot delete. There are appointments for this staff. Deactivate it instead.</p></div>';
            } else {
                $wpdb->delete("{$wpdb->prefix}altego_service_staff", ['staff_id' => $id]);
                $wpdb->delete("{$wpdb->prefix}altego_staff_schedule", ['staff_id' => $id]);
                $wpdb->delete($table, ['id' => $id]);
                echo '<div class="updated"><p>Staff deleted</p></div>';
            }
        }

        // Edit row
        $edit = null;
        if (isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'edit') {
            $edit = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d", intval($_GET['id'])));
        }

        // Form
        echo '<h2>'.($edit?'Edit staff':'Add staff').'</h2>';
        echo '<form method="post" style="max-width:760px">';
        wp_nonce_field('altego_staff_save');
        echo '<input type="hidden" name="altego_staff_save" value="1">';
        echo '<input type="hidden" name="id" value="'.esc_attr($edit->id ?? 0).'">';

        echo '<p><label>Location <select name="location_id">';
        foreach ($locs as $l) {
            $sel = selected(intval($edit->location_id ?? 0), $l->id, false);
            echo '<option value="'.esc_attr($l->id).'" '.$sel.'>'.esc_html($l->name).'</option>';
        }
        echo '</select></label></p>';

        echo '<p><label>Name <input class="regular-text" name="name" value="'.esc_attr($edit->name ?? '').'" required></label></p>';
        echo '<p><label>Title <input class="regular-text" name="title" value="'.esc_attr($edit->title ?? '').'"></label></p>';
        echo '<p><label>Email <input class="regular-text" name="email" value="'.esc_attr($edit->email ?? '').'"></label></p>';
        echo '<p><label>Phone <input class="regular-text" name="phone" value="'.esc_attr($edit->phone ?? '').'"></label></p>';
        echo '<p><label>Avatar URL <input class="regular-text" name="avatar_url" value="'.esc_attr($edit->avatar_url ?? '').'"></label></p>';
        echo '<p><label>Rating <input type="number" step="0.01" min="0" max="5" name="rating" value="'.esc_attr($edit->rating ?? 0).'"></label></p>';
        echo '<p><label>Reviews <input type="number" min="0" name="reviews_count" value="'.esc_attr($edit->reviews_count ?? 0).'"></label></p>';
        $checked = isset($edit) ? intval($edit->active) : 1;
        echo '<p><label><input type="checkbox" name="active" value="1" '.checked($checked,1,false).'> Active</label></p>';
        submit_button($edit?'Update staff':'Add staff');
        echo '</form>';

        // List
        $rows = $wpdb->get_results("
      SELECT st.*, l.name AS location_name
      FROM {$wpdb->prefix}altego_staff st
      LEFT JOIN {$wpdb->prefix}altego_locations l ON l.id = st.location_id
      ORDER BY st.active DESC, st.name ASC
    ");
        echo '<h2>List</h2>';
        echo '<table class="widefat striped"><thead><tr>
      <th>ID</th><th>Avatar</th><th>Name</th><th>Title</th><th>Email</th><th>Phone</th><th>Rating</th><th>Reviews</th><th>Location</th><th>Status</th><th style="width:260px">Actions</th>
    </tr></thead><tbody>';
        foreach ($rows as $r) {
            $edit_link   = wp_nonce_url(add_query_arg(['tab'=>'staff','action'=>'edit','id'=>$r->id]), 'x');
            $toggle_link = wp_nonce_url(add_query_arg(['tab'=>'staff','action'=>'toggle','id'=>$r->id]), 'altego_st_toggle_'.$r->id);
            $delete_link = wp_nonce_url(add_query_arg(['tab'=>'staff','action'=>'delete','id'=>$r->id]), 'altego_st_delete_'.$r->id);
            echo '<tr>';
            echo '<td>'.esc_html($r->id).'</td>';
            echo '<td>'.($r->avatar_url ? '<img src="'.esc_url($r->avatar_url).'" style="width:36px;height:36px;object-fit:cover;border-radius:50%">' : '').'</td>';
            echo '<td>'.esc_html($r->name).'</td>';
            echo '<td>'.esc_html($r->title).'</td>';
            echo '<td>'.esc_html($r->email).'</td>';
            echo '<td>'.esc_html($r->phone).'</td>';
            echo '<td>'.esc_html($r->rating).'</td>';
            echo '<td>'.esc_html($r->reviews_count).'</td>';
            echo '<td>'.esc_html($r->location_name).'</td>';
            echo '<td>'.($r->active?'active':'inactive').'</td>';
            echo '<td><a class="button" href="'.esc_url($edit_link).'">Edit</a> ';
            echo '<a class="button" href="'.esc_url($toggle_link).'">'.($r->active?'Deactivate':'Activate').'</a> ';
            echo '<a class="button button-link-delete" href="'.esc_url($delete_link).'" onclick="return confirm(\'Delete permanently?\')">Delete</a></td>';
            echo '</tr>';
        }
        if (!$rows) echo '<tr><td colspan="11">No staff</td></tr>';
        echo '</tbody></table>';
    }

    /* ============ LOCATIONS ============ */

    private static function locations_ui() {
        global $wpdb;
        $table = "{$wpdb->prefix}altego_locations";

        // Save
        if (isset($_POST['altego_location_save']) && check_admin_referer('altego_location_save')) {
            $id = intval($_POST['id'] ?? 0);
            $data = [
                'name'       => sanitize_text_field($_POST['name'] ?? ''),
                'subtitle'   => sanitize_text_field($_POST['subtitle'] ?? ''),
                'logo_url'   => esc_url_raw($_POST['logo_url'] ?? ''),
                'address'    => sanitize_text_field($_POST['address'] ?? ''),
                'phone1'     => sanitize_text_field($_POST['phone1'] ?? ''),
                'phone2'     => sanitize_text_field($_POST['phone2'] ?? ''),
                'website_url'=> esc_url_raw($_POST['website_url'] ?? ''),
                'telegram'   => sanitize_text_field($_POST['telegram'] ?? ''),
                'active'     => empty($_POST['active']) ? 0 : 1,
                'updated_at' => current_time('mysql'),
            ];

            if ($id) {
                $wpdb->update($table, $data, ['id' => $id]);
                echo '<div class="updated"><p>Location updated</p></div>';
            } else {
                $data['created_at'] = current_time('mysql');
                $wpdb->insert($table, $data);
                echo '<div class="updated"><p>Location added</p></div>';
            }
        }

        // Toggle
        if (isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'toggle' && check_admin_referer('altego_loc_toggle_'.intval($_GET['id']))) {
            $id = intval($_GET['id']);
            $cur = $wpdb->get_var($wpdb->prepare("SELECT active FROM $table WHERE id=%d", $id));
            $wpdb->update($table, ['active' => $cur ? 0 : 1, 'updated_at'=>current_time('mysql')], ['id'=>$id]);
            echo '<div class="updated"><p>Status changed</p></div>';
        }

        // Delete safely
        if (isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'delete' && check_admin_referer('altego_loc_delete_'.intval($_GET['id']))) {
            $id = intval($_GET['id']);
            $used_staff = intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}altego_staff WHERE location_id=%d", $id)));
            $used_appt  = intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}altego_appointments WHERE location_id=%d", $id)));
            if ($used_staff || $used_appt) {
                echo '<div class="error"><p>Cannot delete. Location is used. Deactivate it instead.</p></div>';
            } else {
                $wpdb->delete($table, ['id' => $id]);
                echo '<div class="updated"><p>Location deleted</p></div>';
            }
        }

        // Edit row
        $edit = null;
        if (isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'edit') {
            $edit = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d", intval($_GET['id'])));
        }

        // Form
        echo '<h2>'.($edit?'Edit location':'Add location').'</h2>';
        echo '<form method="post" style="max-width:760px">';
        wp_nonce_field('altego_location_save');
        echo '<input type="hidden" name="altego_location_save" value="1">';
        echo '<input type="hidden" name="id" value="'.esc_attr($edit->id ?? 0).'">';
        echo '<p><label>Name <input class="regular-text" name="name" value="'.esc_attr($edit->name ?? '').'" required></label></p>';
        echo '<p><label>Address <input class="regular-text" name="address" value="'.esc_attr($edit->address ?? '').'"></label></p>';
        $checked = isset($edit) ? intval($edit->active) : 1;
        echo '<p><label>Subtitle <input class="regular-text" name="subtitle" value="'.esc_attr($edit->subtitle ?? '').'"></label></p>';
        echo '<p><label>Logo URL <input class="regular-text" name="logo_url" value="'.esc_attr($edit->logo_url ?? '').'" placeholder="https://..."></label></p>';
        echo '<p><label>Phone 1 <input class="regular-text" name="phone1" value="'.esc_attr($edit->phone1 ?? '').'"></label></p>';
        echo '<p><label>Phone 2 <input class="regular-text" name="phone2" value="'.esc_attr($edit->phone2 ?? '').'"></label></p>';
        echo '<p><label>Website <input class="regular-text" name="website_url" value="'.esc_attr($edit->website_url ?? '').'" placeholder="https://..."></label></p>';
        echo '<p><label>Telegram <input class="regular-text" name="telegram" value="'.esc_attr($edit->telegram ?? '').'" placeholder="https://t.me/..."></label></p>';
        echo '<p><label><input type="checkbox" name="active" value="1" '.checked($checked,1,false).'> Active</label></p>';
        submit_button($edit?'Update location':'Add location');
        echo '</form>';

        // List
        $rows = $wpdb->get_results("SELECT * FROM $table ORDER BY active DESC, name ASC");
        echo '<h2>List</h2>';
        echo '<table class="widefat striped"><thead><tr><th>ID</th><th>Logo</th><th>Name</th><th>Subtitle</th><th>Address</th><th>Status</th><th style="width:220px">Actions</th></tr></thead><tbody>';
// строка
        foreach ($rows as $r) {
            $edit_link   = wp_nonce_url(add_query_arg(['tab'=>'locations','action'=>'edit','id'=>$r->id]), 'x');
            $toggle_link = wp_nonce_url(add_query_arg(['tab'=>'locations','action'=>'toggle','id'=>$r->id]), 'altego_loc_toggle_'.$r->id);
            $delete_link = wp_nonce_url(add_query_arg(['tab'=>'locations','action'=>'delete','id'=>$r->id]), 'altego_loc_delete_'.$r->id);
            echo '<tr>';
            echo '<td>'.($r->logo_url?'<img src="'.esc_url($r->logo_url).'" style="width:28px;height:28px;border-radius:6px;object-fit:cover">':'').'</td>';
            echo '<td>'.esc_html($r->subtitle).'</td>';
            echo '<td>'.esc_html($r->id).'</td>';
            echo '<td>'.esc_html($r->name).'</td>';
            echo '<td>'.esc_html($r->address).'</td>';
            echo '<td>'.($r->active?'active':'inactive').'</td>';
            echo '<td><a class="button" href="'.esc_url($edit_link).'">Edit</a> ';
            echo '<a class="button" href="'.esc_url($toggle_link).'">'.($r->active?'Deactivate':'Activate').'</a> ';
            echo '<a class="button button-link-delete" href="'.esc_url($delete_link).'" onclick="return confirm(\'Delete permanently?\')">Delete</a></td>';
            echo '</tr>';
        }
        if (!$rows) echo '<tr><td colspan="5">No locations</td></tr>';
        echo '</tbody></table>';
    }

    /* ============ LINKS ============ */

    private static function links_ui() {
        global $wpdb;

        if ($_POST && isset($_POST['altego_links_save']) && check_admin_referer('altego_links')) {
            $service_id = intval($_POST['service_id']);
            $staff_ids  = array_map('intval', $_POST['staff_ids'] ?? []);
            $wpdb->delete("{$wpdb->prefix}altego_service_staff", ['service_id' => $service_id]);
            foreach ($staff_ids as $sid) {
                $wpdb->insert("{$wpdb->prefix}altego_service_staff", [
                    'service_id' => $service_id,
                    'staff_id'   => $sid,
                    'duration'   => 0,
                    'price'      => 0,
                ]);
            }
            echo '<div class="updated"><p>Saved</p></div>';
        }

        $services = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}altego_services WHERE active = 1 ORDER BY name ASC");
        $staff    = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}altego_staff WHERE active = 1 ORDER BY name ASC");

        $service_id = isset($_GET['service_id']) ? intval($_GET['service_id']) : ( $services[0]->id ?? 0 );
        $current = $service_id ? $wpdb->get_col($wpdb->prepare(
            "SELECT staff_id FROM {$wpdb->prefix}altego_service_staff WHERE service_id = %d", $service_id
        )) : [];

        echo '<h2>Service–Staff</h2>';
        echo '<form method="get"><input type="hidden" name="page" value="altego-catalogs" /><input type="hidden" name="tab" value="links" />';
        echo '<p><label>Service <select name="service_id" onchange="this.form.submit()">';
        foreach ($services as $s) {
            $sel = selected($service_id, $s->id, false);
            echo '<option value="'.esc_attr($s->id).'" '.$sel.'>'.esc_html($s->name).'</option>';
        }
        echo '</select></label></p></form>';

        if ($service_id) {
            echo '<form method="post">';
            wp_nonce_field('altego_links');
            echo '<input type="hidden" name="altego_links_save" value="1">';
            echo '<input type="hidden" name="service_id" value="'.esc_attr($service_id).'">';
            echo '<table class="widefat"><thead><tr><th width="60">Use</th><th>Staff</th></tr></thead><tbody>';
            foreach ($staff as $st) {
                $checked = in_array($st->id, $current, true) ? 'checked' : '';
                echo '<tr><td><input type="checkbox" name="staff_ids[]" value="'.esc_attr($st->id).'" '.$checked.'></td><td>'.esc_html($st->name).'</td></tr>';
            }
            echo '</tbody></table>';
            submit_button('Save links');
            echo '</form>';
        } else {
            echo '<p>Add at least one service.</p>';
        }
    }
}
