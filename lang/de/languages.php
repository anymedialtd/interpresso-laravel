<?php

return [
    'form' => [
        'select' => [
            'placeholder' => 'Sprache auswählen',
        ],
        'info' => 'Fügen Sie nur neue Sprachen hinzu. Bereits vorhandene Sprachen können nicht erneut angelegt werden.',
        'button' => [
            'add' => 'Hinzufügen',
            'close' => 'Schließen',
        ],
    ],
    'button' => [
        'import_translations' => 'Übersetzungen importieren',
        'import_languages' => 'Sprachen importieren',
        'add_language' => 'Sprache hinzufügen',
        'find_missing_translations' => 'Fehlende Übersetzungen suchen',
        'chat_gpt_enabled' => '(OPENAI AKTIVIERT)',
        'delete_jobs' => 'Laufende Stapelverarbeitung abbrechen',
    ],
    'table' => [
        'head' => [
            'language_code' => 'Sprachcode',
            'language_name' => 'Sprache',
            'language_native_name' => 'Eigenbezeichnung',
        ],
    ],
    'import_languages_success' => 'Import abgeschlossen. Importierte Sprachen: :languages',
    'import_languages_success_nothing_imported' => 'Import abgeschlossen. Keine Sprachen importiert.',
    'import_translations_success' => 'Import (:language_code) abgeschlossen. Importierte Übersetzungen: :total',
    'find_missing_translations_success' => 'Import (:language_code) abgeschlossen. Importierte fehlende Übersetzungen: :total',
    'find_missing_translations_success_nothing_found' => 'Import fehlender Übersetzungen abgeschlossen. Es gibt nichts zu importieren.',
    'deleted' => 'Sprache gelöscht!',
    'created' => 'Sprache :language angelegt!',
    'info_fallback_language' => 'Ihre Standardsprache (Konfiguration: app.locale) ist :language. Prüfen Sie vor dem Import, ob diese Einstellung stimmt. Klicken Sie auf "Sprachen importieren", um die Anwendung einzurichten.',
];
