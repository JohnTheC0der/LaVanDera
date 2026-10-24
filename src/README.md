# LaVanDera backend (PHP + MySQL on XAMPP)

## First-time setup
1. Install XAMPP, start **Apache** and **MySQL** in the XAMPP Control Panel.
2. Put the project at `C:\xampp\htdocs\LaVanDera\` (so this folder is `...\LaVanDera\src`).
3. Open http://localhost/phpmyadmin -> **Import** -> choose `database/lavandera.sql`
   (then optionally `database/lavandera_sample_data.sql` for demo stores/orders; demo password: `password`).
4. DB credentials live in `src/config/config.php` (XAMPP default: user `root`, no password).
5. Open http://localhost/LaVanDera/public/store/Store_Dashboard_Page.php

Already imported the schema before? Add the two new store benefits without re-importing:

    INSERT IGNORE INTO benefits (code, name, description) VALUES
      ('ECO_PACKAGING','Eco-friendly packaging','returns laundry in eco-friendly packaging'),
      ('EXPRESS_SERVICE','Express service','offers faster turnaround on orders');
    UPDATE benefits SET description='waives the send and return rider fees for customers' WHERE code='FREE_DELIVERY';

## Folder map
    public/          what the browser loads (pages, css, js, images, api/, uploads/)
      api/store/     JSON endpoints used by the store pages
      uploads/       store images saved by PHP (not committed to git)
    src/             PHP logic, not directly reachable from the browser (.htaccess blocks it)
      bootstrap.php  include first in every page/endpoint: config + session + helpers
      auth.php       login_user(), logout_user(), current_user(), page/API guards
      fees.php       calc_delivery_fee($km)  <- use this for send/return fees on the customer side too
      store/         store profile + order logic
      admin/         admin_repo.php: dashboard numbers, customer/store/rider lists, approve/reject/suspend
    database/        lavandera.sql (+ sample data)

## For the sign-in / sign-up code (shared contract)
- Start every PHP file with `require __DIR__ . '/../../src/bootstrap.php';` (adjust the `../`).
- After verifying the password (or creating the account) call `login_user(['id' => $id, 'role' => $role]);`
  then redirect by `users.status` (see the comment at the top of `src/auth.php`).
- New accounts: customer `active`, rider `pending_approval`, store `setup_required`.
- Use `password_hash()` / `password_verify()` for passwords.
- Page links to the login and pending pages are set once in `src/config/config.php`
  (`login_file`, `pending_store_file`) - change the `.html` to `.php` there when the login page is converted.
- **Store pending-approval page** (`public/auth/Store_Pending_Approval_Page.php`) reads the status from the database.
  A store that just submitted setup is signed out but remembered via `sign_out_pending_store()`. When your sign-in code
  finds a correct email + password for a store with status `pending_approval`, call `remember_pending_store($id)`
  (don't sign them in) and redirect to `pending_store_url()`.
- `current_user()` also returns `name` (store / customer / rider name); the store pages pass it, with the email, to the
  shared navbar. Customer / rider pages can do the same: put `window.LV_SESSION = {id, role, name, email}` in the page and
  load `public/js/lavandera_api.js` before calling `LV.renderNav()`. It also makes the navbar Logout end the PHP session.

## Admin pages
- Pages: `public/admin/Admin_{Dashboard,Customers,Stores,Riders}_Page.php` (only a signed-in `admin` with status `active`; anyone else is sent to the login page).
- API: `public/api/admin/{dashboard,customers,stores,riders}.php`. Stores and riders accept POST `{action: approve|reject|suspend, id, reason}`:
  `approve` = pending/rejected/suspended -> active (also "Reactivate"), `reject` = pending -> rejected (reason shown to the applicant),
  `suspend` = active -> suspended. Wrong-state requests get a clear "status has changed" message. `users.reviewed_by/reviewed_at` are filled in.
- Stores page also has Feature / Unfeature (`action: feature|unfeature`, sets `stores.is_featured`; only active stores can be featured). The Customer Home page should list `is_featured = 1` stores from `v_store_listing`.
- The sign-in code must already block `rejected` / `suspended` accounts (that is what makes a suspension take effect).

## Store location and delivery fees (OpenStreetMap)
- `stores.latitude/longitude` are required when a store submits setup or saves its dashboard (Philippines only).
- `public/js/lavandera_map.js` -> `LVMap.picker({...})`: Leaflet map + "Find address on map" button (Nominatim, only when the
  button is pressed) + draggable pin + hidden `latitude` / `longitude` inputs. Reuse it for the customer's address.
- Leaflet is loaded from cdnjs in the page (`leaflet.css` / `leaflet.js`, v1.9.4). Needs internet; no API key.
- `distance_km($lat1, $lng1, $lat2, $lng2)` in `src/fees.php` (same formula as `LV.distanceKm`) then
  `calc_delivery_fee($km)` gives the send / return fee. Copy the result onto the order when it is placed.

## Order status hand-offs (so rider / customer code matches the store code)
| Step | Who | What must change |
|---|---|---|
| Place order | customer | `orders.status='pending_store'` (copy addresses, fees, distance onto the order) |
| Confirm | store | status -> `waiting_for_driver`, creates `delivery_jobs` row (`send`, `available`) |
| Rider accepts send job | rider | job -> `accepted` + `rider_id`; **order -> `enroute_to_store`** |
| Confirm delivery | store | order -> `at_laundromat`; send job completed; cash marked paid |
| Return to customer | store | order -> `waiting_for_return_driver`; creates `return` job |
| Rider accepts return job | rider | job -> `accepted`; **order -> `enroute_to_customer`** |
| Confirm return | customer | order -> `completed` (+ `completed_at`); return job completed |
Write a row to `order_status_history` on every change, as `store_order_set_status()` does.
