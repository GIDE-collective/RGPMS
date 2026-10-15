-- ============================================================
-- RG Trade and Projects - Construction Project Management System
-- (RG PMS)
-- Database schema + demo seed data
-- MySQL 5.7+/8.0  |  utf8mb4  |  InnoDB
-- ============================================================

CREATE DATABASE IF NOT EXISTS rgpms
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE rgpms;

-- ------------------------------------------------------------
-- 1. ROLES
-- ------------------------------------------------------------
CREATE TABLE roles (
  id          TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name        VARCHAR(50)  NOT NULL UNIQUE,      -- admin | personnel | client
  description VARCHAR(255) NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 2. USERS (system login accounts)
-- ------------------------------------------------------------
CREATE TABLE users (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  role_id       TINYINT UNSIGNED NOT NULL,
  full_name     VARCHAR(120) NOT NULL,
  email         VARCHAR(160) NOT NULL UNIQUE,
  phone         VARCHAR(30)  NULL,
  password_hash VARCHAR(255) NOT NULL,
  is_active     TINYINT(1)   NOT NULL DEFAULT 1,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 3. CLIENTS (company records; optionally linked to a login user)
-- ------------------------------------------------------------
CREATE TABLE clients (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id        INT UNSIGNED NULL,               -- login account for this client (nullable)
  company_name   VARCHAR(160) NOT NULL,
  contact_person VARCHAR(120) NOT NULL,
  phone          VARCHAR(30)  NULL,
  email          VARCHAR(160) NULL,
  address        VARCHAR(255) NULL,
  created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_clients_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 4. PROJECTS
--    status: not_yet_commenced | in_progress | delayed | completed
-- ------------------------------------------------------------
CREATE TABLE projects (
  id                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  client_id             INT UNSIGNED NOT NULL,
  project_manager_id    INT UNSIGNED NULL,        -- users.id (admin role)
  name                  VARCHAR(160) NOT NULL,
  build_type            VARCHAR(120) NULL,        -- e.g. Residential, Commercial, Civil
  square_meters         DECIMAL(10,2) NULL,
  location              VARCHAR(255) NULL,
  start_date            DATE NULL,
  target_deadline       DATE NULL,
  actual_completion_date DATE NULL,
  status                ENUM('not_yet_commenced','in_progress','delayed','completed') NOT NULL DEFAULT 'not_yet_commenced',
  progress_percent      DECIMAL(5,2) NOT NULL DEFAULT 0,
  budget                DECIMAL(14,2) NOT NULL DEFAULT 0,
  description           TEXT NULL,
  created_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_projects_client FOREIGN KEY (client_id) REFERENCES clients(id),
  CONSTRAINT fk_projects_pm FOREIGN KEY (project_manager_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 5. QUOTATIONS + QUOTATION LINE ITEMS
--    status: draft | sent | approved | re_evaluation
-- ------------------------------------------------------------
CREATE TABLE quotations (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  project_id         INT UNSIGNED NOT NULL,
  quotation_number   VARCHAR(40)  NOT NULL UNIQUE,
  issue_date         DATE         NOT NULL,
  valid_until        DATE         NULL,
  notes              TEXT         NULL,
  vat_rate           DECIMAL(5,2) NOT NULL DEFAULT 15.00,
  status             ENUM('draft','sent','approved','re_evaluation') NOT NULL DEFAULT 'draft',
  client_response_at DATETIME     NULL,
  client_response_note TEXT       NULL,
  created_by         INT UNSIGNED NULL,
  created_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_quotations_project FOREIGN KEY (project_id) REFERENCES projects(id),
  CONSTRAINT fk_quotations_createdby FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE quotation_items (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  quotation_id INT UNSIGNED NOT NULL,
  description  VARCHAR(255) NOT NULL,
  quantity     DECIMAL(12,2) NOT NULL DEFAULT 1,
  unit         VARCHAR(30)  NULL,
  rate         DECIMAL(14,2) NOT NULL DEFAULT 0,
  amount       DECIMAL(14,2) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  CONSTRAINT fk_qitems_quotation FOREIGN KEY (quotation_id) REFERENCES quotations(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 6. PERSONNEL (internal employees + subcontractors - records only)
-- ------------------------------------------------------------
CREATE TABLE personnel (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id    INT UNSIGNED NULL,                   -- login account for internal staff (nullable)
  name       VARCHAR(120) NOT NULL,
  type       ENUM('internal','subcontractor') NOT NULL DEFAULT 'internal',
  role       VARCHAR(120) NULL,                   -- e.g. Site Engineer, Labourer, Plumbing Subcontractor
  phone      VARCHAR(30)  NULL,
  email      VARCHAR(160) NULL,
  speciality VARCHAR(160) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_personnel_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- personnel assigned to projects
CREATE TABLE project_personnel (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  project_id   INT UNSIGNED NOT NULL,
  personnel_id INT UNSIGNED NOT NULL,
  assigned_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_project_person (project_id, personnel_id),
  CONSTRAINT fk_pp_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  CONSTRAINT fk_pp_person FOREIGN KEY (personnel_id) REFERENCES personnel(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 7. MATERIALS (company-wide stock) + MATERIAL REQUESTS
-- ------------------------------------------------------------
CREATE TABLE materials (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name           VARCHAR(160) NOT NULL,
  unit           VARCHAR(30)  NOT NULL DEFAULT 'unit',
  stock_quantity DECIMAL(12,2) NOT NULL DEFAULT 0,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_material_name (name)
) ENGINE=InnoDB;

CREATE TABLE material_requests (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  project_id   INT UNSIGNED NOT NULL,
  requested_by INT UNSIGNED NULL,                -- users.id (personnel account)
  material_id  INT UNSIGNED NOT NULL,
  quantity     DECIMAL(12,2) NOT NULL,
  unit         VARCHAR(30)  NOT NULL DEFAULT 'unit',
  reason       TEXT NULL,
  status       ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  reviewed_by  INT UNSIGNED NULL,
  reviewed_at  DATETIME     NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_mr_project FOREIGN KEY (project_id) REFERENCES projects(id),
  CONSTRAINT fk_mr_requestor FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_mr_material FOREIGN KEY (material_id) REFERENCES materials(id),
  CONSTRAINT fk_mr_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- materials recorded against a project once a request is approved & procured
CREATE TABLE project_materials (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  project_id  INT UNSIGNED NOT NULL,
  material_id INT UNSIGNED NOT NULL,
  quantity    DECIMAL(12,2) NOT NULL,
  unit        VARCHAR(30)  NOT NULL DEFAULT 'unit',
  recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_pm_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  CONSTRAINT fk_pm_material FOREIGN KEY (material_id) REFERENCES materials(id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 8. SITE REPORTS (daily / weekly)
-- ------------------------------------------------------------
CREATE TABLE site_reports (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  project_id     INT UNSIGNED NOT NULL,
  reported_by    INT UNSIGNED NULL,              -- users.id (personnel)
  report_date    DATE NOT NULL,
  period         ENUM('daily','weekly') NOT NULL DEFAULT 'daily',
  completed_work TEXT NULL,
  materials_used TEXT NULL,
  delays         TEXT NULL,
  site_notes     TEXT NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_reports_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  CONSTRAINT fk_reports_by FOREIGN KEY (reported_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 9. PROGRESS: construction stages + history (used by final summary)
-- ------------------------------------------------------------
CREATE TABLE project_stages (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  project_id      INT UNSIGNED NOT NULL,
  name            VARCHAR(120) NOT NULL,          -- Foundation, Structure, Roofing, Finishing...
  progress_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  CONSTRAINT fk_stages_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE progress_history (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  project_id INT UNSIGNED NOT NULL,
  stage_id   INT UNSIGNED NULL,
  reported_by INT UNSIGNED NULL,
  old_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
  new_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
  note       VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_ph_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  CONSTRAINT fk_ph_stage FOREIGN KEY (stage_id) REFERENCES project_stages(id) ON DELETE SET NULL,
  CONSTRAINT fk_ph_by FOREIGN KEY (reported_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 10. PROJECT PHOTOGRAPHS (approved before client visibility)
-- ------------------------------------------------------------
CREATE TABLE project_photos (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  project_id  INT UNSIGNED NOT NULL,
  uploaded_by INT UNSIGNED NOT NULL,             -- users.id
  file_path   VARCHAR(255) NOT NULL,
  caption     VARCHAR(255) NULL,
  status      ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  approved_by INT UNSIGNED NULL,
  approved_at DATETIME     NULL,
  uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_photos_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  CONSTRAINT fk_photos_by FOREIGN KEY (uploaded_by) REFERENCES users(id),
  CONSTRAINT fk_photos_approvedby FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 11. PROJECT DOCUMENTS (scoped per project)
-- ------------------------------------------------------------
CREATE TABLE documents (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  project_id  INT UNSIGNED NOT NULL,
  name        VARCHAR(255) NOT NULL,
  category    ENUM('quotation','plan','invoice','permit','cost_report','programme','commercial_dashboard','meeting_minutes','activity_schedule','material_tracking','other') NOT NULL DEFAULT 'other',
  file_path   VARCHAR(255) NOT NULL,
  uploaded_by INT UNSIGNED NULL,
  uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_docs_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  CONSTRAINT fk_docs_by FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 12. INVOICES
-- ------------------------------------------------------------
CREATE TABLE invoices (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  project_id     INT UNSIGNED NOT NULL,
  invoice_number VARCHAR(40) NOT NULL UNIQUE,
  issue_date     DATE NOT NULL,
  due_date       DATE NULL,
  description    VARCHAR(255) NULL,
  amount         DECIMAL(14,2) NOT NULL DEFAULT 0,
  status         ENUM('issued','paid') NOT NULL DEFAULT 'issued',
  created_by     INT UNSIGNED NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_invoices_project FOREIGN KEY (project_id) REFERENCES projects(id),
  CONSTRAINT fk_invoices_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 13. NOTIFICATIONS
-- ------------------------------------------------------------
CREATE TABLE notifications (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id    INT UNSIGNED NOT NULL,
  title      VARCHAR(255) NOT NULL,
  message    TEXT NULL,
  link       VARCHAR(255) NULL,
  is_read    TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_notif_user (user_id, is_read),
  CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;


-- ------------------------------------------------------------
-- 14. PROJECT CONTROL: MEETINGS, PROGRAMMES, COSTS, CASHFLOW,
--     ACTIVITY SCHEDULES, MATERIAL TRACKING AND ISSUES
-- ------------------------------------------------------------
CREATE TABLE project_meetings (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  project_id INT UNSIGNED NOT NULL,
  meeting_date DATETIME NOT NULL,
  meeting_type VARCHAR(80) NOT NULL DEFAULT 'Progress meeting',
  attendees TEXT NULL,
  agenda TEXT NULL,
  minutes TEXT NOT NULL,
  client_present TINYINT(1) NOT NULL DEFAULT 1,
  client_action_required TEXT NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_meetings_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  CONSTRAINT fk_meetings_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE project_programme (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  project_id INT UNSIGNED NOT NULL,
  activity VARCHAR(180) NOT NULL,
  baseline_start DATE NULL,
  baseline_finish DATE NULL,
  forecast_start DATE NULL,
  forecast_finish DATE NULL,
  actual_start DATE NULL,
  actual_finish DATE NULL,
  percent_complete DECIMAL(5,2) NOT NULL DEFAULT 0,
  status ENUM('not_started','in_progress','complete','delayed') NOT NULL DEFAULT 'not_started',
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_programme_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE project_costs (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  project_id INT UNSIGNED NOT NULL,
  cost_date DATE NOT NULL,
  category VARCHAR(100) NOT NULL,
  description VARCHAR(255) NULL,
  supplier VARCHAR(160) NULL,
  reference_no VARCHAR(80) NULL,
  committed_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  actual_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_costs_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  CONSTRAINT fk_costs_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE project_cashflow (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  project_id INT UNSIGNED NOT NULL,
  period_month DATE NOT NULL,
  planned_expenditure DECIMAL(14,2) NOT NULL DEFAULT 0,
  actual_expenditure DECIMAL(14,2) NOT NULL DEFAULT 0,
  notes TEXT NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cashflow_project_month (project_id, period_month),
  CONSTRAINT fk_cashflow_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  CONSTRAINT fk_cashflow_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE activity_schedules (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  project_id INT UNSIGNED NOT NULL,
  activity VARCHAR(180) NOT NULL,
  planned_date DATE NULL,
  actual_date DATE NULL,
  status ENUM('planned','in_progress','complete','delayed') NOT NULL DEFAULT 'planned',
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_activity_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE material_tracking (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  project_id INT UNSIGNED NOT NULL,
  material_id INT UNSIGNED NOT NULL,
  required_qty DECIMAL(12,2) NOT NULL DEFAULT 0,
  ordered_qty DECIMAL(12,2) NOT NULL DEFAULT 0,
  delivered_qty DECIMAL(12,2) NOT NULL DEFAULT 0,
  used_qty DECIMAL(12,2) NOT NULL DEFAULT 0,
  shortage_qty DECIMAL(12,2) NOT NULL DEFAULT 0,
  expected_delivery DATE NULL,
  status ENUM('planned','ordered','part_delivered','delivered','shortage') NOT NULL DEFAULT 'planned',
  notes TEXT NULL,
  updated_by INT UNSIGNED NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_mt_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  CONSTRAINT fk_mt_material FOREIGN KEY (material_id) REFERENCES materials(id),
  CONSTRAINT fk_mt_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE project_issues (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  project_id INT UNSIGNED NOT NULL,
  reported_by INT UNSIGNED NULL,
  issue_type ENUM('delay','material_shortage','design','commercial','quality','safety','other') NOT NULL DEFAULT 'other',
  severity ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  title VARCHAR(180) NOT NULL,
  description TEXT NULL,
  impact_programme_days INT NOT NULL DEFAULT 0,
  impact_cost DECIMAL(14,2) NOT NULL DEFAULT 0,
  status ENUM('open','under_review','escalated','mitigated','closed') NOT NULL DEFAULT 'open',
  mitigation TEXT NULL,
  escalated_to_client TINYINT(1) NOT NULL DEFAULT 0,
  client_decision TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  resolved_at DATETIME NULL,
  PRIMARY KEY (id),
  CONSTRAINT fk_issues_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  CONSTRAINT fk_issues_by FOREIGN KEY (reported_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- SEED / DEMO DATA
-- ============================================================

INSERT INTO roles (id, name, description) VALUES
  (1, 'admin',     'Project Manager / Administrator'),
  (2, 'personnel', 'Internal employee, engineer or site staff'),
  (3, 'client',    'Client of RG Trade and Projects');

-- Passwords below are hashed with password_hash(); the demo password is:
--   Admin account:    admin@rgtrade.co.za / Admin@123
--   Personnel:        p.dlamini@rgtrade.co.za / Staff@123   etc.
--   Client accounts:  same password Client@123

INSERT INTO users (id, role_id, full_name, email, phone, password_hash, is_active) VALUES
  (1, 1, 'Fon Nche Alex',        'admin@rgtrade.co.za',   '068 740 5254', '$2y$10$HvDSOOSiEgE6wphqmTrvye0tSnis7E534qD3z5VWtWsBAZy9eBIby', 1),
  (2, 2, 'Tebogo Dlamini',       't.dlamini@rgtrade.co.za', '083 111 2233', '$2y$10$OO0C.FfN83titGOIHWljgOYTxIIf0oPuc9ly3spjgEsnT0rPdJvHK', 1),
  (3, 2, 'Sipho Nkosi',          's.nkosi@rgtrade.co.za',  '082 444 5566', '$2y$10$OO0C.FfN83titGOIHWljgOYTxIIf0oPuc9ly3spjgEsnT0rPdJvHK', 1),
  (4, 3, 'Rebaone Goliath',      'goliathreba@gmail.com', '079 706 1210', '$2y$10$Pv1955zSYp8h74jJoN7NIOWxOfVwFAe3dLLJhUGm4uec/mfb/RQWC', 1),
  (5, 3, 'Naledi Mokoena',       'naledi.mokoena@gmail.com', '076 555 7788', '$2y$10$Pv1955zSYp8h74jJoN7NIOWxOfVwFAe3dLLJhUGm4uec/mfb/RQWC', 1);

INSERT INTO clients (id, user_id, company_name, contact_person, phone, email, address) VALUES
  (1, 4, 'Dlamini Family Trust', 'Rebaone Goliath', '079 706 1210', 'goliathreba@gmail.com', '12 Acacia Street, Polokwane'),
  (2, 5, 'Karabo Investments (Pty) Ltd', 'Naledi Mokoena', '076 555 7788', 'naledi.mokoena@gmail.com', '8 Mopani Road, Tzaneen');

INSERT INTO projects (id, client_id, project_manager_id, name, build_type, square_meters, location,
                      start_date, target_deadline, actual_completion_date, status, progress_percent, budget, description) VALUES
  (1, 1, 1, 'Dlamini Family Residence', 'Residential - double storey', 340.00, '12 Acacia Street, Polokwane',
   '2026-06-01', '2026-11-30', NULL, 'in_progress', 62.00, 1850000.00,
   'Design and construction of a double-storey family home including foundations, structure, roofing and finishes.'),
  (2, 2, 1, 'Karabo Retail Complex - Civil Works', 'Commercial / Civil', 1200.00, '8 Mopani Road, Tzaneen',
   '2026-08-10', '2026-09-15', NULL, 'in_progress', 35.00, 3200000.00,
   'Site preparation, earthworks, concrete slab and civil infrastructure for a retail complex.'),
  (3, 2, 1, 'Mopani Warehouse Extension', 'Industrial', 800.00, '23 Marula Street, Tzaneen',
   '2026-12-01', '2027-04-30', NULL, 'not_yet_commenced', 0.00, 2100000.00,
   'Warehouse extension with steel frame structure and cladding - quotation approved, commencement pending.');

INSERT INTO quotations (id, project_id, quotation_number, issue_date, valid_until, notes, vat_rate, status, client_response_at, client_response_note, created_by) VALUES
  (1, 1, 'RGQ-2026-001', '2026-05-12', '2026-06-12', 'Full construction quotation including materials and labour.', 15.00, 'approved', '2026-05-20 10:32:00', 'Approved - please proceed with planning.', 1),
  (2, 2, 'RGQ-2026-002', '2026-07-25', '2026-08-25', 'Civil works and site preparation.', 15.00, 'approved', '2026-08-02 14:15:00', 'Approved after negotiation on earthworks rates.', 1),
  (3, 3, 'RGQ-2026-003', '2026-08-10', '2026-09-10', 'Warehouse extension - steel structure.', 15.00, 'approved', '2026-08-18 09:05:00', 'Approved.', 1),
  (4, 1, 'RGQ-2026-004', '2026-08-20', '2026-09-20', 'Revised quotation - swimming pool and paving.', 15.00, 'sent', NULL, NULL, 1);

INSERT INTO quotation_items (quotation_id, description, quantity, unit, rate, amount) VALUES
  (1, 'Excavation and foundations', 1, 'lump sum', 320000.00, 320000.00),
  (1, 'Structural works (columns, beams, slabs)', 340, 'm2', 2450.00, 833000.00),
  (1, 'Roofing and ceiling', 340, 'm2', 850.00, 289000.00),
  (1, 'Plastering and finishes', 340, 'm2', 620.00, 210800.00),
  (2, 'Site clearing and earthworks', 1200, 'm2', 185.00, 222000.00),
  (2, 'Concrete slab (150mm reinforced)', 1200, 'm2', 480.00, 576000.00),
  (3, 'Steel frame supply and erection', 800, 'm2', 950.00, 760000.00),
  (4, 'Swimming pool construction', 1, 'lump sum', 350000.00, 350000.00),
  (4, 'Paving (interlocking blocks)', 180, 'm2', 320.00, 57600.00);

INSERT INTO personnel (id, user_id, name, type, role, phone, email, speciality) VALUES
  (1, 2, 'Tebogo Dlamini',  'internal', 'Project Manager - Civil Engineering', '083 111 2233', 't.dlamini@rgtrade.co.za', 'Civil engineering'),
  (2, 3, 'Sipho Nkosi',     'internal', 'Quantity Surveyor',                  '082 444 5566', 's.nkosi@rgtrade.co.za',   'Cost management and commercial reporting'),
  (3, NULL, 'Johan van Wyk',   'subcontractor', 'Plumbing Subcontractor', '084 777 8899', 'jvanwyk@gmail.com', 'Plumbing and drainage'),
  (4, NULL, 'Blessing Chauke', 'subcontractor', 'Electrical Subcontractor','081 222 3344', 'bchauke@gmail.com', 'Electrical installations'),
  (5, NULL, 'RG Administrator', 'internal', 'Administrator', NULL, NULL, 'Project administration'),
  (6, 1, 'Fon Nche Alex', 'internal', 'Project Manager', '068 740 5254', 'admin@rgtrade.co.za', 'Construction project management');

INSERT INTO project_personnel (project_id, personnel_id) VALUES
  (1, 1), (1, 2), (1, 3), (1, 4), (1, 6),
  (2, 1), (2, 2), (2, 6);

INSERT INTO materials (id, name, unit, stock_quantity) VALUES
  (1, 'Cement (50kg)',  'bags',  320),
  (2, 'Bricks',         'units', 12500),
  (3, 'River Sand',     'm3',    18.5),
  (4, 'Concrete Stone', 'm3',    22),
  (5, 'Steel (rebar 12mm)', 'lengths', 140),
  (6, 'Paint (20L)',    'tins',  24);

INSERT INTO material_requests (id, project_id, requested_by, material_id, quantity, unit, reason, status, reviewed_by, reviewed_at, created_at) VALUES
  (1, 1, 2, 1, 40, 'bags', 'Foundations - first floor pour', 'approved', 1, '2026-08-10 09:20:00', '2026-08-09 16:45:00'),
  (2, 1, 2, 5, 30, 'lengths', 'Reinforcement for first floor ring beam', 'pending', NULL, NULL, '2026-08-24 11:30:00'),
  (3, 2, 2, 3, 6, 'm3', 'Bedding layer for slab', 'rejected', 1, '2026-08-20 08:10:00', '2026-08-19 15:05:00');

INSERT INTO project_materials (project_id, material_id, quantity, unit, recorded_at) VALUES
  (1, 1, 120, 'bags', '2026-08-10 10:00:00'),
  (1, 2, 8000, 'units', '2026-08-12 10:00:00'),
  (1, 5, 60, 'lengths', '2026-08-15 10:00:00'),
  (2, 3, 12, 'm3', '2026-08-18 10:00:00');

INSERT INTO site_reports (id, project_id, reported_by, report_date, period, completed_work, materials_used, delays, site_notes) VALUES
  (1, 1, 2, '2026-08-21', 'daily', 'Completed ground-floor brickwork on the eastern wall. Fixed formwork for ring beam.', 'Bricks: 450 units. Cement: 12 bags.', 'None', 'Site tidy; expecting concrete delivery tomorrow morning.'),
  (2, 1, 2, '2026-08-22', 'daily', 'Ring beam concrete pour completed. Stripped formwork.', 'Cement: 20 bags. River sand: 1.5 m3. Concrete stone: 2 m3.', 'Concrete truck arrived 2 hours late.', 'Need to order rebar for first floor columns soon.'),
  (3, 2, 3, '2026-08-24', 'weekly', 'Week 2: site clearing 90% complete, compaction started on northern section.', 'Fuel and plant hire.', 'Rain on Wednesday caused 1 day delay.', 'Compaction roller booked for Monday.');

INSERT INTO project_stages (id, project_id, name, progress_percent) VALUES
  (1, 1, 'Excavation & Foundations', 100),
  (2, 1, 'Structure',                68),
  (3, 1, 'Roofing',                  10),
  (4, 1, 'Finishes',                 0),
  (5, 2, 'Site Preparation',         70),
  (6, 2, 'Concrete Slab',            15);

INSERT INTO progress_history (project_id, stage_id, reported_by, old_percent, new_percent, note, created_at) VALUES
  (1, 1, 2, 0, 100, 'Foundations complete', '2026-07-15 12:00:00'),
  (1, 2, 2, 0, 45, 'Ground floor structure done', '2026-08-05 12:00:00'),
  (1, 2, 2, 45, 68, 'First floor columns cast', '2026-08-22 12:00:00'),
  (2, 5, 3, 0, 70, 'Clearing and compaction', '2026-08-22 12:00:00'),
  (2, 6, 3, 0, 15, 'Reinforcement laid for slab', '2026-08-24 12:00:00');

-- Photos: add one approved and one pending (uploaded_at timestamps set back a bit)
INSERT INTO project_photos (id, project_id, uploaded_by, file_path, caption, status, approved_by, approved_at, uploaded_at) VALUES
  (1, 1, 2, 'uploads/photos/demo/dlamini-ring-beam.jpg', 'First floor ring beam concrete pour', 'approved', 1, '2026-08-22 14:00:00', '2026-08-22 08:30:00'),
  (2, 1, 2, 'uploads/photos/demo/dlamini-brickwork.jpg', 'Ground floor brickwork - eastern wall', 'pending', NULL, NULL, '2026-08-24 09:15:00');

INSERT INTO documents (id, project_id, name, category, file_path, uploaded_by, uploaded_at) VALUES
  (1, 1, 'Approved Building Plan - Dlamini Residence', 'plan', 'uploads/documents/demo/dlamini-building-plan.txt', 1, '2026-05-22 10:00:00'),
  (2, 2, 'Karabo Civil Works - Signed Contract', 'other', 'uploads/documents/demo/karabo-contract.txt', 1, '2026-08-03 09:00:00');

INSERT INTO invoices (id, project_id, invoice_number, issue_date, due_date, description, amount, status, created_by, created_at) VALUES
  (1, 1, 'RGI-2026-001', '2026-06-15', '2026-07-15', 'Deposit - Dlamini Family Residence', 555000.00, 'paid', 1, '2026-06-15 10:00:00'),
  (2, 1, 'RGI-2026-002', '2026-08-15', '2026-09-15', 'Progress payment 1 - structure', 462500.00, 'issued', 1, '2026-08-15 10:00:00'),
  (3, 2, 'RGI-2026-003', '2026-08-12', '2026-09-12', 'Deposit - Karabo Retail Complex', 960000.00, 'issued', 1, '2026-08-12 10:00:00');

INSERT INTO notifications (user_id, title, message, link, is_read, created_at) VALUES
  (4, 'New progress photograph approved', 'A new progress photograph has been approved for your project: First floor ring beam concrete pour.', 'client/project_view.php?id=1', 0, '2026-08-22 14:05:00'),
  (4, 'Quotation awaiting your response', 'Revised quotation RGQ-2026-004 is ready for your review.', 'client/quotation_view.php?id=4', 0, '2026-08-20 09:00:00'),
  (1, 'Pending photo approval', 'A progress photograph uploaded by Tebogo Dlamini is awaiting approval.', 'admin/photos.php', 0, '2026-08-24 09:16:00'),
  (1, 'Pending material request', 'Material request #2 (Steel rebar 12mm) is awaiting your review.', 'admin/material_requests.php', 0, '2026-08-24 11:31:00'),
  (5, 'Project commenced', 'Your project Karabo Retail Complex has commenced on site.', 'client/project_view.php?id=2', 0, '2026-08-10 08:00:00');


-- Project controls demo data
INSERT INTO project_meetings (project_id, meeting_date, meeting_type, attendees, agenda, minutes, client_present, client_action_required, created_by) VALUES
(1, '2026-08-22 14:30:00', 'Monthly progress meeting', 'Project Manager; Quantity Surveyor; Site team; Rebaone Goliath (Client)', 'Progress, programme, costs, materials and upcoming activities', 'Ring beam concrete pour completed. First floor structure is progressing. Rebar requirement discussed. Client requested that any programme risk affecting November completion be flagged immediately.', 1, 'Confirm approval of any cost impact arising from additional reinforcement if required.', 1),
(2, '2026-08-24 15:00:00', 'Progress meeting', 'Project Manager; Civil Engineering PM; Site team; Naledi Mokoena (Client)', 'Earthworks, weather delay, concrete slab and plant availability', 'Site clearing is 90% complete. Rain caused a one-day delay. The team will resequence compaction activities and monitor the slab programme.', 1, 'Confirm acceptance of revised activity sequence if the weather delay continues.', 1);

INSERT INTO project_programme (project_id, activity, baseline_start, baseline_finish, forecast_start, forecast_finish, actual_start, actual_finish, percent_complete, status, notes) VALUES
(1, 'Excavation & Foundations', '2026-06-01', '2026-07-15', '2026-06-01', '2026-07-15', '2026-06-01', '2026-07-15', 100, 'complete', 'Completed to baseline.'),
(1, 'Structure', '2026-07-16', '2026-09-30', '2026-07-16', '2026-10-05', '2026-07-16', NULL, 68, 'in_progress', 'Forecast finish moved by five days due to material and delivery coordination.'),
(1, 'Roofing', '2026-10-01', '2026-10-31', '2026-10-06', '2026-11-05', NULL, NULL, 10, 'in_progress', 'Forecast updated from current structure progress.'),
(1, 'Finishes', '2026-10-15', '2026-11-30', '2026-11-01', '2026-12-10', NULL, NULL, 0, 'delayed', 'Forecast subject to recovery plan.'),
(2, 'Site Preparation', '2026-08-10', '2026-08-25', '2026-08-10', '2026-08-26', '2026-08-10', NULL, 70, 'delayed', 'One day weather delay.'),
(2, 'Concrete Slab', '2026-08-26', '2026-09-05', '2026-08-27', '2026-09-08', NULL, NULL, 15, 'in_progress', 'Resequenced after weather interruption.');

INSERT INTO project_costs (project_id, cost_date, category, description, supplier, reference_no, committed_amount, actual_amount, created_by) VALUES
(1, '2026-08-10', 'Materials', 'Cement and reinforcement', 'Local Building Supplies', 'PO-001', 145000, 132500, 1),
(1, '2026-08-18', 'Plant', 'Concrete and small plant hire', 'Plant Hire SA', 'PH-014', 62000, 58000, 1),
(1, '2026-08-22', 'Labour', 'Progress claim - structure', 'RG Trade Site Team', 'LAB-008', 185000, 176000, 1),
(2, '2026-08-24', 'Plant', 'Compaction roller and operator', 'Plant Hire SA', 'PH-021', 88000, 82000, 1);

INSERT INTO project_cashflow (project_id, period_month, planned_expenditure, actual_expenditure, notes, created_by) VALUES
(1, '2026-06-01', 300000, 285000, 'Mobilisation and foundations', 1),
(1, '2026-07-01', 420000, 405000, 'Foundations and structure', 1),
(1, '2026-08-01', 520000, 486500, 'Structure and materials', 1),
(1, '2026-09-01', 380000, 0, 'Structure completion', 1),
(1, '2026-10-01', 180000, 0, 'Roofing and finishes', 1),
(1, '2026-11-01', 50000, 0, 'Final finishes', 1),
(2, '2026-08-01', 500000, 410000, 'Site preparation', 1),
(2, '2026-09-01', 700000, 0, 'Concrete and civil works', 1);

INSERT INTO activity_schedules (project_id, activity, planned_date, actual_date, status, notes) VALUES
(1, 'First floor ring beam concrete pour', '2026-08-22', '2026-08-22', 'complete', 'Completed and recorded in site report.'),
(1, 'First floor column reinforcement', '2026-08-28', NULL, 'in_progress', 'Rebar order being tracked.'),
(1, 'Roof structure procurement', '2026-09-25', NULL, 'planned', 'Supplier confirmation required before procurement.'),
(2, 'Northern section compaction', '2026-08-25', NULL, 'delayed', 'Rescheduled after rain.');

INSERT INTO material_tracking (project_id, material_id, required_qty, ordered_qty, delivered_qty, used_qty, shortage_qty, expected_delivery, status, notes, updated_by) VALUES
(1, 5, 120, 100, 90, 60, 20, '2026-08-30', 'shortage', 'Additional rebar required for first floor columns.', 1),
(1, 1, 180, 180, 160, 120, 0, '2026-08-29', 'part_delivered', 'Balance due on next delivery.', 1),
(2, 3, 20, 20, 12, 12, 0, '2026-08-28', 'part_delivered', 'Remaining river sand booked.', 1);

INSERT INTO project_issues (project_id, reported_by, issue_type, severity, title, description, impact_programme_days, impact_cost, status, mitigation, escalated_to_client, client_decision) VALUES
(1, 2, 'material_shortage', 'high', 'Rebar shortage for first floor columns', 'Current delivered reinforcement is below the required quantity for the next activity.', 2, 18000, 'escalated', 'Source additional approved reinforcement and resequence non-dependent works.', 1, 'Client to approve additional material cost if supplier variation is required.'),
(2, 3, 'delay', 'medium', 'Rain affected compaction', 'Heavy rain caused one working day to be lost on the northern section.', 1, 12000, 'mitigated', 'Resequence compaction and maintain plant booking for the next available dry period.', 1, 'Client acknowledged revised sequence.');

