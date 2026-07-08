-- Optional manual seed if you do not want auto-registration
INSERT INTO llx_saas_modules(code,label,description,is_core,date_created)
VALUES ('takepos','TakePOS','TakePOS module controlled by saascore',1,NOW())
ON DUPLICATE KEY UPDATE label=VALUES(label), description=VALUES(description), is_core=VALUES(is_core);

INSERT INTO llx_saas_permissions(code,label,module_code,description,date_created) VALUES
('takepos.use','Use TakePOS','takepos','Use POS terminal',NOW()),
('takepos.admin','Administer TakePOS','takepos','Administer POS settings',NOW())
ON DUPLICATE KEY UPDATE label=VALUES(label), module_code=VALUES(module_code), description=VALUES(description);

INSERT INTO llx_saas_limits(code,label,module_code,default_value,description,date_created)
VALUES ('takepos.terminals','POS terminals','takepos',1,'Maximum allowed POS terminal number for the tenant',NOW())
ON DUPLICATE KEY UPDATE label=VALUES(label), module_code=VALUES(module_code), default_value=VALUES(default_value), description=VALUES(description);
