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

## Rider pages and API
Pages (`public/rider/`): `lavandera_rider_dashboard.php`, `Rider_Orders_Page.php`, `Rider_Order_History_Page.php`, `Rider_Profile_Page.php`.
All four call `guard_rider_page()` (approved riders only) and read everything from the database through two endpoints:

| Endpoint | Does |
|---|---|
| `GET api/rider/jobs.php` | `{available, current, history, stats}`. Optional `?history_limit=20&history_offset=0` pages through finished jobs (max 100 per page); `stats.completed` is the total. |
| `POST api/rider/jobs.php` | `{"action":"accept","job_id":N}` (CSRF header required). Returns the refreshed board. Only one rider can win a job. |
| `GET/POST api/rider/profile.php` | Rider name + phone (email and status cannot be changed here). |

What a rider sees, and when:
* **Available** jobs show addresses, distance and pay, but NOT the customer's name or phone.
* **Current** jobs (accepted, not finished) also show the customer's name/phone and the store's phone.
* **History** shows finished jobs without any phone numbers.
* A rider never marks a job done. The store's *Confirm delivery* completes the send job and the customer's *Confirm return* completes the return job (see the hand-off table above); the job then moves into the rider's History and earnings.
* There are no ratings or online/offline status in the database, so the pages do not show them.
* "Today" is worked out in PHP (Asia/Manila), not with `CURDATE()`, so it matches the time zone that wrote `completed_at`.

## Customer pages and API
Pages (`public/customer/`): Home, Stores, View Store, Place Order, Confirm Order, My Orders, Profile. All call `guard_customer_page()`.

| Endpoint | Does |
|---|---|
| `GET api/customer/stores.php` | Approved stores (view `v_store_listing`) with THIS customer's distance and one-leg delivery fee, plus the fee rule. Distance/fee are `null` until the customer pins an address. |
| `GET/POST api/customer/profile.php` | Name (optional), contact, address and map pin. `joined` = account creation date. |
| `GET api/customer/orders.php` | All the customer's orders, newest first, with timelines. |
| `POST api/customer/orders.php` | `place` (store_id, bag, payment, notes) / `cancel` (only while `pending_store`) / `confirm_return` (only while `enroute_to_customer`). |

Rules the server enforces (the browser only previews):
* Every price and fee is recalculated on the server; totals sent by the browser are ignored.
* The customer must have a map pin; the store must be approved, accepting orders, and have all 3 prices and a map pin.
* The same bag from the same store within 15 seconds counts as one order (double-click guard).
* Addresses, pins and fees are copied onto the order, so later profile/price changes never rewrite history.
* GCash is a simulation: the order is marked paid with a `DEMO-` reference and no money moves. The pages say so.
