# HELLO HOBNOBBERS

Responsive clubbing & event booking web application built for PHP 8+/MySQL, XAMPP and cPanel.

## Stack
- Frontend: semantic HTML5, vanilla ES6 JavaScript, CSS3, responsive UI
- Backend: PHP 8+, PDO, session authentication
- Database: MySQL 5.7+/8
- Payments: Razorpay
- QR: QRServer remote image generation (configurable)
- Email: PHP mail() or PHPMailer SMTP
- Optional WhatsApp: Meta WhatsApp Cloud API

## Local XAMPP
1. Extract this folder into `C:\xampp\htdocs\hello-hobnobbers`.
2. Start Apache + MySQL.
3. Create a database named `hello_hobnobbers`.
4. Import `database/install.sql` in phpMyAdmin.
5. Edit `backend/config/config.php`:
   - database name/user/password
   - app URLs
   - Razorpay test/live keys
   - email sender / SMTP
6. Open:
   `http://localhost/hello-hobnobbers/frontend/`
7. Admin:
   `http://localhost/hello-hobnobbers/frontend/admin-login.html`
   - username: `admin`
   - first password: `ChangeMe@123`
   - change the admin password before production.

## Production
Set real database credentials, HTTPS URLs, Razorpay keys, email SMTP and any WhatsApp credentials in `backend/config/config.php`.

## User flow
Guest -> Home/Events -> Event Details -> Book -> Sign In/Sign Up -> Profile + identity document -> Admin review -> approval/rejection -> payment -> booking confirmed -> QR event pass.

A rejected user can sign in, see `Correction Required`, edit the profile and resubmit.

## Admin
Separate login and dashboard:
- Dashboard: users, gender, events, bookings, revenue, attendance
- Users: approved, rejected, blocked, pending
- Verification: open details, view document securely, approve/reject
- Events: create, draft/published/completed/cancelled filters
- Bookings: all/confirmed/pending/cancelled
- Payments: successful/failed/pending/refunds
- Event Passes: active/used/expired/cancelled
- Announcements: all approved users or confirmed event attendees
- Check-in: camera QR scanner where browser BarcodeDetector is supported, plus manual pass code
- Reports: users, events, bookings, payments, cancellations, attendance CSV

## 2-day reminders
Configure a daily cPanel cron to request:
`backend/api/cron/send-event-reminders.php`
It sends an in-app notification and email to confirmed attendees whose event is two days away. The activity log prevents duplicate reminder sends.

## Important
- Do not keep real API secrets in source control.
- Uploaded identity documents are served through an authenticated admin endpoint.
- Email delivery depends on correct domain mail/SMTP configuration.
- Razorpay must be configured before live payment testing.
