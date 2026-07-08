-- Foreign keys for WhatsApp templates
ALTER TABLE llx_chwhatsapp_templates ADD CONSTRAINT fk_chwhatsapp_templates_user_author FOREIGN KEY (fk_user_author) REFERENCES llx_user(rowid);
ALTER TABLE llx_chwhatsapp_templates ADD CONSTRAINT fk_chwhatsapp_templates_user_modif FOREIGN KEY (fk_user_modif) REFERENCES llx_user(rowid);
