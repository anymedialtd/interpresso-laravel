<?php

return [
    'form' => [
        'select' => [
            'placeholder' => 'Sélectionner une langue',
        ],
        'info' => 'Ajoutez uniquement de nouvelles langues. Les langues déjà présentes ne peuvent pas être ajoutées à nouveau.',
        'button' => [
            'add' => 'Ajouter',
            'close' => 'Fermer',
        ],
    ],
    'button' => [
        'import_translations' => 'Importer les traductions',
        'import_languages' => 'Importer les langues',
        'add_language' => 'Ajouter une langue',
        'find_missing_translations' => 'Rechercher les traductions manquantes',
        'chat_gpt_enabled' => '(OPENAI ACTIVÉ)',
        'delete_jobs' => 'Annuler le traitement par lot en cours',
    ],
    'table' => [
        'head' => [
            'language_code' => 'Code de langue',
            'language_name' => 'Langue',
            'language_native_name' => 'Nom dans la langue d\'origine',
        ],
    ],
    'import_languages_success' => 'Importation terminée. Langues importées : :languages',
    'import_languages_success_nothing_imported' => 'Importation terminée. Aucune langue importée.',
    'import_translations_success' => 'Importation (:language_code) terminée. Traductions importées : :total',
    'find_missing_translations_success' => 'Importation (:language_code) terminée. Traductions manquantes importées : :total',
    'find_missing_translations_success_nothing_found' => 'Importation des traductions manquantes terminée. Aucun élément à importer.',
    'deleted' => 'Langue supprimée !',
    'created' => 'Langue :language créée !',
    'info_fallback_language' => 'Votre langue par défaut (configuration : app.locale) est :language. Vérifiez ce réglage avant d\'importer les langues. Cliquez sur "Importer les langues" pour commencer à utiliser l\'application.',
];
