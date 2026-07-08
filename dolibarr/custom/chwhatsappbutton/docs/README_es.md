# ChWhatsAppButton - Módulo de WhatsApp para Dolibarr 🇪🇸

**[🇬🇧 English](README_en.md) | [🇫🇷 Français](README_fr.md) | [🇮🇹 Italiano](README_it.md)**

---

## 📱 Descripción

**ChWhatsAppButton** es un módulo para Dolibarr que agrega botones de WhatsApp en las fichas de terceros, proyectos, presupuestos y facturas. Permite enviar mensajes de WhatsApp directamente desde Dolibarr usando plantillas personalizables con variables de sustitución automática.

## ✨ Características

- ✅ **Botones de WhatsApp** integrados en terceros, proyectos, presupuestos y facturas
- ✅ **Plantillas personalizables** con variables de sustitución automática
- ✅ **Detección automática** de números de teléfono del tercero
- ✅ **Mensajes personalizados** además de plantillas predefinidas
- ✅ **Integración perfecta** con WhatsApp Web/Desktop
- ✅ **Gestión completa de plantillas** desde la interfaz de Dolibarr
- ✅ **Multiidioma** (Español, Inglés, Francés, Italiano)
- ✅ **6 plantillas predefinidas** listas para usar
- ✅ **Modal intuitivo** para selección de plantillas

## 📋 Requisitos

- **Dolibarr**: Versión 11.0 o superior
- **PHP**: Versión 7.0 o superior
- **MySQL/MariaDB**: Cualquier versión compatible con Dolibarr
- **WhatsApp Web o Desktop**: Instalado en el equipo del usuario
- **Números de teléfono**: Configurados en los terceros (formato internacional recomendado)

## 🚀 Instalación

### Método 1: Instalación Manual

1. **Copiar el módulo** en el directorio `custom/` de Dolibarr:
   ```bash
   cp -r chwhatsappbutton /ruta/a/dolibarr/htdocs/custom/
   ```

2. **Ir a la configuración de módulos**:
   - Inicio → Configuración → Módulos/Aplicaciones

3. **Buscar y activar**:
   - Buscar "WhatsApp Button"
   - Hacer clic en **Activar**

4. **Verificar instalación**:
   - El módulo creará automáticamente la tabla de base de datos
   - Se insertarán 6 plantillas predefinidas en el idioma configurado

### Método 2: Instalación desde ZIP

1. **Comprimir el módulo**:
   ```bash
   zip -r chwhatsappbutton.zip chwhatsappbutton/
   ```

2. **Subir a Dolibarr**:
   - Inicio → Configuración → Módulos/Aplicaciones
   - Clic en **Desplegar módulo externo**
   - Seleccionar el archivo ZIP

3. **Activar el módulo** desde la lista de módulos

### Verificación de la Instalación

Después de activar el módulo, verificar:
- ✅ Menú "WhatsApp" visible en **Herramientas**
- ✅ Submenús "Plantillas de Mensajes" y "Nueva Plantilla"
- ✅ Tabla `llx_chwhatsapp_templates` creada en la base de datos
- ✅ 6 plantillas predefinidas insertadas

## 📖 Uso

### Configuración Inicial

1. **Acceder a las plantillas**:
   - Herramientas → WhatsApp → Plantillas de Mensajes

2. **Revisar plantillas predefinidas**:
   - Envío de factura
   - Recordatorio de pago
   - Envío de presupuesto
   - Seguimiento de presupuesto
   - Actualización de proyecto
   - Mensaje general a tercero

3. **Configurar números de teléfono**:
   - Asegurarse de que los terceros tengan números en formato internacional
   - Ejemplo: +34612345678 (España), +33612345678 (Francia)

### Enviar Mensajes de WhatsApp

#### Paso 1: Abrir la ficha
Abrir la ficha de un **tercero**, **proyecto**, **presupuesto** o **factura**.

#### Paso 2: Localizar el botón
Si el tercero tiene un número de teléfono configurado, aparecerá un **botón verde de WhatsApp** junto al botón de "Enviar E-mail".

#### Paso 3: Seleccionar plantilla
1. Hacer clic en el botón **WhatsApp**
2. Se abrirá un modal con:
   - Nombre del tercero
   - Número de teléfono detectado
   - Lista de plantillas disponibles para ese tipo de entidad
   - Área de texto para mensaje personalizado

#### Paso 4: Enviar mensaje
1. **Opción A**: Hacer clic en "Enviar este mensaje" en una plantilla
2. **Opción B**: Escribir un mensaje personalizado y hacer clic en "Enviar mensaje personalizado"
3. Se abrirá WhatsApp Web/Desktop con el mensaje prellenado
4. Revisar y enviar desde WhatsApp

### Gestión de Plantillas

#### Crear una Nueva Plantilla

1. **Acceder al formulario**:
   - Herramientas → WhatsApp → Nueva Plantilla

2. **Completar los campos obligatorios**:
   - **Referencia**: Código único (ej: `INVOICE_REMINDER`)
   - **Etiqueta**: Nombre descriptivo (ej: "Recordatorio de factura")
   - **Tipo de Entidad**: Seleccionar entre:
     - Tercero
     - Proyecto
     - Presupuesto
     - Factura
   - **Texto del Mensaje**: Contenido con variables

3. **Campos opcionales**:
   - **Descripción**: Explicación del uso
   - **Activo**: Marcar para que esté disponible
   - **Por Defecto**: Marcar para que sea la primera opción
   - **Posición**: Orden de aparición (menor número = más arriba)

4. **Guardar** la plantilla

#### Editar una Plantilla Existente

1. Ir a **Herramientas → WhatsApp → Plantillas de Mensajes**
2. Hacer clic en el nombre de la plantilla
3. Hacer clic en **Modificar**
4. Realizar los cambios necesarios
5. Guardar

#### Eliminar una Plantilla

1. Abrir la plantilla
2. Hacer clic en **Eliminar**
3. Confirmar la eliminación

### Variables de Sustitución

Las plantillas soportan variables que se sustituyen automáticamente según el contexto:

#### Variables Generales (Todos los tipos)
- `__THIRDPARTY_NAME__` - Nombre del tercero
- `__THIRDPARTY_CODE__` - Código del cliente

#### Variables de Proyectos
- `__PROJECT_REF__` - Referencia del proyecto
- `__PROJECT_TITLE__` - Título del proyecto

#### Variables de Presupuestos
- `__PROPAL_REF__` - Referencia del presupuesto
- `__PROPAL_TOTAL_TTC__` - Total con impuestos

#### Variables de Facturas
- `__INVOICE_REF__` - Referencia de la factura
- `__INVOICE_TOTAL_TTC__` - Total con impuestos

#### Ejemplo de Plantilla con Variables

```
Hola __THIRDPARTY_NAME__,

Te informamos que la factura __INVOICE_REF__ por un importe de __INVOICE_TOTAL_TTC__ está disponible.

Gracias por tu confianza.

Saludos cordiales.
```

**Resultado** (ejemplo con factura FA2025-0226 de 72,60 EUR):
```
Hola ESTABILIDADES, S.L.,

Te informamos que la factura FA2025-0226 por un importe de 72,60 EUR está disponible.

Gracias por tu confianza.

Saludos cordiales.
```

## 🔧 Configuración

### Página de Configuración del Módulo

Acceder a **Herramientas → WhatsApp → Configuración** para ver:
- Estado del módulo
- Información de uso
- Requisitos del sistema
- Guía rápida de configuración
- Documentación de variables

### Configuración de Permisos

El módulo incluye tres niveles de permisos:

1. **Leer plantillas de WhatsApp**
   - Ver la lista de plantillas
   - Ver el detalle de las plantillas

2. **Crear/modificar plantillas de WhatsApp**
   - Crear nuevas plantillas
   - Editar plantillas existentes

3. **Eliminar plantillas de WhatsApp**
   - Eliminar plantillas

**Asignar permisos**:
- Inicio → Usuarios y Grupos → [Usuario]
- Pestaña **Permisos**
- Sección **ChWhatsAppButton**
- Marcar los permisos deseados

## 📱 Requisitos del Usuario Final

Para que los usuarios puedan enviar mensajes de WhatsApp:

### 1. WhatsApp Web o Desktop

**Opción A: WhatsApp Web**
- URL: https://web.whatsapp.com
- Escanear código QR con el móvil
- Mantener la sesión abierta

**Opción B: WhatsApp Desktop**
- Windows: https://www.whatsapp.com/download
- Mac: https://www.whatsapp.com/download
- Iniciar sesión y mantener abierto

### 2. Sesión Activa

El usuario debe tener WhatsApp Web/Desktop:
- ✅ Abierto
- ✅ Conectado
- ✅ Con sesión activa

### 3. Números de Teléfono Correctos

Los números deben estar:
- ✅ En formato internacional: `+[código país][número]`
- ✅ Sin espacios ni guiones (se limpian automáticamente)
- ✅ Configurados en el campo `phone` o `phone_mobile` del tercero

**Ejemplos de formatos válidos**:
- España: `+34612345678`
- Francia: `+33612345678`
- Italia: `+39612345678`
- México: `+52612345678`

## 🎨 Personalización

### Personalizar Estilos CSS

Editar el archivo `css/chwhatsappbutton.css`:

```css
/* Personalizar color del botón */
#chwhatsapp-test-btn {
    background-color: #128C7E !important; /* Verde WhatsApp oscuro */
}

/* Personalizar modal */
.chwhatsapp-modal-content {
    border-radius: 15px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
}
```

### Personalizar Comportamiento JavaScript

Editar el archivo `js/chwhatsappbutton.js` para modificar:
- Posición del botón
- Comportamiento del modal
- Validaciones adicionales

### Agregar Nuevos Tipos de Entidad

Para agregar el botón en otros tipos de fichas:

1. Editar `core/modules/modChwhatsappbutton.class.php`
2. Agregar el hook correspondiente en el array `hooks`
3. Actualizar `js/chwhatsappbutton.js` para detectar el nuevo tipo

## 📊 Base de Datos

### Tabla: llx_chwhatsapp_templates

Estructura de la tabla de plantillas:

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `rowid` | int(11) | ID único (clave primaria) |
| `ref` | varchar(128) | Referencia única de la plantilla |
| `label` | varchar(255) | Nombre de la plantilla |
| `description` | text | Descripción del uso |
| `message_text` | longtext | Texto del mensaje con variables |
| `entity_type` | varchar(50) | Tipo: thirdparty, project, propal, invoice |
| `is_active` | tinyint(1) | 1 = activa, 0 = inactiva |
| `is_default` | tinyint(1) | 1 = por defecto, 0 = normal |
| `position` | int(11) | Orden de aparición |
| `fk_user_author` | int(11) | ID del usuario creador |
| `fk_user_modif` | int(11) | ID del último modificador |
| `datec` | datetime | Fecha de creación |
| `tms` | timestamp | Última modificación |

### Consultas SQL Útiles

**Ver todas las plantillas activas**:
```sql
SELECT ref, label, entity_type, is_default 
FROM llx_chwhatsapp_templates 
WHERE is_active = 1 
ORDER BY position;
```

**Contar plantillas por tipo**:
```sql
SELECT entity_type, COUNT(*) as total 
FROM llx_chwhatsapp_templates 
WHERE is_active = 1 
GROUP BY entity_type;
```

## 🔍 Solución de Problemas

### Problema: El botón de WhatsApp no aparece

**Causas posibles**:
1. ❌ El tercero no tiene número de teléfono
2. ❌ El módulo no está activado
3. ❌ No hay plantillas activas para ese tipo
4. ❌ JavaScript no se cargó correctamente

**Soluciones**:
1. ✅ Verificar que el tercero tenga `phone` o `phone_mobile`
2. ✅ Activar el módulo en Configuración → Módulos
3. ✅ Crear/activar plantillas en Herramientas → WhatsApp
4. ✅ Limpiar caché del navegador (Ctrl+F5)
5. ✅ Verificar consola del navegador (F12) para errores JavaScript

### Problema: WhatsApp Web no se abre

**Causas posibles**:
1. ❌ Bloqueador de ventanas emergentes activo
2. ❌ WhatsApp no está instalado
3. ❌ Navegador no compatible

**Soluciones**:
1. ✅ Permitir ventanas emergentes desde Dolibarr
2. ✅ Instalar WhatsApp Web o Desktop
3. ✅ Usar navegador moderno (Chrome, Firefox, Edge)

### Problema: Las variables no se sustituyen

**Causas posibles**:
1. ❌ Variables mal escritas (mayúsculas/minúsculas)
2. ❌ El objeto no tiene los datos necesarios
3. ❌ Error en el código PHP

**Soluciones**:
1. ✅ Verificar ortografía exacta: `__INVOICE_REF__`
2. ✅ Asegurarse de que la factura tenga referencia y total
3. ✅ Revisar logs de PHP en `documents/dolibarr.log`

### Problema: Error al cargar plantillas

**Causas posibles**:
1. ❌ Permisos insuficientes
2. ❌ Error de base de datos
3. ❌ Archivo AJAX no accesible

**Soluciones**:
1. ✅ Verificar permisos del usuario
2. ✅ Verificar que la tabla existe: `SHOW TABLES LIKE 'llx_chwhatsapp_templates'`
3. ✅ Verificar que el archivo `ajax/get_templates.php` existe y es accesible

### Problema: El modal no se cierra

**Causas posibles**:
1. ❌ Error de JavaScript
2. ❌ Conflicto con otro módulo

**Soluciones**:
1. ✅ Recargar la página (F5)
2. ✅ Verificar consola del navegador (F12)
3. ✅ Desactivar temporalmente otros módulos personalizados

## 🛠️ Desarrollo

### Estructura del Módulo

```
chwhatsappbutton/
├── admin/
│   └── chwhatsappbutton_setup.php      # Configuración del módulo
├── ajax/
│   └── get_templates.php               # Endpoint para cargar plantillas
├── class/
│   ├── actions_chwhatsappbutton.class.php  # Hooks (no usado actualmente)
│   └── chwhatsapptemplate.class.php    # Clase de gestión de plantillas
├── core/
│   └── modules/
│       └── modChwhatsappbutton.class.php   # Definición del módulo
├── css/
│   └── chwhatsappbutton.css            # Estilos del botón y modal
├── docs/
│   ├── README_es.md                    # Documentación en español
│   ├── README_en.md                    # Documentación en inglés
│   ├── README_fr.md                    # Documentación en francés
│   └── README_it.md                    # Documentación en italiano
├── js/
│   ├── chwhatsappbutton.js             # Lógica del botón y modal
│   └── chwhatsappbutton_lang.js.php    # Traducciones para JavaScript
├── langs/
│   ├── es_ES/
│   │   └── chwhatsappbutton.lang       # Traducciones español
│   ├── en_US/
│   │   └── chwhatsappbutton.lang       # Traducciones inglés
│   ├── fr_FR/
│   │   └── chwhatsappbutton.lang       # Traducciones francés
│   └── it_IT/
│       └── chwhatsappbutton.lang       # Traducciones italiano
├── sql/
│   ├── llx_chwhatsapp_templates.sql    # Crear tabla
│   └── llx_chwhatsapp_templates_drop.sql # Eliminar tabla
├── templatecard.php                    # Formulario de plantilla
├── templateslist.php                   # Lista de plantillas
├── test_activation.php                 # Script de prueba
└── README.md                           # Documentación principal
```

### Hooks Utilizados

El módulo se integra mediante JavaScript que se carga en las siguientes páginas:
- `thirdpartycard` - Ficha de tercero
- `projectcard` - Ficha de proyecto
- `propalcard` - Ficha de presupuesto
- `invoicecard` - Ficha de factura
- `contactcard` - Ficha de contacto

### API Interna

#### Endpoint: ajax/get_templates.php

**Método**: GET

**Parámetros**:
- `entity_type`: Tipo de entidad (thirdparty, project, propal, invoice)
- `entity_id`: ID de la entidad

**Respuesta exitosa**:
```json
{
  "success": true,
  "phone": "+34612345678",
  "thirdparty_name": "ESTABILIDADES, S.L.",
  "templates": [
    {
      "id": 1,
      "ref": "INVOICE_SEND",
      "label": "Envío de factura",
      "description": "Notificar envío de factura",
      "message": "Hola ESTABILIDADES, S.L.,\n\nTe informamos...",
      "is_default": 1
    }
  ],
  "entity_data": {
    "THIRDPARTY_NAME": "ESTABILIDADES, S.L.",
    "INVOICE_REF": "FA2025-0226",
    "INVOICE_TOTAL_TTC": "72,60 EUR"
  }
}
```

**Respuesta de error**:
```json
{
  "success": false,
  "error": "No se encontró número de teléfono para este tercero"
}
```

### Agregar Nuevas Variables

Para agregar nuevas variables de sustitución:

1. **Editar** `ajax/get_templates.php`
2. **Agregar** la variable en el array `$entity_data`:
```php
$entity_data['NUEVA_VARIABLE'] = $object->campo;
```
3. **Documentar** la variable en los archivos de idioma
4. **Usar** en plantillas: `__NUEVA_VARIABLE__`

## 📝 Información del Módulo

- **Nombre**: ChWhatsAppButton
- **Número de módulo**: 105004
- **Versión**: 1.0.0
- **Familia**: interface
- **Compatibilidad**: Dolibarr 11.0+
- **Licencia**: GPL-3.0+
- **Idiomas**: Español, Inglés, Francés, Italiano

## 🤝 Contribuir

Para contribuir al desarrollo del módulo:

1. **Fork** el repositorio
2. **Crear** una rama para tu feature:
   ```bash
   git checkout -b feature/NuevaCaracteristica
   ```
3. **Commit** tus cambios:
   ```bash
   git commit -m 'Agregar nueva característica'
   ```
4. **Push** a la rama:
   ```bash
   git push origin feature/NuevaCaracteristica
   ```
5. **Abrir** un Pull Request

### Guías de Contribución

- Seguir el estilo de código de Dolibarr
- Agregar traducciones en los 4 idiomas
- Documentar nuevas funcionalidades
- Incluir pruebas cuando sea posible
- Actualizar el CHANGELOG

## 📄 Changelog

### v1.0.0 (2025)
- ✅ **Versión inicial del módulo**
- ✅ Botones de WhatsApp en terceros, proyectos, presupuestos y facturas
- ✅ Sistema de plantillas con variables de sustitución automática
- ✅ Interfaz completa de gestión de plantillas (crear, editar, eliminar)
- ✅ 6 plantillas predefinidas listas para usar
- ✅ Soporte multiidioma completo (ES, EN, FR, IT)
- ✅ Modal intuitivo para selección de plantillas
- ✅ Mensajes personalizados además de plantillas
- ✅ Detección automática de números de teléfono
- ✅ Integración perfecta con WhatsApp Web/Desktop
- ✅ Sistema de permisos granular
- ✅ Documentación completa en 4 idiomas

## 🙏 Agradecimientos

- Gracias a la **comunidad de Dolibarr** por el excelente framework
- Gracias a **WhatsApp** por la API de WhatsApp Web
- Gracias a todos los **contribuidores** del proyecto

## 📧 Soporte

Para soporte, consultas o reportar problemas:
- Abrir un issue en el repositorio
- Contactar al equipo de desarrollo
- Consultar la documentación oficial de Dolibarr

---

**¡Disfruta enviando mensajes de WhatsApp desde Dolibarr!** 📱✨

*Desarrollado con ❤️ para la comunidad de Dolibarr*
