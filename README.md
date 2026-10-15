# RG Trade and Projects - Construction Project Management System (RG PMS)

A secure, role-based web application for **RG Trade and Projects** (construction & civil
engineering) that centralises clients, projects, quotations, personnel, materials, progress
photographs and documentation - as specified in the approved Project Proposal
(ITPJA3-B34, Deliverable 1).

Built with **PHP 8 · MySQL · HTML5 · CSS3 · JavaScript · Bootstrap 5** - no framework
dependencies, works on XAMPP or any standard LAMP host.

---

## 1. What the system does

Three portals in one responsive web app:

| Portal | Who | What they can do |
|---|---|---|
| **Project Manager / Admin** | `admin/` | Dashboard with projects grouped by status (not yet commenced / in progress / delayed / completed), pending-approval notifications, company material stock. Manage clients, projects, quotations (with line items, VAT and PDF), personnel & subcontractor assignment, material requests (approve/reject), progress-photo approvals, per-project budgets & documents, invoices (with PDF), auto-generated **final project summary**. |
| **Personnel** | `personnel/` | View assigned projects, submit daily/weekly site reports, request materials, update stage progress percentages, upload progress photographs (stamped with uploader + time). |
| **Client** | `client/` | Track overall progress %, view the names/roles of people on the project, view **approved** photographs (with uploader and time), respond to quotations - **Approve** or **Request Re-evaluation and Negotiation Meeting** - and view project documents and invoices. |

### Client-agreed business flow (implemented end to end)
1. Client approaches RG Trade and Projects - 2. client & project info captured -
3. project record created - 4. quotation created & sent - 5. client approves **or** requests
re-evaluation - 6. project fit for commencement - 7. personnel/subcontractors assigned -
8. site updates incl. material requests - 9. PM reviews and responds - 10. photos reviewed &
approved before reaching the client - 11. client gets updates & notifications -
12. project monitored to completion - **auto-generated final project summary**.

### Key rules implemented
- **Role-based access control (RBAC):** each role sees only its own data. Clients only see
  their own projects; pending photographs are invisible to clients.
- **Client accounts are created by the company** - there is no public self-registration.
  RG Trade and Projects sets up client portal logins (and hands the credentials to the
  client) when a project discussion begins. The Admin can create client logins from
  *Clients - New client - "Create a client portal login account"*.
- **Quotation responses** are a genuine *approve* or *re-evaluation & negotiation meeting*
  choice, recorded with date and client comment. Approval marks the project fit for
  commencement (payments handled outside the system, by agreement).
- **Material Request workflow:** request - PM review - Approved / Pending / Rejected -
  recorded against the project, with company stock reduced on approval.
- **Progress:** stage percentages updated from site, overall % = average of stages,
  every change logged in the progress history.
- **Documents & budgets are scoped per project**, not company-wide; the dashboard shows
  only project status and company material stock.
- **Final project summary** is generated automatically at completion from captured data
  (quotation history, progress history, delays, materials, invoices) - no manual report writing.
- **Security:** hashed passwords (`password_hash`), prepared statements (PDO), CSRF tokens
  on every form, upload validation, `.htaccess` blocking script execution in `uploads/`.

---

## 2. Demo accounts (seeded in `sql/schema.sql`)

| Role | Email | Password |
|---|---|---|
| Project Manager / Admin | `admin@rgtrade.co.za` | `Admin@123` |
| Personnel (Site Engineer) | `t.dlamini@rgtrade.co.za` | `Staff@123` |
| Personnel (Labourer) | `s.nkosi@rgtrade.co.za` | `Staff@123` |
| Client (Dlamini Family Trust) | `goliathreba@gmail.com` | `Client@123` |
| Client (Karabo Investments) | `naledi.mokoena@gmail.com` | `Client@123` |

> Change these passwords before going live.

---

## 3. Installation (XAMPP - recommended for local demo)

1. **Copy the project** into `C:\xampp\htdocs\rg-pms` (Linux: `/var/www/html/rg-pms`).
2. **Start Apache and MySQL** in the XAMPP Control Panel.
3. **Create the database:** open `http://localhost/phpmyadmin`, click *Import*, choose
   `sql/schema.sql`, and run it. This creates the `rgpms` database, all 18 tables and the
   demo data. (Or run: `mysql -u root < sql/schema.sql`)
4. **Check `config/config.php`:** the defaults are XAMPP's (`root` / empty password,
   `BASE_URL = '/rg-pms'`). Adjust if your setup differs:
   - `DB_USER` / `DB_PASS` - your MySQL credentials
   - `BASE_URL` - set to `''` if the app sits at your domain root, or `/rg-pms`
     (with leading slash) if it is in a sub-folder.
5. **Ensure `uploads/` is writable** by the web server (photos and documents land here).
6. Open `http://localhost/rg-pms` and sign in with a demo account above.

### Installing on a live host (cPanel etc.)
1. Upload the project folder to `public_html` (or a sub-folder).
2. Create a MySQL database + user in cPanel; import `sql/schema.sql` into it
   (phpMyAdmin - Import).
3. Update `config/config.php` with the host's database name/user/password and `BASE_URL`.
4. Set `uploads/` (and `uploads/photos`, `uploads/documents`) permissions to writable.

---

## 4. Project structure

```
rg-pms/
├── index.php               Public landing page
├── login.php / register.php (notice page - no public registration) / logout.php / profile.php / notifications.php
├── admin/                  Project Manager / Administrator portal
│   ├── dashboard.php       Projects by status + approvals + material stock
│   ├── clients.php         client_new.php, client_view.php
│   ├── projects.php        project_new.php, project_view.php (9 tabs)
│   ├── quotations.php      quotation_new.php (line items), quotation_view.php (PDF)
│   ├── personnel.php       personnel_new.php (+ login creation for internal staff)
│   ├── material_requests.php   review - approve/reject
│   ├── photos.php          photograph approval queue
│   ├── materials.php       company-wide stock
│   ├── invoices.php        invoice_new.php, invoice_view.php (PDF)
│   └── summary.php         auto-generated final project summary
├── personnel/              dashboard.php, project_view.php (reports/requests/progress/photos)
├── client/                 dashboard.php, project_view.php, quotation_view.php,
│                           invoice_view.php
├── includes/               auth.php (sessions/RBAC), functions.php (helpers, uploads,
│                           notifications), layout.php (header/nav/footer), pdf.php (FPDF)
├── config/                 config.php (edit me), db.php (PDO)
├── sql/schema.sql          database + seed data
├── assets/                 css, js, vendor (Bootstrap 5.3, Bootstrap Icons, FPDF 1.86)
└── uploads/                photos/ and documents/ (uploaded files)
```

---

## 5. Feature walkthrough (for your demo / presentation)

1. **Admin dashboard** - three project status groups, pending approvals banner
   (photographs + material requests), quotation-response list, low-stock alerts.
2. **Quotation flow** - *New quotation* with dynamic line items, quantities, rates and VAT;
   save draft or send to client; client responds *Approve* or *Request Re-evaluation*; the
   response (with note + timestamp) appears in the PM quotation view, which offers a
   *Prepare revised quotation* shortcut. Quotations and invoices download as PDF.
3. **Personnel on site** - submit a daily report, raise a material request, update a stage
   percentage (e.g. Roofing 25%), upload a progress photo.
4. **PM approvals** - approve the material request (stock reduces, material recorded
   against the project) and approve the photo.
5. **Client portal** - log in as the client: project progress %, team names/roles, approved
   photos with uploader/time, documents, quotation response, invoice PDF.
6. **Final summary** - mark a project Completed; the final summary assembles quotation
   history, progress history, delays, materials, invoices and team automatically.

---

## 6. Notes / scope boundaries

- Payments and payment discussions happen **outside the system** (per the agreed proposal);
  the system only records that a quotation was approved and tracks invoice status.
- Subcontractors are **records only** - they do not log in.
- No clock-in/out, leave requests, payroll, banking integration, CAD/BIM or native mobile
  apps - all removed from scope by client agreement.

---

© 2026 RG Trade and Projects · RG PMS - built for ITPJA3-B34 Project (Quantix Solutions)


## Project reporting and controls upgrade
The current build includes a project control centre for the company workflow described by RG Trade and Projects:
- Progress meetings with client attendance and meeting minutes.
- Programme baseline vs forecast dates and activity progress.
- Cost report with committed and actual expenditure.
- Monthly cashflow and cumulative burn-rate comparison.
- Commercial dashboard covering budget, commitments, actual costs, invoicing and payments.
- Activity schedule.
- Material tracking register for required, ordered, delivered, used and shortage quantities.
- Issue/decision management for delays, material shortages and unforeseen events, including client escalation notifications.
- Read-only client reporting pack.
- Public company profile page describing the May 2025 establishment and four-person core team.

For an existing database, import `sql/migration_2026_08_project_controls.sql` first. For a new installation, `sql/schema.sql` already contains the new tables and demo data.
