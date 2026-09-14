<?php

return [
    'saved' => 'Impostazione salvata.',
    'domains_required' => 'I domini sono obbligatori quando il coordinamento tra host è attivo.',
    'autosave_info' => 'Ogni campo viene salvato separatamente quando viene modificato. Se JavaScript è disattivato, usa il pulsante Salva del campo.',
    'main_domain' => [
        'label' => 'Dominio principale',
        'info' => 'Dominio principale su cui vengono elaborate le traduzioni. Modifica questo valore nel file di configurazione del pacchetto.',
    ],
    'enable_multi_host' => [
        'label' => 'Attiva coordinamento tra host',
        'info' => 'Lascia disattivato per un singolo progetto. Attiva per controllare i job in corso, esportare le traduzioni e annullare i job sugli host partecipanti.',
    ],
    'domains' => [
        'label' => 'Domini',
        'info' => 'Obbligatorio solo se il coordinamento tra host è attivo. Inserisci tutti i domini che condividono il sistema di traduzione, separati da virgole e preceduti da http:// o https://. Esempio: http://example.com,https://example.com.',
    ],
    'import_settings' => 'Impostazioni di importazione',
    'db_loader_text' => 'Carica traduzioni dal database (importa prima le traduzioni dai file)',
    'import_vendor_text' => 'Importa traduzioni dei pacchetti (disattiva il caricamento dal database, importa i file dei pacchetti e poi riattivalo)',
    'enable_pending_translations_notifications' => 'Attiva notifiche per le traduzioni in sospeso.',
    'enable_automatic_pending_translations_notifications' => 'Attiva notifiche automatiche per le traduzioni in sospeso.',
    'enable_open_ai_translations' => 'Attiva la traduzione con OpenAI durante la creazione delle traduzioni mancanti.',
    'import_only_from_root_language' => [
        'label' => 'Importa solo dalla lingua di origine.',
        'info' => 'Attiva per importare solo i file della lingua di origine (:language).',
    ],
    'allow_deleting_languages' => [
        'label' => 'Consenti l\'eliminazione delle lingue.',
    ],
];
