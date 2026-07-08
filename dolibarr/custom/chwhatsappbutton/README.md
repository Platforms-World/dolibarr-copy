# ChWhatsAppButton - WhatsApp Module for Dolibarr

**[🇪🇸 Español](docs/README_es.md) | [🇬🇧 English](docs/README_en.md) | [🇫🇷 Français](docs/README_fr.md) | [🇮🇹 Italiano](docs/README_it.md)**

---

## 📱 Description

**ChWhatsAppButton** es un módulo para Dolibarr que agrega botones de WhatsApp en las fichas de terceros, proyectos, presupuestos y facturas. Permite enviar mensajes de WhatsApp directamente desde Dolibarr usando plantillas personalizables.

## ✨ Características

- ✅ **Botones de WhatsApp** en terceros, proyectos, presupuestos y facturas
- ✅ **Plantillas personalizables** con variables de sustitución
- ✅ **Detección automática** de números de teléfono
- ✅ **Mensajes personalizados** además de plantillas predefinidas
- ✅ **Integración con WhatsApp Web/Desktop**
- ✅ **Gestión completa de plantillas** desde la interfaz
- ✅ **Multiidioma** (español incluido)

## 📋 Requisitos

- Dolibarr 11.0 o superior
- PHP 7.0 o superior
- WhatsApp Web o WhatsApp Desktop instalado en el equipo del usuario
- Números de teléfono configurados en los terceros

## 🚀 Instalación

### Método 1: Instalación Manual

1. Copiar la carpeta `chwhatsappbutton` en el directorio `custom/` de Dolibarr:
   ```
   dolibarr/custom/chwhatsappbutton/
   ```

2. Ir a **Inicio → Configuración → Módulos/Aplicaciones**

3. Buscar "WhatsApp Button" y hacer clic en **Activar**

4. El módulo creará automáticamente la tabla de base de datos e insertará las plantillas por defecto

### Método 2: Instalación desde ZIP

1. Comprimir la carpeta `chwhatsappbutton` en un archivo ZIP

2. En Dolibarr, ir a **Inicio → Configuración → Módulos/Aplicaciones**

3. Hacer clic en **Desplegar módulo externo**

4. Seleccionar el archivo ZIP y subir

5. Activar el módulo

## 📖 Uso

### Configuración Inicial

1. Ir a **Herramientas → WhatsApp → Plantillas de Mensajes**

2. Revisar las plantillas predefinidas o crear nuevas

3. Asegurarse de que los terceros tengan números de teléfono configurados

### Enviar Mensajes de WhatsApp

1. Abrir la ficha de un **tercero**, **proyecto**, **presupuesto** o **factura**

2. Si el tercero tiene un número de teléfono, aparecerá un botón **WhatsApp** en la barra de acciones

3. Hacer clic en el botón para abrir el selector de plantillas

4. Seleccionar una plantilla o escribir un mensaje personalizado

5. Hacer clic en **Enviar este mensaje** para abrir WhatsApp Web con el mensaje prellenado

### Gestión de Plantillas

#### Crear una Nueva Plantilla

1. Ir a **Herramientas → WhatsApp → Nueva Plantilla**

2. Completar los campos:
   - **Referencia**: Código único de la plantilla
   - **Etiqueta**: Nombre descriptivo
   - **Descripción**: Explicación del uso de la plantilla
   - **Tipo de Entidad**: Tercero, Proyecto, Presupuesto o Factura
   - **Texto del Mensaje**: Contenido del mensaje con variables

3. Marcar como **Activo** para que esté disponible

4. Opcionalmente marcar como **Por Defecto** para que sea la primera opción

#### Variables de Sustitución

Las plantillas soportan las siguientes variables que se sustituyen automáticamente:

**Para todos los tipos:**
- `__THIRDPARTY_NAME__` - Nombre del tercero
- `__THIRDPARTY_CODE__` - Código del cliente

**Para proyectos:**
- `__PROJECT_REF__` - Referencia del proyecto
- `__PROJECT_TITLE__` - Título del proyecto

**Para presupuestos:**
- `__PROPAL_REF__` - Referencia del presupuesto
- `__PROPAL_TOTAL_HT__` - Total sin impuestos
- `__PROPAL_TOTAL_TTC__` - Total con impuestos

**Para facturas:**
- `__INVOICE_REF__` - Referencia de la factura
- `__INVOICE_TOTAL_HT__` - Total sin impuestos
- `__INVOICE_TOTAL_TTC__` - Total con impuestos

#### Ejemplo de Plantilla

```
Hola __THIRDPARTY_NAME__,

Te hemos enviado el presupuesto __PROPAL_REF__ por un importe de __PROPAL_TOTAL_TTC__.

Quedamos a tu disposición para cualquier consulta.

Saludos cordiales.
```

## 🔧 Configuración

### Página de Configuración

Ir a **Herramientas → WhatsApp → Configuración** para ver:
- Estado del módulo
- Información de uso
- Requisitos del sistema
- Guía de configuración

### Permisos

El módulo incluye tres niveles de permisos:
- **Leer**: Ver plantillas de WhatsApp
- **Escribir**: Crear y modificar plantillas
- **Eliminar**: Eliminar plantillas

Asignar permisos en **Usuarios → [Usuario] → Permisos → ChWhatsAppButton**

## 📱 Requisitos del Usuario

Para que los usuarios puedan enviar mensajes de WhatsApp:

1. **WhatsApp Web o Desktop instalado**:
   - WhatsApp Web: https://web.whatsapp.com
   - WhatsApp Desktop (Windows): https://www.whatsapp.com/download
   - WhatsApp Desktop (Mac): https://www.whatsapp.com/download

2. **Sesión activa**: El usuario debe tener WhatsApp Web/Desktop abierto y conectado

3. **Números de teléfono**: Los terceros deben tener números de teléfono configurados en formato internacional (ej: +34612345678)

## 🎨 Personalización

### CSS Personalizado

Editar el archivo `css/chwhatsappbutton.css` para personalizar la apariencia de los botones y modales.

### JavaScript Personalizado

Editar el archivo `js/chwhatsappbutton.js` para modificar el comportamiento de los botones.

## 📊 Base de Datos

El módulo crea una tabla:

- **llx_chwhatsapp_templates**: Almacena las plantillas de mensajes

La tabla se crea automáticamente al activar el módulo.

## 🔍 Solución de Problemas

### El botón de WhatsApp no aparece

**Causas posibles:**
1. El tercero no tiene número de teléfono configurado
2. El módulo no está activado
3. No hay plantillas activas para ese tipo de entidad

**Solución:**
1. Verificar que el tercero tenga un número de teléfono en el campo `phone` o `phone_mobile`
2. Activar el módulo en **Configuración → Módulos**
3. Crear o activar plantillas en **Herramientas → WhatsApp → Plantillas**

### WhatsApp Web no se abre

**Causas posibles:**
1. El navegador bloqueó la ventana emergente
2. WhatsApp Web/Desktop no está instalado

**Solución:**
1. Permitir ventanas emergentes desde Dolibarr
2. Instalar WhatsApp Web o Desktop

### El mensaje no tiene las variables sustituidas

**Causas posibles:**
1. Las variables están mal escritas
2. El objeto no tiene los datos necesarios

**Solución:**
1. Verificar que las variables coincidan exactamente con las documentadas
2. Asegurarse de que el objeto tenga los datos (ej: el presupuesto tenga un total)

## 🛠️ Desarrollo

### Estructura del Módulo

```
chwhatsappbutton/
├── admin/
│   └── chwhatsappbutton_setup.php      # Página de configuración
├── class/
│   ├── actions_chwhatsappbutton.class.php  # Hooks para inyectar botones
│   └── chwhatsapptemplate.class.php    # Clase de plantillas
├── core/
│   ├── modules/
│   │   └── modChwhatsappbutton.class.php   # Definición del módulo
│   └── triggers/
│       └── interface_99_modChwhatsappbutton_ChwhatsappbuttonTriggers.class.php
├── css/
│   └── chwhatsappbutton.css            # Estilos
├── js/
│   └── chwhatsappbutton.js             # JavaScript
├── langs/
│   └── es_ES/
│       └── chwhatsappbutton.lang       # Traducciones español
├── sql/
│   ├── llx_chwhatsapp_templates.sql    # Crear tabla
│   ├── llx_chwhatsapp_templates.key.sql # Claves foráneas
│   ├── data.sql                        # Datos iniciales
│   └── llx_chwhatsapp_templates_drop.sql # Eliminar tabla
├── templatecard.php                    # Formulario de plantilla
├── templateslist.php                   # Lista de plantillas
└── README.md                           # Este archivo
```

### Hooks Utilizados

El módulo utiliza los siguientes hooks de Dolibarr:
- `thirdpartycard` - Ficha de tercero
- `projectcard` - Ficha de proyecto
- `propalcard` - Ficha de presupuesto
- `invoicecard` - Ficha de factura
- `contactcard` - Ficha de contacto

## 📝 Información del Módulo

- **Número de módulo**: 105004
- **Versión**: 1.0.0
- **Familia**: interface
- **Autor**: DolibarrModules
- **Licencia**: GPL-3.0

## 🤝 Contribuir

Para contribuir al desarrollo del módulo:

1. Fork el repositorio
2. Crear una rama para tu feature (`git checkout -b feature/AmazingFeature`)
3. Commit tus cambios (`git commit -m 'Add some AmazingFeature'`)
4. Push a la rama (`git push origin feature/AmazingFeature`)
5. Abrir un Pull Request

## 📧 Soporte

Para soporte o consultas sobre el módulo, contactar al equipo de desarrollo.

## 📄 Changelog

### v1.0.0 (2024)
- ✅ Versión inicial
- ✅ Botones de WhatsApp en terceros, proyectos, presupuestos y facturas
- ✅ Sistema de plantillas con variables de sustitución
- ✅ Interfaz de gestión de plantillas
- ✅ 6 plantillas predefinidas
- ✅ Soporte multiidioma (español)
- ✅ Mensajes personalizados
- ✅ Modal de selección de plantillas

## 🙏 Agradecimientos

Gracias a la comunidad de Dolibarr por el excelente framework y documentación.

---

**¡Disfruta enviando mensajes de WhatsApp desde Dolibarr!** 📱✨
