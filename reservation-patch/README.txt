COZY BAKE - CUSTOMER RESERVATION PATCH

IMPORTANT: This patch adds MySQL-backed reservations. It does not replace db.php,
login.php, signup.php, menu.php, menu.html, or index.html.

INSTALLATION
1. Back up your existing reservation.html and custdashboard.html.
2. Open phpMyAdmin: http://localhost/phpmyadmin
3. Select bakery_reservation_system, open SQL, paste and run reservation.sql.
4. Copy reservation.php, getreservations.php, reservation.html, and custdashboard.html
   from this patch into:
   C:\xampp\htdocs\Bakery\Bakery-Reservation-System\
5. Replace only reservation.html and custdashboard.html when asked. Add the two new
   PHP files. Do not replace db.php or the working menu files.
6. Make sure you are logged in as a customer before opening reservation.html.

TEST
1. Open http://localhost/Bakery/Bakery-Reservation-System/reservation.html
2. Choose outlet, a future date/time, guest count and a table; enter phone number.
3. Submit. The page should show a booking ID and then return to the dashboard.
4. In phpMyAdmin run:
   SELECT * FROM reservation ORDER BY reservation_id DESC;
5. Open custdashboard.html and check Upcoming Booking / Booking History.

NOTES
- Reservations are stored in the new reservation table and linked to customer.customer_id.
- A table at the same outlet/date/time can only be reserved once (unique database key).
- Table capacity follows the original page's rule: T1-T5 = 2 seats, T6-T10 = 4,
  T11-T15 = 6.
- This patch saves reservation requests as Pending. It does not implement staff approval,
  cancellation, or editing yet.
