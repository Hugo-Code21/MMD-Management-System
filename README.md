# MMD Media Management System

A PHP 8+ and MySQL MVP for MMD's member roster, event calendar, documentation projects, assignments, and school photo archive link. The first release focuses on login, roles, dashboard, members, events, and projects; equipment, uploads, and reports can be added later.

## Requirements

- PHP 8.1+ with PDO MySQL enabled
- MySQL 8+ or a compatible MariaDB version
- Apache (for example, XAMPP)

## Local setup with XAMPP

1. Put this folder under `C:\xampp\htdocs`.
2. In phpMyAdmin, import `database/schema.sql`. It creates `mmd_management` and seeds the 12 initial members and their fixed system roles.
3. Copy `.env.example` to `.env` in the project root. Set a private setup code and your MySQL connection details. Generate a strong setup code with:

   ```powershell
   php -r "echo bin2hex(random_bytes(24)), PHP_EOL;"
   ```

   Keep `.env` out of source control. Do not put the setup code or database password into PHP, JavaScript, screenshots, or chat.
4. Open the application at `http://localhost/MMD%20Management%20System/`.
5. Share the setup code privately with the 12 listed members. Each person uses it once to choose their registered name and set an individual password of at least 12 characters. The setup page stops offering registrations once every seeded member has an account.

To use a different folder URL, set `APP_BASE_URL` (for example, `/MMD%20Management%20System`) in `.env`. Leave it empty when opening the app at the web server root.

## Initial registered roster

| Name | Position |
| --- | --- |
| Hugo Delvian Maheswara | Coordinator |
| Reihan M.A. | Assistant coordinator |
| Anastasya | Member |
| Rindi | Member |
| Ahsan | Member |
| Arthur | Coordinator |
| Andrian | Member |
| Bilqis | Member |
| Aline | Member |
| Shaka | Head coordinator |
| Deni | Member |
| Tyas | Assistant coordinator |

Coordinator accounts can create events and projects and assign registered users. Other members can view events and only their assigned projects. Roles are assigned from the seeded roster, not selected during account setup.

## Security and errors

- Passwords are stored using PHP's `password_hash()` and checked with `password_verify()`.
- SQL uses PDO prepared statements; state-changing forms use CSRF tokens.
- Session cookies are HTTP-only and use `SameSite=Lax`; sessions are rotated on login.
- `.env` is ignored by Git and blocked from direct Apache access.
- PHP errors and stack traces are never displayed to users. Details are written to the PHP/Apache server error log; the browser gets a generic error page.
- Use HTTPS when exposing the system beyond a local development machine.

The `Link Foto` navigation opens [the school's photo archive](https://smkmahardhika.sch.id/link-foto/) in a new tab.
"# MMD-Management-System" 
