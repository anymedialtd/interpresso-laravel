<?php

return [
    'saved' => 'Paramètre enregistré.',
    'domains_required' => 'Les domaines sont obligatoires lorsque la coordination entre hôtes est activée.',
    'autosave_info' => 'Chaque champ est enregistré séparément lors de sa modification. Si JavaScript est désactivé, utilisez son bouton Enregistrer.',
    'main_domain' => [
        'label' => 'Domaine principal',
        'info' => 'Domaine principal sur lequel s\'exécute le traitement des traductions. Modifiez cette valeur dans le fichier de configuration du package.',
    ],
    'enable_multi_host' => [
        'label' => 'Activer la coordination entre hôtes',
        'info' => 'Laissez cette option désactivée pour un seul projet. Activez-la pour vérifier les tâches en cours, exporter les traductions et annuler les tâches sur les hôtes participants.',
    ],
    'domains' => [
        'label' => 'Domaines',
        'info' => 'Requis uniquement si la coordination entre hôtes est activée. Indiquez tous les domaines partageant le système de traduction, séparés par des virgules et précédés de http:// ou https://. Exemple : http://example.com,https://example.com.',
    ],
    'import_settings' => 'Paramètres d\'importation',
    'db_loader_text' => 'Charger les traductions depuis la base de données (importez d\'abord les traductions depuis les fichiers)',
    'import_vendor_text' => 'Importer les traductions des packages (désactivez d\'abord le chargement depuis la base de données, importez les fichiers des packages, puis réactivez-le)',
    'enable_pending_translations_notifications' => 'Activer les notifications de traductions en attente.',
    'enable_automatic_pending_translations_notifications' => 'Activer les notifications automatiques de traductions en attente.',
    'enable_open_ai_translations' => 'Activer la traduction avec OpenAI lors de la création des traductions manquantes.',
    'import_only_from_root_language' => [
        'label' => 'Importer uniquement la langue source.',
        'info' => 'Activez cette option pour importer uniquement les fichiers de la langue source (:language).',
    ],
    'allow_deleting_languages' => [
        'label' => 'Autoriser la suppression de langues.',
    ],
];
