COZY BAKE - REQUESTED FEATURE UPDATES

Included:
1. Staff directory removal: admin can mark an individual staff member as Removed. The record and user account are retained. Removed staff can be restored from the same directory.
2. Profit statistics graph: the detailed Profit Statistics modal now displays a monthly line graph from existing payment totals. The project does not record ingredient costs, labour costs, or operating expenses, so this is revenue/sales, not net profit.
3. Storefront product archive: removing a product now archives it (soft removal). Archived products are hidden from the storefront and can be restored from the admin catalog.

IMPORTANT FIRST STEP:
1. Back up your current bakery_reservation_system database in phpMyAdmin (Export).
2. In phpMyAdmin, select bakery_reservation_system > Import.
3. Import feature_updates_migration.sql from this package. It adds product.is_active with a default of 1; existing products remain visible.
4. Copy the updated project files over your existing Bakery-Reservation-System folder, keeping your current folder name/path. The ZIP contains the project folder and this migration.
5. Log in as administrator and test each feature.

Files changed:
- admin.html
- admin_catalog.php
- admin_users.php
- menu.php
- feature_updates_migration.sql

No database rows are deleted by these features. Staff removal is an admin action, not a customer-facing permission. Product archive preserves product IDs and existing order-item references.
