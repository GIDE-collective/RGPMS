-- RG PMS upgrade: project reporting and controls
-- Run this against an existing RG PMS database before using project_controls.php.
-- If installing from scratch, use sql/schema.sql instead.

USE rgpms;

ALTER TABLE documents
  MODIFY category ENUM('quotation','plan','invoice','permit','cost_report','programme','commercial_dashboard','meeting_minutes','activity_schedule','material_tracking','other')
  NOT NULL DEFAULT 'other';

CREATE TABLE IF NOT EXISTS project_meetings (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  project_id INT UNSIGNED NOT NULL,
  meeting_date DATETIME NOT NULL,
  meeting_type VARCHAR(80) NOT NULL DEFAULT 'Progress meeting',
  attendees TEXT NULL, agenda TEXT NULL, minutes TEXT NOT NULL,
  client_present TINYINT(1) NOT NULL DEFAULT 1,
  client_action_required TEXT NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY(id),
  CONSTRAINT fk_meetings_project FOREIGN KEY(project_id) REFERENCES projects(id) ON DELETE CASCADE,
  CONSTRAINT fk_meetings_by FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS project_programme (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT, project_id INT UNSIGNED NOT NULL,
  activity VARCHAR(180) NOT NULL, baseline_start DATE NULL, baseline_finish DATE NULL,
  forecast_start DATE NULL, forecast_finish DATE NULL, actual_start DATE NULL, actual_finish DATE NULL,
  percent_complete DECIMAL(5,2) NOT NULL DEFAULT 0,
  status ENUM('not_started','in_progress','complete','delayed') NOT NULL DEFAULT 'not_started',
  notes TEXT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY(id),
  CONSTRAINT fk_programme_project FOREIGN KEY(project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS project_costs (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT, project_id INT UNSIGNED NOT NULL,
  cost_date DATE NOT NULL, category VARCHAR(100) NOT NULL, description VARCHAR(255) NULL,
  supplier VARCHAR(160) NULL, reference_no VARCHAR(80) NULL,
  committed_amount DECIMAL(14,2) NOT NULL DEFAULT 0, actual_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  created_by INT UNSIGNED NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY(id),
  CONSTRAINT fk_costs_project FOREIGN KEY(project_id) REFERENCES projects(id) ON DELETE CASCADE,
  CONSTRAINT fk_costs_by FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS project_cashflow (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT, project_id INT UNSIGNED NOT NULL,
  period_month DATE NOT NULL, planned_expenditure DECIMAL(14,2) NOT NULL DEFAULT 0,
  actual_expenditure DECIMAL(14,2) NOT NULL DEFAULT 0, notes TEXT NULL,
  created_by INT UNSIGNED NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY(id),
  UNIQUE KEY uq_cashflow_project_month(project_id,period_month),
  CONSTRAINT fk_cashflow_project FOREIGN KEY(project_id) REFERENCES projects(id) ON DELETE CASCADE,
  CONSTRAINT fk_cashflow_by FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS activity_schedules (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT, project_id INT UNSIGNED NOT NULL,
  activity VARCHAR(180) NOT NULL, planned_date DATE NULL, actual_date DATE NULL,
  status ENUM('planned','in_progress','complete','delayed') NOT NULL DEFAULT 'planned',
  notes TEXT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY(id),
  CONSTRAINT fk_activity_project FOREIGN KEY(project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS material_tracking (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT, project_id INT UNSIGNED NOT NULL, material_id INT UNSIGNED NOT NULL,
  required_qty DECIMAL(12,2) NOT NULL DEFAULT 0, ordered_qty DECIMAL(12,2) NOT NULL DEFAULT 0,
  delivered_qty DECIMAL(12,2) NOT NULL DEFAULT 0, used_qty DECIMAL(12,2) NOT NULL DEFAULT 0,
  shortage_qty DECIMAL(12,2) NOT NULL DEFAULT 0, expected_delivery DATE NULL,
  status ENUM('planned','ordered','part_delivered','delivered','shortage') NOT NULL DEFAULT 'planned',
  notes TEXT NULL, updated_by INT UNSIGNED NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY(id),
  CONSTRAINT fk_mt_project FOREIGN KEY(project_id) REFERENCES projects(id) ON DELETE CASCADE,
  CONSTRAINT fk_mt_material FOREIGN KEY(material_id) REFERENCES materials(id),
  CONSTRAINT fk_mt_by FOREIGN KEY(updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS project_issues (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT, project_id INT UNSIGNED NOT NULL, reported_by INT UNSIGNED NULL,
  issue_type ENUM('delay','material_shortage','design','commercial','quality','safety','other') NOT NULL DEFAULT 'other',
  severity ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  title VARCHAR(180) NOT NULL, description TEXT NULL, impact_programme_days INT NOT NULL DEFAULT 0,
  impact_cost DECIMAL(14,2) NOT NULL DEFAULT 0,
  status ENUM('open','under_review','escalated','mitigated','closed') NOT NULL DEFAULT 'open',
  mitigation TEXT NULL, escalated_to_client TINYINT(1) NOT NULL DEFAULT 0, client_decision TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, resolved_at DATETIME NULL, PRIMARY KEY(id),
  CONSTRAINT fk_issues_project FOREIGN KEY(project_id) REFERENCES projects(id) ON DELETE CASCADE,
  CONSTRAINT fk_issues_by FOREIGN KEY(reported_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;


-- Align the internal team records with the current company structure.
UPDATE personnel SET role='Project Manager - Civil Engineering', speciality='Civil engineering'
WHERE user_id=2;
UPDATE personnel SET role='Quantity Surveyor', speciality='Cost management and commercial reporting'
WHERE user_id=3;
UPDATE personnel SET role='Project Manager', speciality='Construction project management'
WHERE user_id=1;

INSERT INTO personnel (user_id, name, type, role, speciality)
SELECT NULL, 'RG Administrator', 'internal', 'Administrator', 'Project administration'
WHERE NOT EXISTS (SELECT 1 FROM personnel WHERE name='RG Administrator');

INSERT INTO project_personnel (project_id, personnel_id)
SELECT p.id, pn.id
FROM projects p CROSS JOIN personnel pn
WHERE pn.user_id=1 AND NOT EXISTS (
  SELECT 1 FROM project_personnel pp WHERE pp.project_id=p.id AND pp.personnel_id=pn.id
);
