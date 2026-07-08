# TakePOS API v1 
 
TakePOS API v1 is a stateless JSON-only REST layer for `takepos/api/v1/`. 
It does not require browser login, session login, CSRF tokens, or ajax endpoints as the public API. 
 
## Authentication 
 
All official endpoints under `takepos/api/v1/` authenticate only with a bearer token: 
 
```bash 
curl -H 'Authorization: Bearer YOUR_TOKEN' 'https://kafo-erp.com/dolibarr/takepos/api/v1/products.php' 
``` 

To obtain a token from a TakePOS user login, use the dedicated login endpoint:

```bash
curl -X POST \
  -H 'Content-Type: application/json' \
  -d '{"login":"cashier1","password":"secret123"}' \
  'https://kafo-erp.com/dolibarr/takepos/api/v1/auth_login.php'
```
 
Notes: 
- No browser session is required. 
- No redirect to `login.php` should happen for API requests. 
- No CSRF token is required. 
- Tokens are validated against the existing TakePOS API token storage used by `admin/api_webhooks.php`. 
- Tokens issued by `auth_login.php` inherit the effective API scopes of the authenticated user.
 
## Response Contract 
 
Success responses: 
 
```json 
{ 
  "success": true, 
  "data": {}, 
  "meta": { 
    "entity": 1 
  } 
} 
``` 
 
Error responses: 
 
```json 
{ 
  "success": false, 
  "error": { 
    "code": "AUTH_FAILED", 
    "message": "Unauthorized" 
  } 
} 
``` 
 
Missing token returns `401 Unauthorized`: 
 
```json 
{ 
  "success": false, 
  "error": { 
    "code": "TOKEN_MISSING", 
    "message": "Authorization Bearer token is required"
  } 
} 
``` 
 
Invalid or inactive token returns `401 Unauthorized` with `AUTH_FAILED` or `TOKEN_DISABLED`. 
 
## Official Endpoints 
 
Authentication: `auth_login.php`

Phase 1: `products.php`, `terminals.php`, `shifts.php`, `stores.php` 
Phase 2: `carts.php`, `cart_items.php`, `held_sales.php`, `checkout.php`, `invoices.php` 
Phase 3: `invoices_validate.php`, `payments.php`, `checkout_pay.php`, `refunds.php` 
 
Legacy endpoints are kept for backward compatibility, but the official contract is the unified JSON API above. 
 
## Curl Examples 
 
Products: 
```bash 
curl -i -H 'Authorization: Bearer VALID_TOKEN' 'https://kafo-erp.com/dolibarr/takepos/api/v1/products.php?q=bread&limit=10' 
``` 
 
Create cart: 
```bash 
curl -X POST -H 'Authorization: Bearer VALID_TOKEN' -H 'Content-Type: application/json' -d '{"terminal_id":1,"thirdparty_id":23}' 'https://kafo-erp.com/dolibarr/takepos/api/v1/carts.php' 
``` 
 
Validate invoice: 
```bash 
curl -X POST -H 'Authorization: Bearer VALID_TOKEN' -H 'Content-Type: application/json' -d '{"cart_id":105}' 'https://kafo-erp.com/dolibarr/takepos/api/v1/invoices_validate.php' 
``` 
 
Pay invoice: 
```bash 
curl -X POST -H 'Authorization: Bearer VALID_TOKEN' -H 'Content-Type: application/json' -d '{"invoice_id":9001,"method":"cash","amount":11.60,"terminal_id":1}' 'https://kafo-erp.com/dolibarr/takepos/api/v1/payments.php' 
``` 
 
Refund: 
```bash 
curl -X POST -H 'Authorization: Bearer VALID_TOKEN' -H 'Content-Type: application/json' -d '{"invoice_id":9001,"amount":2.00,"reason":"return item"}' 'https://kafo-erp.com/dolibarr/takepos/api/v1/refunds.php' 
``` 
