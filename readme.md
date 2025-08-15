# Altego WP Booking

A lightweight booking plugin for WordPress. Create services, staff, and locations. Embed a booking widget on your site. Accept appointments through REST. Verify phone by email code (optional). Protect the form with reCAPTCHA. Manage schedule and appointments in the admin. Give clients a booking management page.

## Features

- Three step widget: pick service, pick staff, pick date and time slot
- Drop down datepicker with Monday as the first day. Past dates are blocked. Days without slots are blocked based on real availability
- Email verification code for phone confirmation (can be turned off)
- reCAPTCHA v3 support (optional)
- Appointment creation through REST. Client receives a manage link by email
- Booking management page with modern UI: Cancel, Reschedule, Add to Calendar and map by location address
- Admin Schedule view with monthly calendar and daily lists
- CRUD for Services, Staff, Locations with photos and contact info
- Work schedule per staff member with slot grid generation and conflict checks
- ICS file for calendar export
- Reschedule button URL is configurable in Settings

## Requirements

- WordPress 6.1 or newer
- PHP 7.4 or newer
- Outgoing email configured
- reCAPTCHA v3 keys if you want the protection

## Installation

1. Copy the `altego-wp` folder to `wp-content/plugins` or upload a zip
2. Activate the plugin in WordPress
3. Activation creates database tables and a page named `booking-manage` with the manage shortcode

## Quick start

1. Go to Altego → Services and add services (duration and price)
2. Go to Staff and add staff members (avatar, title, rating)
3. Go to Locations and add at least one location (address and contacts)
4. Configure staff work rules in Staff → schedule
5. Add the booking shortcode to a page
   ```text
   [altego_booking]
   ```
6. Submit a test booking and check the email with the manage link

## Shortcodes

- Booking widget
   ```text
   [altego_booking]
   ```
- Client booking management (the page is created on activation)

Всегда показывать подробности
```text
[altego_booking_manage]
```

## Settings

### Altego → Settings
- Timezone
- Slot step in minutes
- Manage link lifetime in hours
- OTP enabled (email code for phone confirmation)
- OTP lifetime in minutes
- reCAPTCHA enabled
- reCAPTCHA site key and secret key
- Reschedule URL (we pass a, t, date, start, end, staff_id, service_id)

### Altego → Notifications

- Toggle verification code sending and edit email templates

## Admin areas

- Schedule: month calendar with counts per day and a daily list of appointments
- Services: create and edit services
- Staff: manage staff cards, photos, titles, ratings, work rules
- Locations: address, phone numbers, website, Telegram, logo
- Import: reserved for future import tools

## Availability and slot logic

- Work rules are per staff member
- The plugin builds a slot grid from duration and slot step
- Busy times exclude slots. Only statuses new and confirmed block time
- The datepicker blocks days that have no available slots for the selected service and staff

## Booking management page

- Header with date and time
- Staff card with photo, title, rating, reviews
- Cancel changes status to canceled
- Reschedule goes to your configured URL with all parameters for prefill
- Add to Calendar returns an ICS file
- Live map based on OpenStreetMap with a marker by location address
- Contacts with quick actions (Call and Directions)

## REST API

Namespace altego/v1

`GET /services` → `{ items: [{id, name, duration, price}] }`

`GET /staff?service_id=ID` → `{ items: [{id, name}] }`

`GET /slots?staff_id=ID&service_id=ID&date=YYYY-MM-DD` → `{ slots: ["09:00", ...] }`

`GET /availability?staff_id=ID&service_id=ID&date_from=YYYY-MM-DD&date_to=YYYY-MM-DD` → `{ days: { "2025-08-16": 6, ... } }`

`POST /appointments`

Request
```json
{
  "location_id": 1,
  "staff_id": 3,
  "service_id": 12,
  "date": "2025-08-16",
  "start": "09:00",
  "recaptcha": "optional",
  "client": {"name":"John","email":"mail@site.com","phone":"+380..."}
}
```


Response contains appointment_id and manage_url

`GET /appointments` list with filters

`GET /appointments/{id}` single appointment with related names

`PUT /appointments/{id}` update with conflict check that excludes the current row

`POST /otp/send` body: `email` or `phone` (or both) and `recaptcha` if enabled

`POST /otp/check` body: `email` or `phone` (or both) and `code`

## Database tables

Prefix `wp_` is shown as an example

`wp_altego_services`

`wp_altego_staff`

`wp_altego_locations`

`wp_altego_clients`

`wp_altego_appointments`

`wp_altego_staff_rules` (work schedule)

## Hooks

`altego_mail_headers` filter to adjust email headers

`altego_appointment_created` action after an appointment is created

`altego_appointment_status_changed` action on status change

## Troubleshooting

### Send Code shows a route error
Make sure OTP REST routes are loaded and the plugin is activated.

### Invalid or expired code
The server stores the code under both email and phone keys. The check must send the same email or phone that received the code.

### Cannot pick a date
The widget shows available days only after service and staff are selected. Add work rules for that staff member.

### Week starts on the wrong day
The widget uses Monday as the first day.

## Styling

Widget styles live in `assets/css/booking.css`. Logic for lists, slots, datepicker, reCAPTCHA, OTP is in `assets/js/booking.js`.

## License

MIT