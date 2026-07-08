# ChWhatsAppButton - Modulo WhatsApp per Dolibarr 🇮🇹

**[🇪🇸 Español](README_es.md) | [🇬🇧 English](README_en.md) | [🇫🇷 Français](README_fr.md)**

---

## 📱 Descrizione

**ChWhatsAppButton** è un modulo per Dolibarr che aggiunge pulsanti WhatsApp nelle schede di terze parti, progetti, preventivi e fatture. Permette di inviare messaggi WhatsApp direttamente da Dolibarr utilizzando modelli personalizzabili con sostituzione automatica delle variabili.

## ✨ Caratteristiche

- ✅ **Pulsanti WhatsApp** integrati in terze parti, progetti, preventivi e fatture
- ✅ **Modelli personalizzabili** con sostituzione automatica delle variabili
- ✅ **Rilevamento automatico** dei numeri di telefono della terza parte
- ✅ **Messaggi personalizzati** oltre ai modelli predefiniti
- ✅ **Integrazione perfetta** con WhatsApp Web/Desktop
- ✅ **Gestione completa dei modelli** dall'interfaccia Dolibarr
- ✅ **Multilingua** (Spagnolo, Inglese, Francese, Italiano)
- ✅ **6 modelli predefiniti** pronti all'uso
- ✅ **Modal intuitivo** per la selezione dei modelli

## 📋 Requisiti

- **Dolibarr**: Versione 11.0 o superiore
- **PHP**: Versione 7.0 o superiore
- **MySQL/MariaDB**: Qualsiasi versione compatibile con Dolibarr
- **WhatsApp Web o Desktop**: Installato sul dispositivo dell'utente
- **Numeri di telefono**: Configurati nelle terze parti (formato internazionale raccomandato)

## 🚀 Installazione

### Metodo 1: Installazione Manuale

1. **Copiare il modulo** nella directory `custom/` di Dolibarr:
   ```bash
   cp -r chwhatsappbutton /percorso/a/dolibarr/htdocs/custom/
   ```

2. **Andare alla configurazione dei moduli**:
   - Home → Configurazione → Moduli/Applicazioni

3. **Cercare e attivare**:
   - Cercare "WhatsApp Button"
   - Cliccare su **Attiva**

4. **Verificare l'installazione**:
   - Il modulo creerà automaticamente la tabella del database
   - Verranno inseriti 6 modelli predefiniti nella lingua configurata

### Metodo 2: Installazione da ZIP

1. **Comprimere il modulo**:
   ```bash
   zip -r chwhatsappbutton.zip chwhatsappbutton/
   ```

2. **Caricare in Dolibarr**:
   - Home → Configurazione → Moduli/Applicazioni
   - Cliccare su **Distribuisci modulo esterno**
   - Selezionare il file ZIP

3. **Attivare il modulo** dall'elenco dei moduli

## 📖 Utilizzo

### Configurazione Iniziale

1. **Accedere ai modelli**:
   - Strumenti → WhatsApp → Modelli WhatsApp

2. **Esaminare i modelli predefiniti**:
   - Invio fattura
   - Promemoria pagamento
   - Invio preventivo
   - Seguito preventivo
   - Aggiornamento progetto
   - Messaggio generale alla terza parte

3. **Configurare i numeri di telefono**:
   - Assicurarsi che le terze parti abbiano numeri in formato internazionale
   - Esempio: +39612345678 (Italia), +41612345678 (Svizzera)

### Invio di Messaggi WhatsApp

#### Passo 1: Aprire la scheda
Aprire una scheda di **terza parte**, **progetto**, **preventivo** o **fattura**.

#### Passo 2: Localizzare il pulsante
Se la terza parte ha un numero di telefono configurato, apparirà un **pulsante verde WhatsApp** accanto al pulsante "Invia Email".

#### Passo 3: Selezionare un modello
1. Cliccare sul pulsante **WhatsApp**
2. Si aprirà un modal con:
   - Nome della terza parte
   - Numero di telefono rilevato
   - Elenco dei modelli disponibili per quel tipo di entità
   - Area di testo per messaggio personalizzato

#### Passo 4: Inviare il messaggio
1. **Opzione A**: Cliccare su "Invia questo messaggio" su un modello
2. **Opzione B**: Scrivere un messaggio personalizzato e cliccare su "Invia messaggio personalizzato"
3. WhatsApp Web/Desktop si aprirà con il messaggio precompilato
4. Verificare e inviare da WhatsApp

### Gestione dei Modelli

#### Creare un Nuovo Modello

1. **Accedere al modulo**:
   - Strumenti → WhatsApp → Nuovo Modello

2. **Completare i campi obbligatori**:
   - **Riferimento**: Codice univoco (es: `INVOICE_REMINDER`)
   - **Etichetta**: Nome descrittivo (es: "Promemoria fattura")
   - **Tipo di Entità**: Selezionare tra:
     - Terza Parte
     - Progetto
     - Preventivo
     - Fattura
   - **Testo del Messaggio**: Contenuto con variabili

3. **Campi opzionali**:
   - **Descrizione**: Spiegazione dell'utilizzo
   - **Attivo**: Spuntare per renderlo disponibile
   - **Predefinito**: Spuntare per renderlo la prima opzione
   - **Posizione**: Ordine di visualizzazione (numero inferiore = posizione superiore)

4. **Salvare** il modello

### Variabili di Sostituzione

I modelli supportano variabili che vengono automaticamente sostituite in base al contesto:

#### Variabili Generali (Tutti i tipi)
- `__THIRDPARTY_NAME__` - Nome della terza parte
- `__THIRDPARTY_CODE__` - Codice cliente

#### Variabili di Progetti
- `__PROJECT_REF__` - Riferimento del progetto
- `__PROJECT_TITLE__` - Titolo del progetto

#### Variabili di Preventivi
- `__PROPAL_REF__` - Riferimento del preventivo
- `__PROPAL_TOTAL_TTC__` - Totale con tasse

#### Variabili di Fatture
- `__INVOICE_REF__` - Riferimento della fattura
- `__INVOICE_TOTAL_TTC__` - Totale con tasse

#### Esempio di Modello con Variabili

```
Salve __THIRDPARTY_NAME__,

La informiamo che la fattura __INVOICE_REF__ per un importo di __INVOICE_TOTAL_TTC__ è disponibile.

Grazie per la Sua fiducia.

Cordiali saluti.
```

## 🔧 Configurazione

### Pagina di Configurazione del Modulo

Accedere a **Strumenti → WhatsApp → Configurazione** per vedere:
- Stato del modulo
- Informazioni sull'utilizzo
- Requisiti di sistema
- Guida rapida alla configurazione
- Documentazione delle variabili

### Configurazione dei Permessi

Il modulo include tre livelli di permessi:

1. **Leggere i modelli WhatsApp**
   - Visualizzare l'elenco dei modelli
   - Visualizzare i dettagli dei modelli

2. **Creare/modificare i modelli WhatsApp**
   - Creare nuovi modelli
   - Modificare i modelli esistenti

3. **Eliminare i modelli WhatsApp**
   - Eliminare i modelli

**Assegnare i permessi**:
- Home → Utenti & Gruppi → [Utente]
- Scheda **Permessi**
- Sezione **ChWhatsAppButton**
- Spuntare i permessi desiderati

## 📱 Requisiti per l'Utente Finale

Perché gli utenti possano inviare messaggi WhatsApp:

### 1. WhatsApp Web o Desktop

**Opzione A: WhatsApp Web**
- URL: https://web.whatsapp.com
- Scansionare il codice QR con il cellulare
- Mantenere la sessione aperta

**Opzione B: WhatsApp Desktop**
- Windows: https://www.whatsapp.com/download
- Mac: https://www.whatsapp.com/download
- Accedere e mantenere aperto

### 2. Sessione Attiva

L'utente deve avere WhatsApp Web/Desktop:
- ✅ Aperto
- ✅ Connesso
- ✅ Con sessione attiva

### 3. Numeri di Telefono Corretti

I numeri devono essere:
- ✅ In formato internazionale: `+[prefisso paese][numero]`
- ✅ Senza spazi o trattini (puliti automaticamente)
- ✅ Configurati nel campo `phone` o `phone_mobile` della terza parte

**Esempi di formati validi**:
- Italia: `+39612345678`
- Svizzera: `+41612345678`
- Francia: `+33612345678`
- Spagna: `+34612345678`

## 🔍 Risoluzione dei Problemi

### Problema: Il pulsante WhatsApp non appare

**Cause possibili**:
1. ❌ La terza parte non ha un numero di telefono
2. ❌ Il modulo non è attivato
3. ❌ Nessun modello attivo per quel tipo
4. ❌ JavaScript non si è caricato correttamente

**Soluzioni**:
1. ✅ Verificare che la terza parte abbia `phone` o `phone_mobile`
2. ✅ Attivare il modulo in Configurazione → Moduli
3. ✅ Creare/attivare modelli in Strumenti → WhatsApp
4. ✅ Svuotare la cache del browser (Ctrl+F5)
5. ✅ Verificare la console del browser (F12) per errori JavaScript

### Problema: WhatsApp Web non si apre

**Cause possibili**:
1. ❌ Blocco pop-up attivo
2. ❌ WhatsApp non installato
3. ❌ Browser incompatibile

**Soluzioni**:
1. ✅ Consentire i pop-up da Dolibarr
2. ✅ Installare WhatsApp Web o Desktop
3. ✅ Usare un browser moderno (Chrome, Firefox, Edge)

### Problema: Le variabili non vengono sostituite

**Cause possibili**:
1. ❌ Variabili scritte male (maiuscole/minuscole)
2. ❌ L'oggetto non ha i dati necessari
3. ❌ Errore nel codice PHP

**Soluzioni**:
1. ✅ Verificare l'ortografia esatta: `__INVOICE_REF__`
2. ✅ Assicurarsi che la fattura abbia riferimento e totale
3. ✅ Verificare i log PHP in `documents/dolibarr.log`

## 📊 Database

### Tabella: llx_chwhatsapp_templates

Struttura della tabella dei modelli:

| Campo | Tipo | Descrizione |
|-------|------|-------------|
| `rowid` | int(11) | ID univoco (chiave primaria) |
| `ref` | varchar(128) | Riferimento univoco del modello |
| `label` | varchar(255) | Nome del modello |
| `description` | text | Descrizione dell'utilizzo |
| `message_text` | longtext | Testo del messaggio con variabili |
| `entity_type` | varchar(50) | Tipo: thirdparty, project, propal, invoice |
| `is_active` | tinyint(1) | 1 = attivo, 0 = inattivo |
| `is_default` | tinyint(1) | 1 = predefinito, 0 = normale |
| `position` | int(11) | Ordine di visualizzazione |
| `fk_user_author` | int(11) | ID utente creatore |
| `fk_user_modif` | int(11) | ID ultimo modificatore |
| `datec` | datetime | Data di creazione |
| `tms` | timestamp | Ultima modifica |

## 📝 Informazioni sul Modulo

- **Nome**: ChWhatsAppButton
- **Numero modulo**: 105004
- **Versione**: 1.0.0
- **Famiglia**: interface
- **Compatibilità**: Dolibarr 11.0+
- **Licenza**: GPL-3.0+
- **Lingue**: Spagnolo, Inglese, Francese, Italiano

## 🤝 Contribuire

Per contribuire allo sviluppo del modulo:

1. **Fork** il repository
2. **Creare** un branch per la tua funzionalità:
   ```bash
   git checkout -b feature/NuovaFunzionalita
   ```
3. **Commit** le tue modifiche:
   ```bash
   git commit -m 'Aggiungere nuova funzionalità'
   ```
4. **Push** al branch:
   ```bash
   git push origin feature/NuovaFunzionalita
   ```
5. **Aprire** una Pull Request

## 📄 Changelog

### v1.0.0 (2025)
- ✅ **Versione iniziale del modulo**
- ✅ Pulsanti WhatsApp in terze parti, progetti, preventivi e fatture
- ✅ Sistema di modelli con sostituzione automatica delle variabili
- ✅ Interfaccia completa di gestione dei modelli (creare, modificare, eliminare)
- ✅ 6 modelli predefiniti pronti all'uso
- ✅ Supporto multilingua completo (ES, EN, FR, IT)
- ✅ Modal intuitivo per la selezione dei modelli
- ✅ Messaggi personalizzati oltre ai modelli
- ✅ Rilevamento automatico dei numeri di telefono
- ✅ Integrazione perfetta con WhatsApp Web/Desktop
- ✅ Sistema di permessi granulare
- ✅ Documentazione completa in 4 lingue

## 🙏 Ringraziamenti

- Grazie alla **comunità Dolibarr** per l'eccellente framework
- Grazie a **WhatsApp** per l'API WhatsApp Web
- Grazie a tutti i **contributori** del progetto

## 📧 Supporto

Per supporto, domande o per segnalare problemi:
- Aprire un issue nel repository
- Contattare il team di sviluppo
- Consultare la documentazione ufficiale di Dolibarr

---

**Goditi l'invio di messaggi WhatsApp da Dolibarr!** 📱✨

*Sviluppato con ❤️ per la comunità Dolibarr*
