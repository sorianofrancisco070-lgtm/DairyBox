# 🐃 DairyBox – Production & Herd Health System

**South East Asian Institute of Technology – CICT**  
Dairy Box Surallah, D.A Compound Surallah, South Cotabato, Philippines

---

## Overview

A full web-based dairy farm management system with role-based access for:
- **Farm Managers** – Full control, analytics, reporting, user management
- **Farm Caretakers** – Daily milk recording, health logs, vaccinations
- **Dairy Cooperatives** – Production analytics and member farm reporting
- **Veterinarians** – Health records, vaccination schedules, early detection

---

## Features

| Feature | Description |
|---|---|
| 🐃 Buffalo Records | Complete digital profile per animal with QR code |
| 🥛 Milk Production | Daily/session recording with analytics & charts |
| ❤️ Health Records | Diagnoses, treatments, follow-ups |
| 💉 Vaccinations | Schedule management, overdue alerts |
| 🤰 Breeding & Calving | Breeding logs, pregnancy tracking, calving records |
| 📊 Production Analytics | Charts by day/month/buffalo, session breakdown |
| 🔍 QR Code Lookup | Scan or search to pull full animal profile instantly |
| ⚠️ Early Detection | Production drop alerts, prolonged illness flags |
| 🧠 Decision Support | Data-driven recommendations for farm management |
| 📦 Inventory | Medicine/vaccine stock with low-stock & expiry alerts |
| 🔔 Notifications | Role-targeted alerts for vaccinations, calving, health |
| 📄 Reports | Printable monthly reports (production, health, breeding) |
| 👤 User Management | Role-based access control (Farm Manager only) |

---

## Tech Stack

| Layer | Technology |
|---|---|
| Frontend | HTML5, CSS3, Bootstrap 5.3, JavaScript |
| Charts | Chart.js 4.4 |
| QR Codes | QRCodeJS |
| Backend | PHP 8+ (procedural, no framework required) |
| Database | MySQL 8 / MariaDB 10.4+ |
| Icons | Font Awesome 6.5 |

---

## Installation

### Requirements
- PHP 8.0+ with PDO and PDO_MySQL extensions
- MySQL 8.0 / MariaDB 10.4+
- Apache or Nginx with mod_rewrite

### Steps

1. **Clone / copy** files to your web server root (e.g. `htdocs/dairybox/`)

2. **Run the setup wizard:**
   ```
   http://localhost/dairybox/setup.php
   ```
   Enter your MySQL credentials and click **Run Setup**. This creates the database, tables, and sample data.

3. **Delete `setup.php`** after successful setup.

4. **Access the system:**
   ```
   http://localhost/dairybox/
   ```

5. **Login with default credentials:**

   | Username | Password | Role |
   |---|---|---|
   | manager1 | password | Farm Manager |
   | caretaker1 | password | Farm Caretaker |
   | coop1 | password | Dairy Cooperative |
   | vet1 | password | Veterinarian |

---

## Manual Database Setup (Alternative)

If you prefer to set up manually:

```bash
mysql -u root -p < database/dairybox_db.sql
```

Then update `config/database.php` with your credentials.

---

## Directory Structure

```
dairybox/
├── index.php                    # Login page
├── setup.php                    # One-time database installer
├── assets/
│   ├── css/style.css            # Custom styles
│   └── img/                     # Place logo.png here
├── auth/
│   ├── login.php                # Auth handler
│   └── logout.php
├── config/
│   ├── database.php             # DB connection
│   └── session.php              # Auth helpers
├── database/
│   └── dairybox_db.sql          # Full schema + sample data
├── includes/
│   ├── header.php               # Shared page header + sidebar
│   ├── footer.php               # Shared page footer
│   ├── nav_farm_manager.php     # Role nav menus
│   ├── nav_farm_caretaker.php
│   ├── nav_dairy_cooperative.php
│   └── nav_veterinarian.php
├── modules/                     # Shared feature modules
│   ├── buffaloes.php            # Buffalo CRUD + search
│   ├── milk_production.php      # Milk recording
│   ├── health_records.php       # Health events
│   ├── vaccinations.php         # Vaccination management
│   ├── breeding.php             # Breeding + calving
│   ├── production_analytics.php # Charts & analytics
│   ├── qr_scan.php              # QR lookup & generation
│   ├── early_detection.php      # Automated alerts
│   ├── decision_support.php     # Recommendations
│   ├── inventory.php            # Stock management
│   ├── notifications.php        # Alerts & reminders
│   ├── reports.php              # Printable reports
│   └── users.php                # User management
├── Farm_Managers_User/
│   └── dashboard.php
├── Farm_Caretakers_USer/
│   └── dashboard.php
├── Dairy_Cooperatives_USer/
│   └── dashboard.php
└── Veterinarians_User/
    └── dashboard.php
```

---

## Proponents

- Dela Torre, Vine Angel L.
- Jalandoni, Ronel S.
- Soriano, Francisco

**SEAIT – College of Information and Communication Technology**
