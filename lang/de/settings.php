<?php

return [
    'saved' => 'Einstellung gespeichert.',
    'domains_required' => 'Bei aktivierter Koordination mehrerer Hosts müssen Domains angegeben werden.',
    'autosave_info' => 'Jedes Feld wird bei einer Änderung einzeln gespeichert. Bei deaktiviertem JavaScript verwenden Sie die jeweilige Schaltfläche "Speichern".',
    'main_domain' => [
        'label' => 'Hauptdomain',
        'info' => 'Die Hauptdomain, auf der die Übersetzungsverarbeitung ausgeführt wird. Ändern Sie diesen Wert in der Konfigurationsdatei des Pakets.',
    ],
    'enable_multi_host' => [
        'label' => 'Koordination mehrerer Hosts aktivieren',
        'info' => 'Für ein einzelnes Projekt deaktiviert lassen. Aktivieren Sie diese Option, um laufende Aufträge zu prüfen, Übersetzungen zu exportieren und Aufträge auf beteiligten Hosts abzubrechen.',
    ],
    'domains' => [
        'label' => 'Domains',
        'info' => 'Nur bei aktivierter Koordination mehrerer Hosts erforderlich. Tragen Sie alle Domains ein, die das Übersetzungssystem gemeinsam nutzen, durch Kommas getrennt und mit http:// oder https://, zum Beispiel http://example.com,https://example.com.',
    ],
    'import_settings' => 'Importeinstellungen',
    'db_loader_text' => 'Übersetzungen aus der Datenbank laden (importieren Sie zuvor die Übersetzungen aus den Dateien)',
    'import_vendor_text' => 'Paketübersetzungen importieren (deaktivieren Sie zunächst das Laden aus der Datenbank, importieren Sie die Paketdateien und aktivieren Sie es anschließend wieder)',
    'enable_pending_translations_notifications' => 'Benachrichtigungen über ausstehende Übersetzungen aktivieren.',
    'enable_automatic_pending_translations_notifications' => 'Automatische Benachrichtigungen über ausstehende Übersetzungen aktivieren.',
    'enable_open_ai_translations' => 'Übersetzung mit OpenAI beim Ergänzen fehlender Übersetzungen aktivieren.',
    'import_only_from_root_language' => [
        'label' => 'Nur aus der Ausgangssprache importieren.',
        'info' => 'Aktivieren Sie diese Option, um nur Dateien der Ausgangssprache (:language) zu importieren.',
    ],
    'allow_deleting_languages' => [
        'label' => 'Löschen von Sprachen erlauben.',
    ],
];
