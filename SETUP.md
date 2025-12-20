# FMS Project Setup Instructions

Follow these steps to set up the Faculty Management System (FMS) on a new PC or environment.

## 1. Prerequisites
- PHP 8.2+
- Composer
- Database (MySQL/MariaDB)

## 2. Setup Steps

### Step 1: Install Dependencies
```bash
composer install
npm install
```

### Step 2: Environment Setup
Copy `.env.example` to `.env` and configure your database credentials.
```bash
cp .env.example .env
php artisan key:generate
```

### Step 3: Database Migration & Role Generation
Run the migrations and generate the Shield roles/permissions.
> **Note:** Shield generation is required *before* seeding because it automatically creates the `super_admin` role.

```bash
php artisan migrate
php artisan shield:generate --all
```
*Select `yes` to all prompts if asked.*

### Step 4: Seed Database
Run the seeders to create the default users.
```bash
php artisan db:seed
```

## 3. Account Credentials
The following accounts are created with the default password: `123456789`

| Role | Email |
| :--- | :--- |
| **Super Admin** | `milton2913@gmail.com` |
| **Admin** | `admin@fms.diu.edu.bd` |
| **Registrar** | `registrar@fms.diu.edu.bd` |
| **Teacher** | `teacher@fms.diu.edu.bd` |
| **Researcher** | `researcher@fms.diu.edu.bd` |

## 4. Troubleshooting
- If `super_admin` role error occurs during seeding, ensure you ran `php artisan shield:generate --all` first.
