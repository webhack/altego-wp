<?php
if (!defined('ABSPATH')) { exit; }

class Altego_REST_Appointments extends WP_REST_Controller {
  private $ns = 'altego/v1';
  private $repo;

  public function __construct() {
    $this->repo = Altego_Repo::instance();
  }

  public function register_routes() {
    register_rest_route($this->ns, '/slots', [
      'methods' => 'GET',
      'callback' => [$this, 'get_slots'],
      'permission_callback' => '__return_true'
    ]);

    register_rest_route($this->ns, '/appointments', [
      'methods' => 'POST',
      'callback' => [$this, 'create'],
      'permission_callback' => '__return_true'
    ]);

      register_rest_route($this->ns, '/appointments', [
          'methods' => 'GET',
          'callback' => [$this, 'index'],
          'permission_callback' => function(){ return current_user_can('altego_schedule_view'); }
      ]);

      register_rest_route($this->ns, '/appointments/(?P<id>\d+)', [
          'methods' => 'GET',
          'callback' => [$this, 'show'],
          'permission_callback' => function(){ return current_user_can('altego_schedule_view'); }
      ]);

      register_rest_route($this->ns, '/appointments/(?P<id>\d+)', [
          'methods' => 'PUT',
          'callback' => [$this, 'update'],
          'permission_callback' => function(){ return current_user_can('altego_schedule_edit'); }
      ]);

  }

    private function fetch_row($id) {
        global $wpdb;
        $sql = "
    SELECT
      a.id, a.date, a.time_start AS start, a.time_end AS end, a.status,
      a.location_id, a.staff_id, a.service_id, a.client_id, a.notes,
      s.name AS service_name, st.name AS staff_name,
      c.name AS client_name, c.phone AS client_phone, c.email AS client_email
    FROM {$wpdb->prefix}altego_appointments a
    LEFT JOIN {$wpdb->prefix}altego_services s ON s.id = a.service_id
    LEFT JOIN {$wpdb->prefix}altego_staff st ON st.id = a.staff_id
    LEFT JOIN {$wpdb->prefix}altego_clients c ON c.id = a.client_id
    WHERE a.id = %d
    LIMIT 1
  ";
        return $wpdb->get_row($wpdb->prepare($sql, $id), ARRAY_A);
    }

    public function show(WP_REST_Request $r) {
        $id = intval($r['id']);
        if (!$id) return new WP_Error('bad_request', 'Invalid id', ['status' => 400]);
        $row = $this->fetch_row($id);
        if (!$row) return new WP_Error('not_found', 'Appointment not found', ['status' => 404]);
        return rest_ensure_response($row);
    }

    public function update(WP_REST_Request $r) {
        global $wpdb;
        $id = intval($r['id']);
        if (!$id) return new WP_Error('bad_request', 'Invalid id', ['status' => 400]);

        $cur = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}altego_appointments WHERE id = %d", $id
        ), ARRAY_A);
        if (!$cur) return new WP_Error('not_found', 'Appointment not found', ['status' => 404]);

        $p = $r->get_json_params();
        $allowed_status = ['new','confirmed','canceled','completed','no_show'];

        $service_id = isset($p['service_id']) ? intval($p['service_id']) : intval($cur['service_id']);
        $staff_id   = isset($p['staff_id'])   ? intval($p['staff_id'])   : intval($cur['staff_id']);
        $date       = isset($p['date'])       ? sanitize_text_field($p['date']) : $cur['date'];
        $start      = isset($p['start'])      ? sanitize_text_field($p['start']) : $cur['time_start'];
        $status     = isset($p['status'])     ? sanitize_text_field($p['status']) : $cur['status'];
        $notes      = isset($p['notes'])      ? sanitize_textarea_field($p['notes']) : $cur['notes'];

        if (!in_array($status, $allowed_status, true)) {
            return new WP_Error('bad_request', 'Invalid status', ['status' => 400]);
        }

        // пересчитаем конец если изменились услуга или старт
        $end = Altego_Repo::instance()->calc_end_time($service_id, $start);

        if ($this->slot_taken($staff_id, $date, $start, $end, $id, false)) {
            return new WP_Error('busy', 'Time slot already taken', ['status' => 409]);
        }

        // обновим клиента если пришли поля
        if (!empty($p['client']) && is_array($p['client'])) {
            $c = $p['client'];
            $upd = [];
            if (isset($c['name']))  $upd['name']  = sanitize_text_field($c['name']);
            if (isset($c['phone'])) $upd['phone'] = sanitize_text_field($c['phone']);
            if (isset($c['email'])) $upd['email'] = sanitize_email($c['email']);
            if ($upd) {
                $wpdb->update("{$wpdb->prefix}altego_clients", $upd, ['id' => intval($cur['client_id'])]);
            }
        }

        // апдейт записи
        $ok = $wpdb->update("{$wpdb->prefix}altego_appointments", [
            'service_id' => $service_id,
            'staff_id'   => $staff_id,
            'date'       => $date,
            'time_start' => $start,
            'time_end'   => $end,
            'status'     => $status,
            'notes'      => $notes,
            'updated_at' => current_time('mysql'),
        ], ['id' => $id]);

        if ($ok === false) {
            $err = $wpdb->last_error ?: 'Update failed';
            return new WP_Error('db', $err, ['status' => 500]);
        }

        return rest_ensure_response($this->fetch_row($id));
    }


    private function build_grid($rules, $duration, $step) {
    $slots = [];
    foreach ($rules as $r) {
      $t = strtotime($r['start']);
      $end = strtotime($r['end']);
      while ($t + $duration * 60 <= $end) {
        $slots[] = date('H:i', $t);
        $t += $step * 60;
      }
    }
    return $slots;
  }

  private function exclude_overlaps($candidates, $busy, $duration) {
    $ok = [];
    foreach ($candidates as $hhmm) {
      list($h, $m) = array_map('intval', explode(':', $hhmm));
      $start = $h * 60 + $m;
      $end   = $start + $duration;
      $intersects = false;
      foreach ($busy as $b) {
        $bs = intval(substr($b['start'],0,2)) * 60 + intval(substr($b['start'],3,2));
        $be = intval(substr($b['end'],0,2)) * 60 + intval(substr($b['end'],3,2));
        if (!($be <= $start || $bs >= $end)) { $intersects = true; break; }
      }
      if (!$intersects) $ok[] = $hhmm;
    }
    return $ok;
  }

  public function get_slots(WP_REST_Request $r) {
    $staff_id   = intval($r->get_param('staff_id'));
    $service_id = intval($r->get_param('service_id'));
    $date       = sanitize_text_field($r->get_param('date'));

    if (!$staff_id || !$service_id || !$date) {
      return new WP_Error('bad_request', 'Missing parameters', ['status' => 400]);
    }

    $service = $this->repo->get_service($service_id);
    if (!$service) return new WP_Error('not_found', 'Service not found', ['status' => 404]);

    $rules   = $this->repo->get_staff_rules_for_date($staff_id, $date);
    $busy    = $this->repo->get_busy_for_date($staff_id, $date);

      $step = intval(Altego_Settings::get('slot_step'));
      $grid = $this->build_grid($rules, intval($service['duration']), max(5, $step));
      $grid = $this->exclude_overlaps($grid, $busy, intval($service['duration']));

    return rest_ensure_response([
      'date' => $date,
      'service_id' => $service_id,
      'staff_id' => $staff_id,
      'duration' => intval($service['duration']),
      'slots' => $grid
    ]);
  }

    public function create(WP_REST_Request $r) {
        global $wpdb;
        $payload = $r->get_json_params();
        $client  = $payload['client'] ?? [];

        $recaptcha = sanitize_text_field($payload['recaptcha'] ?? '');
        if (!Altego_Guards::verify_recaptcha($recaptcha)) {
            return new WP_Error('captcha', 'reCAPTCHA validation failed', ['status' => 400]);
        }

        // Resolve table name explicitly and verify it exists
        $table_apts = $wpdb->prefix . 'altego_appointments';
        $exists = $wpdb->get_var( $wpdb->prepare("SHOW TABLES LIKE %s", $table_apts) );
        if (!$exists) {
            // Fallback for unusual prefixes or multisite edge cases
            $alt = $wpdb->base_prefix . 'altego_appointments';
            $alt_exists = $wpdb->get_var( $wpdb->prepare("SHOW TABLES LIKE %s", $alt) );
            if ($alt_exists) { $table_apts = $alt; }
        }

        // Final check
        $exists = $wpdb->get_var( $wpdb->prepare("SHOW TABLES LIKE %s", $table_apts) );
        if (!$exists) {
            return new WP_Error('db', 'Appointments table not found for prefix '. $wpdb->prefix .' tried '. $table_apts, ['status' => 500]);
        }

        // Upsert client
        $client_id = Altego_Repo::instance()->upsert_client([
            'name'  => $client['name'] ?? '',
            'email' => $client['email'] ?? '',
            'phone' => $client['phone'] ?? '',
        ]);

        $location_id = intval($payload['location_id'] ?? 1);
        $staff_id    = intval($payload['staff_id'] ?? 0);
        $service_id  = intval($payload['service_id'] ?? 0);
        $date        = sanitize_text_field($payload['date'] ?? '');
        $start       = sanitize_text_field($payload['start'] ?? '');

        if (!$staff_id || !$service_id || !$date || !$start) {
            return new WP_Error('bad_request', 'Missing fields', ['status' => 400]);
        }

        $end = Altego_Repo::instance()->calc_end_time($service_id, $start);

        // Begin
        $wpdb->query('START TRANSACTION');

// Check conflicts c блокировкой
        if ($this->slot_taken($staff_id, $date, $start, $end, 0, true)) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('busy', 'Time slot already taken', ['status' => 409]);
        }

        // Insert
        $ok = $wpdb->insert($table_apts, [
            'location_id' => $location_id,
            'staff_id'    => $staff_id,
            'service_id'  => $service_id,
            'client_id'   => $client_id,
            'date'        => $date,
            'time_start'  => $start,
            'time_end'    => $end,
            'status'      => 'confirmed',
            'created_at'  => current_time('mysql'),
            'updated_at'  => current_time('mysql'),
        ]);

        if (!$ok) {
            $err = $wpdb->last_error ?: 'Failed to create appointment';
            error_log('[Altego REST] insert failed: '. $err .' last_query='. $wpdb->last_query);
            $wpdb->query('ROLLBACK');
            return new WP_Error('db', $err, ['status' => 500]);
        }

        $appointment_id = intval($wpdb->insert_id);

        // Verify the row is really there
        $check = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table_apts} WHERE id = %d", $appointment_id));
        if (intval($check) !== 1) {
            error_log('[Altego REST] post-insert verify failed id='. $appointment_id .' table='. $table_apts);
            $wpdb->query('ROLLBACK');
            return new WP_Error('db', 'Insert verification failed', ['status' => 500]);
        }

        $wpdb->query('COMMIT');

        // Token and notifications
        $token = wp_generate_password(32, false, false);
        $life  = intval(Altego_Settings::get('token_lifetime_hours'));
        set_transient("altego_token_$appointment_id", $token, $life * HOUR_IN_SECONDS);

        Altego_Notify::send('confirmation', $appointment_id);
        Altego_Mailer::send_confirmation($appointment_id);

        return [
            'appointment_id' => $appointment_id,
            'manage_url' => add_query_arg(['a' => $appointment_id, 't' => $token], site_url('/booking-manage')),
            'status' => 'confirmed'
        ];
    }


    public function index(WP_REST_Request $r) {
        global $wpdb;
        $df = sanitize_text_field($r->get_param('date_from'));
        $dt = sanitize_text_field($r->get_param('date_to'));
        $staff_id = intval($r->get_param('staff_id'));
        $service_id = intval($r->get_param('service_id'));
        $status = sanitize_text_field($r->get_param('status'));

        $where = ["1=1"];
        $args = [];

        if ($df) { $where[] = "a.date >= %s"; $args[] = $df; }
        if ($dt) { $where[] = "a.date <= %s"; $args[] = $dt; }
        if ($staff_id) { $where[] = "a.staff_id = %d"; $args[] = $staff_id; }
        if ($service_id) { $where[] = "a.service_id = %d"; $args[] = $service_id; }
        if ($status) { $where[] = "a.status = %s"; $args[] = $status; }

        $sql = "
  SELECT
    a.id, a.date,
    a.time_start AS start, a.time_end AS end,
    a.status, a.location_id, a.staff_id, a.service_id, a.client_id,
    s.name AS service_name, st.name AS staff_name,
    c.name AS client_name, c.phone AS client_phone
  FROM {$wpdb->prefix}altego_appointments a
  LEFT JOIN {$wpdb->prefix}altego_services s ON s.id = a.service_id
  LEFT JOIN {$wpdb->prefix}altego_staff st ON st.id = a.staff_id
  LEFT JOIN {$wpdb->prefix}altego_clients c ON c.id = a.client_id
  WHERE ".implode(' AND ', $where)."
  ORDER BY a.date ASC, a.time_start ASC
  LIMIT 2000
";


        $rows = $wpdb->get_results($wpdb->prepare($sql, $args), ARRAY_A);

        return rest_ensure_response(['items' => $rows]);
    }

    /** Проверка занятости слота. $exclude_id исключает текущую запись. $for_update блокирует строки в транзакции. */
    private function slot_taken($staff_id, $date, $start, $end, $exclude_id = 0, $for_update = false) {
        global $wpdb;
        $sql  = "SELECT 1 FROM {$wpdb->prefix}altego_appointments
           WHERE staff_id = %d
             AND date = %s
             AND status IN ('new','confirmed')
             AND NOT (time_end <= %s OR time_start >= %s)";
        $args = [$staff_id, $date, $start, $end];
        if ($exclude_id) {
            $sql .= " AND id <> %d";
            $args[] = (int)$exclude_id;
        }
        $sql .= $for_update ? " LIMIT 1 FOR UPDATE" : " LIMIT 1";
        return (bool) $wpdb->get_var($wpdb->prepare($sql, $args));
    }


}
