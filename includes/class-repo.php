<?php
if (!defined('ABSPATH')) { exit; }

class Altego_Repo {
  private static $instance = null;
  public static function instance() {
    if (self::$instance === null) self::$instance = new self();
    return self::$instance;
  }

  public function get_service($service_id) {
    global $wpdb;
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}altego_services WHERE id = %d", $service_id), ARRAY_A);
  }

    public function get_staff_rules_for_date($staff_id, $date) {
        global $wpdb;
        $weekday = intval(date('N', strtotime($date))); // 1 Mon .. 7 Sun

        // 1. персональные правила
        $r = $wpdb->get_row($wpdb->prepare(
            "SELECT start, end, break_start, break_end
     FROM {$wpdb->prefix}altego_staff_schedule
     WHERE staff_id = %d AND weekday = %d AND active = 1",
            $staff_id, $weekday
        ), ARRAY_A);

        if ($r) {
            $out = [];
            // с учетом перерыва
            if (!empty($r['break_start']) && !empty($r['break_end'])) {
                if (strtotime($r['start']) < strtotime($r['break_start'])) {
                    $out[] = ['start' => $r['start'], 'end' => $r['break_start']];
                }
                if (strtotime($r['break_end']) < strtotime($r['end'])) {
                    $out[] = ['start' => $r['break_end'], 'end' => $r['end']];
                }
                return $out;
            }
            return [['start' => $r['start'], 'end' => $r['end']]];
        }

        // 2. дефолтные правила
        if (class_exists('Altego_Workhours')) {
            $def = Altego_Workhours::get_defaults();
            $d = $def[(string)$weekday] ?? null;
            if ($d && empty($d['off']) && !empty($d['start']) && !empty($d['end'])) {
                if (!empty($d['b_start']) && !empty($d['b_end'])) {
                    $out = [];
                    if (strtotime($d['start']) < strtotime($d['b_start'])) {
                        $out[] = ['start' => $d['start'], 'end' => $d['b_start']];
                    }
                    if (strtotime($d['b_end']) < strtotime($d['end'])) {
                        $out[] = ['start' => $d['b_end'], 'end' => $d['end']];
                    }
                    return $out;
                }
                return [['start' => $d['start'], 'end' => $d['end']]];
            }
        }

        // 3. fallback only when Workhours class is unavailable
        if (!class_exists('Altego_Workhours')) {
            return [
                ['start' => '09:00', 'end' => '13:00'],
                ['start' => '14:00', 'end' => '18:00'],
            ];
        }
// если класс рабочих часов есть и для дня нет правил или он выходной возвращаем пусто
        return [];
    }


    public function get_busy_for_date($staff_id, $date) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT time_start AS start, time_end AS end
     FROM {$wpdb->prefix}altego_appointments
     WHERE staff_id = %d AND date = %s AND status IN ('new','confirmed')",
            $staff_id, $date
        ), ARRAY_A);
    }


    public function calc_end_time($service_id, $start_time) {
    $service = $this->get_service($service_id);
    $duration = intval($service['duration']);
    $t = strtotime($start_time);
    return date('H:i', $t + $duration * 60);
  }

  public function upsert_client($data) {
    global $wpdb;
    $email = isset($data['email']) ? sanitize_email($data['email']) : '';
    $phone = isset($data['phone']) ? sanitize_text_field($data['phone']) : '';
    $name  = isset($data['name'])  ? sanitize_text_field($data['name'])  : '';

    $existing = $wpdb->get_var($wpdb->prepare(
      "SELECT id FROM {$wpdb->prefix}altego_clients WHERE email = %s AND phone = %s LIMIT 1", $email, $phone
    ));
    if ($existing) {
      $wpdb->update("{$wpdb->prefix}altego_clients",
        ['name' => $name, 'updated_at' => current_time('mysql')],
        ['id' => $existing]
      );
      return intval($existing);
    } else {
      $wpdb->insert("{$wpdb->prefix}altego_clients", [
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'created_at' => current_time('mysql'),
        'updated_at' => current_time('mysql'),
      ]);
      return intval($wpdb->insert_id);
    }
  }

    public function list_services() {
        global $wpdb;
        return $wpdb->get_results("SELECT id, name, duration, price FROM {$wpdb->prefix}altego_services ORDER BY name ASC", ARRAY_A);
    }

    public function list_staff_by_service($service_id) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT st.id, st.name, st.email, st.phone
     FROM {$wpdb->prefix}altego_staff st
     INNER JOIN {$wpdb->prefix}altego_service_staff ss ON ss.staff_id = st.id
     WHERE ss.service_id = %d AND st.active = 1
     ORDER BY st.name ASC", $service_id
        ), ARRAY_A);
    }

    public function slot_taken($staff_id, $date, $start, $end, $exclude_id = 0) {
        global $wpdb;
        // пересекается если НЕ (кончается до начала или начинается после конца)
        $sql = "SELECT COUNT(*) 
                  FROM {$wpdb->prefix}altego_appointments
                 WHERE staff_id = %d
                   AND date = %s
                   AND status IN ('new','confirmed')
                   AND NOT (time_end <= %s OR time_start >= %s)";
        $args = [$staff_id, $date, $start, $end];
        if ($exclude_id) {
            $sql .= " AND id <> %d";
            $args[] = (int)$exclude_id;
        }
        $cnt = (int)$wpdb->get_var($wpdb->prepare($sql, $args));
        return $cnt > 0;
    }

}
