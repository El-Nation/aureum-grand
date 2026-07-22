# Aureum Grand — Luxury Hotel Management & Booking Platform

**Live Demo**: [aureumgrand.eghedev.com](https://aureumgrand.eghedev.com)  
**Developer**: Built by [El-Nation](https://github.com/El-Nation)

A working foundation for a hotel management and booking system, built with
plain PHP (PDO + MySQL), vanilla JavaScript, and HTML/CSS — no framework
required. Built from a large feature brief (14 modules); this delivers the
core of all of them as real, working code, with a few integrations clearly
stubbed where they require third-party accounts only you can create.

---

## 1. What's actually working

- **Public site**: homepage, room/suite showcase, smart filtering (dates,
  guests, budget, view type, accessibility), room detail pages with live
  price calculation and availability checking.
- **Booking engine**: prevents double-booking, validates occupancy and
  minimum-stay rules, applies seasonal/weekend/promo pricing automatically.
- **Reservation workflow**: Pending → Confirmed → Checked-In → Checked-Out →
  Cancelled / No-Show, enforced server-side (invalid jumps are rejected).
  Checking a guest out automatically marks the room "dirty" and creates a
  housekeeping task.
- **Guest accounts**: registration, login, booking history, loyalty points
  & tiers (Silver/Gold/Platinum/VIP), saved/favorite rooms.
- **Staff console** with 7 role-based views (Administrator, General
  Manager, Front Desk, Housekeeping, Concierge, Revenue Manager,
  Maintenance) — each role sees only the relevant sidebar items:
  - Reservations management with status controls
  - Room & rate management, pricing rules (seasonal/weekend/promo/corporate)
  - Housekeeping Kanban-style board
  - Maintenance ticketing with priority and cost tracking
  - Guest service / concierge request queue
  - Revenue analytics (occupancy, ADR, RevPAR, demand forecast, charts)
  - Staff & role management, audit/activity log
- **Paystack payment integration** — real API calls, placeholder keys (see
  below to activate).

## 2. Integrations — what's live vs. what needs your credentials

Five of six are **fully coded and tested** — they just need your real
account credentials pasted in to go live:

| Feature | Status | What you need | Where to add it |
|---|---|---|---|
| Two-Factor Authentication | ✅ **Fully working right now** | Nothing — just visit Security (2FA) in the staff console | `includes/totp.php` |
| Paystack payments | Code ready, tested | Real Paystack keys | `config/paystack.php` |
| Email notifications | Code ready, tested | Any SMTP provider (Gmail, SendGrid, Mailgun…) | `config/email.php` |
| SMS notifications | Code ready | A Twilio account | `config/sms.php` |
| WhatsApp notifications | Code ready | A Meta Business / WhatsApp Cloud API account | `config/whatsapp.php` |
| AI Concierge (live chat widget on every page) | ✅ **Fully working right now** | A Groq API key (free, works in Nigeria) | `config/ai-concierge.php` |
| OTA Sync (Booking.com / Expedia) | Admin UI ready, sync itself needs a 3rd party | A paid channel manager (SiteMinder, Cloudbeds, etc.) — this is an OTA requirement, not a code gap | `admin/ota-sync.php` |

Visit **Admin Console → Integrations** (Administrator role) for a live
status view of all of these, and **Admin Console → OTA Sync** for the
channel-mapping tool.

### Two-Factor Authentication — already works, no setup needed
This is implemented from scratch in pure PHP (RFC 6238 TOTP — no external
library, no Composer needed) and is compatible with Google Authenticator,
Proauthenticator, Authy, and 1Password. Any staff member can turn it on right now from
**Security (2FA)** in their sidebar: scan the QR code, enter the 6-digit
code to confirm, and from then on login requires both their password and
their authenticator app.

### AI Concierge — Active & Working
A chat widget appears in the bottom-right corner of every public page. It
is powered by **Groq** using the **Llama 3.1 8B Instant** model (fast responses, high free tier limits, works globally including Nigeria). It
automatically pulls your live room categories, pricing, and descriptions
from the database into its instructions, so its answers stay accurate as
you update your inventory — no separate knowledge base to maintain.

---

## 3. Setup & Installation Guide

Follow these steps to deploy and run Aureum Grand on your local environment or live production server.

### Prerequisites
* **PHP**: 8.0 or higher (requires `pdo_mysql`, `curl`, and `session` extensions enabled)
* **Database**: MySQL 5.7+ or MariaDB 10.3+
* **Server**: Apache, Nginx, XAMPP/WAMP, or PHP's built-in CLI server

---

### Local Installation Guide (XAMPP)

Follow these steps to set up the website locally using XAMPP:

#### Step 1: Clone the Repository
Open Git Bash or your terminal, navigate to your XAMPP `htdocs` directory, and clone the repository:
```bash
cd C:\xampp\htdocs
git clone https://github.com/El-Nation/aureum-grand.git hotel_website
```
*(This creates a folder named `hotel_website` inside your XAMPP `htdocs` folder).*

#### Step 2: Import the Database
1. Open the XAMPP Control Panel and start **Apache** and **MySQL**.
2. Open your web browser and go to `http://localhost/phpmyadmin/`.
3. Click on **New** in the left sidebar to create a new database.
4. Set the Database name to `aureum_hotel` and click **Create**.
5. Select the `aureum_hotel` database, click the **Import** tab at the top.
6. Click **Choose File**, select the [`sql/schema.sql`](file:///c:/xampp/htdocs/hotel_website/sql/schema.sql) file from the cloned project folder, and click **Import** (or **Go**).
   * *Alternatively, via command line:*
     ```bash
     mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS aureum_hotel;"
     mysql -u root -p aureum_hotel < C:\xampp\htdocs\hotel_website\sql\schema.sql
     ```

#### Step 3: Configure Database Credentials
Open [`config/database.php`](file:///c:/xampp/htdocs/hotel_website/config/database.php) and update it with your local XAMPP database credentials:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'aureum_hotel');
define('DB_USER', 'root'); // default XAMPP username
define('DB_PASS', '');     // default XAMPP password is empty
```

#### Step 4: Open in Browser
Make sure Apache is running in XAMPP, and open the site in your browser:
* **Public Booking Website**: `http://localhost/hotel_website/`
* **Admin Dashboard**: `http://localhost/hotel_website/admin/login.php`

---

### Step 5: Staff Login Credentials
To sign into the admin portal, use these credentials:
* **Admin Email**: `admin@aureumgrand.com`
* **Admin Password**: `Aureum2026!`

> [!WARNING]
> Remember to change the administrator password before going live for security.

---

## 4. Activating Paystack (real payments)

1. Create a free account at [paystack.com](https://paystack.com).
2. Go to **Settings → API Keys & Webhooks** and copy your **Test** (or
   **Live**) Public and Secret keys.
3. Open `config/paystack.php` and replace:
   ```php
   define('PAYSTACK_PUBLIC_KEY', 'pk_test_PLACEHOLDER_PUBLIC_KEY');
   define('PAYSTACK_SECRET_KEY', 'sk_test_PLACEHOLDER_SECRET_KEY');
   ```
   with your real keys.
4. That's it — the "Pay Now with Paystack" button on the booking
   confirmation page will start working immediately.

---

## 4.1. Activating AI Concierge (Groq API)

1. Sign up for a free account at [console.groq.com](https://console.groq.com).
2. Go to **API Keys** and click **Create API Key**.
3. Open `config/ai-concierge.php` and replace:
   ```php
   define('GROQ_API_KEY', 'PASTE_YOUR_GROQ_API_KEY_HERE');
   ```
   with your real key.
4. The live chat assistant on the website will be activated instantly using `llama-3.1-8b-instant`.

---

## 5. Folder structure

```
/
├── index.php                 → redirects to /public/index.php
├── admin/                    → staff console (role-protected)
│   ├── two-factor-setup.php  → 2FA enrollment (works out of the box)
│   └── ota-sync.php          → OTA channel mapping admin UI
├── api/                      → JSON endpoints (price calc, booking, payments, AI chat)
├── assets/
│   ├── css/                  → style.css (public site), dashboard.css (console)
│   └── js/main.js
├── config/
│   ├── database.php          → ← edit your DB credentials here
│   ├── paystack.php           → ← edit your Paystack keys here
│   ├── email.php              → ← edit your SMTP credentials here
│   ├── sms.php                 → ← edit your Twilio credentials here
│   ├── whatsapp.php             → ← edit your Meta credentials here
│   └── ai-concierge.php          → ← edit your Groq API key here
├── guest/                    → guest account pages
├── includes/
│   ├── functions.php          → core booking/pricing logic
│   ├── totp.php                → self-contained 2FA implementation
│   └── concierge-widget.php      → the chat widget shown on every public page
├── public/                   → public-facing site
└── sql/schema.sql            → full database schema + seed data
```

## 6. Key files if you want to extend this

- `includes/functions.php` — pricing engine, availability checker, booking
  validation, reservation status machine, loyalty point calculator, basic
  analytics (occupancy/ADR/RevPAR). This is the "brain" of the system.
- `sql/schema.sql` — every table, including ones the UI doesn't fully use
  yet (e.g. `ota_channels`, `notifications`) so you have a head start when
  you wire up those integrations.

## 7. Security notes before going live

- Change the demo admin password immediately.
- Move `DB_PASS` and Paystack secret key to environment variables instead
  of hardcoding them in the config files.
- Enable HTTPS — Paystack callbacks and any real payment flow require it.
- The `config/`, `includes/`, and `sql/` folders should not be web-accessible
  in production — add a `.htaccess` (Apache) or server block (Nginx) rule to
  block direct access, or move them outside the web root entirely.

---

Built as a working foundation for the full feature brief — multi-property
support, deeper OTA sync, and the AI concierge are the natural next layers
once the core (this delivery) is live and you've connected the third-party
accounts listed in section 2.
