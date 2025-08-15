<?php
if (!defined('ABSPATH')) { exit; }

class Altego_Workhours {
    const OPTION_DEFAULTS = 'altego_work_defaults';

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'menu']);
    }

    public static function menu() {
        add_submenu_page(
            'altego',
            __('Working hours', 'altego-wp'),
            __('Working hours', 'altego-wp'),
            'altego_schedule_edit',
            'altego-hours',
            [__CLASS__, 'render']
        );
    }

    private static function default_defaults() {
        // по умолчанию Пн Пт 09 00 18 00 с перерывом 13 00 14 00 выходные Сб Вс
        return [
            '1' => ['off' => 0, 'start' => '09:00', 'end' => '18:00', 'b_start' => '13:00', 'b_end' => '14:00'],
            '2' => ['off' => 0, 'start' => '09:00', 'end' => '18:00', 'b_start' => '13:00', 'b_end' => '14:00'],
            '3' => ['off' => 0, 'start' => '09:00', 'end' => '18:00', 'b_start' => '13:00', 'b_end' => '14:00'],
            '4' => ['off' => 0, 'start' => '09:00', 'end' => '18:00', 'b_start' => '13:00', 'b_end' => '14:00'],
            '5' => ['off' => 0, 'start' => '09:00', 'end' => '18:00', 'b_start' => '13:00', 'b_end' => '14:00'],
            '6' => ['off' => 1, 'start' => '', 'end' => '', 'b_start' => '', 'b_end' => ''],
            '7' => ['off' => 1, 'start' => '', 'end' => '', 'b_start' => '', 'b_end' => ''],
        ];
    }

    public static function get_defaults() {
        $v = get_option(self::OPTION_DEFAULTS, []);
        if (!is_array($v) || empty($v)) $v = self::default_defaults();
        // нормализуем
        $res = self::default_defaults();
        foreach ($res as $d => $row) {
            if (isset($v[$d]) && is_array($v[$d])) {
                $res[$d] = wp_parse_args($v[$d], $row);
            }
        }
        return $res;
    }

    public static function render() {
        if (!current_user_can('altego_schedule_edit')) wp_die(__('Access denied', 'altego-wp'));
        global $wpdb;

        // обработка сохранения дефолтов
        if (isset($_POST['altego_hours_defaults']) && check_admin_referer('altego_hours_defaults')) {
            $save = [];
            for ($d = 1; $d <= 7; $d++) {
                $row = $_POST['def'][$d] ?? [];
                $save[(string)$d] = [
                    'off'     => empty($row['off']) ? 0 : 1,
                    'start'   => sanitize_text_field($row['start'] ?? ''),
                    'end'     => sanitize_text_field($row['end'] ?? ''),
                    'b_start' => sanitize_text_field($row['b_start'] ?? ''),
                    'b_end'   => sanitize_text_field($row['b_end'] ?? ''),
                ];
            }
            update_option(self::OPTION_DEFAULTS, $save);
            echo '<div class="updated"><p>Saved</p></div>';
        }

        // обработка сохранения сотрудника
        if (isset($_POST['altego_hours_staff']) && check_admin_referer('altego_hours_staff')) {
            $staff_id = intval($_POST['staff_id']);
            for ($d = 1; $d <= 7; $d++) {
                $row = $_POST['st'][$d] ?? [];
                $off = empty($row['off']) ? 0 : 1;
                // если выходной удалим запись
                if ($off) {
                    $wpdb->delete("{$wpdb->prefix}altego_staff_schedule", ['staff_id' => $staff_id, 'weekday' => $d]);
                    continue;
                }
                $start = sanitize_text_field($row['start'] ?? '');
                $end   = sanitize_text_field($row['end'] ?? '');
                $bs    = sanitize_text_field($row['b_start'] ?? '');
                $be    = sanitize_text_field($row['b_end'] ?? '');

                // валидации простые
                if (!$start || !$end) {
                    $wpdb->delete("{$wpdb->prefix}altego_staff_schedule", ['staff_id' => $staff_id, 'weekday' => $d]);
                    continue;
                }
                // upsert
                $wpdb->replace("{$wpdb->prefix}altego_staff_schedule", [
                    'staff_id'    => $staff_id,
                    'weekday'     => $d,
                    'start'       => $start,
                    'end'         => $end,
                    'break_start' => $bs ?: null,
                    'break_end'   => $be ?: null,
                    'active'      => 1,
                ]);
            }
            echo '<div class="updated"><p>Saved</p></div>';
        }

        $weeks = [1=>'Mon',2=>'Tue',3=>'Wed',4=>'Thu',5=>'Fri',6=>'Sat',7=>'Sun'];
        $defaults = self::get_defaults();

        $staff = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}altego_staff ORDER BY name ASC");
        $staff_id = isset($_GET['staff_id']) ? intval($_GET['staff_id']) : ( $staff[0]->id ?? 0 );

        // загрузим расписание сотрудника
        $rows = [];
        if ($staff_id) {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT weekday, start, end, break_start, break_end FROM {$wpdb->prefix}altego_staff_schedule WHERE staff_id = %d",
                $staff_id
            ), ARRAY_A);
        }
        $byDay = [];
        foreach ($rows as $r) { $byDay[intval($r['weekday'])] = $r; }

        echo '<div class="wrap"><h1>Working hours</h1>';

        // Блок дефолтов
        echo '<h2>Defaults</h2>';
        echo '<form method="post">';
        wp_nonce_field('altego_hours_defaults');
        echo '<input type="hidden" name="altego_hours_defaults" value="1">';
        echo '<table class="widefat"><thead><tr><th>Day</th><th>Off</th><th>Start</th><th>End</th><th>Break start</th><th>Break end</th></tr></thead><tbody>';
        for ($d=1; $d<=7; $d++) {
            $r = $defaults[(string)$d];
            echo '<tr>';
            echo '<td>'.esc_html($weeks[$d]).'</td>';
            echo '<td><input type="checkbox" name="def['.$d.'][off]" value="1" '.checked($r['off'],1,false).'></td>';
            echo '<td><input name="def['.$d.'][start]" value="'.esc_attr($r['start']).'" type="time"></td>';
            echo '<td><input name="def['.$d.'][end]" value="'.esc_attr($r['end']).'" type="time"></td>';
            echo '<td><input name="def['.$d.'][b_start]" value="'.esc_attr($r['b_start']).'" type="time"></td>';
            echo '<td><input name="def['.$d.'][b_end]" value="'.esc_attr($r['b_end']).'" type="time"></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        submit_button('Save defaults');
        echo '</form>';

        // Блок сотрудника
        echo '<h2>Staff schedule</h2>';
        echo '<form method="get" style="margin-bottom:12px">';
        echo '<input type="hidden" name="page" value="altego-hours">';
        echo '<p><label>Staff <select name="staff_id" onchange="this.form.submit()">';
        foreach ($staff as $st) {
            $sel = selected($staff_id, $st->id, false);
            echo '<option value="'.esc_attr($st->id).'" '.$sel.'>'.esc_html($st->name).'</option>';
        }
        echo '</select></label></p></form>';

        if ($staff_id) {
            echo '<form method="post">';
            wp_nonce_field('altego_hours_staff');
            echo '<input type="hidden" name="altego_hours_staff" value="1">';
            echo '<input type="hidden" name="staff_id" value="'.esc_attr($staff_id).'">';
            echo '<table class="widefat"><thead><tr><th>Day</th><th>Off</th><th>Start</th><th>End</th><th>Break start</th><th>Break end</th></tr></thead><tbody>';
            for ($d=1; $d<=7; $d++) {
                $base = $defaults[(string)$d];
                $rr = $byDay[$d] ?? null;
                $off = $rr ? 0 : ($base['off'] ? 1 : 0);
                $start = $rr ? $rr['start'] : $base['start'];
                $end   = $rr ? $rr['end']   : $base['end'];
                $bs    = $rr ? ($rr['break_start'] ?: '') : $base['b_start'];
                $be    = $rr ? ($rr['break_end'] ?: '')   : $base['b_end'];

                echo '<tr>';
                echo '<td>'.esc_html($weeks[$d]).'</td>';
                echo '<td><input type="checkbox" name="st['.$d.'][off]" value="1" '.checked($off,1,false).'></td>';
                echo '<td><input name="st['.$d.'][start]" value="'.esc_attr($start).'" type="time"></td>';
                echo '<td><input name="st['.$d.'][end]" value="'.esc_attr($end).'" type="time"></td>';
                echo '<td><input name="st['.$d.'][b_start]" value="'.esc_attr($bs).'" type="time"></td>';
                echo '<td><input name="st['.$d.'][b_end]" value="'.esc_attr($be).'" type="time"></td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
            submit_button('Save staff schedule');
            echo '</form>';
        }

        echo '</div>';
    }
}
