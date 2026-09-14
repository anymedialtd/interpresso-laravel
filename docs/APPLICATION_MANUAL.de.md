Dies ist eine Übersetzung des englischen Originals, das maßgeblich bleibt.

# Anwendungshandbuch

Dieses Handbuch wird in der Übersetzungsverwaltung unter der Route `interpresso.manual` angezeigt, normalerweise unter `/translator/manual`. Nutzen Sie das automatisch erzeugte Inhaltsverzeichnis zur Navigation. Auf breiten Bildschirmen bleibt es neben dem Artikel sichtbar und lässt sich innerhalb des Fensters scrollen, damit alle Abschnitte erreichbar bleiben. Die vier Arbeitsbereiche sind Sprachen, Übersetzungen, Übersetzer und Einstellungen.

Die Oberfläche richtet sich nach `app.locale` der Hostanwendung. Deutsch (`de`), Französisch (`fr`), Spanisch (`es`) und Italienisch (`it`) sind enthalten. Für die aktive Sprache wird `docs/APPLICATION_MANUAL.{locale}.md` geladen. Fehlt eine Übersetzung, wird `docs/APPLICATION_MANUAL.md` verwendet. Das englische Original bleibt maßgeblich. Übersetzte Überschriften behalten die Abschnittsanker des Originals.

## Erste Schritte {#getting-started}

### Installieren, veröffentlichen und migrieren {#install-publish-and-migrate}

Das Paket benötigt PHP ab 8.2 innerhalb der Hauptversion 8 und Laravel 12 oder 13, wie in `composer.json` angegeben. Führen Sie die Installationsbefehle im Verzeichnis Ihrer Laravel-Anwendung aus:

```bash
composer require anymedialtd/interpresso-laravel
php artisan vendor:publish --tag=interpresso-config
php artisan vendor:publish --tag=interpresso-migrations
php artisan vendor:publish --tag=interpresso-public
php artisan migrate
```

Die automatische Paketerkennung von Composer registriert die Service Provider. Die Veröffentlichung der Konfiguration umfasst `config/interpresso.php` und `config/openai.php`. Die Laufzeitressourcen werden als `public/vendor/interpresso/css/app.css` und `public/vendor/interpresso/js/app.js` veröffentlicht. Mit den optionalen Tags `interpresso-translations` und `interpresso-views` können Sie Oberflächentexte oder Vorlagen überschreiben. Das Paket lädt seine Migrationen auch direkt.

Bestehende Installationen müssen vor der Migration [die Umstellung auf den Namen Interpresso](INSTALLATION.md#adopting-the-interpresso-identity) durchführen. Die Standardtabellen verwenden jetzt `interpresso_`. Setzen Sie die Optionen `table_*` auf die bisherigen Tabellennamen, um deren Daten zu behalten. Konfiguration, PHP-Integrationen, Zeitpläne, veröffentlichte Anpassungen und alle beteiligten API-Hosts müssen gemeinsam auf den neuen Namen umgestellt werden.

Die Migrationen erstellen Tabellen für Sprachen, Übersetzungen, Übersetzer, Zuweisungen und Einstellungen sowie fehlende Tabellen für Warteschlangen, Stapel, fehlgeschlagene Aufträge und Benachrichtigungen. Legen Sie `INTERPRESSO_DB_CONNECTION` und eigene Tabellennamen in `config/interpresso.php` vor der Migration fest. Eine Sprache entsprechend `config('app.fallback_locale')` wird angelegt. Sie muss in der Liste der unterstützten Sprachen enthalten sein.

Neue Installationen aktivieren `db_loader` und deaktivieren die Koordination mehrerer Hosts. Wählen Sie vor der Bereitstellung von Übersetzungen einen Cache außerhalb der Datenbank und importieren Sie die Quelldaten wie unten beschrieben. Für Warteschlangenaktionen der Oberfläche benötigen Sie eine asynchrone Queue-Verbindung und einen Worker für die konfigurierte Paketwarteschlange, normalerweise:

```bash
php artisan queue:work --queue=languageProcessor
```

Verwenden Sie auf Shared Hosting ohne Supervisor `QUEUE_CONNECTION=database` und aktivieren Sie `interpresso.schedule.queue_worker` mit [einer Cron-Zeile](#cron-without-supervisor). Dies ist die empfohlene Einrichtung; die Schaltflächen funktionieren normal.

Cache-Speicher und Queue-Verbindung sind getrennte Einstellungen. Eine Datenbankwarteschlange kann mit einem Cache außerhalb der Datenbank verwendet werden.

### Anmelden und Standardpasswort ändern {#sign-in-and-change-the-default-password}

Die Standardadresse zur Anmeldung lautet `/translator/login`. Wenn noch kein Übersetzer vorhanden ist, legt die Migration diesen Administrator an:

- E-Mail: `admin@admin.com`
- Passwort: `aaaaaaaa`
- Vor- und Nachname: `admin`

Melden Sie sich an, öffnen Sie **Übersetzer**, bearbeiten Sie den Administrator und wählen Sie sofort **Passwort ändern**. Das Passwort muss mindestens acht Zeichen lang sein und mit der Bestätigung übereinstimmen. Eine Profiländerung allein ändert das Passwort nicht. Übersetzer-ID `1` kann über die Anwendung nicht gelöscht werden; Profil und Passwort sind jedoch bearbeitbar.

Das Anmeldeformular enthält E-Mail, Passwort und **Angemeldet bleiben**. Bei ungültigen Zugangsdaten bleibt die Anmeldeseite geöffnet. Pro IP-Adresse sind zehn Versuche je Minute erlaubt; danach wird die Wartezeit angezeigt. Konten verwenden den Session-Guard `interpresso_translator` des Pakets. Mit **Abmelden** in der Navigation beenden Sie die Sitzung.

Oberflächenrouten werden nur registriert, wenn `config('interpresso.main_server_domain')` exakt mit `config('app.url')` übereinstimmt. Die Hauptserveradresse stammt aus `INTERPRESSO_MAIN_SERVER_DOMAIN` und entspricht standardmäßig der Anwendungs-URL. `INTERPRESSO_ENABLED=false` deaktiviert die Registrierung von Oberfläche und API sowie den eigenen Übersetzungslader. Routenpräfixe und Seitenpfade sind in `config/interpresso.php` konfigurierbar.

### Erster Import {#first-import}

Die **Ausgangssprache** wird durch `config('app.locale')` bestimmt. Die **Ersatzsprache** ist `config('app.fallback_locale')`; sie ist zugleich die anfängliche Beispielsprache im Editor. Beide können voneinander abweichen.

1. Stellen Sie die Quelldateien im Sprachverzeichnis von Laravel bereit, normalerweise `lang/`. Prüfen Sie die Konfiguration der Ausgangs- und Ersatzsprache.
2. Öffnen Sie **Sprachen** und wählen Sie **Sprachen importieren**. Unterstützte Sprachverzeichnisse wie `lang/en/` und `lang/de/` werden erkannt. Eine JSON-Datei wie `lang/de.json` allein legt keine Sprache an. Verwenden Sie dafür **Sprache hinzufügen** oder erstellen Sie das Sprachverzeichnis.
3. Warten Sie auf den Abschluss, laden Sie die Sprachliste neu und wählen Sie **Übersetzungen importieren**. Importiert werden PHP- und JSON-Einträge für bereits gespeicherte Sprachen sowie Übersetzungen konfigurierter Modelle. Stellen Sie vorher die Optionen für Paketübersetzungen und den Import der Ausgangssprache ein.
4. Mit **Fehlende Übersetzungen suchen** legen Sie zu den Einträgen der Ausgangssprache entsprechende Einträge in anderen Sprachen an. Prüfen, übersetzen und geben Sie diese frei.
5. Im Datenbankmodus werden freigegebene Anwendungsübersetzungen aus der Datenbank bereitgestellt. Im Dateimodus exportieren Sie nach der Freigabe. Modellübersetzungen müssen in beiden Modi in die Spalten der Anwendungsmodelle exportiert werden.

Die entsprechenden CLI-Befehle für den ersten Import lauten:

```bash
php artisan interpresso:import-languages
php artisan interpresso:import-translations
php artisan interpresso:find-missing-translations
```

Importe ergänzen fehlende Datensätze. Ändert sich eine Quelldatei, werden vorhandene Datenbankübersetzungen nicht überschrieben. Datei- und Modellimporte markieren neue Datensätze als freigegeben und exportiert. Die Funktion zum Ergänzen fehlender Übersetzungen erzeugt dagegen nicht freigegebene, zu übersetzende und nicht exportierte Einträge, auch wenn OpenAI den Text liefert.

## Sprachen {#languages}

### Durchsuchen, suchen, hinzufügen und löschen {#browse-search-add-and-delete}

Die Liste zeigt Sprachcode, Name und Eigenbezeichnung, nach Code sortiert, mit zehn Einträgen pro Seite. **Suchen** durchsucht diese drei Felder. **Anzeigen** öffnet die Übersetzungen einer Sprache. Administratoren sehen alle Sprachen, andere Übersetzer nur ihre zugewiesenen. Bei Bedarf stehen vorherige, nächste und nummerierte Seiten zur Verfügung.

**Sprache hinzufügen** öffnet eine Auswahl der unterstützten Sprachen. Wählen Sie einen noch unbenutzten Code und klicken Sie auf **Hinzufügen**. Doppelte oder nicht unterstützte Codes werden abgewiesen. Angelegt werden ein Sprachdatensatz und, falls noch nicht vorhanden, das Sprachverzeichnis unter `lang/`, auch bei aktiviertem Datenbankmodus. Übersetzungseinträge entstehen dabei nicht. **Schließen** blendet das Formular aus.

Die Zeilenaktion **Löschen** ist nur für Administratoren sichtbar, wenn `allow_deleting_languages` aktiviert ist. Sie entfernt die Übersetzerzuweisungen und die Sprache. Zugehörige Übersetzungen werden durch den kaskadierenden Fremdschlüssel der Datenbank gelöscht. Sprachdateien und Sprachverzeichnis bleiben erhalten, sodass ein späterer Dateiimport die Sprache erneut anlegen kann. Es gibt keinen Bestätigungsdialog. Behalten Sie die Ersatzsprache, wenn ihre Werte als Übersetzungsbeispiele verfügbar bleiben sollen.

### Importieren und fehlende Einträge ergänzen {#import-and-fill-missing-entries}

Die folgenden Aktionen der Werkzeugleiste stehen Administratoren zur Verfügung:

- **Sprachen importieren** stellt die Suche nach unterstützten Sprachverzeichnissen in die Warteschlange. Vorhandene Codes werden übersprungen.
- **Übersetzungen importieren** stellt den Import von PHP, JSON, optionalen Paketübersetzungen und konfigurierten Modellen in die Warteschlange. Er umfasst die konfigurierten Sprachen, nicht nur sichtbare Suchergebnisse.
- **Fehlende Übersetzungen suchen** erstellt fehlende Datensätze anhand der gemeinsamen Übersetzungskennung im Hintergrund. Als Quelle dient die Sprache zu `app.locale`, ersatzweise die erste Sprache. Nur Kennungen dieser Ausgangssprache werden in andere Sprachen kopiert. Die Funktion durchsucht keinen Anwendungscode nach Übersetzungsaufrufen und führt auch keine Schlüssel aller Sprachen in der Ausgangssprache zusammen. Vorhandene Gegenstücke bleiben unverändert. Bei aktiviertem OpenAI werden fehlende Werte zur automatischen Übersetzung übergeben; andernfalls wird der Ausgangstext zur späteren Bearbeitung kopiert.

Der Paketimport liest veröffentlichte Anpassungen unter `lang/vendor/{namespace}/` vor dem registrierten Übersetzungsverzeichnis des Pakets. Bereits importierte Anpassungen bleiben daher erhalten. PHP-Übersetzungsdateien müssen Arrays zurückgeben, JSON-Dateien müssen sich als Arrays dekodieren lassen. Verschachtelte Schlüssel werden zur Speicherung abgeflacht. PHP-Unterverzeichnisse bleiben im Gruppennamen erhalten.

### Freigeben, exportieren und abbrechen {#approve-export-and-cancel}

**Übersetzungen aller Sprachen freigeben** stellt die Freigabe sämtlicher nicht freigegebener Einträge aller Sprachen in die Warteschlange, unabhängig von Suche und Filtern. Die Freigabe entfernt die Kennzeichnungen für Übersetzungsbedarf und Änderung, verwirft den gespeicherten alten Wert und trägt den handelnden Administrator als Freigebenden ein. Die Sammelfreigabe verlangt keinen gefüllten Wert. Prüfen Sie daher vorher fehlende Texte.

Bei deaktiviertem `db_loader` exportiert **Alle Sprachen exportieren** im Hintergrund Sprachen mit freigegebenen, nicht geänderten und noch nicht exportierten Einträgen. Exportiert werden Anwendungs- und Paketdateien sowie Modellübersetzungen. Fehlen geeignete Einträge, meldet eine Benachrichtigung, dass nichts exportiert wurde.

Bei aktiviertem `db_loader` beschränkt **Alle übersetzten Modelle exportieren** sowohl die Vorauswahl als auch jeden Exportauftrag auf Modelleinträge. PHP- und JSON-Einträge werden über die Datenbank bereitgestellt.

Nach einem erfolgreichen, nicht abgebrochenen Exportstapel aus der Oberfläche fordert die aktivierte Koordination mehrerer Hosts erzwungene Exporte auf den anderen konfigurierten Hosts an. Der normale CLI-Export leitet diese Anforderung nicht weiter.

**Laufende Stapelverarbeitung abbrechen** markiert unvollständige Paketstapel als abgebrochen und entfernt Aufträge aus der Datenbankwarteschlange des Pakets. Bei aktivierter Hostkoordination wird der Abbruch auch auf den anderen konfigurierten Hosts angefordert. Das Ergebnis nennt die betroffenen lokalen Aufträge und Stapel. Grenzen des Abbruchs finden Sie unter **Hintergrundaufträge und Warteschlangen**.

## Übersetzungen {#translations}

Öffnen Sie eine Sprache über **Sprachen > Anzeigen**. Die Standardroute ist `/translator/translations/{language}` mit dem Namen `interpresso.translations`. Übersetzer ohne Administratorrechte müssen dieser Sprache zugewiesen sein.

### Tabelle, Suche und Mehrfachfilter {#table-search-and-multi-select-filters}

Die Tabelle zeigt ID, Paketinhalt, Namensraum, Gruppe, Zu übersetzen, Freigegeben, Freigegeben von, Geändert, Geändert von, Exportiert, Schlüssel, Inhalt und Vorheriger Inhalt. Die Personen in den Änderungs- und Freigabespalten werden mit Namen angezeigt, in Filtern mit E-Mail-Adressen. Werte erscheinen als Klartext. Mit **Übersetzen** bearbeiten Sie den vollständigen Wert. Pro Seite werden zwanzig Zeilen angezeigt.

**Suchen** durchsucht ID, Namensraum, Gruppe, Schlüssel, aktuellen und vorherigen Wert. Eine übermittelte Änderung der Suche setzt die Seitennummer auf eins zurück.

Drei Menüs mit Mehrfachauswahl grenzen die Ergebnisse ein:

- **Typ**: PHP, JSON oder Modell. Modelleinträge stehen für konfigurierte Übersetzungsspalten von Eloquent-Modellen.
- **Geändert von**: als letzte Bearbeiter gespeicherte Übersetzer.
- **Freigegeben von**: als aktuelle Freigebende gespeicherte Übersetzer.

Mehrere Optionen innerhalb eines Menüs werden mit ODER verknüpft. Verschiedene Menüs, Statusfilter und Suche werden gemeinsam angewendet. Entfernen Sie alle Auswahlen eines Menüs, um dessen Einschränkung aufzuheben. Eine eigene Auswahl für Einträge ohne Bearbeiter oder Freigebenden gibt es nicht.

### Fünf Filter mit jeweils drei Zuständen {#five-three-state-filters}

Öffnen Sie **Statusfilter**. Jede Schaltfläche wechselt zwischen **grau: keine Einschränkung**, **grün: wahr**, **rot: falsch** und wieder grau. Gefiltert wird jeweils die gespeicherte boolesche Spalte:

- **Zu übersetzen** (`needs_translation`): Eine Übersetzung wurde angefordert oder ein fehlendes Gegenstück angelegt. Falsch bedeutet, dass keine Anforderung markiert ist, nicht zwingend eine Freigabe.
- **Freigegeben** (`approved`): Der Eintrag ist freigegeben. Falsch umfasst angeforderte oder fehlende Übersetzungen ebenso wie bearbeitete Texte, die auf Freigabe warten.
- **Geändert** (`updated_translation`): Ein bearbeiteter Wert wartet auf Freigabe. Dies ist eine Ablaufkennzeichnung, kein Datumsfilter und keine dauerhafte Änderungshistorie.
- **Paketinhalt** (`is_vendor`): Der Eintrag ist als Inhalt eines Pakets markiert.
- **Exportiert** (`exported`): Das Datenbankkennzeichen meldet den Eintrag als exportiert. Es prüft nicht das Dateisystem und ist für die Bereitstellung aus der Datenbank nicht erforderlich.

Für angeforderte Arbeit setzen Sie Zu übersetzen auf grün. Zur Prüfung bearbeiteter Texte setzen Sie Freigegeben auf rot und Geändert auf grün. Für reguläre Dateiexporte wählen Sie Freigegeben grün, Geändert rot und Exportiert rot. Die Filter betreffen nur die Tabelle. Sammelfreigabe und Export verwenden eigene Abfragen für die gesamte Sprache.

Die Suche wird nach einer kurzen Tipp-Pause oder mit Enter abgeschickt. Statusfilter und Mehrfachauswahlen senden GET-Formulare, laden die Seite neu und setzen die Seitennummer zurück. Die URL enthält `search`, `page`, `needs_translation`, `approved`, `updated_translation`, `is_vendor`, `exported`, `types[]`, `updatedBy[]` und `approvedBy[]`. Eine Statusschaltfläche wechselt den Parameter von nicht vorhanden zu `true`, dann `false` und wieder nicht vorhanden. Lesezeichen, Neuladen sowie Vorwärts-/Rückwärtsnavigation stellen die Auswahl wieder her. Der Filter für Sprachzuweisungen verwendet `selectedLanguages[]`.

### Beispielsprache und Übersetzungsdialog {#example-language-and-translate-modal}

Wählen Sie die **Beispielsprache**, bevor Sie **Übersetzen** für eine Zeile öffnen. Sie bestimmt die Vorlage, nicht die bearbeitete Sprache. Standard ist `app.fallback_locale`, sofern diese Sprache erlaubt ist, andernfalls die aktuelle Sprache. Fehlt das gewählte Beispiel oder ist es leer, verwendet der Editor den erlaubten Wert der Ersatzsprache.

Der Dialog zeigt Übersetzungskennung, Beispieltext, ein Textfeld mit dem aktuellen Wert und ausklappbare Sprachcodes für verfügbare Beispiele derselben Kennung. Auswahl, Beispiele und Ersatzsprachensuche sind bei Nicht-Administratoren auf zugewiesene Sprachen begrenzt. Beispieltexte werden maskiert und nicht als HTML ausgegeben.

Mit **Dialog schließen**, Escape oder einem Klick außerhalb schließen Sie den Editor, ohne Änderungen im Textfeld zu speichern. Der Fokus kehrt zur Schaltfläche Übersetzen zurück.

### Aktualisieren und OpenAI-Aktionen {#update-and-openai-actions}

**Übersetzung aktualisieren** speichert nur, wenn der Text vom gespeicherten Wert abweicht. Beim Beginn einer Bearbeitung werden der vorherige Wert sowie Bearbeiter und Freigebender gesichert. Der handelnde Übersetzer wird als Bearbeiter eingetragen, der aktuelle Freigebende entfernt und der Eintrag als geändert, nicht freigegeben und nicht exportiert markiert. Zu übersetzen wird ebenfalls entfernt. Weitere Änderungen vor der Freigabe behalten denselben ursprünglichen Wert. Speichern führt weder automatisch zur Freigabe noch zum Export.

**Mit OPENAI übersetzen** erscheint, wenn OpenAI aktiviert ist und ein nicht leeres, erlaubtes Beispiel vorliegt. Als Quelle dient dessen tatsächliche Sprache, auch nach dem Rückgriff auf die Ersatzsprache. Ein Vorschlag füllt ein noch unverändertes Textfeld, ohne zu speichern. Haben Sie den Entwurf bereits bearbeitet oder ändern Sie ihn während der Anfrage, erscheint der Vorschlag separat mit **Vorschlag übernehmen**. Ihr Entwurf bleibt bestehen, bis Sie ihn ausdrücklich ersetzen. Prüfen Sie den Text und speichern Sie mit **Übersetzung aktualisieren**. Während des Ladens und bei Fehlern bleibt der Editor nutzbar. Das Schließen bricht eine noch laufende Anfrage ab.

Eine leere oder fehlerhafte Serverantwort oder ein Vorschlag ohne Textwert führt zu einer Fehlermeldung; Ihr Entwurf bleibt erhalten. Sie können erneut einen Vorschlag anfordern oder Ihren eigenen Text speichern.

**Aktualisieren und andere Sprachen automatisch übersetzen (OPENAI)** steht nur Administratoren zur Verfügung, wenn OpenAI aktiv und die aktuelle Sprache die Ausgangssprache ist. Bei geändertem Wert wird diese Bearbeitung einmal gespeichert und die Aktualisierung vorhandener Übersetzungen derselben Kennung in anderen Sprachen eingereiht. Fehlende Gegenstücke werden nicht angelegt; verwenden Sie vorher Fehlende Übersetzungen suchen. Die Ergebnisse bleiben nicht freigegeben und nicht exportiert.

OpenAI ist optional und benötigt `openai-php/laravel`, gültige API-Einstellungen in `config/openai.php` und die aktivierte Funktion. Das Paket sendet den Ausgangstext an OpenAI und fordert die Beibehaltung von Laravel-Platzhaltern wie `:name`. Prüfen Sie das Ergebnis. `interpresso.open_ai_model` bestimmt das angeforderte Modell; `interpresso.max_open_ai_missing_trans` die Größe der Anfrageblöcke für fehlende Übersetzungen. Fehlgeschlagene Anfragen, eine fehlende Integration sowie Antworten mit fehlenden oder zusätzlichen Schlüsseln oder Werten, die keine Zeichenketten sind, lassen die ursprüngliche Eingabe des Dienstes unverändert. Im Dialog ist das der Beispieltext. Ein unveränderter Ausgangssatz belegt daher keine erfolgreiche Übersetzung. Fehlende Sprachen oder eine Null-Eingabe werden ohne Anfrage unverändert zurückgegeben. Array-Anfragen behalten leere Zeichenketten und `"0"`.

### Freigeben, anfordern, Anforderung zurücknehmen und wiederherstellen {#approve-request-remove-request-and-restore}

Administratoren sehen folgende Zeilenaktionen:

- **Freigeben** erscheint für einen nicht freigegebenen Eintrag mit nicht leerem aktuellem Wert. Die Aktion übernimmt den Text, erfasst den Freigebenden, entfernt Zu übersetzen und Geändert, löscht Vorheriger Inhalt und die gespeicherten früheren Bearbeiter-/Freigabereferenzen und invalidiert Übersetzungscaches. Sie schreibt keine Dateien und setzt Exportiert nicht auf wahr. Die Zeichenkette `"0"` ist gültig. Bei leerem Wert fehlt die Schaltfläche; die Sammelfreigabe prüft das nicht.
- **Übersetzung anfordern** erscheint, wenn Zu übersetzen falsch ist. Die Aktion setzt Zu übersetzen auf wahr und Freigegeben auf falsch. Sie sichert keinen vorherigen Wert und verschickt selbst keine E-Mail.
- **Übersetzungsanfrage zurücknehmen** erscheint, wenn Zu übersetzen wahr ist. Die Aktion setzt Zu übersetzen auf falsch und Freigegeben auf wahr. Andere Kennzeichen bleiben unverändert; dies entspricht nicht der vollständigen Freigabeaktion.
- **Wiederherstellen** erscheint für einen nicht freigegebenen Eintrag mit gespeichertem Vorherigem Inhalt, auch bei leerer Zeichenkette oder `"0"`. Die Aktion stellt den Wert sowie den früheren Bearbeiter und Freigebenden wieder her, entfernt gespeicherte Historie und Geändert und markiert den Eintrag als freigegeben und exportiert. Sie schreibt keine Dateien und entfernt Zu übersetzen nicht ausdrücklich. Es gibt nur einen gespeicherten alten Wert, keine Versionshistorie. Sobald eine Freigabe ihn entfernt, ist Wiederherstellen nicht mehr verfügbar.

### Eine gesamte Sprache freigeben und exportieren {#approve-and-export-a-whole-language}

**Übersetzungen ({Sprachcode}) freigeben** stellt alle nicht freigegebenen Einträge der aktuellen Sprache zur Freigabe in die Warteschlange, auch außerhalb der Filter und mit leeren Werten.

Im Dateimodus exportiert **Sprache exportieren** freigegebene Einträge mit Geändert falsch und Exportiert falsch im Hintergrund, einschließlich geeigneter Modellübersetzungen. Im Datenbankmodus beschränkt **Übersetzte Modelle exportieren** sowohl die Eignungsprüfung als auch den Auftrag auf Modelleinträge. Beide Schaltflächen erzwingen keinen erneuten Export bereits exportierter Einträge. Verwenden Sie dafür die CLI-Option `--force=1`. Die Aktionen stehen Administratoren zur Verfügung.

## Übersetzer {#translators}

### Konten durchsuchen und filtern {#browse-and-filter-accounts}

Dieser Bereich, normalerweise `/translator/translators`, ist Administratoren vorbehalten. Er zeigt ID, Vorname, Nachname, E-Mail, Telefon, Administratorstatus und zugewiesene Sprachnamen mit zehn Konten pro Seite.

**Suchen** durchsucht ID, Vorname, Nachname, E-Mail und Telefon. Die Sprachauswahl zeigt Übersetzer, denen **jede ausgewählte Sprache** zugewiesen ist. Leeren der Auswahl entfernt die Einschränkung. Der automatische Zugriff eines Administrators auf alle Sprachen erzeugt keine Zuweisungen. Ein Zuweisungsfilter kann deshalb auch Administratoren ausschließen.

### Anlegen, bearbeiten, Sprachen zuweisen und löschen {#create-edit-assign-languages-and-delete}

Öffnen Sie das Anlageformular und geben Sie E-Mail, Telefon, Vorname, Nachname, Passwort, Passwortbestätigung, Sprachzuweisungen und Administratorrechte an. Die E-Mail-Adresse muss gültig und eindeutig sein; Vor- und Nachname benötigen mindestens zwei Zeichen. Telefon ist optional, muss aber bei Angabe eindeutig sein. Passwörter müssen mindestens acht Zeichen lang sein und mit der Bestätigung übereinstimmen.

Ein Konto ohne Administratorrechte benötigt mindestens eine gültige Sprachzuweisung. Doppelte oder nicht vorhandene Zuweisungs-IDs werden abgewiesen. Administratoren können ohne Zuweisungen gespeichert werden und auf alle Sprachen zugreifen. Explizite Zuweisungen bestimmen weiterhin, für welche Sprachen ein Konto Benachrichtigungen erhält.

Schließen Sie nach der Auswahl das Zuweisungsmenü mit Sprachen und senden Sie das Formular ab. Validierungsfehler erscheinen neben den Feldern, auch bei Sprachzuweisungen und Passwortbestätigungen. Abgewiesene Formulare behalten Profilwerte; Passwörter müssen neu eingegeben werden.

**Anlegen** speichert ein neues Konto. **Bearbeiten** öffnet ein vorhandenes; **Aktualisieren** speichert Profil, Administratorstatus und ausgewählte Zuweisungen. Die bisherige Zuweisungsliste wird dabei ersetzt. **Schließen** blendet das Formular aus. Die Kontoanlage verschickt weder eine Einladung noch eine Passwort-E-Mail.

Mit **Löschen** in der Zeile entfernen Sie ein Konto. Für Übersetzer-ID `1` fehlt die Aktion, und der Löschendpunkt weist den Versuch ab. Andere Löschungen erfolgen direkt ohne Bestätigungsdialog.

### Passwortänderungen und Erinnerungen an ausstehende Übersetzungen {#password-changes-and-pending-notifications}

Beim Bearbeiten eines Kontos öffnet **Passwort ändern** ein separates Formular. Geben Sie ein neues Passwort und die passende Bestätigung ein und betätigen Sie dessen Schaltfläche. **Schließen** führt zum Profilformular zurück. Diese administrative Änderung verlangt kein aktuelles Passwort. Für Nicht-Administratoren gibt es keine eigene Seite zum Ändern oder Zurücksetzen des Passworts.

Bei aktiviertem `enable_pending_notifications` zeigt das Bearbeitungsformular eine Schaltfläche für Erinnerungen an ausstehende Übersetzungen. Jede ausdrücklich zugewiesene Sprache wird geprüft. Enthält sie Einträge mit `needs_translation=true`, wird eine Benachrichtigung für E-Mail und Datenbank eingereiht. Es werden nicht alle nicht freigegebenen Einträge gezählt. Ohne angeforderte Übersetzungen wird für die jeweilige Zuweisung nichts versendet. Konfigurieren Sie den Mailtransport und starten Sie den Paket-Worker. Die Erfolgsmeldung bestätigt die Anforderung, nicht die Zustellung der E-Mail.

Automatische Erinnerungen verwenden einen eigenen Befehl und Schalter, beschrieben in der CLI-Referenz. Keine der beiden Einstellungen verschickt Einladungen, ändert Zuweisungen oder gibt Übersetzungen frei.

## Einstellungen {#settings}

Die Einstellungen unter normalerweise `/translator/settings` sind Administratoren vorbehalten. Angezeigt werden die konfigurierte Hauptserverdomain als nicht bearbeitbarer Text, acht Schalter und das Feld Domains. Ändern Sie den Hauptserver über `INTERPRESSO_MAIN_SERVER_DOMAIN` oder die Anwendungskonfiguration.

Jedes Feld besitzt ein eigenes POST-Formular mit Validierung. Mit JavaScript wird beim Umschalten oder Verlassen des Domains-Felds nur dieses Feld übermittelt und die Seite neu geladen. Ohne JavaScript verwenden Sie die jeweilige Schaltfläche Speichern. Ein ungültiges Feld verhindert nicht das Speichern anderer gültiger Felder. Nach jedem erfolgreichen Schreibvorgang aktualisiert der Server den Einstellungscache. Bei Fehlern bleibt der eingegebene Wert zur Korrektur erhalten; erneutes Neuladen zeigt den gespeicherten Zustand. Speichern Sie Domains vor dem Aktivieren der Hostkoordination.

### Einstellungsreferenz {#settings-reference}

Die folgenden neun Spalten sind bearbeitbar. Die Standardwerte gelten für Neuinstallationen, nicht für gespeicherte Einstellungen nach einem Upgrade.

- **`db_loader`**, Standard `true`: Wählt den Datenbanklader anstelle des normalen Laravel-Dateiladers. Freigegebene Anwendungsübersetzungen werden direkt aus der Datenbank bereitgestellt, ohne regelmäßigen Dateiexport. Deaktivieren Sie die Option, wenn Sprachdateien zur Laufzeit benötigt werden. Oberfläche und normaler CLI-Export exportieren bei aktivierter Option nur Modelle; beachten Sie die Ausnahmen für erzwungene Exporte. Bestehende Installationen behalten ihren gespeicherten Wahr-/Falsch-Wert bei Änderungen des Standards.
- **`import_vendor`**, Standard `false`: Bezieht registrierte Paketnamensräume in den Import ein. Bei Datenbankmodus und deaktivierter Option verwenden Paketübersetzungen weiterhin den übergeordneten Dateilader. Aktivieren bewirkt, dass auch diese Namensräume die Datenbank nutzen. Importieren Sie sie deshalb zuvor. Verwenden Sie diese Option, wenn Übersetzer Pakettexte verwalten sollen. Deaktivieren löscht keine importierten Paketeinträge und schließt vorhandene Paketzeilen nicht vom Dateiexport aus.
- **`enable_open_ai_translations`**, Standard `false`: Aktiviert OpenAI-Aufrufe für fehlende Übersetzungen und Editoraktionen und zeigt die entsprechenden Schaltflächen. Konfigurieren Sie zuerst die optionale Integration. Die Option übersetzt nicht automatisch alle vorhandenen Zeilen und gibt erzeugte Texte nicht frei. Ohne Integration gibt der Dienst weiterhin seine Eingabe zurück.
- **`enable_pending_notifications`**, Standard `false`: Zeigt die manuelle Erinnerungsaktion im Übersetzerformular. Damit können Administratoren Erinnerungen für einzelne Konten anfordern. Die Option richtet keinen Zeitplan ein und wird vom automatischen Benachrichtigungsbefehl nicht geprüft.
- **`enable_automatic_pending_notifications`**, Standard `false`: Erlaubt dem automatischen Erinnerungsbefehl, die expliziten Zuweisungen aller Übersetzer zu durchlaufen. Verwenden Sie einen eigenen Zeitplan oder aktivieren Sie `interpresso.schedule.pending_notifications`. Die Option arbeitet unabhängig von `enable_pending_notifications`; der Befehl prüft die gespeicherte Einstellung bei Ausführung.
- **`import_only_from_root_language`**, Standard `false`: Beschränkt Übersetzungsimporte einschließlich Modell- und Paketimport auf die Sprache zu `app.locale`. Verwenden Sie dies, wenn die Ausgangsquellen maßgeblich sind und andere Sprachen in der Verwaltung gepflegt werden. Sprachen importieren wird nicht eingeschränkt; vorhandene Einträge anderer Sprachen werden nicht gelöscht. Fehlende Übersetzungen suchen kann anschließend entsprechende Einträge in anderen Sprachen anlegen.
- **`allow_deleting_languages`**, Standard `false`: Zeigt Administratoren die Löschaktionen für Sprachen. Aktivieren Sie dies, wenn Sprachdatensätze samt Übersetzungen entfernt werden sollen. Der Endpunkt prüft Administratorrechte und diesen Schalter.
- **`enable_multi_host`**, Standard `false`: Ergänzt Auftragsprüfungen auf konfigurierten Hosts und ermöglicht die Weiterleitung von Export und Abbruch aus der Oberfläche. Für ein einzelnes Projekt bleibt es deaktiviert. Gespeicherte Domains lösen dann keine solchen Anfragen aus. Die Aktivierung in den Einstellungen verlangt ein gefülltes Domains-Feld. Bestehende nicht leere Domainlisten werden durch die Upgrade-Migration aktiviert; eine reine Umgebungskonfiguration der Hosts aktiviert die Funktion nicht.
- **`domains`**, Standard `null`: Kommagetrennte Installations-URLs für Hostanfragen. Geben Sie `http://` oder `https://` an, beispielsweise `https://one.example,https://two.example`, ohne abschließenden Schrägstrich. Der Code entfernt umgebende Leerzeichen und hängt API-Pfade an. Tragen Sie nur beteiligte Installationen ein. Bei gespeichertem Null-Wert dient `INTERPRESSO_MULTIPLE_DB_HOSTS` als Ersatz; eine gespeicherte leere Zeichenkette verwendet diesen Ersatz nicht. Deaktivieren Sie die Hostkoordination, bevor Sie das Feld leeren. Die Validierung verlangt bei aktivierter Funktion einen Wert, prüft aber weder URL-Syntax noch Erreichbarkeit.

Die Tabelle enthält außerdem die internen Felder `process_running`, `process_owner`, `process_started_at` und `process_expires_at` sowie ID und Zeitstempel. Sie sind keine Bedienelemente der Einstellungen. Der Controller weist Änderungen außerhalb der neun genannten Spalten ab. Eine zeitlich begrenzte Prozesssperre blockiert nur, solange sie aktiv und nicht abgelaufen ist. Die Migration ergänzt optionale Metadaten, ohne vorhandene Einstellungen als gesperrt zu markieren.

## Bereitstellung von Übersetzungen {#how-translations-are-served}

### Datenbankmodus {#database-mode}

`db_loader=true` ist der Standard für neue Installationen. Der anfängliche Einstellungsdatensatz und der Spaltenstandard sind wahr. Die Upgrade-Migration ändert den Standard und leert die zwischengespeicherte Laderauswahl, ohne vorhandene Datensätze zu überschreiben. Ein Rollback stellt den falschen Standard wieder her, ohne gespeicherte Einstellungen zu ändern.

Der Laravel-Übersetzungslader liest zwischengespeicherte Datenbankeinträge für Sprache, Gruppe und Namensraum. Für freigegebene Zeilen verwendet er `value`, für nicht freigegebene `old_value`. Der Entwurf wird erst nach Freigabe verwendet. Ein neu erzeugter, nicht freigegebener Eintrag kann ohne alten Wert sein; dafür liefert der Datenbanklader keinen freigegebenen Text. Eine Übersetzungsanforderung für einen freigegebenen Eintrag legt keinen alten Wert an, sodass auch dieser während der fehlenden Freigabe ohne nutzbaren Text bleiben kann. Paketnamensräume verwenden Dateien, wenn `import_vendor` deaktiviert ist.

Validierungsmeldungen behalten die integrierten Laravel-Standardtexte und Anpassungen der Hostanwendung aus `validation.php`, auch vor dem Übersetzungsimport. Datenbankeinträge für Validierung überschreiben diese Vorgaben nach denselben Freigabe-/Altwertregeln. Andere Anwendungsgruppen bleiben ausschließlich datenbankgestützt.

Auch die paketinternen oder veröffentlichten Texte der Oberfläche bleiben bei aktiviertem Paketimport verfügbar. Geprüfte Datenbankeinträge können sie überschreiben.

Das Laden aus der Datenbank schreibt nichts nach `lang/`. Im normalen Ablauf importieren Sie einmal, bearbeiten und geben frei. Es gibt keine exportierten Anwendungsdateien, die bei einer Bereitstellung überschrieben werden könnten. **`interpresso:export-translations-deployment` ist für den Datenbankmodus unnötig.** Die Übersetzungen bleiben bei Dateisystembereitstellungen in der Datenbank erhalten. Modellübersetzungen müssen weiterhin in ihre JSON-Spalten der Anwendungsdatenbank exportiert werden.

Die Einstellung verbietet nicht sämtliche Dateischreibvorgänge: Sprache hinzufügen erstellt ein Verzeichnis. Der Bereitstellungsbefehl und der Endpunkt für erzwungene Exporte zwischen Hosts setzen die Beschränkung auf Modelle nicht und können auch bei aktiviertem `db_loader` Dateien schreiben.

**Im Datenbankmodus ist eine funktionierende Datenbank für die Darstellung von Webanfragen zwingend erforderlich.** Datenbankfehler werden bei der Registrierung des Webladers und bei nicht zwischengespeicherten Abfragen weitergegeben. Der Rückfall auf Laravels Dateilader bei Datenbankausnahmen existiert nur bei der Laderregistrierung unter `runningInConsole()`. Er deckt weder spätere Konsolenabfragen noch einen automatischen Web-Rückfall bei Ausfällen ab. Cacheeinträge können einzelne Abfragen beantworten, ersetzen jedoch keine funktionierende Datenbank vollständig.

### Dateimodus {#file-mode}

Bei `db_loader=false` nutzt Laravel seinen Dateilader für Übersetzungen zur Laufzeit. Die Paketdatenbank speichert weiterhin Bearbeitungen und Prüfstatus. Exportieren Sie nach der Freigabe PHP-/JSON-Übersetzungen nach `lang/` und Modellübersetzungen in die Modellspalten. Ein normaler Export verlangt Freigegeben wahr, Geändert falsch und Exportiert falsch. Ein erzwungener Export ignoriert nur Exportiert.

PHP-Anwendungsexporte gehen nach `lang/{locale}/{group}.php`, JSON nach `lang/{locale}.json` und Paketanpassungen nach `lang/vendor/{namespace}/`. Geeignete Schlüssel werden mit vorhandenen Inhalten zusammengeführt. PHP-Punktschlüssel werden als verschachtelte Arrays geschrieben: `a.b` wird zu `['a' => ['b' => 'text']]`. Entsprechende wörtliche Punktschlüssel älterer PHP-Exporte werden beim Neuschreiben entfernt, andere Einträge bleiben erhalten. JSON behält wörtliche Schlüssel. Mit einem erzwungenen Export im Dateimodus schreiben Sie bereits als exportiert markierte, freigegebene Einträge neu.

Der Dateimodus liefert Sprachdateien zur Laufzeit, die in ein Bereitstellungsartefakt aufgenommen werden können. Sie müssen mit der Übersetzungsdatenbank synchron bleiben. Ersetzt eine Bereitstellung die Dateien, kann der aktuelle Text verloren gehen, bis ein erzwungener Export ihn wiederherstellt. Der Datenbankmodus vermeidet diesen Schritt, benötigt aber Datenbank und Cache für die Auslieferung. Die Übersetzungsverwaltung selbst bleibt auch im Dateimodus datenbankabhängig.

### Cache und Modellintegration {#cache-and-model-integration}

**`CACHE_DRIVER` darf im Datenbankmodus nicht `database` sein.** Verwendet die Cachekonfiguration `CACHE_STORE`, gilt dort dieselbe Einschränkung. Nutzen Sie Redis oder Memcached, auf einem einzelnen Server auch Dateicache. Webprozesse und Queue-Worker benötigen denselben Cache-Speicher und dasselbe Präfix. Hosts mit gemeinsamen Übersetzungen sollten Redis oder Memcached teilen, damit Versionsänderungen alle Hosts erreichen.

Übersetzungscaches haben keine TTL. Ihre Schlüssel enthalten eine gemeinsame Version, die nach dem Commit von Übersetzungsschreibvorgängen erhöht wird, einschließlich Paket-Sammelimporten, Freigaben, Exporten und Löschungen. Gezielte Invalidierung bleibt bestehen. Anwendungscode, der bei Sammelschreibvorgängen Modellereignisse umgeht, muss nach erfolgreichem Schreiben `Translation::invalidateCacheAfterWrite()` aufrufen, damit die Invalidierung nach dem Commit erfolgt. Rohes SQL ohne diesen Aufruf kann veraltete Cachewerte hinterlassen.

Konfigurieren Sie Modellklassen in `interpresso.translatable_models`. Jedes Modell muss seine übersetzbaren Spalten angeben, beispielsweise `public array $translatable = ['name'];`. Diese Spalten müssen JSON-Objekte mit Sprachcodes als Schlüsseln enthalten. Der Import kopiert vorhandene Sprachwerte in Modelleinträge. Der Export aktualisiert die jeweilige Sprache innerhalb der vorhandenen Modellspalte und markiert die Übersetzung als exportiert. Zielmodell und Spalte müssen weiterhin existieren. Eine Freigabe in der Verwaltung allein ändert keine Modelldaten der Anwendung.

## Einzelprojekt und mehrere Hosts {#single-project-and-multi-host}

### Betrieb als Einzelprojekt {#single-project-operation}

Ein einzelnes Projekt ist der Standard. Lassen Sie `enable_multi_host` deaktiviert und Domains leer. Für die normale Oberfläche ist kein gemeinsames Host-Geheimnis erforderlich. Importe, fehlende Übersetzungen, Freigaben und normale Exporte prüfen lokale Paketaufträge und Stapel. Export und Abbruch bleiben lokal, selbst wenn eine alte Domainliste gespeichert ist.

### Beteiligte Hosts konfigurieren {#configure-participating-hosts}

Verwenden Sie die Hostkoordination für Installationen, deren Verarbeitung und Exporte abgestimmt werden müssen, meist mit derselben Übersetzungsdatenbank über `INTERPRESSO_DB_CONNECTION`. Die Koordination repliziert keine getrennten Übersetzungsdatenbanken.

1. Verbinden Sie die Installationen mit der vorgesehenen Übersetzungsdatenbank und konfigurieren Sie ihren Zugriff auf Warteschlange und Cache.
2. Setzen Sie auf allen beteiligten Hosts dasselbe `INTERPRESSO_API_SHARED_SECRET`. Es liefert `interpresso.api_shared_api_key`; ausgehende Anfragen senden es als `api_key`.
3. Speichern Sie die Installations-URLs einschließlich Protokoll unter **Einstellungen > Domains**.
4. Aktivieren Sie **Koordination mehrerer Hosts aktivieren**.
5. Halten Sie `INTERPRESSO_MAIN_SERVER_DOMAIN` mit der Hauptinstallation der Oberfläche konsistent. Nur die Installation, deren `app.url` diesem Wert entspricht, registriert die Webrouten der Verwaltung. API-Routen bleiben auf aktivierten Installationen registriert.

Bei aktivierter Auftragsprüfung werden andere gelistete Hosts nach laufender Arbeit gefragt. Gemeldete aktive Arbeit blockiert die Aktion. Unerreichbare Hosts und erfolglose Antworten werden ignoriert. Dies ist weder eine verteilte Sperre noch ein Beleg, dass ein unerreichbarer Host untätig ist. Nach UI-Exporten werden erzwungene Exporte bei anderen Hosts angefordert; ein Abbruch wird an deren Abbruchendpunkte gesendet. URLs, die exakt dem Protokoll und Host der aktuellen Anfrage entsprechen, werden übersprungen.

### API zwischen Hosts {#inter-host-api}

Das Paket stellt unabhängig vom Präfix der Verwaltung folgende POST-Endpunkte unter `/api` bereit:

- `/api/interpresso-has-jobs-running`: Meldet, ob lokale Paketaufträge oder unvollständige, nicht abgebrochene Stapel vorliegen.
- `/api/cancelJobs`: Bricht lokale Paketstapel ab und löscht lokale Aufträge der Paket-Datenbankwarteschlange.
- `/api/interpresso-force-export`: Benötigt eine aufschiebende Warteschlange, erwirbt eine lokale Prozesssperre und reiht einen erzwungenen Export für jede Sprache ein. Ungeeignete Verbindungen liefern vor Beginn HTTP 503 mit einem CLI-Ersatz. Bei belegter lokaler Verarbeitung wird HTTP 409 mit Eigentümer-, Start- und Ablaufdaten geliefert. Sperren anderer Hosts werden nicht geprüft, da der aufrufende Host seinen eigenen Export noch abschließt. Der Endpunkt kann auch im Datenbankmodus Dateien schreiben.
- `/api/interpresso-get-languages`: Liefert Sprachdatensätze für den Entwickler-Download.
- `/api/interpresso-get-paginated-translations`: Liefert Übersetzungen seitenweise mit 500 Datensätzen für den Entwickler-Download.

Alle verlangen eine nicht leere Zeichenkette `api_key`, die dem gemeinsamen Geheimnis entspricht. **Ohne konfiguriertes Geheimnis verweigert die API den Zugriff mit HTTP 503.** Die Anfragevalidierung erfolgt zuerst: Ein fehlender oder falsch typisierter `api_key` erhält HTTP 422. Bei konfiguriertem Geheimnis führt ein abweichender Schlüssel zu HTTP 401. Der öffentliche GET-Endpunkt `/api/version` meldet nur die Paketversion und verwendet diese Authentifizierungsmiddleware nicht.

Das Abschalten der Hostkoordination beendet automatische ausgehende Koordinationsanfragen. Es entfernt oder deaktiviert diese authentifizierten API-Endpunkte nicht. Auch der Entwickler-Download verwendet die API unabhängig vom Schalter.

## Hintergrundaufträge und Warteschlangen {#background-jobs-and-queues}

### Was im Hintergrund läuft {#what-runs-in-the-background}

Die Oberfläche reiht Sprach- und Übersetzungsimporte, das Ergänzen fehlender Übersetzungen, Sammelfreigaben, Exporte und Aktualisieren mit automatischer Übersetzung in Laravel-Stapel ein. Beim Ergänzen fehlender Übersetzungen werden weitere Aufträge hinzugefügt, sobald Arbeit gefunden wird. Aufträge verwenden `interpresso.queue_name` (Standard `languageProcessor`), Stapel `interpresso.batch_name` (`languageBatch`). Erinnerungen und Abschlussbenachrichtigungen für Administratoren verwenden ebenfalls diese Warteschlange.

Lang dauernde Übersetzungsarbeit wird nie innerhalb einer HTTP-Anfrage ausgeführt, auch nicht nach dem Senden der Antwort. Vor dem Anlegen eines Stapels oder dem Erwerb einer Sperre prüfen Oberfläche und Host-Exportendpunkt `queue.default` und `queue.connections.<connection>.driver`. Verbindungsaliase werden unterstützt. `sync`, `null`, fehlende Konfiguration und Laravels Treiber `deferred` werden abgewiesen. Failover wird abgewiesen, wenn eine Ersatzverbindung ungeeignet oder zyklisch ist. Ein konfigurierter asynchroner Treiber beweist nicht, dass ein Worker läuft. Eingereihte Aufträge warten, bis sie abgeholt werden.

Wählen Sie einen dieser drei unterstützten Betriebsmodi:

1. **Supervisor / dauerhaft laufender Worker: beste Wahl für Verarbeitung in Echtzeit.** Setzen Sie `QUEUE_CONNECTION=database` (oder `redis`) und überwachen Sie einen Worker für `languageProcessor` oder Ihren `interpresso.queue_name`. Beispiel: `php -d max_execution_time=0 artisan queue:work --queue=languageProcessor --timeout=900 --tries=1`. Setzen Sie `retry_after` der Verbindung über das Zeitlimit pro Auftrag, etwa auf 960 Sekunden, und `INTERPRESSO_PROCESS_LOCK_TTL=1800`. Bei SQS konfigurieren Sie das entsprechende Sichtbarkeitslimit. Bemessen Sie die Grenzen nach längstem Auftrag und Wartezeit; prüfen Sie bei Fehlern `failed_jobs` und Anwendungsprotokolle.
2. **Ohne Supervisor: empfohlen für Shared Hosting.** Setzen Sie `QUEUE_CONNECTION=database`, aktivieren Sie `interpresso.schedule.queue_worker` und ergänzen Sie die einzelne minütliche Cron-Zeile unten. Die Schaltflächen funktionieren normal. Cron startet einen zeitlich begrenzten Worker und verarbeitet bereitstehende Aufträge innerhalb einer Minute; lange Aufträge und Rückstände können weitere Durchläufe benötigen. Supervisor und ein dauerhaft laufender Worker sind dafür nicht erforderlich.
3. **Sync: nur für kleine Installationen.** Mit `QUEUE_CONNECTION=sync` können Sie die Oberfläche zum Durchsuchen sowie für einzelne Änderungen und Prüfungen verwenden. Lange Vorgänge werden abgewiesen und der passende Artisan-Befehl wird genannt. Führen Sie diesen manuell in der CLI aus. Speicher- und Zeitlimits der PHP-CLI sowie Hostinglimits gelten weiterhin. Auch kleine Installationen führen keine Sammelarbeit innerhalb von HTTP-Anfragen aus.

Erstellen Sie nach Änderungen der Queue-Konfiguration gegebenenfalls den Konfigurationscache neu (`php artisan config:cache`) und starten Sie dauerhaft laufende Worker neu. Mit `null` werden eingereihte Benachrichtigungen verworfen.

Eine abgewiesene Sammelaktion nennt den genauen CLI-Ersatz, etwa `php artisan interpresso:import-translations`, und erklärt, wie eine Datenbankwarteschlange mit Scheduler die Schaltfläche nutzbar macht. Es werden keine Daten geschrieben, kein Stapel gestartet und keine Prozesssperre erworben. Aktualisieren mit automatischer Übersetzung zeigt dieselben Einrichtungshinweise vor dem Speichern des Ausgangsentwurfs. Einzelne Zeilen lassen sich weiterhin bearbeiten und freigeben.

| Oberflächen-/API-Aktion | CLI-Ersatz |
| --- | --- |
| Sprachen importieren | `php artisan interpresso:import-languages` |
| Übersetzungen importieren | `php artisan interpresso:import-translations` |
| Fehlende Übersetzungen suchen | `php artisan interpresso:find-missing-translations` |
| Alle Sprachen freigeben | `php artisan interpresso:approve-translations --translator=1` |
| Eine Sprache freigeben | `php artisan interpresso:approve-translations --translator=1 --language=en` |
| Alle Sprachen exportieren | `php artisan interpresso:export-translations` |
| Eine Sprache exportieren | `php artisan interpresso:export-translations --language=en` |
| Modelle exportieren | Dem passenden Exportbefehl `--only-models` hinzufügen |
| Erzwungener Export über Host-API | `php artisan interpresso:export-translations-deployment` |

Die Freigabemeldung enthält die tatsächliche ID des angemeldeten Administrators, sprachbezogene Meldungen den gewählten Sprachcode. Die authentifizierte Host-API liefert HTTP **503** mit einer JSON-`message`, die den Befehl zum erzwungenen Export enthält, wenn die Verbindung Arbeit nicht aufschieben kann. `interpresso:export-translations-deployment` entspricht der Host-API, indem es auch im Datenbankmodus Dateien und Modelle neu schreibt. Der normale Befehl mit `--force=1` berücksichtigt den Datenbankmodus. Jeder empfangende Host benötigt eine aufschiebende Verbindung und einen Worker. Eine unterstützte Warteschlange mit aktiver Prozesssperre liefert weiterhin **409**.

### Cron ohne Supervisor {#cron-without-supervisor}

**Dies ist die empfohlene Einrichtung für Shared Hosting.** Aktivieren Sie den Worker-Zeitplan in der Umgebung der Hostanwendung und verwenden Sie einen persistenten Cache:

```dotenv
QUEUE_CONNECTION=database
INTERPRESSO_SCHEDULE_QUEUE_WORKER=true
CACHE_STORE=file
```

Damit wird `interpresso.schedule.queue_worker` aktiviert; der Standard ist `false`. Ergänzen Sie bei Upgrades fehlende Optionen in der veröffentlichten Konfiguration, ohne lokale Einstellungen zu überschreiben. Führen Sie bei fehlenden Queue-/Stapeltabellen `php artisan migrate` aus und erneuern Sie den Konfigurationscache mit `php artisan config:cache`. Ergänzen Sie genau eine Cron-Zeile und passen Sie Anwendungspfad und PHP-Programm an:

```cron
* * * * * cd /path && php artisan schedule:run >> /dev/null 2>&1
```

Der Cron-Benutzer muss PHP-CLI und Hintergrundprozesse ausführen sowie Speicher- und Exportpfade beschreiben können. Prüfen Sie den registrierten Zeitplan und testen Sie einen Durchlauf manuell:

```bash
php artisan schedule:list
php artisan interpresso:work
```

`interpresso:work` ruft `queue:work` für `interpresso.queue_name` auf der Standardverbindung auf, mit `--stop-when-empty`, `--max-time=50`, `--max-jobs=100`, `--memory=128`, `--timeout=60`, `--sleep=0` und `--tries=1`. Bei leerer Warteschlange endet der Befehl sofort mit 0. Nach Erreichen einer Grenze verbleibende Arbeit folgt im nächsten Durchlauf; verzögerte oder reservierte Aufträge bleiben für spätere Durchläufe erhalten.

Konfigurieren Sie `interpresso.queue_worker.max_time` (Sekunden), `interpresso.queue_worker.max_jobs`, `interpresso.queue_worker.memory` (MB) und `interpresso.queue_worker.timeout` (Sekunden pro Auftrag). Die Umgebungsvariablen lauten `INTERPRESSO_QUEUE_WORKER_MAX_TIME`, `INTERPRESSO_QUEUE_WORKER_MAX_JOBS`, `INTERPRESSO_QUEUE_WORKER_MEMORY` und `INTERPRESSO_QUEUE_WORKER_TIMEOUT`. Alle Werte müssen positive ganze Zahlen sein; null/unbegrenzt und ungültige Werte werden abgewiesen. Zeit- und Speichergrenzen werden zwischen Aufträgen geprüft. PHP-CLI benötigt PCNTL, damit Laravel einen hängenden Auftrag nach Zeitablauf unterbrechen kann; andernfalls ist dafür eine Prozessgrenze des Hostings nötig. Setzen Sie auch endliche Netzwerkzeitlimits. Halten Sie `retry_after` über dem Auftragszeitlimit beziehungsweise konfigurieren Sie die SQS-Sichtbarkeit passend. `interpresso.process_lock_ttl` muss über dem längsten ununterbrochenen Auftrag und der erwarteten Wartezeit liegen.

Der Worker läuft jede Minute im Hintergrund mit `withoutOverlapping`. Seine Cache-Sperre verfällt nach `ceil((max_time + timeout) / 60) + 1` Minuten, standardmässig drei Minuten, damit der letzte Auftrag enden kann. Regulärer Abschluss gibt sie früher frei. Verwenden Sie auf einem Host etwa persistenten Dateicache und auf mehreren Hosts gemeinsamen Cache, niemals flüchtigen Array-/Null-Cache. Die Scheduler-Cache-Sperre verhindert überlappende Cron-Worker. Der separate `ProcessLock` in der Datenbank schützt Übersetzungsvorgänge über HTTP, CLI und Stapel hinweg; beide sind nötig. Manuelle Worker-Aufrufe sind nicht durch die Scheduler-Sperre geschützt.

Derselbe Konfigurationsblock bietet unabhängige Optionen: `interpresso.schedule.prune_batches` startet `interpresso:prune-batches` jede Minute; `interpresso.schedule.pending_notifications` startet `interpresso:send-automatic-pending-translations-notification` täglich um Mitternacht in der Scheduler-Zeitzone. Aktivieren Sie diese mit `INTERPRESSO_SCHEDULE_PRUNE_BATCHES=true` und `INTERPRESSO_SCHEDULE_PENDING_NOTIFICATIONS=true`. Beide sind standardmässig `false`, auch bei aktivem Worker. Wartung läuft vor einem neu geplanten Worker; bestehende Prozesssperren können einen Wartungsaufruf dennoch aussetzen lassen. Automatische Erinnerungen benötigen zusätzlich die gespeicherte Einstellung `enable_automatic_pending_notifications` und funktionierenden Mailtransport. Diese Einstellung wird erst bei Ausführung geprüft; die Zeitplanregistrierung liest keine Einstellungstabelle.

Alle drei Paketzeitpläne entfallen bei einer Verbindung ohne Hintergrundverarbeitung, einschliesslich sync/null/deferred/fehlender oder unsicherer Failover-Verbindungen. Sie planen keine Importe oder Freigaben selbst: Administratoren verwenden weiterhin die Oberfläche. Zum Deaktivieren setzen Sie `INTERPRESSO_SCHEDULE_QUEUE_WORKER=false` und erneuern den Konfigurationscache; ein laufender Aufruf endet innerhalb seiner konfigurierten Grenzen.

### Warum eine Aktion blockiert sein kann {#why-an-action-may-be-blocked}

Importe, das Ergänzen fehlender Übersetzungen, Exporte, Sammelfreigaben, Aktualisieren mit automatischer Übersetzung und alle Zeilenänderungen (Aktualisieren, Freigeben, Anfordern, Anforderung zurücknehmen, Wiederherstellen) starten nicht, solange eine aktive Prozesssperre, ein Paketauftrag oder ein unvollständiger, nicht abgebrochener Stapel besteht. Dialogabfragen und KI-Entwurfsvorschläge bleiben verfügbar, da sie keine Übersetzungen schreiben. Die ausführenden CLI-Befehle nutzen denselben Schutz. Bei mehreren Hosts kommen die beschriebenen Prüfungen hinzu; unerreichbare Hosts werden ignoriert.

Die Befehle prüfen die lokale kooperative Sperre, Paketaufträge, offene nicht abgebrochene Stapel und bei aktivierter Hostkoordination die konfigurierten Gegenstellen. Vor der Arbeit erwerben sie die Sperre im Einstellungsdatensatz mit einem bedingten Datenbank-UPDATE, auch unter `QUEUE_CONNECTION=sync` und Cron. Bei Belegung werden Eigentümer (Host, PID, Operation und Aufruf-ID) und Startzeit gemeldet; der Befehl kehrt ohne Arbeit zurück. Ausnahmen und PHP-Fehler geben die Befehlssperre in `finally` frei.

`interpresso.process_lock_ttl` beträgt standardmäßig 900 Sekunden und lässt sich über `INTERPRESSO_PROCESS_LOCK_TTL` setzen. Abgelaufene Sperren und alte Kennzeichen ohne Ablaufzeit verhindern den Erwerb nicht. Lange Importe erneuern die Sperre zwischen Dateien und Modellblöcken mit `ProcessLock::refresh()`. Eigene lange Operationen sollten `refresh()` auf ihrem Sperrobjekt vor Ablauf der TTL aufrufen. Setzen Sie die TTL höher als die längste ununterbrochene Arbeitseinheit. Getrennte Datenbanken stimmen sich weiterhin bestmöglich über HTTP ab, nicht über eine verteilte atomare Sperre.

Controlleränderungen erwerben dieselbe Sperre vor dem Schreiben oder Einreihen. Warteschlangenstapel behalten sie nach Ende der HTTP-Anfrage. Ihre Rückruffunktionen geben sie nach Abschluss oder Fehler frei. Reservierte Aufträge prüfen vor der Arbeit Abbruchstatus und Sperreigentum. Alte Rückrufe können keinen neuen Eigentümer entsperren. Blockierungsmeldungen nennen Eigentümer und Startzeit. Abbruch, Sprach-/Kontoverwaltung und Einstellungsänderungen bleiben möglich.

Lokaler Schutz und Abbruchdienst lesen `jobs` und `job_batches` über die Standarddatenbankverbindung. `interpresso.db_connection` konfiguriert Paketmodelle und Migrationen, leitet aber nicht jede Queue-Tabellenabfrage um. Stimmen Sie die Queue-/Stapelkonfiguration darauf ab. Das Löschen von Datenbank-`jobs` leert keine Warteschlange außerhalb der Datenbank.

Administrative Erfolgsmeldungen werden nur für erfolgreiche, nicht abgebrochene Stapel zugestellt. Fehler führen stattdessen zu Fehlermeldungen. Alle vier Sperrfelder und der Einstellungscache werden bei Abschluss, Abbruch, Einreihungsfehler und Auftragsfehler geleert; die CLI tut dies auch bei einem PHP-Fehler im Dienst.

### Stapel abbrechen und bereinigen {#cancel-and-maintain-batches}

Mit **Sprachen > Laufende Stapelverarbeitung abbrechen** markieren Sie offene Paketstapel als abgebrochen und löschen Datenbankzeilen aus `jobs` für die Paketwarteschlange. Auch eingereihte Paketbenachrichtigungen können entfernt werden. Ein Abbruch macht abgeschlossene Importe, Bearbeitungen oder Exporte nicht rückgängig und beendet keinen Worker, der bereits einen Auftrag ausführt. Aufträge prüfen den Stapelabbruch vor Beginn ihrer Verarbeitung, auch wenn ein Worker sie bereits reserviert hat. Eine allgemeine Prüfung mitten im Auftrag gibt es nicht. Prüfen Sie das Ergebnis, bevor Sie Ersatzarbeit starten.

Bei aktivierter Hostkoordination fordert dieselbe Schaltfläche einen Abbruch auf den anderen konfigurierten Hosts an. Eine Auswahl einzelner Aufträge oder eine Seite zum Wiederholen fehlgeschlagener Aufträge gibt es nicht.

`interpresso:prune-batches` entfernt alte abgeschlossene oder abgebrochene Stapeldatensätze. Aktive Arbeit und eingereihte oder fehlgeschlagene Aufträge bleiben erhalten. Aktivieren Sie `interpresso.schedule.prune_batches` zur minütlichen Bereinigung und `interpresso.schedule.pending_notifications` für tägliche Erinnerungen. Beide sind optional und benötigen eine Hintergrundwarteschlange.

## Berechtigungen und gemeinsame Bedienelemente {#permissions-and-shared-controls}

### Zugriff für Administratoren und Übersetzer {#administrator-and-translator-access}

Administratoren können alle Sprachdatensätze, Übersetzungen, die Übersetzerverwaltung und Einstellungen aufrufen. Ihre Oberfläche bietet Anlegen und Löschen von Sprachen, Importe, das Ergänzen fehlender Übersetzungen, Sammelfreigabe und -export, Abbruch sowie Freigabe-, Anforderungs- und Wiederherstellungsaktionen.

Übersetzer ohne Administratorrechte können in ihrer normalen Oberfläche:

- Zugewiesene Sprachen auflisten und durchsuchen sowie deren Übersetzungen öffnen.
- Suchen, blättern und alle Übersetzungsfilter dieser Sprachen verwenden.
- Eine Beispielsprache wählen, Übersetzen öffnen, verfügbare Beispiele lesen und Texte zur Prüfung bearbeiten.
- OpenAI nutzen, wenn die Funktion aktiv ist und die Voraussetzungen für Quelle und Beispiel erfüllt sind.
- Dieses Handbuch lesen, ihre Benachrichtigungen bedienen, das Farbschema wechseln und sich abmelden.

Nicht-Administratoren sehen weder die Sammelwerkzeugleiste für Sprachen noch Freigabe-, Anforderungs-, Wiederherstellungs- oder Exportaktionen, Übersetzerverwaltung und Einstellungen. Für sie gibt es keine Kontoverwaltungsseite.

Alle privilegierten Endpunkte erzwingen Administratorrechte auf dem Server. Jede Zeilenaktion löst die Übersetzungs-ID über die angeforderte Sprache auf und prüft Sprachzuweisungen. Eine fremde Zeilen-ID führt ohne Datenänderung zu 403. Nicht-Administratoren dürfen erlaubte Übersetzungen bearbeiten und Entwurfsvorschläge anfordern, aber nicht freigeben, Übersetzungen anfordern, wiederherstellen, gesammelt aktualisieren, exportieren, Konten verwalten, Einstellungen ändern oder Sprachwartung ausführen. Lesen, Ändern des Lesestatus und Sammelmarkierungen von Benachrichtigungen sind auf den angemeldeten Übersetzer und den Empfängertyp begrenzt.

### Navigation, Farbschema und Benachrichtigungen {#navigation-theme-and-notifications}

Angemeldete Übersetzer sehen Sprachen und Handbuch in der Navigation, Administratoren zusätzlich Übersetzer und Einstellungen. Auf kleinen Bildschirmen öffnen Sie das kompakte Hauptmenü. Die Farbschema-Schaltfläche wechselt zwischen Hell und Dunkel und speichert die Auswahl in einem Cookie sowie im Browserspeicher. Der Server berücksichtigt ein gespeichertes Cookie bereits vor der ersten Darstellung. Ohne Auswahl folgt das Stylesheet schon vor JavaScript der Systemeinstellung. Die Seite folgt weiteren Systemänderungen, bis Sie selbst ein Schema wählen.

Die Oberfläche verwendet DaisyUI-Komponenten mit hellem und dunklem Farbschema für Formulare, Tabellen, Menüs, Benachrichtigungen und Übersetzungseditor. Einstellungen nutzen sichtbare Kontrollkästchenschalter; der Editor lässt sich weiterhin per Schließen-Schaltfläche, Escape oder Außenklick schließen.

Die Benachrichtigungsschaltfläche zeigt die Anzahl ungelesener Nachrichten. Öffnen Sie das Feld darüber zum Lesen, schließen Sie es ohne Lesemarkierung, markieren Sie eine einzelne Nachricht beim Ausblenden als gelesen oder wählen Sie **Alle als gelesen markieren**. Das Feld ist bei Bedarf scrollbar. Bei sichtbarem Tab werden Benachrichtigungen alle fünf Sekunden aktualisiert; verborgene Tabs pausieren. Unveränderte Antworten erhalten vorhandene Bedienelemente und Fokus. Sofortmeldungen sind davon getrennt: Erfolg, gelöscht, Information oder Warnung, jeweils mit Schließen-Schaltfläche und automatischem Ausblenden.

Eine vorhandene Einstellung in `localStorage["color-theme"]` wird beim ersten Start des externen Theme-Moduls in das Cookie `interpresso-color-theme` übernommen. Ein gültiges Cookie hat Vorrang vor dem Browserspeicher und aktualisiert sowohl `.dark` als auch `data-theme`. Beim ersten Besuch nach dem Upgrade kennt der Server eine nur lokal gespeicherte Auswahl noch nicht; sie kann das Systemschema erst nach dem Laden des Moduls ersetzen. Spätere Anfragen verwenden sofort das Cookie. Der Wechsel funktioniert auch ohne verfügbaren localStorage.

Als Cookiepfad wird das URL-Präfix des Pakets verwendet, normalerweise `/translator`. Beim Speichern wird ein Duplikat am Pfad mit abschließendem Schrägstrich (`/translator/`) entfernt, damit ein älteres, spezifischeres Cookie die neue Auswahl bei der nächsten Anfrage nicht überschreibt.

Das Paket aktiviert standardmäßig strenge Browser-Sicherheitsheader. Skripte und Styles werden aus externen Dateien geladen, Meldungsdaten stehen in einem maskierten HTML-Attribut, und die Oberfläche lässt sich nicht in einen Frame einbetten. Bei CDN-Bereitstellungen müssen erlaubte Ressourcenursprünge in `interpresso.security_headers.extra_sources` konfiguriert werden; siehe [Konfiguration](CONFIGURATION.md#browser-security-headers). Die Header gelten nur für Webrouten des Pakets.

## CLI-Referenz {#cli-reference}

Führen Sie Befehle als `php artisan ...` im Verzeichnis der Laravel-Hostanwendung aus. Hier sind alle elf Befehlssignaturen aus `src/Console/Commands/` aufgeführt. Ausführende Befehle erwerben die gemeinsame Prozesssperre und können vorzeitig mit Eigentümer und Startzeit zurückkehren. Das bedeutet keinen abgeschlossenen Vorgang. Sofern nicht anders beschrieben, verwenden Befehle die gespeicherten Einstellungen.

### interpresso:import-languages {#interpressoimport-languages}

Signatur:

```text
interpresso:import-languages
```

Importiert unterstützte Sprachverzeichnisse direkt unter Laravels Sprachpfad und überspringt bereits gespeicherte Codes. Erkennt keine Sprachen allein anhand von `{locale}.json` und importiert keine Übersetzungstexte. Läuft synchron und reiht eine Ergebnisbenachrichtigung für Administratoren ein. Verwenden Sie den Befehl für den ersten Import oder nach dem Hinzufügen von Sprachverzeichnissen.

```bash
php artisan interpresso:import-languages
```

### interpresso:import-translations {#interpressoimport-translations}

Signatur:

```text
interpresso:import-translations
```

Importiert PHP-/JSON-Quellen und konfigurierte Modellübersetzungen vorhandener Sprachen. Berücksichtigt `import_vendor` und `import_only_from_root_language`. Vorhandene Paare aus Sprache und gemeinsamer Kennung bleiben erhalten; neue Importe gelten als freigegeben und exportiert. Meldet die Gesamtzahl vorhandener und neu eingefügter Übersetzungen. Verwenden Sie den Befehl nach neuen Quellschlüsseln oder Modellwerten und nach dem Import der Sprachen. Geänderte Dateien aktualisieren keine vorhandenen Übersetzungswerte.

```bash
php artisan interpresso:import-translations
```

### interpresso:find-missing-translations {#interpressofind-missing-translations}

Signatur:

```text
interpresso:find-missing-translations
```

Vergleicht die Übersetzungsanzahl je Sprache und ergänzt bei Unterschieden fehlende Gegenstücke der Ausgangssprache. Leere Sprachen werden bei der Prüfung gesondert berücksichtigt. Verwendet `app.locale` als Quelle, ersatzweise die erste Sprache, und kann OpenAI nutzen. Neue Zeilen sind nicht freigegeben, zu übersetzen und nicht exportiert. Meldet eingefügte Anzahlen und reiht bei vorhandenem Ausgangssprachdatensatz eine entsprechende Administratorbenachrichtigung ein.

Verwenden Sie den Befehl nach dem Import von Ausgangsschlüsseln oder dem Hinzufügen von Sprachen. **Einschränkung:** Gleiche Anzahlen können unterschiedliche Schlüsselmengen verbergen. Die CLI meldet dann `Everything up to date.`, ohne Kennungen zu prüfen. Die Oberflächenaktion Fehlende Übersetzungen suchen ruft den Dienst ohne diese Abkürzung auf.

```bash
php artisan interpresso:find-missing-translations
```

### interpresso:approve-translations {#interpressoapprove-translations}

Signatur:

```text
interpresso:approve-translations {--translator=} {--language=}
```

Gibt synchron alle noch nicht freigegebenen Übersetzungen frei, wahlweise nur die Sprache aus `--language=en`. `--translator=ID` ist Pflicht und muss einen vorhandenen Übersetzer mit Administratorrechten identifizieren; Freigaben werden dieser ID zugeordnet. Eine ungültige Zuordnung oder unbekannte Sprache beendet den Befehl mit Status 1 ohne Schreibvorgänge. Der Befehl nutzt die gemeinsame Prozesssperre, erneuert sie zwischen Sprachen, invalidiert Übersetzungscaches über den vorhandenen Freigabedienst und sendet Ergebnisbenachrichtigungen an Administratoren. Er funktioniert mit `QUEUE_CONNECTION=sync` ohne Worker. Prüfen Sie die Übersetzungen vor dem Aufruf.

```bash
php artisan interpresso:approve-translations --translator=1
php artisan interpresso:approve-translations --translator=1 --language=en
```

### interpresso:export-translations {#interpressoexport-translations}

Signatur:

```text
interpresso:export-translations {--force=} {--language=} {--only-models}
```

Exportiert freigegebene, nicht geänderte Übersetzungen. Normalerweise werden nur als nicht exportiert markierte Zeilen berücksichtigt. `--force` erwartet einen Wert, der in boolesch umgewandelt wird. Mit `--force=1` werden bereits exportierte Zeilen einbezogen; ohne Option oder mit `--force=0` bleibt das normale Verhalten. Die Option umgeht weder Freigabe noch Schutz vor laufenden Aufträgen.

Im Dateimodus werden PHP-/JSON- und Modellinhalte exportiert. Im Datenbankmodus wird nur Modellexport angefordert, Dateien werden übersprungen. `--only-models` begrenzt auch im Dateimodus auf Modelle. `--language=en` beschränkt Vorauszählung und Ausführung auf diese Sprache. Ein unbekannter Code führt ohne Export zum Fehler. Die Ausführung ist synchron und reiht Ergebnisbenachrichtigungen für Administratoren ein; andere Hosts erhalten keine Exportanforderung. Anzahlen beziehen sich auf Übersetzungszeilen, nicht auf Dateien.

Verwenden Sie den Befehl zur regulären Veröffentlichung oder im Dateimodus zum erzwungenen Neuschreiben von Dateien, deren Datenbankzeilen bereits als exportiert gelten.

```bash
php artisan interpresso:export-translations
php artisan interpresso:export-translations --force=1
```

### interpresso:export-translations-deployment {#interpressoexport-translations-deployment}

Signatur:

```text
interpresso:export-translations-deployment
```

Exportiert synchron und erzwungen alle freigegebenen, nicht geänderten Übersetzungen jeder Sprache einschließlich Modelle und ignoriert Exportiert. Akzeptiert keine Option `--force`. Nutzt den gemeinsamen Prozessschutz, leitet keine Exporte weiter und übergibt die Beschränkung auf Modelle im Datenbankmodus nicht. Deshalb können selbst bei `db_loader=true` Dateien geschrieben werden.

Verwenden Sie den Befehl nach einer Dateimodus-Bereitstellung, die exportierte Übersetzungen ersetzt hat. Für den Datenbankmodus ist er unnötig; lassen Sie ihn dort im Bereitstellungsablauf weg. Er meldet den Abschluss je Sprache auch dann, wenn keine geeigneten Inhalte vorlagen.

```bash
php artisan interpresso:export-translations-deployment
```

### interpresso:work {#interpressowork}

Signatur:

```text
interpresso:work
```

Verarbeitet nur die konfigurierte Paketwarteschlange und endet bei leerer Warteschlange oder erreichtem Zeit-/Auftragslimit. Restliche Arbeit folgt beim nächsten Cron-Durchlauf. Rückgabewert 0 bedeutet leere Warteschlange oder reguläres Erreichen einer Grenze, 1 eine nicht geeignete Verbindung oder ungültige Grenzen. Sonst wird der Rückgabewert des Workers weitergegeben. Auftragsfehler können auch bei Rückgabewert 0 aufgezeichnet sein; prüfen Sie Fehlerdatensätze und Protokolle. Es gibt keine befehlsspezifischen Optionen; setzen Sie die Grenzen unter [Cron ohne Supervisor](#cron-without-supervisor).

```bash
php artisan interpresso:work
```

### interpresso:prune-batches {#interpressoprune-batches}

Signatur:

```text
interpresso:prune-batches
```

Löscht zu `interpresso.batch_name` passende Zeilen aus `job_batches` auf `interpresso.db_connection`, wenn Abschluss- oder Abbruchzeit älter als `interpresso.prune_batch_hours` ist, standardmäßig 24 Stunden. Aktive Stapel, Queue-Aufträge und Fehlerdatensätze bleiben unberührt. Es gibt keine befehlsspezifischen Optionen oder Abschlussausgabe.

Verwenden Sie den Befehl zur Bereinigung aufbewahrter Stapel. Aktivieren Sie `interpresso.schedule.prune_batches` für automatische minütliche Bereinigung oder richten Sie einen eigenen Zeitplan ein.

```bash
php artisan interpresso:prune-batches
```

### interpresso:send-automatic-pending-translations-notification {#interpressosend-automatic-pending-translations-notification}

Signatur:

```text
interpresso:send-automatic-pending-translations-notification
```

Dies ist der tatsächlich von `SendAutomaticPendingNotifications` implementierte Befehlsname. `interpresso:send-automatic-pending-notifications` ist kein registrierter Alias.

Bei aktivem `enable_automatic_pending_notifications` durchläuft der Befehl alle Übersetzer einschließlich Administratoren und deren explizite Sprachzuweisungen. Für jede Sprache mit zu übersetzenden Zeilen werden Datenbank- und E-Mail-Benachrichtigungen eingereiht. Ohne ausstehende Zeilen erfolgt keine Zustellung. Bei deaktivierter Option tut der Befehl nichts. Er benötigt `enable_pending_notifications` nicht, nutzt den gemeinsamen Prozessschutz und gibt keine Erfolgszusammenfassung aus.

Verwenden Sie den Befehl für regelmässige Erinnerungen mit funktionierendem Mailtransport und dauerhaftem oder per Cron gestartetem Worker. Aktivieren Sie `interpresso.schedule.pending_notifications` für den täglichen Zeitplan oder richten Sie einen eigenen ein. Wiederholte Aufrufe können erneut an dieselbe ausstehende Arbeit erinnern.

```bash
php artisan interpresso:send-automatic-pending-translations-notification
```

### interpresso:developer-download {#interpressodeveloper-download}

Signatur:

```text
interpresso:developer-download
```

Lädt Sprachen und seitenweise Übersetzungen von `interpresso.main_server_domain`, konfiguriert über `INTERPRESSO_MAIN_SERVER_DOMAIN`, mit `INTERPRESSO_API_SHARED_SECRET` herunter. Ersetzt lokale Sprach- und Übersetzungszeilen und exportiert danach die heruntergeladenen freigegebenen, nicht geänderten Inhalte erzwungen. Der Dateimodus exportiert Dateien und Modelle, der Datenbankmodus nur Modelle. Einstellungen, Übersetzerkonten und Zuweisungen werden nicht heruntergeladen.

Verwenden Sie dies ausschließlich, um eine Entwicklungskopie bewusst durch die Übersetzungsdaten des Hauptservers zu ersetzen. **Lokale Übersetzungsarbeit wird ohne Bestätigungsabfrage gelöscht beziehungsweise ersetzt.** Der gemeinsame Prozessschutz gilt. Es gibt keine Beschränkung auf lokale Umgebungen, keinen Probelauf, kein Hostargument und keinen Zusammenführungsmodus. Die Datenbankphase nutzt eine Transaktion und MySQL-/MariaDB-Anweisungen mit `SET FOREIGN_KEY_CHECKS`; in dieser Form ist sie nicht auf SQLite oder PostgreSQL übertragbar. Der Export erfolgt nach dem Commit. Ein Exportfehler macht den Austausch der Datenbank nicht rückgängig. Für Modellexporte müssen lokale Zielmodelle vorhanden sein.

```bash
php artisan interpresso:developer-download
```

### interpresso:unlock {#interpressounlock}

Signatur:

```text
interpresso:unlock {--force}
```

Zeigt gespeicherten Eigentümer und Startzeit. Ohne `--force` werden abgelaufene oder alte Sperren entfernt; eine aktive Sperre wird mit Exitcode 1 abgewiesen. `--force` entfernt auch aktive Sperren. Erfolgreiches Freigeben endet mit 0 und leert `process_running`, `process_owner`, `process_started_at` und `process_expires_at`. Ein bereits entsperrter Einstellungsdatensatz kann gefahrlos erneut entsperrt werden. Die Bereinigung nur abgelaufener Sperren nutzt ein bedingtes UPDATE, damit ein gleichzeitiger Erwerb oder eine Erneuerung nicht gelöscht wird.

```bash
php artisan interpresso:unlock
php artisan interpresso:unlock --force
```

Entsperren beendet keinen PHP-Prozess, bricht keine Queue-Datensätze ab und rollt keine Änderungen zurück. Stoppen oder prüfen Sie den alten Prozess, bevor Sie eine aktive Sperre erzwingen. Vorhandene Queue-/Stapeldatensätze können weiter blockieren; verwenden Sie **Laufende Stapelverarbeitung abbrechen**, um diese abzubrechen. Der Abbruch gibt nur die Sperre des betroffenen Stapels frei, nicht die eines getrennten Cron-Laufs.

## Fehlerbehebung {#troubleshooting}

### Aufträge werden nicht fertig oder ein anderer Prozess wird gemeldet {#jobs-do-not-finish-or-another-process-is-reported}

Prüfen Sie bei asynchronen Warteschlangen, ob ein Worker die konfigurierte Queue abholt, normalerweise `languageProcessor`. Cron-/Artisan-Befehle unter `QUEUE_CONNECTION=sync` benötigen für Übersetzungsarbeit keinen Worker; HTTP-Sammelaktionen lehnen sync immer ab. Prüfen Sie Eigentümer, Start und Ablauf der gemeldeten Sperre. Verwenden Sie `interpresso:unlock` für veraltete Metadaten und `--force` erst, nachdem der alte Lauf sicher gestoppt ist. Prüfen Sie die `jobs` der Paketwarteschlange, offene `languageBatch`-Stapel, Anwendungsprotokolle und Fehlerdatensätze. Auch ausstehende Benachrichtigungen können die Queue belegen. Ein Worker nur für die Standardqueue verarbeitet die Paketqueue nicht.

Nachdem Sie laufende Arbeit geprüft haben, verwenden Sie **Laufende Stapelverarbeitung abbrechen** für verwaiste Arbeit. Die Bereinigung alter Stapel ist kein Abbruch. Prüfen Sie bei mehreren Hosts Gegenstellen und gemeinsame Geheimnisse. Unerreichbare Hosts werden bei der Belegungsprüfung ignoriert, die Exportweiterleitung kann aber fehlschlagen. Beachten Sie die oben beschriebenen Queue-Tabellenverbindungen und Grenzen anderer Queue-Typen.

### Sprache, Schlüssel oder Beispiel fehlt {#a-language-key-or-source-example-is-missing}

Sprachen importieren erkennt nur unterstützte Sprachverzeichnisse. Legen Sie bei reinen JSON-Quellen die Sprache ausdrücklich an. Stellen Sie vor dem Import sicher, dass `lang/` existiert. Der Datenbankmodus erstellt beim Import keine fehlenden Quellverzeichnisse. Ausgangs- und Ersatzsprachdatensätze müssen vorhanden sein. Laden Sie die Liste nach Hintergrundimporten neu.

Übersetzungen importieren besucht nur gespeicherte Sprachen. Ausgangssprachen- und Paketoptionen können Quellen ausschließen. Ein erneuter Import überschreibt keine vorhandenen Werte. Fehlende Übersetzungen suchen kopiert Kennungen der Ausgangssprache, keine Schlüssel, die nur in anderen Sprachen vorkommen. Die CLI-Abkürzung bei gleicher Anzahl kann unterschiedliche Schlüsselmengen übersehen; nutzen Sie dann die Oberfläche. Ein fehlender Datensatz für `app.fallback_locale` verhindert das Laden des Editors. Legen Sie diese Sprache erneut an.

### Filter oder Einstellungen scheinen nicht gespeichert zu werden {#filters-or-settings-appear-not-to-save}

Suche und Filter laden die Seite mit URL-Parametern neu. Einstellungsfelder werden bei Änderungen einzeln gesendet. Warten Sie auf den Abschluss der Navigation und prüfen Sie Feldfehler bei abgewiesenen Einstellungen. Speichern Sie Domains vor dem Aktivieren der Hostkoordination und deaktivieren Sie diese vor dem Leeren. Reagieren Bedienelemente nicht, bauen und veröffentlichen Sie die Paketressourcen neu und prüfen Sie JavaScript- oder Netzwerkanfragen auf Fehler. Stapel- und Benachrichtigungsabfragen pausieren absichtlich in verborgenen Tabs.

### Freigegebene Inhalte fehlen oder Dateien bleiben unverändert {#approved-content-is-not-visible-or-files-are-unchanged}

Geben Sie im Dateimodus Änderungen frei und exportieren Sie sie. Normale Exporte überspringen nicht freigegebene, geänderte oder bereits exportierte Zeilen. Verwenden Sie `--force=1` nur zum erneuten Export freigegebener, nicht geänderter Inhalte. Prüfen Sie Dateirechte und die Gültigkeit der PHP-/JSON-Quellen. Ungültiges bestehendes JSON und Kodierungsfehler brechen den Export ab, statt die Datei stillschweigend mit ungültigem Inhalt zu ersetzen.

Im Datenbankmodus sind fehlende Dateischreibvorgänge im normalen Ablauf beabsichtigt. Prüfen Sie Freigabe und vorherigen Wert, Cachekonfiguration und gemeinsames Präfix. Exportiert falsch verhindert keine Datenbankbereitstellung. Modellspalten benötigen weiterhin Modellexport. Bereitstellungsbefehl und Host-Endpunkt für erzwungenen Export können trotz Datenbankeinstellung Dateien schreiben.

### Datenbank-, Cache- oder Einstellungsfehler {#database-cache-or-settings-failures}

Stellen Sie bei einem Webausfall im Datenbankmodus die Datenbankverbindung wieder her. Es gibt keinen automatischen Web-Rückfall auf Dateien. `CACHE_DRIVER`/`CACHE_STORE` müssen einen Speicher außerhalb der Datenbank verwenden. Worker und Webprozesse müssen dieselbe passende Cachekonfiguration nutzen. Paketschreibvorgänge invalidieren versionierte Einträge nach dem Commit; externe Sammelschreibvorgänge benötigen den Invalidierungsaufruf.

Fehlt die Einstellungstabelle oder ihr Datensatz, kann die Laderauswahl Laravels Dateilader wählen. Paketoperationen, die Einstellungen benötigen, werfen bei fehlendem Datensatz `MissingSettingsException`. Stellen Sie den gespeicherten Datensatz wieder her; das Paket legt ihn nicht automatisch neu an und ersetzt keine gespeicherten Präferenzen. Nach einer Reparatur außerhalb der Oberfläche aktualisieren Sie den Cache mit `Setting::getFreshCached()` im Wartungscode der Anwendung.

### OpenAI oder Erinnerungs-E-Mails bewirken nichts {#openai-or-pending-email-does-nothing}

Prüfen Sie Schalter, optionales OpenAI-Paket, API-Konfiguration, gewähltes Beispiel und Anwendungsprotokolle. Bei OpenAI-Fehlern kann der Ausgangstext unverändert zurückkommen. Editoraktionen hängen von Ausgangssprache und vorhandenem Beispiel ab. Erzeugte Texte benötigen weiterhin Prüfung und Freigabe.

Erinnerungen zählen zu übersetzende Zeilen, nicht alle ungeprüften Zeilen. Prüfen Sie explizite Sprachzuweisungen, Mailtransport und Queue-Worker. Manuelle und automatische Erinnerungseinstellungen sind unabhängig. Aktivieren Sie für tägliche Erinnerungen zusätzlich `interpresso.schedule.pending_notifications`. Das Gelesen-Markieren einer Bildschirmmeldung ändert keinen Übersetzungsstatus.

### Zugriffs-, Routen- und Hostfehler {#access-routes-and-inter-host-errors}

Prüfen Sie bei fehlenden Oberflächenrouten `INTERPRESSO_ENABLED`, das konfigurierte Präfix und die exakte Übereinstimmung zwischen Hauptserverwert und `app.url`. Bei 403 für Nicht-Administratoren prüfen Sie Zuweisungen und ob die Seite Administratoren vorbehalten ist. Bei fehlenden Zeilenaktionen prüfen Sie Freigabe-, Anforderungs- und Altwertbedingungen.

HTTP 503 zwischen Hosts bedeutet nach erfolgreicher Anfragevalidierung, dass kein gemeinsames Geheimnis konfiguriert ist. HTTP 401 bedeutet Nichtübereinstimmung; HTTP 422 kann auf einen fehlenden oder ungültigen `api_key` hinweisen. Konfigurieren Sie überall dasselbe Geheimnis und Installations-URLs mit Protokoll. Der Hostkoordinationsschalter deaktiviert die API nicht.

### Ausnahmen bei Import, Modellexport oder Entwickler-Download {#import-model-export-or-developer-download-exceptions}

Prüfen Sie protokollierte Pfade und Fehlerkennungen bei ungültigen Quelldateien oder Einfügefehlern. Null-Werte für Quelltext, Namensraum oder Gruppe sind keine gültigen Kopiermetadaten für fehlende Übersetzungen. Anwendungsübersetzungen verwenden einen leeren Namensraum, JSON-Übersetzungen eine leere Gruppe. Modellexporte benötigen eine Eloquent-Modellklasse, einen vorhandenen Datensatz und eine vorhandene JSON-Übersetzungsspalte. Fehlende Ziele lösen eine Ausnahme aus, statt stillschweigend als exportiert zu gelten.

Der Entwickler-Download benötigt kompatibles MySQL-/MariaDB-SQL, erreichbare API-Endpunkte des Hauptservers und das passende gemeinsame Geheimnis. Er ersetzt lokale Datensätze und bestätigt die Transaktion vor dem Export. Prüfen Sie daher vor einer Wiederholung, welche Phase fehlgeschlagen ist. Lokale Übersetzer- und Zuweisungsdaten werden nicht synchronisiert.
