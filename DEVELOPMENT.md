# AI-Assisted POS (UMKM) — Development Instructions (Phase 1: Login & Dashboard)

## Implemented Scope
- **Login & Authentication** (`/login`, `/logout`) with CSRF protection, session regeneration on login, session invalidation + CSRF token regeneration on logout, active-user check, and remember-me support.
- **Role-Based Access Control** (`administrator`, `cashier`) via `App\Http\Middleware\RoleMiddleware`.
- **Dashboard** (`/dashboard`) with database-driven KPI cards (Rupiah formatting), 7-day Sales Overview Chart (Chart.js), Recent Transactions table, Low Stock Alert panel, and role-specific Quick Actions.
- **Placeholder Navigation** for upcoming modules (`POS / Transactions`, `Products`, `Categories`, `Transaction History`, `Users`, `Settings`).

## Development Accounts
All seeded development users use the securely hashed password: **`password`**

| Name | Email | Role | Status |
| :--- | :--- | :--- | :--- |
| Admin UMKM | `admin@pos.test` | `administrator` | Active |
| Cashier One | `cashier1@pos.test` | `cashier` | Active |
| Cashier Two | `cashier2@pos.test` | `cashier` | Active |

## Local Commands (XAMPP Windows)
```powershell
# Run migrations and development seeder against MySQL (ai_pos)
C:\xampp\php\php.exe artisan migrate:fresh --seed

# Build frontend assets (Tailwind CSS, Alpine.js, Chart.js, Inter font)
npm run build

# Run automated feature & unit tests
C:\xampp\php\php.exe artisan test

# Start local Laravel development server
C:\xampp\php\php.exe artisan serve
```
