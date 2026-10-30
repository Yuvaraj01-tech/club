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


## Profile / verification v2
- Email is the username.
- No profile photo and no document number are collected.
- Marital status: Never Married, Separated, Divorced, Widowed.
- Government document types: Aadhaar, PAN, Voter ID, Driving Licence, Any Government Document.
- Age is calculated only from DOB and 18+ is enforced server-side.
- Signup shows inline field errors and password visibility controls.
- Admin Verification has list view, Open Details modal, Approve and Reject actions. Rejection reason is required and shown in an admin alert box; email notification is attempted.
- Rejected users can edit and resubmit from My Profile.


## SMTP mail setup
The project is configured for the SMTP details supplied for `admin@sangamam.org.in`:
- Host: `mail.sangamam.org.in`
- Port: `465`
- Encryption: `ssl`
- Username: `admin@sangamam.org.in`
- Password: enter the email account password in `backend/config/config.php`

Install PHPMailer from the project root with:
```bash
composer install
```
Do not commit the real SMTP password.

## Existing database
If you already imported an older Hello Hobnobbers database, run `database/migration-profile-verification-v2.sql` after taking a database backup. A fresh install should use `database/install.sql`.

## Hello Hobnobbers SMTP configuration

The supplied Webmail account uses secure SMTP:

- From / Username: `support@hellohobnobbers.com`
- SMTP host: `mail.hellohobnobbers.com`
- SMTP port: `465`
- Encryption: `SSL`
- Authentication: required

Set the real Webmail password in `backend/config/config.php` under `mail.smtp.password`. Do not put the password in frontend JavaScript or commit it to source control.

Install PHPMailer before testing SMTP:

```bash
composer install
```

Razorpay test keys go in the same `backend/config/config.php` file under `razorpay.key_id` and `razorpay.key_secret`.
