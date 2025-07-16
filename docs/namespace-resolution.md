# Résolution Intelligente des Namespaces

Laravel Flowpipe implémente une résolution intelligente des namespaces pour les classes de steps, permettant une utilisation plus flexible et maintenable.

## Principe de Fonctionnement

La résolution suit cette logique simple :

1. **Namespace complet** : Si le nom de classe contient un backslash (`\`), il est utilisé tel quel
2. **Namespace relatif** : Si le nom de classe ne contient pas de backslash, il est préfixé avec la configuration `step_namespace`

## Configuration

Dans votre fichier `config/flowpipe.php` :

```php
'step_namespace' => 'App\\Flowpipe\\Steps',
```

## Exemples d'Utilisation

### 1. Namespace Relatif (Recommandé)

```yaml
flow: user-registration
steps:
  - type: action
    class: UserRegistration\ValidateInputStep
    # Résolu vers : App\Flowpipe\Steps\UserRegistration\ValidateInputStep
    
  - type: action
    class: SimpleValidationStep
    # Résolu vers : App\Flowpipe\Steps\SimpleValidationStep
```

### 2. Namespace Complet

```yaml
flow: user-registration
steps:
  - type: action
    class: Examples\Steps\UserRegistration\ValidateInputStep
    # Utilisé tel quel : Examples\Steps\UserRegistration\ValidateInputStep
    
  - type: action
    class: My\Custom\Namespace\MyStep
    # Utilisé tel quel : My\Custom\Namespace\MyStep
```

### 3. Approche Hybride

```yaml
flow: user-registration
steps:
  # Namespace relatif pour les steps de l'application
  - type: action
    class: UserRegistration\ValidateInputStep
    
  # Namespace complet pour des steps externes
  - type: action
    class: ThirdParty\Package\Steps\ExternalStep
    
  # Classe simple pour des steps utilitaires
  - type: action
    class: LoggerStep
```

## Avantages

1. **Flexibilité** : Permet d'utiliser les deux approches selon le besoin
2. **Maintenabilité** : Les namespaces relatifs sont plus courts et plus faciles à maintenir
3. **Rétrocompatibilité** : Les flows existants continuent de fonctionner
4. **Configuration centralisée** : Un seul endroit pour configurer le namespace par défaut

## Cas d'Usage Recommandés

- **Namespace relatif** : Pour les steps de votre application
- **Namespace complet** : Pour les steps de packages externes ou des exemples
- **Classe simple** : Pour des steps utilitaires simples

Cette approche offre le meilleur des deux mondes : la simplicité pour les cas courants et la flexibilité pour les cas spéciaux.
