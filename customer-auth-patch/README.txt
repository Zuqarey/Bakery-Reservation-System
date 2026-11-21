COZY BAKE - CUSTOMER AUTHENTICATION PATCH

Files:
- signup.php: saves customer accounts to `user` and `customer` in one transaction; stores a password hash.
- login.php: checks the stored password hash and database role membership; does not auto-create accounts on failed login.

INSTALL:
1. Back up the existing signup.php and login.php in your XAMPP project folder.
2. Copy these two replacement files into:
   C:\xampp\htdocs\Bakery\Bakery-Reservation-System\
3. Replace the old signup.php and login.php.
4. Do not replace db.php, login.html, menu.html, menu.php, or your SQL tables.
5. Open the site through http://localhost/Bakery/Bakery-Reservation-System/login.html
6. Choose NO, REGISTER and create a test customer using a normal email address (not one containing admin or staff before @).
7. After registration, verify in phpMyAdmin:
   SELECT user_id, name, email FROM `user` ORDER BY user_id DESC;
   SELECT customer_id, user_id, full_name, email FROM customer ORDER BY customer_id DESC;
8. Log out and log in with the same email and password. Try an incorrect password too; it should be rejected.

NOTES:
- This patch assumes the `user` and `customer` tables from the supplied schema exist, with the columns already identified in the PHP source.
- Public registration creates customer accounts only. Staff/admin roles must be assigned by an authorized administrator in the database, not by choosing an email containing those words.
- The patch does not modify the product menu or database schema.
