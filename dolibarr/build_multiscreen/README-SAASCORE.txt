TakePOS SaaS overlay for Dolibarr

This package is an overlay of the core /takepos directory to integrate TakePOS with the custom /custom/saascore module.

How to apply:
1. Backup your existing htdocs/takepos directory.
2. Extract this zip into Dolibarr htdocs so it overwrites htdocs/takepos/*
3. Make sure custom/saascore is installed and enabled.
4. In saascore, enable module code: takepos
5. Grant permissions: takepos.use and/or takepos.admin via your tenant roles.
6. Enable desired features and set limit takepos.terminals.

Registered automatically on first request if SaasRegistryService is available:
- Module: takepos
- Permissions: takepos.use, takepos.admin
- Features: takepos.frontend, takepos.payment, takepos.discount, takepos.freezone, takepos.split,
  takepos.receipt, takepos.send, takepos.qr, takepos.restaurant, takepos.public_menu,
  takepos.auto_order, takepos.smpcb, takepos.admin.setup, takepos.admin.bar,
  takepos.admin.appearance, takepos.admin.receipt, takepos.admin.terminal,
  takepos.admin.orderprinters, takepos.admin.printqr, takepos.admin.other
- Limit: takepos.terminals
