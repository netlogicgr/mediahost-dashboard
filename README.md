# MediaHost Dashboard

PHP/MySQL dashboard that connects to multiple cPanel/WHM servers and shows **CPU / RAM / DISK / IO** metrics on a public auto-refreshing page (10s).

## Features

- Public page (`/`) with Bootstrap cards and auto-refresh every 10 seconds.
- Installation wizard (`/install.php`) to configure DB + create first admin user.
- Admin panel:
  - Login/logout
  - Add/remove servers (host + auth type + username + API token)
  - Dashboard overview
  - App updater from GitHub ZIP URL or direct ZIP upload
- Metrics endpoint (`/api/stats.php`) stores only the latest snapshot per server in MySQL (`server_stats`).

## Requirements

- PHP 8.1+
- Extensions: `pdo_mysql`, `curl`, `zip`
- MySQL 5.7+ / MariaDB

## Installation

1. Upload project files to server.
2. Make sure `config/` is writable during first install.
3. Open `/install.php`.
4. Fill DB credentials and admin credentials.
5. Login via `/admin/login.php`.
6. Add servers in `/admin/servers.php`.

> If your app is served from a subfolder (for example `https://domain.com/public/`), links and API calls are auto-detected so the public page can still reach `api/stats.php` without 404 errors.

## cPanel/WHM API Notes

- For WHM token auth, use host like `https://YOUR_SERVER:2087`, auth type `WHM`, username usually `root`.
- For cPanel user token auth, use host like `https://YOUR_SERVER:2083`, auth type `cPanel`, username = cPanel account username.
- The app queries API endpoints and tries to map available numeric values to CPU/RAM/DISK/IO fields.

## Local Run (development)

```bash
php -S 0.0.0.0:8000 -t public
```

Then open `http://localhost:8000`.


## Update behavior

- You can update from **Admin → Updates** using either a ZIP URL or a ZIP file upload.
- After update copy completes, sensitive bootstrap files are removed automatically (`public/install.php`, `README.md`).
