# AI-Assisted POS (UMKM Retail Point of Sale)

An AI-assisted Point of Sale (POS) and retail management web application built with **Laravel 13**, **Tailwind CSS 4**, **Alpine.js**, and **Chart.js**, tailored for small retail businesses and **UMKM** (groceries, convenience stores, and multi-unit retail).

---

## Main Features

### Point of Sale & Checkout
- **Manual POS Terminal**: Fast product search, category filtering, responsive mobile/desktop cart, and base/custom selling unit selection.
- **Editable Orders & Prices**: Adjust quantities, remove items, modify transaction prices with override reasons, and edit orders before finalizing.
- **Hold, Pending & Resume Transactions**: Pause an active cart (`Hold Transaction / Pending`), serve other customers, and resume pending carts with active-cart conflict protection and stock validation warnings.
- **Automatic Payment & Change Calculation**: Enter `Amount Paid (Cash Received)` to automatically compute `Change` or `Remaining Debt` without manual payment status toggles.
- **Optional Staff Assignment ("Taken By")**: Assign an active staff member (`STF-XXXX`) who picked/prepared the order at Checkout via a searchable dropdown, kept separate from the logged-in Cashier.

### AI-Assisted Order Input (Optional)
- **Voice / Speech Recognition Order Input**: Convert spoken customer orders into structured line items.
- **Photo / OCR Order Input**: Extract order items from handwritten notes or order photos.
- **AI Product Matching & Review**: Matches extracted items against your catalog for cashier review before adding to the cart.
- **Graceful Fallback**: Manual POS, inventory, customer, debt, and reporting features work **100% independently** even if no AI API key is configured.

### Inventory, Units & Retail Intelligence
- **Flexible Product Units & Cost/Profit**: Support multiple selling units per product (e.g., piece, pack, box, carton, slop) with conversion ratios, cost price, selling price, and profit margin tracking.
- **Auto-Generated SKUs**: Automatically generates product codes (`PRD-XXXX`), customer IDs (`CUS-XXXX`), and staff IDs (`STF-XXXX`).
- **Stock Movements & Adjustments**: Consistent base-unit stock deductions, manual stock adjustments, and out-of-stock alternative product suggestions.
- **Smart Retail Analytics**:
  - Auto-refreshing Sales Dashboard (every 60 seconds + manual refresh).
  - Top Selling Products (table & Chart.js view) and Top Selling Categories.
  - Smart Restock Recommendations (lead time, safety stock, stock coverage days, priority levels).
  - Dead Stock & Slow-Moving Product Detection.
  - Weekly AI Business Insight summary.

### Customers, Debt & Reporting
- **Customer Debt Management**: Track unpaid/partially paid balances, view the colored Outstanding Debt Running Ticker on the Dashboard, and record repayments with printable debt receipts.
- **Optional "Pay Now" & Admin Debt Removal**: Cashiers can optionally include a customer's previous debt in a new checkout (FIFO settlement), and Administrators can write off or mark externally settled debts with an audit trail.
- **Transaction History & Receipts**: Filterable by date, payment method, status, cashier, and `Taken By` staff; printable thermal-style receipts; admin transaction cancellation (with stock restoration); and CSV/printable Sales Reports.

---

## Tech Stack

- **Backend**: PHP `^8.3`, Laravel `^13.17`
- **Frontend**: Blade, Tailwind CSS `^4.0`, Alpine.js `^3.17`, Chart.js `^4.6`, Vite `^8.0`
- **Database**: MySQL 8+ / MariaDB (default local setup) or SQLite

---

## Prerequisites

Make sure the following are installed on your computer:

- **PHP 8.3+** (e.g., via [XAMPP](https://www.apachefriends.org/) or [Laragon](https://laragon.org/)) with standard Laravel extensions enabled (`pdo_mysql`, `pdo_sqlite`, `mbstring`, `openssl`, `fileinfo`, `curl`, `zip`)
- **Composer** ([getcomposer.org](https://getcomposer.org/))
- **Node.js 20+ & npm** ([nodejs.org](https://nodejs.org/))
- **MySQL / MariaDB** (included in XAMPP / Laragon) — *or SQLite for quick testing*
- **Git** ([git-scm.com](https://git-scm.com/))

---

## Installation Guide (Windows / XAMPP)

### Option A: Automated Windows Helper (`setup.bat`)

```bat
git clone <repository-url>
cd ai-pos
setup.bat
```

Then follow the database instructions printed at the end of `setup.bat`.

### Option B: Manual Step-by-Step Setup

1. **Clone the repository and enter the folder:**
   ```bash
   git clone <repository-url>
   cd ai-pos
   ```

2. **Install PHP dependencies:**
   ```bash
   composer install
   ```

3. **Create your `.env` file and generate the application key:**
   ```bash
   copy .env.example .env
   php artisan key:generate
   ```

4. **Configure your database in `.env`:**
   - **MySQL (Recommended for XAMPP/Laragon):**
     1. Start **MySQL** in XAMPP/Laragon.
     2. Create an empty database named `ai_pos` (e.g., in phpMyAdmin or MySQL CLI: `CREATE DATABASE ai_pos;`).
     3. Verify `.env` has:
        ```env
        DB_CONNECTION=mysql
        DB_HOST=127.0.0.1
        DB_PORT=3306
        DB_DATABASE=ai_pos
        DB_USERNAME=root
        DB_PASSWORD=
        ```
   - **Quick SQLite Alternative:**
     Comment out the MySQL `DB_*` lines in `.env`, set `DB_CONNECTION=sqlite`, and create an empty `database/database.sqlite` file.

5. **Run database migrations and seed demo data:**
   ```bash
   php artisan migrate
   php artisan db:seed
   ```
   *(The seeders are idempotent and create demo users, categories, products, customers with sample debts, demo staff, and sample transactions.)*

6. **Create the storage symlink and build frontend assets:**
   ```bash
   php artisan storage:link
   npm install
   npm run build
   ```

7. **Start the local development server:**
   ```bash
   php artisan serve
   ```
   Open [http://127.0.0.1:8000](http://127.0.0.1:8000) in your browser.

---

## Demo Login Credentials

> **Warning:** These credentials are for **local development and testing only**. Change passwords or remove demo accounts before deploying to production.

| Role | Email | Password |
| :--- | :--- | :--- |
| **Administrator** | `admin@pos.test` | `password` |
| **Cashier 1** | `cashier1@pos.test` | `password` |
| **Cashier 2** | `cashier2@pos.test` | `password` |

---

## AI Configuration (Optional)

Speech Recognition, Photo/OCR Order Extraction, and AI Weekly Insights use your own AI provider API key (which may incur provider usage costs depending on your plan).

1. **Via `.env`:**
   ```env
   AI_PROVIDER=gemini
   AI_API_KEY=your_api_key_here
   AI_MODEL=gemini-3-flash-preview
   AI_BASE_URL=
   AI_TIMEOUT=30
   ```
   Supported `AI_PROVIDER` values: `gemini`, `openai`, `anthropic`, `custom`.

2. **Via Admin UI:**
   Log in as **Administrator** and open **Settings → AI Settings** to configure the provider, model, API key (stored encrypted in the database), and toggle Voice / OCR / Matching features.

- **Without an API key:** The app will not crash. AI screens clearly show that the AI service is unconfigured, and all **Manual POS**, **Inventory**, **Customer/Debt**, **Hold/Pending**, and **Reporting** features continue working normally.

---

## Development & Testing

```bash
# Run automated PHPUnit tests (uses in-memory SQLite configured in phpunit.xml)
php artisan test

# Run Vite dev server with hot reload during frontend development
npm run dev

# Optional: Seed 180 extra Indonesian grocery (toko sembako) demo products
php artisan db:seed --class=SembakoCatalogSeeder
```

---

## Troubleshooting

- **`php` or `composer` is not recognized (Windows):** Add `C:\xampp\php` and Composer's bin directory to your Windows `PATH`, or invoke `C:\xampp\php\php.exe` directly.
- **Database connection refused (`SQLSTATE[HY000] [2002]`):** Make sure MySQL is started in XAMPP/Laragon and that the `ai_pos` database exists and matches `.env`.
- **Missing PHP extensions (`pdo_mysql`, `pdo_sqlite`, `mbstring`, `fileinfo`, `zip`):** Enable them in `php.ini` (remove the leading `;` on `extension=...`) and restart your terminal/server.
- **No application encryption key (`MissingAppKeyException`):** Run `php artisan key:generate`.
- **Frontend styles missing / Vite manifest not found:** Run `npm install && npm run build`.
- **Port 8000 already in use:** Start on another port with `php artisan serve --port=8080`.
- **Stale config or view cache:** Run `php artisan optimize:clear`.
- **AI features say API key missing:** Add your key in `.env` or **Settings → AI Settings**, and ensure the AI integration toggle is enabled.
