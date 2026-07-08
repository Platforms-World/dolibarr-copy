# Changelog - ChWhatsAppButton

Todos los cambios notables en este proyecto serán documentados en este archivo.

El formato está basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.0.0/),
y este proyecto adhiere a [Semantic Versioning](https://semver.org/lang/es/).

## [1.0.0] - 2025-01-13

### ✨ Añadido
- Botones de WhatsApp en fichas de terceros
- Botones de WhatsApp en fichas de proyectos
- Botones de WhatsApp en fichas de presupuestos
- Botones de WhatsApp en fichas de facturas
- Sistema de plantillas de mensajes personalizables
- 6 plantillas predefinidas:
  - Mensaje general a tercero
  - Actualización de proyecto
  - Envío de presupuesto
  - Envío de factura
  - Recordatorio de pago
  - Seguimiento de presupuesto
- Variables de sustitución automática:
  - `__THIRDPARTY_NAME__` - Nombre del tercero
  - `__THIRDPARTY_CODE__` - Código del cliente
  - `__PROJECT_REF__` - Referencia del proyecto
  - `__PROJECT_TITLE__` - Título del proyecto
  - `__PROPAL_REF__` - Referencia del presupuesto
  - `__PROPAL_TOTAL_TTC__` - Total del presupuesto con impuestos
  - `__INVOICE_REF__` - Referencia de la factura
  - `__INVOICE_TOTAL_TTC__` - Total de la factura con impuestos
- Modal de selección de plantillas con vista previa
- Opción de mensaje personalizado (sin plantilla)
- Detección automática de números de teléfono
- Interfaz de gestión de plantillas (lista y formulario)
- Sistema de permisos (leer, escribir, eliminar)
- Página de configuración del módulo
- Soporte multiidioma (español incluido)
- Estilos CSS personalizados
- JavaScript para modal y confirmaciones
- Documentación completa (README, INSTALL, CHANGELOG)
- Integración con WhatsApp Web/Desktop
- Hooks para inyectar botones en las páginas
- Tabla de base de datos `llx_chwhatsapp_templates`
- Scripts SQL para instalación y desinstalación

### 🎨 Características de Diseño
- Modal responsive con diseño moderno
- Vista previa de mensajes en formato WhatsApp
- Botones con icono de WhatsApp
- Confirmación antes de abrir WhatsApp Web
- Estilos consistentes con Dolibarr

### 🔧 Características Técnicas
- Número de módulo: 105004
- Familia: interface
- Compatible con Dolibarr 11.0+
- Requiere PHP 7.0+
- Usa hooks nativos de Dolibarr
- Clase CommonObject para plantillas
- Métodos CRUD completos
- Validación de datos
- Limpieza de números de teléfono
- Encoding correcto para URLs de WhatsApp

### 📚 Documentación
- README.md completo con ejemplos
- INSTALL.md con guía paso a paso
- CHANGELOG.md (este archivo)
- Comentarios en código
- Ayuda contextual en la interfaz

### 🔒 Seguridad
- Validación de permisos de usuario
- Escape de datos en SQL
- Sanitización de inputs
- Protección CSRF con tokens
- Validación de campos requeridos

### 🌍 Internacionalización
- **Soporte completo para 4 idiomas**:
  - 🇪🇸 Español (es_ES)
  - 🇬🇧 Inglés (en_US)
  - 🇫🇷 Francés (fr_FR)
  - 🇮🇹 Italiano (it_IT)
- Plantillas predefinidas en los 4 idiomas
- Traducciones JavaScript dinámicas
- Menús multiidioma
- Documentación completa en 4 idiomas (README_es.md, README_en.md, README_fr.md, README_it.md)
- Sistema de carga de plantillas según idioma configurado en Dolibarr

---

## [Unreleased] - Próximas Versiones

### 🚀 Planificado para v1.1.0
- [ ] Soporte para contactos además de terceros
- [ ] Historial de mensajes enviados
- [ ] Estadísticas de uso de plantillas
- [ ] Exportar/importar plantillas
- [ ] Plantillas por usuario
- [ ] Plantillas por entidad (multiempresa)
- [ ] Variables adicionales (fecha, usuario, etc.)
- [ ] Integración con API de WhatsApp Business
- [ ] Envío masivo de mensajes
- [ ] Programación de mensajes

### 🎯 Ideas Futuras
- [ ] Respuestas automáticas
- [ ] Chatbot básico
- [ ] Integración con CRM
- [ ] Análisis de conversaciones
- [ ] Plantillas con imágenes
- [ ] Soporte para WhatsApp Business API oficial
- [ ] Webhooks para mensajes recibidos
- [ ] Dashboard de métricas

---

## Notas de Versión

### v1.0.0 - Versión Inicial
Esta es la primera versión estable del módulo ChWhatsAppButton. Incluye todas las funcionalidades básicas para enviar mensajes de WhatsApp desde Dolibarr usando plantillas personalizables.

**Características principales:**
- ✅ Botones en 4 tipos de entidades
- ✅ Sistema de plantillas completo
- ✅ Variables de sustitución
- ✅ Interfaz de gestión
- ✅ Documentación completa

**Limitaciones conocidas:**
- Solo funciona con WhatsApp Web/Desktop (no API oficial)
- Requiere que el usuario tenga WhatsApp abierto
- No guarda historial de mensajes enviados
- No soporta envío masivo

**Próximos pasos:**
- Recopilar feedback de usuarios
- Mejorar detección de números de teléfono
- Agregar más variables de sustitución
- Implementar historial de mensajes

---

Para más información sobre instalación y uso, consultar [README.md](README.md) y [INSTALL.md](INSTALL.md).
