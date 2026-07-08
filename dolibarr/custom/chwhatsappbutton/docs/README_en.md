# ChWhatsAppButton - WhatsApp Module for Dolibarr 🇬🇧

**[🇪🇸 Español](README_es.md) | [🇫🇷 Français](README_fr.md) | [🇮🇹 Italiano](README_it.md)**

---

## 📱 Description

**ChWhatsAppButton** is a Dolibarr module that adds WhatsApp buttons to thirdparty, project, proposal, and invoice cards. It allows sending WhatsApp messages directly from Dolibarr using customizable templates with automatic variable substitution.

## ✨ Features

- ✅ **WhatsApp buttons** integrated in thirdparties, projects, proposals, and invoices
- ✅ **Customizable templates** with automatic variable substitution
- ✅ **Automatic detection** of thirdparty phone numbers
- ✅ **Custom messages** in addition to predefined templates
- ✅ **Perfect integration** with WhatsApp Web/Desktop
- ✅ **Complete template management** from Dolibarr interface
- ✅ **Multilingual** (Spanish, English, French, Italian)
- ✅ **6 predefined templates** ready to use
- ✅ **Intuitive modal** for template selection

## 📋 Requirements

- **Dolibarr**: Version 11.0 or higher
- **PHP**: Version 7.0 or higher
- **MySQL/MariaDB**: Any version compatible with Dolibarr
- **WhatsApp Web or Desktop**: Installed on user's device
- **Phone numbers**: Configured in thirdparties (international format recommended)

## 🚀 Installation

### Method 1: Manual Installation

1. **Copy the module** to Dolibarr's `custom/` directory:
   ```bash
   cp -r chwhatsappbutton /path/to/dolibarr/htdocs/custom/
   ```

2. **Go to module configuration**:
   - Home → Setup → Modules/Applications

3. **Search and activate**:
   - Search for "WhatsApp Button"
   - Click **Activate**

4. **Verify installation**:
   - The module will automatically create the database table
   - 6 predefined templates will be inserted in the configured language

### Method 2: ZIP Installation

1. **Compress the module**:
   ```bash
   zip -r chwhatsappbutton.zip chwhatsappbutton/
   ```

2. **Upload to Dolibarr**:
   - Home → Setup → Modules/Applications
   - Click **Deploy external module**
   - Select the ZIP file

3. **Activate the module** from the module list

## 📖 Usage

### Initial Setup

1. **Access templates**:
   - Tools → WhatsApp → WhatsApp Templates

2. **Review predefined templates**:
   - Send invoice
   - Payment reminder
   - Send proposal
   - Proposal follow-up
   - Project update
   - General message to thirdparty

3. **Configure phone numbers**:
   - Ensure thirdparties have numbers in international format
   - Example: +44612345678 (UK), +1612345678 (USA)

### Sending WhatsApp Messages

#### Step 1: Open the card
Open a **thirdparty**, **project**, **proposal**, or **invoice** card.

#### Step 2: Locate the button
If the thirdparty has a configured phone number, a **green WhatsApp button** will appear next to the "Send Email" button.

#### Step 3: Select template
1. Click the **WhatsApp** button
2. A modal will open with:
   - Thirdparty name
   - Detected phone number
   - List of available templates for that entity type
   - Text area for custom message

#### Step 4: Send message
1. **Option A**: Click "Send this message" on a template
2. **Option B**: Write a custom message and click "Send custom message"
3. WhatsApp Web/Desktop will open with the pre-filled message
4. Review and send from WhatsApp

### Template Management

#### Create a New Template

1. **Access the form**:
   - Tools → WhatsApp → New Template

2. **Complete required fields**:
   - **Reference**: Unique code (e.g., `INVOICE_REMINDER`)
   - **Label**: Descriptive name (e.g., "Invoice reminder")
   - **Entity Type**: Select from:
     - Third Party
     - Project
     - Proposal
     - Invoice
   - **Message Text**: Content with variables

3. **Optional fields**:
   - **Description**: Usage explanation
   - **Active**: Check to make it available
   - **Default**: Check to make it the first option
   - **Position**: Display order (lower number = higher position)

4. **Save** the template

### Substitution Variables

Templates support variables that are automatically substituted according to context:

#### General Variables (All types)
- `__THIRDPARTY_NAME__` - Thirdparty name
- `__THIRDPARTY_CODE__` - Customer code

#### Project Variables
- `__PROJECT_REF__` - Project reference
- `__PROJECT_TITLE__` - Project title

#### Proposal Variables
- `__PROPAL_REF__` - Proposal reference
- `__PROPAL_TOTAL_TTC__` - Total with taxes

#### Invoice Variables
- `__INVOICE_REF__` - Invoice reference
- `__INVOICE_TOTAL_TTC__` - Total with taxes

#### Template Example with Variables

```
Hello __THIRDPARTY_NAME__,

We inform you that invoice __INVOICE_REF__ for an amount of __INVOICE_TOTAL_TTC__ is available.

Thank you for your trust.

Best regards.
```

## 🔧 Configuration

### Module Configuration Page

Access **Tools → WhatsApp → Configuration** to see:
- Module status
- Usage information
- System requirements
- Quick setup guide
- Variable documentation

### Permission Configuration

The module includes three permission levels:

1. **Read WhatsApp templates**
   - View template list
   - View template details

2. **Create/modify WhatsApp templates**
   - Create new templates
   - Edit existing templates

3. **Delete WhatsApp templates**
   - Delete templates

**Assign permissions**:
- Home → Users & Groups → [User]
- **Permissions** tab
- **ChWhatsAppButton** section
- Check desired permissions

## 📱 End User Requirements

For users to send WhatsApp messages:

### 1. WhatsApp Web or Desktop

**Option A: WhatsApp Web**
- URL: https://web.whatsapp.com
- Scan QR code with mobile
- Keep session open

**Option B: WhatsApp Desktop**
- Windows: https://www.whatsapp.com/download
- Mac: https://www.whatsapp.com/download
- Log in and keep open

### 2. Active Session

User must have WhatsApp Web/Desktop:
- ✅ Open
- ✅ Connected
- ✅ With active session

### 3. Correct Phone Numbers

Numbers must be:
- ✅ In international format: `+[country code][number]`
- ✅ Without spaces or dashes (automatically cleaned)
- ✅ Configured in `phone` or `phone_mobile` field of thirdparty

**Examples of valid formats**:
- UK: `+44612345678`
- USA: `+1612345678`
- France: `+33612345678`
- Spain: `+34612345678`

## 🔍 Troubleshooting

### Problem: WhatsApp button doesn't appear

**Possible causes**:
1. ❌ Thirdparty has no phone number
2. ❌ Module is not activated
3. ❌ No active templates for that type
4. ❌ JavaScript didn't load correctly

**Solutions**:
1. ✅ Verify thirdparty has `phone` or `phone_mobile`
2. ✅ Activate module in Setup → Modules
3. ✅ Create/activate templates in Tools → WhatsApp
4. ✅ Clear browser cache (Ctrl+F5)
5. ✅ Check browser console (F12) for JavaScript errors

### Problem: WhatsApp Web doesn't open

**Possible causes**:
1. ❌ Pop-up blocker active
2. ❌ WhatsApp not installed
3. ❌ Incompatible browser

**Solutions**:
1. ✅ Allow pop-ups from Dolibarr
2. ✅ Install WhatsApp Web or Desktop
3. ✅ Use modern browser (Chrome, Firefox, Edge)

### Problem: Variables are not substituted

**Possible causes**:
1. ❌ Variables misspelled (uppercase/lowercase)
2. ❌ Object doesn't have necessary data
3. ❌ PHP code error

**Solutions**:
1. ✅ Verify exact spelling: `__INVOICE_REF__`
2. ✅ Ensure invoice has reference and total
3. ✅ Check PHP logs in `documents/dolibarr.log`

## 📊 Database

### Table: llx_chwhatsapp_templates

Template table structure:

| Field | Type | Description |
|-------|------|-------------|
| `rowid` | int(11) | Unique ID (primary key) |
| `ref` | varchar(128) | Unique template reference |
| `label` | varchar(255) | Template name |
| `description` | text | Usage description |
| `message_text` | longtext | Message text with variables |
| `entity_type` | varchar(50) | Type: thirdparty, project, propal, invoice |
| `is_active` | tinyint(1) | 1 = active, 0 = inactive |
| `is_default` | tinyint(1) | 1 = default, 0 = normal |
| `position` | int(11) | Display order |
| `fk_user_author` | int(11) | Creator user ID |
| `fk_user_modif` | int(11) | Last modifier ID |
| `datec` | datetime | Creation date |
| `tms` | timestamp | Last modification |

## 📝 Module Information

- **Name**: ChWhatsAppButton
- **Module Number**: 105004
- **Version**: 1.0.0
- **Family**: interface
- **Compatibility**: Dolibarr 11.0+
- **License**: GPL-3.0+
- **Languages**: Spanish, English, French, Italian

## 🤝 Contributing

To contribute to module development:

1. **Fork** the repository
2. **Create** a branch for your feature:
   ```bash
   git checkout -b feature/NewFeature
   ```
3. **Commit** your changes:
   ```bash
   git commit -m 'Add new feature'
   ```
4. **Push** to the branch:
   ```bash
   git push origin feature/NewFeature
   ```
5. **Open** a Pull Request

## 📄 Changelog

### v1.0.0 (2025)
- ✅ **Initial module version**
- ✅ WhatsApp buttons in thirdparties, projects, proposals, and invoices
- ✅ Template system with automatic variable substitution
- ✅ Complete template management interface (create, edit, delete)
- ✅ 6 predefined templates ready to use
- ✅ Full multilingual support (ES, EN, FR, IT)
- ✅ Intuitive modal for template selection
- ✅ Custom messages in addition to templates
- ✅ Automatic phone number detection
- ✅ Perfect integration with WhatsApp Web/Desktop
- ✅ Granular permission system
- ✅ Complete documentation in 4 languages

## 🙏 Acknowledgments

- Thanks to the **Dolibarr community** for the excellent framework
- Thanks to **WhatsApp** for the WhatsApp Web API
- Thanks to all **contributors** to the project

## 📧 Support

For support, questions, or to report issues:
- Open an issue in the repository
- Contact the development team
- Consult official Dolibarr documentation

---

**Enjoy sending WhatsApp messages from Dolibarr!** 📱✨

*Developed with ❤️ for the Dolibarr community*
