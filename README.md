# Land Information Management System (LIMS)
### Mattu City Urban Land Administration Office — Oromia, Ethiopia

A web-based platform for managing land registration, case workflows, surveys, and official documentation. Supports bilingual interface in **Afaan Oromoo** and **English**.

---

## Features

- **Land Registration** — Full property documentation with coordinates, ownership details, and document uploads
- **Case Management** — Multi-stage workflow: Reported → Assigned → Approved → Finalized
- **Role-Based Access Control** — Four distinct roles with isolated dashboards and permissions
- **Document Generation** — PDF certificates, land reports, and support letters via TCPDF
- **Notification System** — Real-time in-app notifications for case updates
- **Audit Logging** — Complete system activity log with severity levels and IP tracking
- **Bilingual UI** — Full support for Afaan Oromoo (`om`) and English (`en`)
- **Email Support** — Password reset and notifications via PHPMailer

---

## User Roles

| Role | Responsibilities |
|------|-----------------|
| **Admin** | User management, system settings, audit logs, content management |
| **Manager** | Case review & approval, surveyor assignment, stamp management, support letter approval |
| **Record Officer** | Land registration, case reporting, document uploads, case finalization |
| **Surveyor** | Assigned case handling, survey reports, parcel provision, ownership transfers, certificate generation |

---

## Tech Stack

- **Backend**: PHP (procedural)
- **Database**: MySQL
- **Frontend**: Bootstrap 5.3, HTML5, CSS3, JavaScript
- **Libraries**:
  - [PHPMailer](https://github.com/PHPMailer/PHPMailer) — Email delivery
  - [TCPDF](https://tcpdf.org/) — PDF generation
  - [Valitron](https://github.com/vlucas/valitron) — Input validation

---

## Requirements

- PHP 7.4 or higher (with `mysqli` extension)
- MySQL 5.7+
- Composer
- Web server (Apache/Nginx) with `mod_rewrite` enabled
- Write permissions on `Uploads/` and `assets/images/` directories

---

## Installation

**1. Clone the repository**
```bash
git clone https://github.com/your-username/landinfo.git
cd landinfo
```

**2. Install dependencies**
```bash
composer install
```

**3. Configure the database**
```bash
cp includes/config.example.php includes/config.php
```
Edit `includes/config.php` and set your database credentials and `BASE_URL`.

**4. Import the database**
```bash
mysql -u your_user -p landinfo_new < database/landinfo_new.sql
```

**5. Set directory permissions**
```bash
chmod -R 755 Uploads/
chmod -R 755 assets/images/
chmod -R 755 letters/
```

**6. Configure your web server**

Point your document root to the project folder. For Apache, ensure `.htaccess` is enabled.

**7. Access the application**

Open `http://localhost/landinfo` in your browser and log in with your admin credentials.

---

## Project Structure

```
landinfo/
├── includes/           # Core helpers: config, db, auth, logger, languages
├── modules/
│   ├── admin/          # Admin dashboard, user management, system settings
│   ├── manager/        # Case review, assignment, approvals
│   ├── record_officer/ # Land registration, case reporting
│   ├── surveyor/       # Survey reports, certificates, parcel management
│   └── profile.php     # Shared user profile page
├── public/             # Login, logout, password reset (public-facing)
├── templates/          # Shared sidebar/navbar templates
├── assets/             # Static assets (CSS, JS, images)
├── Uploads/            # User-uploaded files (not committed)
├── letters/            # Generated PDF documents (not committed)
└── composer.json
```

---

## Security Notes

- Passwords are hashed using `password_hash()` (bcrypt)
- All database queries use prepared statements
- Role-based access is enforced on every protected page
- `includes/config.php` is excluded from version control — never commit credentials
- File uploads are validated for type and size (max 5MB)

---

## Contributing

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/your-feature`
3. Commit your changes: `git commit -m "Add your feature"`
4. Push to the branch: `git push origin feature/your-feature`
5. Open a Pull Request

---

## License

This project is developed for the **Mattu City Urban Land Administration Office**, Oromia Regional State, Ethiopia.

---

## Contact

For support or inquiries, contact the Mattu City Land Administration Office.
