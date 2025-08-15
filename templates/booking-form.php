<div class="container altego-booking-root">

        <div class="booking-card">
            <div class="card-header">
                <h2 class="card-title">
                    <svg class="icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                    </svg>
                    Service Booking
                </h2>
                <p class="card-description">Book your appointment by filling out the form below</p>
            </div>

            <div class="card-content">
                <!-- Service -->
                <div class="form-group">
                    <label class="label">
                        <svg class="icon-small" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                        </svg>
                        Service
                    </label>
                    <div class="select-container">
                        <select id="service-select" class="select">
                            <option value="">Select a service</option>
                        </select>
                        <svg class="select-arrow" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <polyline points="6,9 12,15 18,9"/>
                        </svg>
                    </div>
                </div>

                <!-- Staff -->
                <div class="form-group">
                    <label class="label">Staff</label>
                    <div class="select-container">
                        <select id="staff-select" class="select" disabled>
                            <option value="">Select staff member</option>
                        </select>
                        <svg class="select-arrow" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <polyline points="6,9 12,15 18,9"/>
                        </svg>
                    </div>
                </div>

                <!-- Date -->
                <div class="form-group">
                    <label class="label">Date</label>
                    <div class="datepicker">
                        <input type="text" id="date-input" class="input" value="" placeholder="DD.MM.YYYY" readonly>
                        <div class="dp-popover altego-hidden" id="dp-popover"></div>
                    </div>
                </div>


                <!-- Time -->
                <div class="form-group">
                    <label class="label">
                        <svg class="icon-small" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <circle cx="12" cy="12" r="10"/><polyline points="12,6 12,12 16,14"/>
                        </svg>
                        Available Times
                    </label>
                    <div class="time-grid" id="time-grid"></div>
                </div>

                <!-- Customer -->
                <div class="form-section">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="label">Name</label>
                            <input type="text" id="customer-name" class="input" placeholder="Enter your name">
                        </div>
                        <div class="form-group">
                            <label class="label">
                                <svg class="icon-small" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>
                                </svg>
                                Email
                            </label>
                            <input type="email" id="customer-email" class="input" placeholder="Enter your email">
                        </div>
                    </div>

                    <div class="form-group" id="otp-block">
                        <label class="label">
                            <svg class="icon-small" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                            </svg>
                            Phone
                        </label>
                        <div class="phone-input-group">
                            <input type="tel" id="customer-phone" class="input phone-input" placeholder="Enter phone number">
                            <button type="button" id="send-code-btn" class="btn btn-outline">Send code</button>
                        </div>

                        <div class="verification-group" id="verification-group" style="display:none">
                            <input type="text" id="verification-code" class="input verification-input" placeholder="Enter verification code">
                            <button type="button" id="verify-btn" class="btn btn-primary">Verify</button>
                        </div>

                        <div class="verification-success" id="verification-success" style="display:none">
                            <svg class="icon-small success-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22,4 12,14.01 9,11.01"/>
                            </svg>
                            <span>Phone verified</span>
                        </div>
                    </div>
                </div>

                <?php if (Altego_Settings::get('recaptcha_enabled') && $site_key): ?>
                    <input type="hidden" id="recaptcha-token" value="">
                <?php endif; ?>

                <button type="button" id="create-appointment-btn" class="btn btn-primary btn-full" disabled>
                    Create Appointment
                </button>

                <div class="appointment-success" id="appointment-success" style="display:none">
                    <div class="success-message">
                        <svg class="icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22,4 12,14.01 9,11.01"/>
                        </svg>
                        <span>Appointment created</span>
                    </div>
                    <div class="manage-link">
                        <svg class="icon-small" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/>
                            <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>
                        </svg>
                        <a href="#" class="link" id="manage-link-a">Manage link</a>
                    </div>
                </div>

            </div>
        </div>
    </div>
<?php
$__altego_offDays = [];
if (class_exists('Altego_Workhours')) {
    $__def = Altego_Workhours::get_defaults();
    foreach ($__def as $__wd => $__row) {
        if (!empty($__row['off'])) $__altego_offDays[] = intval($__wd); // 1..7
    }
}
?>
<script>
    window.AltegoBooking = window.AltegoBooking || {};
    window.AltegoBooking.workhours = window.AltegoBooking.workhours || {};
    window.AltegoBooking.workhours.offDays = <?php echo wp_json_encode(array_map('intval', $__altego_offDays)); ?>;
</script>
