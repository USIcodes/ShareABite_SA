
# ShareABite SA

ShareABite SA is a PHP and MySQL food-sharing marketplace for buyers, sellers, donors, and admins. Users can register, log in, browse listings, place orders, manage seller listings, view admin reports, and enable authenticator-app verification for extra account security.

## Features

- Buyer, seller, and admin roles
- User registration and password login
- Optional authenticator-app two-factor authentication
- Food listing browsing, creation, editing, and moderation
- Buyer order history and tracking
- Seller order confirmation
- PayFast and PayShap payment flow pages
- MySQL schema and migration files included

## Requirements

- PHP 8.0 or newer
- MySQL or MariaDB
- A web server such as Apache, Nginx, XAMPP, or cPanel hosting

GitHub Pages cannot run this website because it uses PHP and MySQL. GitHub can host the source code, but the live website must be deployed to PHP-capable hosting.

## Local Setup

1. Place the project folder in your web root, for example:

   ```text
   C:\xampp\htdocs\Shareabite
   ```

2. Start Apache and MySQL in XAMPP.

3. Import the database:

   ```sql
   SOURCE shareabite.sql;
   ```

4. Visit:

   ```text
   http://localhost/Shareabite/
   ```

## Database Configuration

By default, the app uses local XAMPP-style credentials:

```text
Host: 127.0.0.1
Database: shareabite_sa
User: root
Password: empty
Port: 3306
```

For online hosting, set these environment variables if your host supports them:

```text
SHAREABITE_DB_HOST
SHAREABITE_DB_NAME
SHAREABITE_DB_USER
SHAREABITE_DB_PASS
SHAREABITE_DB_PORT
```

If your host does not support environment variables, update `includes/db.php` with the database credentials supplied by the host.

## Existing Database Migration

If your database already existed before authenticator support was added, run:

```sql
ALTER TABLE users
  ADD COLUMN twoFactorSecret VARCHAR(64) DEFAULT NULL AFTER payshapNumber,
  ADD COLUMN twoFactorEnabled TINYINT(1) NOT NULL DEFAULT 0 AFTER twoFactorSecret;
```

The same migration is stored in:

```text
migrations/2026_06_12_add_two_factor_to_users.sql
```

## Demo Accounts

The sample database includes these users:

```text
admin@shareabite.co.za
thabo@example.co.za
nomvula@example.co.za
```

Demo password:

```text
Password1!
```

Change or remove demo users before publishing a real production site.

## Deployment Notes

- Upload all project files except ignored runtime files.
- Import `shareabite.sql` into your hosting database.
- Configure database credentials in the host environment or in `includes/db.php`.
- Make sure the `uploads` directory is writable by the web server.
- Use HTTPS in production so login/session cookies are protected.
- Do not commit real database passwords, API keys, or session files.

=======
# ShareABite_SA
ShareABite SA helps households, small food sellers, and local donors move good food to people nearby before it is wasted.  Buyers can browse by location, order food for pickup or delivery, pay through PayFast or PayShap, and track each order with a confirmation ticket.
>>>>>>> f93d5922bd87db820eac042130b5d2fa6f8d473c
