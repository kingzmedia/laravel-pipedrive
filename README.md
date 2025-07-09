# Laravel Pipedrive Package

[![Latest Version on Packagist](https://img.shields.io/packagist/v/kingzmedia/laravel-pipedrive.svg?style=flat-square)](https://packagist.org/packages/kingzmedia/laravel-pipedrive)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/kingzmedia/laravel-pipedrive/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/kingzmedia/laravel-pipedrive/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/kingzmedia/laravel-pipedrive/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/kingzmedia/laravel-pipedrive/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/kingzmedia/laravel-pipedrive.svg?style=flat-square)](https://packagist.org/packages/kingzmedia/laravel-pipedrive)

Un wrapper Laravel pour l'API Pipedrive avec support complet des custom fields. Ce package utilise [devio/pipedrive](https://github.com/IsraelOrtuno/pipedrive) et ajoute des fonctionnalités spécifiques à Laravel pour gérer les custom fields de toutes les entités Pipedrive.

## Fonctionnalités

- 🔧 **Gestion complète des custom fields** - Synchronisation et gestion de tous les custom fields Pipedrive
- 📊 **Support de toutes les entités** - Activities, Deals, Files, Goals, Notes, Organizations, Persons, Pipelines, Products, Stages, Users
- 🎯 **16 types de champs supportés** - Text, Date, Options, Relations, etc.
- 🔄 **Synchronisation automatique** - Commandes Artisan pour synchroniser les fields et entités
- ✅ **Validation intégrée** - Validation des valeurs selon le type de champ
- 🎨 **Formatage automatique** - Formatage des valeurs pour l'affichage
- 🏗️ **Service et Façade** - API Laravel intuitive
- 🗃️ **Modèles Eloquent** - Modèles Laravel pour toutes les entités Pipedrive
- 🔍 **Scopes et relations** - Requêtes optimisées avec des scopes prédéfinis

## Support us

[<img src="https://github-ads.s3.eu-central-1.amazonaws.com/laravel-pipedrive.jpg?t=1" width="419px" />](https://spatie.be/github-ad-click/laravel-pipedrive)

We invest a lot of resources into creating [best in class open source packages](https://spatie.be/open-source). You can support us by [buying one of our paid products](https://spatie.be/open-source/support-us).

We highly appreciate you sending us a postcard from your hometown, mentioning which of our package(s) you are using. You'll find our address on [our contact page](https://spatie.be/about-us). We publish all received postcards on [our virtual postcard wall](https://spatie.be/open-source/postcards).

## Installation

You can install the package via composer:

```bash
composer require kingzmedia/laravel-pipedrive
```

You can publish and run the migrations with:

```bash
php artisan vendor:publish --tag="laravel-pipedrive-migrations"
php artisan migrate
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="laravel-pipedrive-config"
```

## Configuration

Ce package supporte deux méthodes d'authentification avec Pipedrive :

### 1. Authentification par Token API (Recommandée pour débuter)

Ajoutez votre token API dans votre fichier `.env` :

```env
PIPEDRIVE_AUTH_METHOD=token
PIPEDRIVE_TOKEN=your_pipedrive_api_token_here
```

### 2. Authentification OAuth 2.0 (Pour les applications publiques)

Pour utiliser OAuth, configurez votre application dans le Pipedrive Developer Hub, puis ajoutez dans votre `.env` :

```env
PIPEDRIVE_AUTH_METHOD=oauth
PIPEDRIVE_CLIENT_ID=your_client_id
PIPEDRIVE_CLIENT_SECRET=your_client_secret
PIPEDRIVE_REDIRECT_URL=https://your-app.com/pipedrive/callback
```

### Test de la connexion

Testez votre configuration avec :

```bash
php artisan pipedrive:test-connection
```

Optionally, you can publish the views using

```bash
php artisan vendor:publish --tag="laravel-pipedrive-views"
```

## Usage

### Synchronisation des Custom Fields

Synchronisez tous les custom fields depuis Pipedrive :

```bash
# Synchroniser tous les custom fields
php artisan pipedrive:sync-custom-fields

# Synchroniser une entité spécifique
php artisan pipedrive:sync-custom-fields --entity=deal

# Forcer la mise à jour
php artisan pipedrive:sync-custom-fields --force

# Synchroniser les entités Pipedrive
php artisan pipedrive:sync-entities

# Synchroniser une entité spécifique
php artisan pipedrive:sync-entities --entity=deals

# Limiter le nombre d'enregistrements
php artisan pipedrive:sync-entities --entity=activities --limit=50
```

### Utilisation du Service

```php
use Keggermont\LaravelPipedrive\Services\PipedriveCustomFieldService;
use Keggermont\LaravelPipedrive\Models\PipedriveCustomField;

$service = app(PipedriveCustomFieldService::class);

// Récupérer les custom fields pour les deals
$dealFields = $service->getFieldsForEntity('deal');

// Récupérer seulement les custom fields (pas les champs par défaut)
$customFields = $service->getCustomFieldsForEntity('deal');

// Rechercher un field par sa clé
$field = $service->findByKey('dcf558aac1ae4e8c4f849ba5e668430d8df9be12', 'deal');

// Valider une valeur
$errors = $service->validateFieldValue($field, $value);

// Formater une valeur pour l'affichage
$formatted = $service->formatFieldValue($field, $rawValue);
```

### Utilisation avec la Façade

```php
use Keggermont\LaravelPipedrive\Facades\PipedriveCustomField;

$fields = PipedriveCustomField::getFieldsForEntity('deal');
$stats = PipedriveCustomField::getFieldStatistics('deal');
```

### Utilisation du Modèle

```php
use Keggermont\LaravelPipedrive\Models\PipedriveCustomField;

// Tous les fields actifs pour les deals
$dealFields = PipedriveCustomField::forEntity('deal')->active()->get();

// Seulement les custom fields
$customFields = PipedriveCustomField::forEntity('deal')->customOnly()->get();

// Fields obligatoires
$mandatoryFields = PipedriveCustomField::forEntity('deal')->mandatory()->get();

// Fields par type
$textFields = PipedriveCustomField::forEntity('deal')->ofType('varchar')->get();
```

### Entités Supportées

- `deal` - Champs des deals (les leads héritent des champs deals)
- `person` - Champs des personnes
- `organization` - Champs des organisations
- `product` - Champs des produits
- `activity` - Champs des activités
- `note` - Champs des notes

### Types de Champs Supportés

- **Text** (`varchar`) - Texte jusqu'à 255 caractères
- **Autocomplete** (`varchar_auto`) - Texte avec autocomplétion
- **Large text** (`text`) - Texte long
- **Numerical** (`double`) - Valeur numérique
- **Monetary** (`monetary`) - Valeur monétaire
- **Multiple options** (`set`) - Sélection multiple
- **Single option** (`enum`) - Sélection unique
- **User** (`user`) - Utilisateur Pipedrive
- **Organization** (`org`) - Organisation
- **Person** (`people`) - Personne
- **Phone** (`phone`) - Numéro de téléphone
- **Time** (`time`) - Heure
- **Time range** (`timerange`) - Plage horaire
- **Date** (`date`) - Date
- **Date range** (`daterange`) - Plage de dates
- **Address** (`address`) - Adresse

Pour plus de détails, consultez la [documentation complète des custom fields](docs/CUSTOM_FIELDS.md).

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [EGGERMONT Kévin](https://github.com/kingzmedia)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
