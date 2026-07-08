# ChWhatsAppButton - Module WhatsApp pour Dolibarr 🇫🇷

**[🇪🇸 Español](README_es.md) | [🇬🇧 English](README_en.md) | [🇮🇹 Italiano](README_it.md)**

---

## 📱 Description

**ChWhatsAppButton** est un module pour Dolibarr qui ajoute des boutons WhatsApp sur les fiches des tiers, projets, propositions commerciales et factures. Il permet d'envoyer des messages WhatsApp directement depuis Dolibarr en utilisant des modèles personnalisables avec substitution automatique de variables.

## ✨ Fonctionnalités

- ✅ **Boutons WhatsApp** intégrés dans les tiers, projets, propositions et factures
- ✅ **Modèles personnalisables** avec substitution automatique de variables
- ✅ **Détection automatique** des numéros de téléphone du tiers
- ✅ **Messages personnalisés** en plus des modèles prédéfinis
- ✅ **Intégration parfaite** avec WhatsApp Web/Desktop
- ✅ **Gestion complète des modèles** depuis l'interface Dolibarr
- ✅ **Multilingue** (Espagnol, Anglais, Français, Italien)
- ✅ **6 modèles prédéfinis** prêts à l'emploi
- ✅ **Modal intuitif** pour la sélection de modèles

## 📋 Prérequis

- **Dolibarr**: Version 11.0 ou supérieure
- **PHP**: Version 7.0 ou supérieure
- **MySQL/MariaDB**: Toute version compatible avec Dolibarr
- **WhatsApp Web ou Desktop**: Installé sur l'appareil de l'utilisateur
- **Numéros de téléphone**: Configurés dans les tiers (format international recommandé)

## 🚀 Installation

### Méthode 1 : Installation Manuelle

1. **Copier le module** dans le répertoire `custom/` de Dolibarr :
   ```bash
   cp -r chwhatsappbutton /chemin/vers/dolibarr/htdocs/custom/
   ```

2. **Aller à la configuration des modules** :
   - Accueil → Configuration → Modules/Applications

3. **Rechercher et activer** :
   - Rechercher "WhatsApp Button"
   - Cliquer sur **Activer**

4. **Vérifier l'installation** :
   - Le module créera automatiquement la table de base de données
   - 6 modèles prédéfinis seront insérés dans la langue configurée

### Méthode 2 : Installation depuis ZIP

1. **Compresser le module** :
   ```bash
   zip -r chwhatsappbutton.zip chwhatsappbutton/
   ```

2. **Télécharger dans Dolibarr** :
   - Accueil → Configuration → Modules/Applications
   - Cliquer sur **Déployer module externe**
   - Sélectionner le fichier ZIP

3. **Activer le module** depuis la liste des modules

## 📖 Utilisation

### Configuration Initiale

1. **Accéder aux modèles** :
   - Outils → WhatsApp → Modèles WhatsApp

2. **Examiner les modèles prédéfinis** :
   - Envoi de facture
   - Rappel de paiement
   - Envoi de proposition commerciale
   - Suivi de proposition
   - Mise à jour du projet
   - Message général au tiers

3. **Configurer les numéros de téléphone** :
   - S'assurer que les tiers ont des numéros au format international
   - Exemple : +33612345678 (France), +32612345678 (Belgique)

### Envoi de Messages WhatsApp

#### Étape 1 : Ouvrir la fiche
Ouvrir une fiche de **tiers**, **projet**, **proposition commerciale** ou **facture**.

#### Étape 2 : Localiser le bouton
Si le tiers a un numéro de téléphone configuré, un **bouton vert WhatsApp** apparaîtra à côté du bouton "Envoyer Email".

#### Étape 3 : Sélectionner un modèle
1. Cliquer sur le bouton **WhatsApp**
2. Un modal s'ouvrira avec :
   - Nom du tiers
   - Numéro de téléphone détecté
   - Liste des modèles disponibles pour ce type d'entité
   - Zone de texte pour message personnalisé

#### Étape 4 : Envoyer le message
1. **Option A** : Cliquer sur "Envoyer ce message" sur un modèle
2. **Option B** : Écrire un message personnalisé et cliquer sur "Envoyer un message personnalisé"
3. WhatsApp Web/Desktop s'ouvrira avec le message pré-rempli
4. Vérifier et envoyer depuis WhatsApp

### Gestion des Modèles

#### Créer un Nouveau Modèle

1. **Accéder au formulaire** :
   - Outils → WhatsApp → Nouveau Modèle

2. **Compléter les champs obligatoires** :
   - **Référence** : Code unique (ex : `INVOICE_REMINDER`)
   - **Libellé** : Nom descriptif (ex : "Rappel de facture")
   - **Type d'Entité** : Sélectionner parmi :
     - Tiers
     - Projet
     - Proposition Commerciale
     - Facture
   - **Texte du Message** : Contenu avec variables

3. **Champs optionnels** :
   - **Description** : Explication de l'utilisation
   - **Actif** : Cocher pour le rendre disponible
   - **Par Défaut** : Cocher pour en faire la première option
   - **Position** : Ordre d'affichage (nombre inférieur = position supérieure)

4. **Enregistrer** le modèle

### Variables de Substitution

Les modèles supportent des variables qui sont automatiquement substituées selon le contexte :

#### Variables Générales (Tous les types)
- `__THIRDPARTY_NAME__` - Nom du tiers
- `__THIRDPARTY_CODE__` - Code client

#### Variables de Projets
- `__PROJECT_REF__` - Référence du projet
- `__PROJECT_TITLE__` - Titre du projet

#### Variables de Propositions
- `__PROPAL_REF__` - Référence de la proposition
- `__PROPAL_TOTAL_TTC__` - Total TTC

#### Variables de Factures
- `__INVOICE_REF__` - Référence de la facture
- `__INVOICE_TOTAL_TTC__` - Total TTC

#### Exemple de Modèle avec Variables

```
Bonjour __THIRDPARTY_NAME__,

Nous vous informons que la facture __INVOICE_REF__ pour un montant de __INVOICE_TOTAL_TTC__ est disponible.

Merci pour votre confiance.

Cordialement.
```

## 🔧 Configuration

### Page de Configuration du Module

Accéder à **Outils → WhatsApp → Configuration** pour voir :
- Statut du module
- Informations d'utilisation
- Prérequis système
- Guide de configuration rapide
- Documentation des variables

### Configuration des Permissions

Le module inclut trois niveaux de permissions :

1. **Lire les modèles WhatsApp**
   - Voir la liste des modèles
   - Voir les détails des modèles

2. **Créer/modifier les modèles WhatsApp**
   - Créer de nouveaux modèles
   - Modifier les modèles existants

3. **Supprimer les modèles WhatsApp**
   - Supprimer les modèles

**Attribuer les permissions** :
- Accueil → Utilisateurs & Groupes → [Utilisateur]
- Onglet **Permissions**
- Section **ChWhatsAppButton**
- Cocher les permissions souhaitées

## 📱 Prérequis pour l'Utilisateur Final

Pour que les utilisateurs puissent envoyer des messages WhatsApp :

### 1. WhatsApp Web ou Desktop

**Option A : WhatsApp Web**
- URL : https://web.whatsapp.com
- Scanner le code QR avec le mobile
- Garder la session ouverte

**Option B : WhatsApp Desktop**
- Windows : https://www.whatsapp.com/download
- Mac : https://www.whatsapp.com/download
- Se connecter et garder ouvert

### 2. Session Active

L'utilisateur doit avoir WhatsApp Web/Desktop :
- ✅ Ouvert
- ✅ Connecté
- ✅ Avec session active

### 3. Numéros de Téléphone Corrects

Les numéros doivent être :
- ✅ Au format international : `+[code pays][numéro]`
- ✅ Sans espaces ni tirets (nettoyés automatiquement)
- ✅ Configurés dans le champ `phone` ou `phone_mobile` du tiers

**Exemples de formats valides** :
- France : `+33612345678`
- Belgique : `+32612345678`
- Suisse : `+41612345678`
- Canada : `+1612345678`

## 🔍 Dépannage

### Problème : Le bouton WhatsApp n'apparaît pas

**Causes possibles** :
1. ❌ Le tiers n'a pas de numéro de téléphone
2. ❌ Le module n'est pas activé
3. ❌ Pas de modèles actifs pour ce type
4. ❌ JavaScript ne s'est pas chargé correctement

**Solutions** :
1. ✅ Vérifier que le tiers a `phone` ou `phone_mobile`
2. ✅ Activer le module dans Configuration → Modules
3. ✅ Créer/activer des modèles dans Outils → WhatsApp
4. ✅ Vider le cache du navigateur (Ctrl+F5)
5. ✅ Vérifier la console du navigateur (F12) pour les erreurs JavaScript

### Problème : WhatsApp Web ne s'ouvre pas

**Causes possibles** :
1. ❌ Bloqueur de pop-ups actif
2. ❌ WhatsApp non installé
3. ❌ Navigateur incompatible

**Solutions** :
1. ✅ Autoriser les pop-ups depuis Dolibarr
2. ✅ Installer WhatsApp Web ou Desktop
3. ✅ Utiliser un navigateur moderne (Chrome, Firefox, Edge)

### Problème : Les variables ne sont pas substituées

**Causes possibles** :
1. ❌ Variables mal orthographiées (majuscules/minuscules)
2. ❌ L'objet n'a pas les données nécessaires
3. ❌ Erreur dans le code PHP

**Solutions** :
1. ✅ Vérifier l'orthographe exacte : `__INVOICE_REF__`
2. ✅ S'assurer que la facture a une référence et un total
3. ✅ Vérifier les logs PHP dans `documents/dolibarr.log`

## 📊 Base de Données

### Table : llx_chwhatsapp_templates

Structure de la table des modèles :

| Champ | Type | Description |
|-------|------|-------------|
| `rowid` | int(11) | ID unique (clé primaire) |
| `ref` | varchar(128) | Référence unique du modèle |
| `label` | varchar(255) | Nom du modèle |
| `description` | text | Description de l'utilisation |
| `message_text` | longtext | Texte du message avec variables |
| `entity_type` | varchar(50) | Type : thirdparty, project, propal, invoice |
| `is_active` | tinyint(1) | 1 = actif, 0 = inactif |
| `is_default` | tinyint(1) | 1 = par défaut, 0 = normal |
| `position` | int(11) | Ordre d'affichage |
| `fk_user_author` | int(11) | ID utilisateur créateur |
| `fk_user_modif` | int(11) | ID dernier modificateur |
| `datec` | datetime | Date de création |
| `tms` | timestamp | Dernière modification |

## 📝 Informations sur le Module

- **Nom** : ChWhatsAppButton
- **Numéro de module** : 105004
- **Version** : 1.0.0
- **Famille** : interface
- **Compatibilité** : Dolibarr 11.0+
- **Licence** : GPL-3.0+
- **Langues** : Espagnol, Anglais, Français, Italien

## 🤝 Contribuer

Pour contribuer au développement du module :

1. **Fork** le dépôt
2. **Créer** une branche pour votre fonctionnalité :
   ```bash
   git checkout -b feature/NouvelleFonctionnalite
   ```
3. **Commit** vos changements :
   ```bash
   git commit -m 'Ajouter nouvelle fonctionnalité'
   ```
4. **Push** vers la branche :
   ```bash
   git push origin feature/NouvelleFonctionnalite
   ```
5. **Ouvrir** une Pull Request

## 📄 Changelog

### v1.0.0 (2025)
- ✅ **Version initiale du module**
- ✅ Boutons WhatsApp dans les tiers, projets, propositions et factures
- ✅ Système de modèles avec substitution automatique de variables
- ✅ Interface complète de gestion des modèles (créer, modifier, supprimer)
- ✅ 6 modèles prédéfinis prêts à l'emploi
- ✅ Support multilingue complet (ES, EN, FR, IT)
- ✅ Modal intuitif pour la sélection de modèles
- ✅ Messages personnalisés en plus des modèles
- ✅ Détection automatique des numéros de téléphone
- ✅ Intégration parfaite avec WhatsApp Web/Desktop
- ✅ Système de permissions granulaire
- ✅ Documentation complète en 4 langues

## 🙏 Remerciements

- Merci à la **communauté Dolibarr** pour l'excellent framework
- Merci à **WhatsApp** pour l'API WhatsApp Web
- Merci à tous les **contributeurs** du projet

## 📧 Support

Pour le support, les questions ou pour signaler des problèmes :
- Ouvrir un issue dans le dépôt
- Contacter l'équipe de développement
- Consulter la documentation officielle de Dolibarr

---

**Profitez de l'envoi de messages WhatsApp depuis Dolibarr !** 📱✨

*Développé avec ❤️ pour la communauté Dolibarr*
