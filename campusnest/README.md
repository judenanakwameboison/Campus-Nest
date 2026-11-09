# CampusNest: Hostel & Rent Management System
PHP 8 + MySQL + HTML/CSS/JavaScript

## Run it (XAMPP on Windows)
1. Install XAMPP (PHP 8.x). Open the XAMPP Control Panel and start **Apache** and **MySQL**.
2. Copy this whole folder to `C:\xampp\htdocs\` so you have `C:\xampp\htdocs\campusnest\`.
3. Open http://localhost/phpmyadmin, click **Import**, choose `database/campusnest_db.sql`, then click **Import**.
   (This creates the database `campusnest_db` with tables and sample data.)
4. Open http://localhost/campusnest/

## IMPORTANT: re-import the database
This version adds new tables (notifications, chat messages, reviews, roommate profiles, room photos). In phpMyAdmin, **Import** `database/campusnest_db.sql` again. It rebuilds everything with fresh sample data (including sample room photos and a 360 view).

## New features
- **Dark mode** (moon icon), **Ctrl+K search**, **live notifications** (bell, checks every 15 seconds)
- **Pay rent** (tenant): simulated mobile money with an animated phone and PIN, then a receipt. **Download PDF** on every receipt.
- **Rent reminders**: Admin > Reports > "Send rent reminders"
- **Chat**: tenant > Chat, admin > Chat (updates every 3 seconds)
- **Reviews** (tenant dashboard) and **roommate matching** (tenant > Roommates)
- **Room page** with photo gallery and **360 view**; **Hostel map**. Admin uploads photos at Admin > Rooms > "Photos / 360".
- Put your real address in `HOSTEL_ADDRESS` in `config/db.php` for the Google Maps button.

## Demo logins
| Role   | Email                    | Password   |
|--------|--------------------------|------------|
| Admin  | admin@campusnest.test    | Admin@123  |
| Tenant | ama@student.test         | Tenant@123 |
| Tenant | kofi@student.test        | Tenant@123 |

## Room photos
Log in as admin, open **Rooms**, and in the "All rooms" table use **Upload photo** on a room (JPG, PNG or WebP, max 2 MB). Files are saved in `assets/img/rooms/` and shown on the public rooms page.

## Settings
- Folder name different from `campusnest`? Change `BASE` in `config/db.php`.
- MySQL root has a password? Change the password in `config/db.php`.

## Folder map
- `index.php`, `book.php`, `login.php` : public pages (guest)
- `admin/` : dashboard, rooms, bookings, tenants, payments, maintenance, reports, audit log
- `tenant/` : tenant dashboard (room, balance, payments, repairs)
- `receipt.php`, `password.php` : shared pages
- `api/rooms.php` : JSON used by the live room search (Fetch)
- `includes/` : auth guards, helpers, header and footer
- `database/campusnest_db.sql` : structure + sample data (export this again before submitting)

## Security built in
PDO prepared statements, password_hash/verify, session + role checks on every protected page, htmlspecialchars output escaping, CSRF tokens on every POST, session regeneration on login, login lockout after 5 failures, 30-minute idle timeout, audit log.

## Quick test checklist (new features)
- Log in as `ama@student.test`: open **Pay rent**, pay GHS 100 using any 10-digit number and any 4-digit PIN, then download the PDF.
- Open **Chat** as Ama, then in another browser log in as admin and reply: both sides update live.
- Open the **Map**, click a room, try the **360 view** on room A101, and toggle dark mode.

## Quick test checklist
1. Log in as admin. Dashboard shows rooms, overdue rent and pending bookings.
2. Log out. On the home page type in the search box: rooms filter live.
3. Request a booking as a guest, then approve it as admin (a temporary tenant password is shown once).
4. Approve a second booking for the same room: refused.
5. Record a part-payment, open the receipt, print it.
6. Log in as a tenant, send a maintenance request, then update it as admin.
7. As a tenant, open http://localhost/campusnest/admin/dashboard.php : Access denied.
