-- Upgrade script: create/enrich llx_saas_audit_log for kafo-ERP-Control audit tracking.
-- Existing installations are also auto-synchronized by:
--   1) modKafoerpcontrol::ensureAuditLogSchema()
--   2) KafoAuditLogService::ensureSchema()

CREATE TABLE IF NOT EXISTS llx_saas_audit_log (
    rowid INTEGER AUTO_INCREMENT PRIMARY KEY,
    entity_id INTEGER NOT NULL,
    entity INTEGER NOT NULL DEFAULT 1,
    date_created DATETIME NOT NULL,
    datec DATETIME NOT NULL,
    fk_user INTEGER NULL,
    fk_user_actor INTEGER NULL,
    fk_user_target INTEGER NULL,
    action_code VARCHAR(64) NOT NULL,
    action_type VARCHAR(64) NOT NULL,
    target_type VARCHAR(64) NOT NULL,
    object_type VARCHAR(64) NOT NULL,
    target_code VARCHAR(128) NULL,
    object_key VARCHAR(128) NULL,
    old_value TEXT NULL,
    new_value TEXT NULL,
    description TEXT NULL,
    ip_address VARCHAR(64) NULL,
    user_agent VARCHAR(255) NULL,
    context_page VARCHAR(255) NULL,
    extra_json TEXT NULL,
    KEY idx_saas_audit_entity (entity_id),
    KEY idx_saas_audit_user (fk_user),
    KEY idx_saas_audit_actor (fk_user_actor),
    KEY idx_saas_audit_target (fk_user_target),
    KEY idx_saas_audit_action (action_type),
    KEY idx_saas_audit_object (object_type),
    KEY idx_saas_audit_datec (datec)
) ENGINE=innodb;
