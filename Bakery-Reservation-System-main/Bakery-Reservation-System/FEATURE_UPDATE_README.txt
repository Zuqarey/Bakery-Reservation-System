COZY BAKE FEATURE UPDATE

TARGET FOLDER:
C:\xampp\htdocs\Bakery\Bakery-Reservation-System

IMPORTANT:
1. Back up the existing project folder and export the bakery_reservation_system database before applying this patch.
2. Extract this ZIP. It contains only the files changed for these three features.
3. Copy the files inside the Bakery-Reservation-System folder into the existing project folder above. Allow Windows to replace ONLY files with the same names.
4. Open phpMyAdmin, select bakery_reservation_system, choose Import, and import feature_updates_migration.sql ONCE. The script checks whether product.is_active already exists before adding it.
5. Sign in to the administrator portal and test:
   - Staff & Users: REMOVE a test staff member, refresh, then RESTORE them. The staff record is retained; status becomes Removed/Active.
   - Menu Catalog: ARCHIVE a test product, confirm it disappears from storefront menu, then RESTORE it.
   - Click the MONTHLY REVENUE card to view the detailed monthly graph.

FILES INCLUDED:
admin.html, admin_users.php, admin_catalog.php, menu.php, staff_data.php, payment.php, feature_updates_migration.sql

NOTES:
- This patch does not overwrite your database or replace the whole project.
- Revenue graphs use recorded payment totals. The current database does not provide product-cost data to calculate net profit, so the graph is labelled revenue rather than profit.
- PHP syntax checks passed for all changed PHP files, and the JavaScript in admin.html passed Node syntax checks. Live browser/database testing still needs to be done in your XAMPP installation.
