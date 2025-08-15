<?php
if (!defined('ABSPATH')) { exit; }

class Altego_Manage_Page {
    public static function init() {
        add_shortcode('altego_booking_manage', [__CLASS__, 'render']);
    }

    private static function star_svg($filled) {
        $cls = $filled ? 'filled' : 'empty';
        return '<svg class="star '.$cls.'" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
      <polygon points="12,2 15.09,8.26 22,9.27 17,14.14 18.18,21.02 12,17.77 5.82,21.02 7,14.14 2,9.27 8.91,8.26"></polygon>
    </svg>';
    }

    public static function render() {
        global $wpdb;

        $appointment_id = isset($_GET['a']) ? intval($_GET['a']) : 0;
        $token = isset($_GET['t']) ? sanitize_text_field($_GET['t']) : '';

        $ok = false;
        if ($appointment_id && $token) {
            $saved = get_transient("altego_token_$appointment_id");
            if ($saved && hash_equals($saved, $token)) $ok = true;
        }

        // стили и js макета
        wp_enqueue_style('altego-manage-modern', ALTEGO_WP_URL . 'assets/css/manage.css', [], ALTEGO_WP_VERSION);
        // подключаем карту
        wp_enqueue_style('leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', [], '1.9.4');
        wp_enqueue_script('leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', [], '1.9.4', true);
        wp_enqueue_script('altego-manage-modern', ALTEGO_WP_URL . 'assets/js/manage.js', ['leaflet'], ALTEGO_WP_VERSION, true);

        ob_start();
        echo '<div class="container">';
        if (!$ok) {
            echo '<div class="bm-empty" style="padding:40px;text-align:center"><h2>Link invalid or expired</h2><p>Please request a new link.</p></div>';
            echo '</div>';
            return ob_get_clean();
        }

        // все данные
        $row = $wpdb->get_row($wpdb->prepare("
    SELECT a.id, a.date, a.time_start AS start, a.time_end AS end, a.status,
           a.staff_id, a.service_id,
           s.name AS service_name, s.price AS service_price,
           st.name AS staff_name, st.title AS staff_title, st.avatar_url AS staff_avatar,
           st.rating AS staff_rating, st.reviews_count AS staff_reviews,
           l.name AS location_name, l.subtitle AS location_subtitle, l.logo_url AS location_logo,
           l.address AS location_address, l.phone1, l.phone2, l.website_url, l.telegram
    FROM {$wpdb->prefix}altego_appointments a
    LEFT JOIN {$wpdb->prefix}altego_services s ON s.id = a.service_id
    LEFT JOIN {$wpdb->prefix}altego_staff st ON st.id = a.staff_id
    LEFT JOIN {$wpdb->prefix}altego_locations l ON l.id = a.location_id
    WHERE a.id = %d
  ", $appointment_id), ARRAY_A);

        if (!$row) {
            echo '<div class="bm-empty" style="padding:40px;text-align:center"><p>Appointment not found.</p></div></div>';
            return ob_get_clean();
        }

        // локализуем адрес для карты
        wp_localize_script('altego-manage-modern', 'AltegoManage', [
            'address' => (string)($row['location_address'] ?: ''),
            'markerTitle' => (string)($row['location_name'] ?: 'Location'),
        ]);

        $date_label = date_i18n('F jS', strtotime($row['date']));
        $time_label = esc_html($row['start']).' - '.esc_html($row['end']);

        // хедер
        echo '<div class="header"><div class="header-content">
          <div class="header-subtitle">
            <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
              <circle cx="12" cy="12" r="10"></circle><polyline points="12,6 12,12 16,14"></polyline>
            </svg><span>Upcoming Appointment</span>
          </div>
          <h1 class="header-title">'.esc_html($date_label).', '.$time_label.'</h1>
        </div></div>';

        echo '<div class="main-content">';

        // карточка сотрудника
        $rating = floatval($row['staff_rating']);
        $filled = max(0, min(5, (int)round($rating)));
        $stars = '';
        for ($i=1;$i<=5;$i++){ $stars .= self::star_svg($i <= $filled); }
        $avatar = $row['staff_avatar'] ?: ALTEGO_WP_URL.'assets/img/avatar-placeholder.png';

        echo '<div class="card"><div class="card-content">
          <div class="barber-info">
            <img src="'.esc_url($avatar).'" alt="'.esc_attr($row['staff_name']).'" class="barber-avatar" />
            <div class="barber-details">
              <h2 class="barber-name">'.esc_html($row['staff_name']).'</h2>
              '.($row['staff_title'] ? '<p class="barber-title">'.esc_html($row['staff_title']).'</p>' : '').'
              <div class="rating-container"><div class="stars">'.$stars.'</div>'.
            ($row['staff_reviews'] ? '<span class="review-count">'.intval($row['staff_reviews']).' reviews</span>' : '').
            '</div>
            </div>
          </div>
        </div></div>';

        // кнопки действий
        $ics = esc_url(home_url('/altego-ics/'.$appointment_id));
        $reschedule_base = (string)Altego_Settings::get('reschedule_url');
        $reschedule_link = '';
        if ($reschedule_base) {
            $reschedule_link = add_query_arg([
                'a' => $appointment_id,
                't' => $token,
                'date' => $row['date'],
                'start' => $row['start'],
                'end' => $row['end'],
                'staff_id' => $row['staff_id'],
                'service_id' => $row['service_id'],
            ], $reschedule_base);
        }

        echo '<div class="action-buttons">';

        // Cancel работает через POST и nonce
        echo '<form method="post" class="action-button cancel" style="border-color:#fecaca" onsubmit="return confirm(\'Cancel this appointment?\')">';
        wp_nonce_field('altego_cancel_'.$appointment_id);
        echo '<input type="hidden" name="altego_action" value="cancel">';
        echo '<button type="submit" style="all:unset;display:flex;flex-direction:column;align-items:center;gap:.5rem;cursor:pointer">
          <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line>
          </svg><span class="action-button-text">Cancel</span>
        </button></form>';

        if ($reschedule_link) {
            echo '<a class="action-button reschedule" href="'.esc_url($reschedule_link).'">
            <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
              <path d="m12 19-7-7 7-7"></path><path d="M19 12H5"></path>
            </svg><span class="action-button-text">Reschedule</span>
          </a>';
        } else {
            echo '<button class="action-button reschedule" type="button" title="Set Reschedule URL in Settings" disabled>
            <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
              <path d="m12 19-7-7 7-7"></path><path d="M19 12H5"></path>
            </svg><span class="action-button-text">Reschedule</span>
          </button>';
        }

        echo '<a class="action-button calendar" href="'.$ics.'">
          <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
            <line x1="16" y1="2" x2="16" y2="6"></line>
            <line x1="8" y1="2" x2="8" y2="6"></line>
            <line x1="3" y1="10" x2="21" y2="10"></line>
          </svg><span class="action-button-text">Add to Calendar</span>
        </a>';

        echo '</div>'; // action-buttons

        // услуги
        $price = floatval($row['service_price']);
        echo '<div class="card shadow-sm"><div class="card-content">
          <h3 class="section-title">Services</h3>
          <div class="service-item"><span class="service-name">'.esc_html($row['service_name']).'</span>
            <span class="service-price">'.number_format_i18n($price,0).' ₴</span></div>
          <div class="separator"></div>
          <div class="total-row"><span class="total-label">Total</span>
            <span class="total-price">'.number_format_i18n($price,0).' ₴</span></div>
        </div></div>';

        // локация и карта
        $loc_logo = $row['location_logo'] ?: '';
        $logo_html = $loc_logo ? '<img class="business-logo" src="'.esc_url($loc_logo).'" alt="">' : '<div class="business-logo">OD</div>';

        echo '<div class="card shadow-sm"><div class="card-content">
          <div class="business-header">'.$logo_html.'
            <div><h3 class="business-name">'.esc_html($row['location_name']).'</h3>'.
            ($row['location_subtitle'] ? '<p class="business-location">'.esc_html($row['location_subtitle']).'</p>' : '').
            '</div>
          </div>

          <div id="bm-map" class="map-placeholder bm-map"><div class="map-gradient"></div></div>';

        // контакты
        echo '<div class="contact-info">';
        if (!empty($row['location_address'])) {
            echo '<div class="contact-item" data-type="addr" data-value="'.esc_attr($row['location_address']).'">
            <svg class="contact-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
              <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle>
            </svg><span class="contact-text">'.esc_html($row['location_address']).'</span>
          </div>';
        }
        if (!empty($row['phone1'])) {
            $p1 = preg_replace('~\\D+~', '', $row['phone1']);
            echo '<div class="contact-item" data-type="tel" data-value="'.esc_attr($p1).'">
            <svg class="contact-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
              <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
            </svg><span class="contact-text">'.esc_html($row['phone1']).'</span>
          </div>';
        }
        if (!empty($row['phone2'])) {
            $p2 = preg_replace('~\\D+~', '', $row['phone2']);
            echo '<div class="contact-item" data-type="tel" data-value="'.esc_attr($p2).'">
            <svg class="contact-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
              <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
            </svg><span class="contact-text">'.esc_html($row['phone2']).'</span>
          </div>';
        }
        if (!empty($row['website_url'])) {
            $host = parse_url($row['website_url'], PHP_URL_HOST) ?: $row['website_url'];
            echo '<div class="contact-item" data-type="web" data-value="'.esc_attr($row['website_url']).'">
            <svg class="contact-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
              <circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line>
              <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path>
            </svg><span class="contact-text">'.esc_html($host).'</span>
          </div>';
        }
        if (!empty($row['telegram'])) {
            echo '<div class="contact-item" data-type="tg" data-value="'.esc_attr($row['telegram']).'">
            <svg class="contact-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
              <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
            </svg><span class="contact-text">'.esc_html($row['telegram']).'</span>
          </div>';
        }
        echo '</div>'; // contact-info

        // нижние кнопки
        $tel = !empty($row['phone1']) ? preg_replace('~\\D+~','',$row['phone1']) : (!empty($row['phone2']) ? preg_replace('~\\D+~','',$row['phone2']) : '');
        $gmaps = !empty($row['location_address']) ? 'https://www.google.com/maps/dir/?api=1&destination='.rawurlencode($row['location_address']) : '#';
        echo '<div class="bottom-actions">';
        if ($tel) {
            echo '<a class="bottom-action" href="tel:'.esc_attr($tel).'">
            <svg class="icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
              <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
            </svg>Call
          </a>';
        }
        if (!empty($row['location_address'])) {
            echo '<a class="bottom-action" href="'.esc_url($gmaps).'" target="_blank" rel="noopener">
            <svg class="icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
              <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle>
            </svg>Directions
          </a>';
        }
        echo '</div>'; // bottom-actions

        echo '</div></div>'; // card + main-content + container

        return ob_get_clean();
    }

}
