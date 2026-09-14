<?php

return [
    'form' => [
        'select' => [
            'placeholder' => 'Seleziona lingua',
        ],
        'info' => 'Aggiungi solo nuove lingue. Le lingue già presenti non possono essere aggiunte di nuovo.',
        'button' => [
            'add' => 'Aggiungi',
            'close' => 'Chiudi',
        ],
    ],
    'button' => [
        'import_translations' => 'Importa traduzioni',
        'import_languages' => 'Importa lingue',
        'add_language' => 'Aggiungi lingua',
        'find_missing_translations' => 'Cerca traduzioni mancanti',
        'chat_gpt_enabled' => '(OPENAI ATTIVO)',
        'delete_jobs' => 'Annulla elaborazione in blocco in corso',
    ],
    'table' => [
        'head' => [
            'language_code' => 'Codice lingua',
            'language_name' => 'Lingua',
            'language_native_name' => 'Nome nella lingua originale',
        ],
    ],
    'import_languages_success' => 'Importazione completata. Lingue importate: :languages',
    'import_languages_success_nothing_imported' => 'Importazione completata. Nessuna lingua importata.',
    'import_translations_success' => 'Importazione (:language_code) completata. Traduzioni importate: :total',
    'find_missing_translations_success' => 'Importazione (:language_code) completata. Traduzioni mancanti importate: :total',
    'find_missing_translations_success_nothing_found' => 'Importazione delle traduzioni mancanti completata. Nessun elemento da importare.',
    'deleted' => 'Lingua eliminata!',
    'created' => 'Lingua :language creata!',
    'info_fallback_language' => 'La lingua predefinita (configurazione: app.locale) è :language. Verifica questa impostazione prima di importare le lingue. Fai clic su "Importa lingue" per iniziare a usare l\'applicazione.',
];
