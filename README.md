# MMD Media Management System

A PHP 8+ and MySQL MVP for MMD's member roster, event calendar, documentation projects, assignments, and school photo archive link. The first release focuses on login, roles, dashboard, members, events, and projects; equipment, uploads, and reports can be added later.

## Requirements

- PHP 8.1+ with PDO MySQL enabled
- MySQL 8+ or a compatible MariaDB version
- Apache (for example, XAMPP)

## Local setup with XAMPP

1. Put this folder under `C:\xampp\htdocs`.
2. In phpMyAdmin, import `database/schema.sql`. It creates `mmd_management`, seeds the 12 initial members and their fixed system roles, and adds example events, projects, and project assignments. Re-importing the schema does not duplicate those example records. For an existing database created before the profile fields were added, run `database/migrations/20261005_member_profile.sql` once; if you already added the birth-year field, run `database/migrations/20261005_birth_date.sql` to switch it to a full date.
3. Copy `.env.example` to `.env` in the project root. Set a private setup code and your MySQL connection details. Generate a strong setup code with:

   ```powershell
   php -r "echo bin2hex(random_bytes(24)), PHP_EOL;"
   ```

   Keep `.env` out of source control. Do not put the setup code or database password into PHP, JavaScript, screenshots, or chat.
4. Open the application at `http://localhost/MMD-Management-System/`.
5. Share the setup code privately with members. On the setup page, each person enters their name, class, birth date in `DD/MM/YY` format, team role, and an individual password of at least 12 characters. They can then sign in using the same name and password. A name already in the seeded roster is linked to that member; otherwise, a new standard member is created. The team role is descriptive only and never grants coordinator permissions.

The application automatically builds links for its current folder. Set `APP_BASE_URL` in `.env` only if it is hosted under a different URL path than its folder name; leave it empty for automatic detection.

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

Coordinator accounts can create, edit, and delete events and projects, update project statuses, and assign active roster members. The Events page links directly to the school's Link Foto archive. Other members can view events and only their assigned projects. System access roles are assigned from the seeded roster, not selected during account setup. User accounts are created through the protected registration page; the schema intentionally does not seed login credentials.

## Security and errors

- Passwords are stored using PHP's `password_hash()` and checked with `password_verify()`.
- SQL uses PDO prepared statements; state-changing forms use CSRF tokens.
- Session cookies are HTTP-only and use `SameSite=Lax`; sessions are rotated on login.
- `.env` is ignored by Git and blocked from direct Apache access.
- PHP errors and stack traces are never displayed to users. Details are written to the PHP/Apache server error log; the browser gets a generic error page.
- Use HTTPS when exposing the system beyond a local development machine.

The `Link Foto` navigation opens [the school's photo archive](https://smkmahardhika.sch.id/link-foto/) in a new tab.
"# MMD-Management-System" 
