# 🧹 Commandes pour nettoyer les migrations

## 🚨 **Le problème :**
Tu as encore d'anciennes migrations publiées qui référencent `active` au lieu de `active_flag`.

## ✅ **Solution :**

### 1. **Supprimer toutes les anciennes migrations Pipedrive**
```bash
# Dans le projet principal (/Users/keggermont/Lab/kaido-kit/)
rm database/migrations/*pipedrive*.php
```

### 2. **Republier les nouvelles migrations**
```bash
php artisan vendor:publish --tag=laravel-pipedrive-migrations --force
```

### 3. **Reset et migrate**
```bash
php artisan db:wipe
php artisan migrate
```

### 4. **Tester**
```bash
php artisan pipedrive:sync-entities --entity=users --limit=2
```

## 🔍 **Vérification :**

Si tu veux vérifier quelles migrations sont publiées :
```bash
ls -la database/migrations/*pipedrive*.php
```

Tu devrais voir des fichiers avec des timestamps récents et des noms comme :
- `2025_07_09_XXXXXX_create_pipedrive_activities_table.php`
- `2025_07_09_XXXXXX_create_pipedrive_deals_table.php`
- etc.

## 📋 **Commandes complètes en une fois :**

```bash
# Nettoyer et republier
rm database/migrations/*pipedrive*.php
php artisan vendor:publish --tag=laravel-pipedrive-migrations --force

# Reset et migrate
php artisan db:wipe
php artisan migrate

# Tester
php artisan pipedrive:sync-entities --entity=users --limit=2
```

Cela devrait résoudre l'erreur `Key column 'active' doesn't exist` ! 🎯
