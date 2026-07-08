# Guía de Instalación - ChWhatsAppButton

## 📦 Instalación Paso a Paso

### Opción 1: Instalación Manual (Recomendada)

1. **Copiar archivos del módulo**
   ```bash
   # Copiar la carpeta chwhatsappbutton a custom/
   cp -r chwhatsappbutton /ruta/a/dolibarr/htdocs/custom/
   ```

2. **Establecer permisos correctos**
   ```bash
   # En Linux/Mac
   chown -R www-data:www-data /ruta/a/dolibarr/htdocs/custom/chwhatsappbutton
   chmod -R 755 /ruta/a/dolibarr/htdocs/custom/chwhatsappbutton
   ```

3. **Activar el módulo**
   - Ir a **Inicio → Configuración → Módulos/Aplicaciones**
   - Buscar "WhatsApp Button"
   - Hacer clic en **Activar**

4. **Verificar instalación**
   - El módulo creará automáticamente la tabla `llx_chwhatsapp_templates`
   - Se insertarán 6 plantillas predefinidas
   - Aparecerá el menú **WhatsApp** en **Herramientas**

### Opción 2: Instalación desde ZIP

1. **Crear archivo ZIP**
   ```bash
   cd /ruta/a/modulos
   zip -r chwhatsappbutton-1.0.0.zip chwhatsappbutton/
   ```

2. **Subir módulo**
   - Ir a **Inicio → Configuración → Módulos/Aplicaciones**
   - Hacer clic en **Desplegar módulo externo**
   - Seleccionar el archivo ZIP
   - Hacer clic en **Enviar**

3. **Activar el módulo**
   - Buscar "WhatsApp Button" en la lista
   - Hacer clic en **Activar**

## ⚙️ Configuración Post-Instalación

### 1. Verificar Tablas de Base de Datos

Conectarse a la base de datos y verificar:

```sql
-- Verificar que la tabla existe
SHOW TABLES LIKE 'llx_chwhatsapp_templates';

-- Verificar plantillas predefinidas
SELECT ref, label, entity_type FROM llx_chwhatsapp_templates;
```

Deberías ver 6 plantillas:
- THIRDPARTY_DEFAULT (tercero)
- PROJECT_UPDATE (proyecto)
- PROPAL_SEND (presupuesto)
- INVOICE_SEND (factura)
- PAYMENT_REMINDER (factura)
- PROPAL_FOLLOWUP (presupuesto)

### 2. Configurar Permisos de Usuario

1. Ir a **Usuarios → [Seleccionar usuario]**
2. Ir a la pestaña **Permisos**
3. Buscar **ChWhatsAppButton**
4. Activar los permisos necesarios:
   - ✅ **Leer**: Ver plantillas
   - ✅ **Escribir**: Crear/modificar plantillas
   - ✅ **Eliminar**: Eliminar plantillas

### 3. Configurar Números de Teléfono en Terceros

Para que aparezcan los botones de WhatsApp, los terceros deben tener números de teléfono:

1. Ir a **Terceros → [Seleccionar tercero]**
2. Hacer clic en **Modificar**
3. Completar el campo **Teléfono** o **Móvil**
4. **Formato recomendado**: +[código país][número]
   - Ejemplo España: +34612345678
   - Ejemplo México: +525512345678
   - Ejemplo Argentina: +5491112345678

### 4. Verificar Hooks Activos

El módulo utiliza hooks para inyectar botones. Verificar que estén activos:

```php
// En conf.php o desde la interfaz
$conf->hooks_modules = array('chwhatsappbutton');
```

### 5. Probar el Módulo

1. **Ir a una ficha de tercero** que tenga teléfono configurado
2. Verificar que aparece el botón **WhatsApp** en la barra de acciones
3. Hacer clic en el botón
4. Debe aparecer un modal con las plantillas disponibles
5. Seleccionar una plantilla y verificar que se abre WhatsApp Web

## 🔧 Instalación Manual de SQL (Si es necesario)

Si el módulo no crea automáticamente las tablas, ejecutar manualmente:

```sql
-- 1. Crear tabla principal
CREATE TABLE IF NOT EXISTS llx_chwhatsapp_templates (
    rowid int(11) NOT NULL AUTO_INCREMENT,
    ref varchar(128) NOT NULL,
    label varchar(255) NOT NULL,
    description text,
    message_text longtext NOT NULL,
    entity_type varchar(50) NOT NULL,
    is_active tinyint(1) DEFAULT 1,
    is_default tinyint(1) DEFAULT 0,
    position int(11) DEFAULT 0,
    fk_user_author int(11) NOT NULL,
    fk_user_modif int(11),
    datec datetime NOT NULL,
    tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (rowid),
    UNIQUE KEY uk_chwhatsapp_templates_ref (ref),
    KEY idx_chwhatsapp_entity_type (entity_type),
    KEY idx_chwhatsapp_active (is_active)
) ENGINE=innodb DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Insertar plantillas predefinidas
INSERT IGNORE INTO llx_chwhatsapp_templates (ref, label, description, message_text, entity_type, is_active, is_default, position, fk_user_author, datec) VALUES
('THIRDPARTY_DEFAULT', 'Mensaje general a tercero', 'Plantilla por defecto para terceros', 'Hola __THIRDPARTY_NAME__,\n\nEsperamos que todo esté bien.\n\nSaludos cordiales.', 'thirdparty', 1, 1, 10, 1, NOW()),
('PROJECT_UPDATE', 'Actualización de proyecto', 'Notificar avances en proyecto', 'Hola __THIRDPARTY_NAME__,\n\nTe escribo sobre el proyecto: __PROJECT_REF__ - __PROJECT_TITLE__\n\n[Escribe tu mensaje aquí]\n\nSaludos.', 'project', 1, 1, 20, 1, NOW()),
('PROPAL_SEND', 'Envío de presupuesto', 'Notificar envío de presupuesto', 'Hola __THIRDPARTY_NAME__,\n\nTe hemos enviado el presupuesto __PROPAL_REF__ por un importe de __PROPAL_TOTAL_TTC__.\n\nQuedamos a tu disposición para cualquier consulta.\n\nSaludos.', 'propal', 1, 1, 30, 1, NOW()),
('INVOICE_SEND', 'Envío de factura', 'Notificar envío de factura', 'Hola __THIRDPARTY_NAME__,\n\nTe informamos que la factura __INVOICE_REF__ por un importe de __INVOICE_TOTAL_TTC__ está disponible.\n\nGracias por tu confianza.\n\nSaludos.', 'invoice', 1, 1, 40, 1, NOW()),
('PAYMENT_REMINDER', 'Recordatorio de pago', 'Recordar pago pendiente', 'Hola __THIRDPARTY_NAME__,\n\nTe recordamos que la factura __INVOICE_REF__ por __INVOICE_TOTAL_TTC__ está pendiente de pago.\n\nSi ya has realizado el pago, por favor ignora este mensaje.\n\nGracias.', 'invoice', 1, 0, 50, 1, NOW()),
('PROPAL_FOLLOWUP', 'Seguimiento de presupuesto', 'Hacer seguimiento de presupuesto enviado', 'Hola __THIRDPARTY_NAME__,\n\n¿Has tenido oportunidad de revisar el presupuesto __PROPAL_REF__ que te enviamos?\n\nEstamos disponibles para cualquier duda o aclaración.\n\nSaludos.', 'propal', 1, 0, 60, 1, NOW());
```

## 🐛 Solución de Problemas de Instalación

### Error: "No se puede crear la tabla"

**Causa**: Permisos insuficientes en la base de datos

**Solución**:
```sql
-- Verificar permisos del usuario de Dolibarr
SHOW GRANTS FOR 'usuario_dolibarr'@'localhost';

-- Debe tener al menos: CREATE, INSERT, UPDATE, DELETE, SELECT
```

### Error: "Módulo no aparece en la lista"

**Causa**: Archivos no están en la ubicación correcta

**Solución**:
1. Verificar que la carpeta esté en `htdocs/custom/chwhatsappbutton/`
2. Verificar que existe el archivo `core/modules/modChwhatsappbutton.class.php`
3. Limpiar caché de Dolibarr: **Inicio → Configuración → Otros → Limpiar caché**

### Error: "Botones no aparecen"

**Causa**: Hooks no están activos o no hay teléfono configurado

**Solución**:
1. Verificar que el módulo está activado
2. Verificar que el tercero tiene teléfono
3. Limpiar caché del navegador (Ctrl+F5)
4. Verificar en `conf.php` que los hooks están habilitados

### Error: "No se pueden guardar plantillas"

**Causa**: Permisos de usuario insuficientes

**Solución**:
1. Ir a **Usuarios → [Usuario] → Permisos**
2. Activar permiso **ChWhatsAppButton → Escribir**
3. O activar como administrador

## 🔄 Actualización del Módulo

Para actualizar a una nueva versión:

1. **Hacer backup de la base de datos**
   ```bash
   mysqldump -u usuario -p base_datos llx_chwhatsapp_templates > backup_templates.sql
   ```

2. **Desactivar el módulo**
   - Ir a **Configuración → Módulos**
   - Desactivar "WhatsApp Button"

3. **Reemplazar archivos**
   ```bash
   rm -rf /ruta/a/dolibarr/htdocs/custom/chwhatsappbutton
   cp -r chwhatsappbutton-nueva-version /ruta/a/dolibarr/htdocs/custom/chwhatsappbutton
   ```

4. **Reactivar el módulo**
   - Activar nuevamente desde **Configuración → Módulos**

5. **Verificar funcionamiento**

## 📋 Checklist de Instalación

- [ ] Archivos copiados a `custom/chwhatsappbutton/`
- [ ] Permisos de archivos configurados (755)
- [ ] Módulo activado desde la interfaz
- [ ] Tabla `llx_chwhatsapp_templates` creada
- [ ] 6 plantillas predefinidas insertadas
- [ ] Menú "WhatsApp" visible en Herramientas
- [ ] Permisos de usuario configurados
- [ ] Números de teléfono configurados en terceros
- [ ] Botón de WhatsApp visible en fichas
- [ ] Modal de plantillas funciona correctamente
- [ ] WhatsApp Web se abre correctamente

## 🎉 ¡Instalación Completada!

Si todos los pasos se completaron correctamente, el módulo está listo para usar.

Para más información, consultar el archivo [README.md](README.md).
