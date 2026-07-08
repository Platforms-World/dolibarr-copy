-- Register / fix executive dashboard feature and permissions
INSERT INTO llx_saas_features(code,label,module_code,description,date_created) VALUES
('takepos.dashboard.pro','Executive dashboard','takepos','POS executive dashboard screen',NOW())
ON DUPLICATE KEY UPDATE label=VALUES(label), module_code=VALUES(module_code), description=VALUES(description);

INSERT INTO llx_saas_permissions(code,label,module_code,description,date_created) VALUES
('takepos.dashboard.view','View executive dashboard','takepos','Allow opening executive dashboard',NOW()),
('takepos.dashboard.export_pdf','Export executive dashboard PDF','takepos','Allow exporting executive dashboard as PDF',NOW())
ON DUPLICATE KEY UPDATE label=VALUES(label), module_code=VALUES(module_code), description=VALUES(description);
