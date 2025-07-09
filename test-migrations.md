# 🧪 Test des nouvelles migrations

## 📋 **Étapes de test :**

### 1. **Publier les migrations**
```bash
php artisan vendor:publish --tag=laravel-pipedrive-migrations --force
```

### 2. **Reset de la base de données**
```bash
php artisan db:wipe
```

### 3. **Exécuter les migrations**
```bash
php artisan migrate
```

### 4. **Vérifier les tables créées**
```bash
php artisan tinker
```

```php
// Dans tinker
use Illuminate\Support\Facades\Schema;

// Vérifier que toutes les tables existent
$tables = [
    'pipedrive_activities',
    'pipedrive_deals', 
    'pipedrive_files',
    'pipedrive_notes',
    'pipedrive_organizations',
    'pipedrive_persons',
    'pipedrive_pipelines',
    'pipedrive_products',
    'pipedrive_stages',
    'pipedrive_users',
    'pipedrive_goals',
    'pipedrive_custom_fields'
];

foreach ($tables as $table) {
    echo $table . ': ' . (Schema::hasTable($table) ? '✅' : '❌') . PHP_EOL;
}

// Vérifier la structure d'une table (exemple: users)
$columns = Schema::getColumnListing('pipedrive_users');
print_r($columns);

// Vérifier que pipedrive_data existe
echo 'pipedrive_data column: ' . (Schema::hasColumn('pipedrive_users', 'pipedrive_data') ? '✅' : '❌') . PHP_EOL;
```

### 5. **Tester la synchronisation**
```bash
php artisan pipedrive:sync-entities --entity=users --limit=2
```

## ✅ **Résultats attendus :**

- Toutes les tables créées avec succès
- Colonne `pipedrive_data` présente dans chaque table
- Colonnes essentielles présentes selon l'entité
- Synchronisation sans erreurs de colonnes manquantes
- Données stockées dans JSON pour les champs non-essentiels

## 🔍 **Structure attendue pour chaque table :**

```sql
-- Colonnes communes
id                    -- Auto-increment
pipedrive_id          -- Unique Pipedrive ID
[essential_fields]    -- Selon l'entité
active_flag           -- Boolean
pipedrive_data        -- JSON avec toutes les autres données
pipedrive_add_time    -- Timestamp
pipedrive_update_time -- Timestamp
created_at            -- Laravel timestamp
updated_at            -- Laravel timestamp
```

## 🚨 **En cas d'erreur :**

1. Vérifier que les stubs sont bien publiés
2. Vérifier que les modèles ont les bons `$fillable`
3. Vérifier que `pipedrive_data` est dans les casts du modèle de base
4. Relancer le processus complet
