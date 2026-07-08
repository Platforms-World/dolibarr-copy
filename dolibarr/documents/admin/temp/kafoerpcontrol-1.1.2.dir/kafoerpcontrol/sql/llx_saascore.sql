CREATE TABLE llx_saas_modules (
    rowid INTEGER AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(64) NOT NULL,
    label VARCHAR(128) NOT NULL,
    description TEXT NULL,
    is_core TINYINT NOT NULL DEFAULT 0,
    date_created DATETIME NOT NULL,
    tms TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_saas_modules_code (code)
) ENGINE=innodb;
CREATE TABLE llx_saas_features (
    rowid INTEGER AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(64) NOT NULL,
    label VARCHAR(128) NOT NULL,
    module_code VARCHAR(64) NULL,
    description TEXT NULL,
    date_created DATETIME NOT NULL,
    tms TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_saas_features_code (code), KEY idx_saas_features_module_code (module_code)
) ENGINE=innodb;
CREATE TABLE llx_saas_limits (
    rowid INTEGER AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(64) NOT NULL,
    label VARCHAR(128) NOT NULL,
    module_code VARCHAR(64) NULL,
    default_value BIGINT NOT NULL DEFAULT 0,
    description TEXT NULL,
    date_created DATETIME NOT NULL,
    tms TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_saas_limits_code (code), KEY idx_saas_limits_module_code (module_code)
) ENGINE=innodb;
CREATE TABLE llx_saas_permissions (
    rowid INTEGER AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(64) NOT NULL,
    label VARCHAR(128) NOT NULL,
    module_code VARCHAR(64) NULL,
    description TEXT NULL,
    date_created DATETIME NOT NULL,
    tms TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_saas_permissions_code (code), KEY idx_saas_permissions_module_code (module_code)
) ENGINE=innodb;
CREATE TABLE llx_saas_tenant_modules (
    rowid INTEGER AUTO_INCREMENT PRIMARY KEY,
    entity_id INTEGER NOT NULL,
    module_code VARCHAR(64) NOT NULL,
    enabled TINYINT NOT NULL DEFAULT 0,
    date_created DATETIME NOT NULL,
    tms TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_saas_tenant_modules (entity_id, module_code), KEY idx_saas_tenant_modules_entity (entity_id)
) ENGINE=innodb;
CREATE TABLE llx_saas_tenant_features (
    rowid INTEGER AUTO_INCREMENT PRIMARY KEY,
    entity_id INTEGER NOT NULL,
    feature_code VARCHAR(64) NOT NULL,
    enabled TINYINT NOT NULL DEFAULT 0,
    date_created DATETIME NOT NULL,
    tms TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_saas_tenant_features (entity_id, feature_code), KEY idx_saas_tenant_features_entity (entity_id)
) ENGINE=innodb;
CREATE TABLE llx_saas_tenant_limits (
    rowid INTEGER AUTO_INCREMENT PRIMARY KEY,
    entity_id INTEGER NOT NULL,
    limit_code VARCHAR(64) NOT NULL,
    value BIGINT NOT NULL,
    date_created DATETIME NOT NULL,
    tms TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_saas_tenant_limits (entity_id, limit_code), KEY idx_saas_tenant_limits_entity (entity_id)
) ENGINE=innodb;
CREATE TABLE llx_saas_bundles (
    rowid INTEGER AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(64) NOT NULL,
    label VARCHAR(128) NOT NULL,
    description TEXT NULL,
    is_active TINYINT NOT NULL DEFAULT 1,
    date_created DATETIME NOT NULL,
    tms TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_saas_bundles_code (code)
) ENGINE=innodb;
CREATE TABLE llx_saas_bundle_modules (
    rowid INTEGER AUTO_INCREMENT PRIMARY KEY,
    bundle_code VARCHAR(64) NOT NULL,
    module_code VARCHAR(64) NOT NULL,
    enabled TINYINT NOT NULL DEFAULT 1,
    UNIQUE KEY uk_saas_bundle_modules (bundle_code, module_code)
) ENGINE=innodb;
CREATE TABLE llx_saas_bundle_features (
    rowid INTEGER AUTO_INCREMENT PRIMARY KEY,
    bundle_code VARCHAR(64) NOT NULL,
    feature_code VARCHAR(64) NOT NULL,
    enabled TINYINT NOT NULL DEFAULT 1,
    UNIQUE KEY uk_saas_bundle_features (bundle_code, feature_code)
) ENGINE=innodb;
CREATE TABLE llx_saas_bundle_limits (
    rowid INTEGER AUTO_INCREMENT PRIMARY KEY,
    bundle_code VARCHAR(64) NOT NULL,
    limit_code VARCHAR(64) NOT NULL,
    value BIGINT NOT NULL,
    UNIQUE KEY uk_saas_bundle_limits (bundle_code, limit_code)
) ENGINE=innodb;
CREATE TABLE llx_saas_bundle_permissions (
    rowid INTEGER AUTO_INCREMENT PRIMARY KEY,
    bundle_code VARCHAR(64) NOT NULL,
    permission_code VARCHAR(64) NOT NULL,
    enabled TINYINT NOT NULL DEFAULT 1,
    UNIQUE KEY uk_saas_bundle_permissions (bundle_code, permission_code)
) ENGINE=innodb;
CREATE TABLE llx_saas_tenant_bundles (
    rowid INTEGER AUTO_INCREMENT PRIMARY KEY,
    entity_id INTEGER NOT NULL,
    bundle_code VARCHAR(64) NOT NULL,
    is_primary TINYINT NOT NULL DEFAULT 1,
    date_created DATETIME NOT NULL,
    UNIQUE KEY uk_saas_tenant_bundle (entity_id, bundle_code), KEY idx_saas_tenant_bundle_entity (entity_id)
) ENGINE=innodb;
CREATE TABLE llx_saas_roles (
    rowid INTEGER AUTO_INCREMENT PRIMARY KEY,
    entity_id INTEGER NOT NULL,
    code VARCHAR(64) NOT NULL,
    label VARCHAR(128) NOT NULL,
    description TEXT NULL,
    is_system TINYINT NOT NULL DEFAULT 0,
    date_created DATETIME NOT NULL,
    tms TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_saas_roles (entity_id, code), KEY idx_saas_roles_entity (entity_id)
) ENGINE=innodb;
CREATE TABLE llx_saas_role_permissions (
    rowid INTEGER AUTO_INCREMENT PRIMARY KEY,
    entity_id INTEGER NOT NULL,
    role_code VARCHAR(64) NOT NULL,
    permission_code VARCHAR(64) NOT NULL,
    allowed TINYINT NOT NULL DEFAULT 1,
    UNIQUE KEY uk_saas_role_permissions (entity_id, role_code, permission_code), KEY idx_saas_role_permissions_entity (entity_id)
) ENGINE=innodb;
CREATE TABLE llx_saas_user_roles (
    rowid INTEGER AUTO_INCREMENT PRIMARY KEY,
    entity_id INTEGER NOT NULL,
    fk_user INTEGER NOT NULL,
    role_code VARCHAR(64) NOT NULL,
    date_created DATETIME NOT NULL,
    UNIQUE KEY uk_saas_user_roles (entity_id, fk_user, role_code), KEY idx_saas_user_roles_user (fk_user), KEY idx_saas_user_roles_entity (entity_id)
) ENGINE=innodb;
CREATE TABLE llx_saas_audit_log (
    rowid INTEGER AUTO_INCREMENT PRIMARY KEY,
    entity_id INTEGER NOT NULL,
    fk_user INTEGER NULL,
    action_code VARCHAR(64) NOT NULL,
    target_type VARCHAR(64) NOT NULL,
    target_code VARCHAR(64) NULL,
    old_value TEXT NULL,
    new_value TEXT NULL,
    ip_address VARCHAR(64) NULL,
    date_created DATETIME NOT NULL,
    KEY idx_saas_audit_entity (entity_id), KEY idx_saas_audit_user (fk_user)
) ENGINE=innodb;

CREATE TABLE llx_saas_user_permissions (
    rowid INTEGER AUTO_INCREMENT PRIMARY KEY,
    entity_id INTEGER NOT NULL,
    fk_user INTEGER NOT NULL,
    permission_code VARCHAR(64) NOT NULL,
    allowed TINYINT NOT NULL DEFAULT 0,
    date_created DATETIME NOT NULL,
    tms TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_saas_user_permissions (entity_id, fk_user, permission_code),
    KEY idx_saas_user_permissions_entity (entity_id), KEY idx_saas_user_permissions_user (fk_user)
) ENGINE=innodb;

CREATE TABLE llx_saas_api_keys (
    rowid INTEGER AUTO_INCREMENT PRIMARY KEY,
    entity_id INTEGER NOT NULL,
    label VARCHAR(128) NOT NULL,
    token_prefix VARCHAR(32) NOT NULL,
    token_hash VARCHAR(255) NOT NULL,
    can_read TINYINT NOT NULL DEFAULT 1,
    can_write TINYINT NOT NULL DEFAULT 0,
    can_update TINYINT NOT NULL DEFAULT 0,
    is_active TINYINT NOT NULL DEFAULT 1,
    last_used_at DATETIME NULL,
    notes TEXT NULL,
    date_created DATETIME NOT NULL,
    tms TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_saas_api_keys_hash (token_hash),
    KEY idx_saas_api_keys_entity (entity_id),
    KEY idx_saas_api_keys_prefix (token_prefix)
) ENGINE=innodb;
