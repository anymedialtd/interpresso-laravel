Questa è una traduzione dell'originale inglese, che resta il riferimento autorevole.

# Manuale dell'applicazione

Questo manuale viene mostrato nel pannello di traduzione alla route `interpresso.manual`, normalmente `/translator/manual`. Usa l'indice delle sezioni generato per navigare. Sugli schermi ampi, l'indice rimane accanto all'articolo e scorre all'interno della finestra, mantenendo raggiungibili tutte le sezioni. Le quattro schermate operative sono Lingue, Traduzioni, Traduttori e Impostazioni.

L'interfaccia segue `app.locale` dell'applicazione ospitante. Sono inclusi tedesco (`de`), francese (`fr`), spagnolo (`es`) e italiano (`it`). Il manuale carica `docs/APPLICATION_MANUAL.{locale}.md` per la lingua attiva e usa `docs/APPLICATION_MANUAL.md` se la traduzione non esiste. L'originale inglese resta autorevole. I titoli tradotti mantengono le ancore delle sezioni dell'originale.

## Primi passi {#getting-started}

### Installazione, pubblicazione e migrazione {#install-publish-and-migrate}

Il pacchetto richiede PHP 8.2 o successivo nella versione principale 8 e Laravel 12 o 13, come dichiarato in `composer.json`. Esegui i comandi di installazione dalla directory dell'applicazione Laravel:

```bash
composer require anymedialtd/interpresso-laravel
php artisan vendor:publish --tag=interpresso-config
php artisan vendor:publish --tag=interpresso-migrations
php artisan vendor:publish --tag=interpresso-public
php artisan migrate
```

Il rilevamento automatico di Composer registra i service provider del pacchetto. La pubblicazione della configurazione include `config/interpresso.php` e `config/openai.php`. Le risorse usate dall'interfaccia vengono pubblicate in `public/vendor/interpresso/css/app.css` e `public/vendor/interpresso/js/app.js`. Pubblicare `interpresso-translations` o `interpresso-views` è facoltativo e permette di personalizzare testi o template. Il pacchetto carica anche le proprie migrazioni direttamente.

Le installazioni esistenti devono seguire [il passaggio all'identità Interpresso](INSTALLATION.md#adopting-the-interpresso-identity) prima di migrare. Le tabelle predefinite ora usano `interpresso_`: imposta le opzioni `table_*` sui nomi esistenti per conservarne i dati. Configurazione, integrazioni PHP, pianificazioni, personalizzazioni pubblicate e tutti gli host API partecipanti devono adottare insieme la nuova identità.

Le migrazioni creano le tabelle per lingue, traduzioni, traduttori, assegnazioni e impostazioni, oltre a code, batch, job falliti e notifiche se mancanti. Imposta `INTERPRESSO_DB_CONNECTION` ed eventuali nomi di tabella personalizzati in `config/interpresso.php` prima della migrazione. Viene creata una lingua corrispondente a `config('app.fallback_locale')`, che deve essere nell'elenco delle lingue supportate.

Le nuove installazioni attivano `db_loader` e disattivano il coordinamento tra host. Prima di servire traduzioni, scegli una cache che non usi il database e importa le sorgenti come descritto sotto. Per le operazioni in coda dell'interfaccia, configura una connessione asincrona e un worker che elabori la coda del pacchetto, normalmente:

```bash
php artisan queue:work --queue=languageProcessor
```

L'archivio della cache e la connessione di coda sono impostazioni distinte. Una coda su database è compatibile con una cache su un altro archivio.

### Accesso e modifica della password predefinita {#sign-in-and-change-the-default-password}

L'URL di accesso predefinito è `/translator/login`. Se non esiste alcun traduttore, la migrazione crea questo amministratore:

- Email: `admin@admin.com`
- Password: `aaaaaaaa`
- Nome e cognome: `admin`

Accedi, apri **Traduttori**, modifica l'amministratore e usa subito **Cambia password**. Imposta almeno otto caratteri e una conferma identica. Aggiornare soltanto il profilo non cambia la password. Il traduttore con ID `1` non può essere eliminato dall'applicazione, ma profilo e password restano modificabili.

Il modulo di accesso contiene email, password e **Resta connesso**. Credenziali errate lasciano aperta la schermata di accesso. Sono consentiti dieci tentativi per indirizzo IP al minuto; superata la soglia viene mostrato il tempo di attesa. Gli account usano il guard di sessione `interpresso_translator` del pacchetto. Usa **Esci** nella navigazione per terminare la sessione.

Le route dell'interfaccia vengono registrate solo se `config('interpresso.main_server_domain')` coincide esattamente con `config('app.url')`. Il server principale deriva da `INTERPRESSO_MAIN_SERVER_DOMAIN`, con l'URL dell'applicazione come valore predefinito. `INTERPRESSO_ENABLED=false` disattiva la registrazione di interfaccia/API e il loader personalizzato. Prefissi e percorsi delle schermate sono configurabili in `config/interpresso.php`.

### Prima importazione {#first-import}

La **lingua di origine** è `config('app.locale')`. La **lingua di fallback** è `config('app.fallback_locale')` ed è anche la lingua di riferimento iniziale dell'editor. Possono essere diverse.

1. Rendi disponibili i file sorgente nel percorso delle lingue di Laravel, normalmente `lang/`. Controlla la configurazione di origine e fallback.
2. Apri **Lingue** e avvia **Importa lingue**. Riconosce directory supportate come `lang/en/` e `lang/de/`. Un file JSON come `lang/de.json` da solo non crea una lingua: usa **Aggiungi lingua** oppure crea la directory corrispondente.
3. Attendi il completamento, aggiorna l'elenco e avvia **Importa traduzioni**. Importa voci PHP e JSON per lingue già registrate nel database e traduzioni dei modelli configurati. Imposta prima le opzioni per i pacchetti e l'importazione dalla lingua di origine.
4. Avvia **Cerca traduzioni mancanti** per creare nelle altre lingue le voci corrispondenti a quelle di origine. Controllale, traducile e approvale.
5. In modalità database, le traduzioni approvate dell'applicazione vengono servite dal database. In modalità file, esporta dopo l'approvazione. Le traduzioni dei modelli devono essere esportate nelle colonne dei modelli dell'applicazione in entrambe le modalità.

I comandi iniziali equivalenti sono:

```bash
php artisan interpresso:import-languages
php artisan interpresso:import-translations
php artisan interpresso:find-missing-translations
```

Le importazioni aggiungono record mancanti senza sovrascrivere traduzioni esistenti quando cambia un file sorgente. Le voci importate da file/modelli sono inizialmente approvate ed esportate. Quelle create dalla ricerca delle traduzioni mancanti restano non approvate, da tradurre e non esportate, anche se il testo proviene da OpenAI.

## Lingue {#languages}

### Consultazione, ricerca, aggiunta ed eliminazione {#browse-search-add-and-delete}

L'elenco mostra codice, nome e nome nella lingua originale, ordinati per codice, con dieci righe per pagina. **Cerca** usa questi tre campi. **Visualizza** apre le traduzioni della lingua. Gli amministratori vedono tutte le lingue; gli altri traduttori solo quelle assegnate. La paginazione offre pagine precedente, successiva e numerate quando necessario.

**Aggiungi lingua** apre un selettore delle lingue supportate. Scegli un codice non utilizzato e premi **Aggiungi**. Codici duplicati o non supportati vengono rifiutati. Si crea il record e, se assente, la sua directory sotto `lang/`, anche con caricamento da database attivo. Non vengono create voci tradotte. **Chiudi** nasconde il modulo.

L'azione **Elimina** della riga appare solo agli amministratori con `allow_deleting_languages` attivo. Rimuove le assegnazioni e la lingua; le traduzioni vengono eliminate dalla chiave esterna a cascata del database. File e directory rimangono, quindi un'importazione successiva può ricreare la lingua. Non viene chiesta conferma. Conserva la lingua di fallback se i suoi valori devono restare disponibili come esempi.

### Importazione e completamento delle voci mancanti {#import-and-fill-missing-entries}

Le seguenti operazioni della barra degli strumenti sono disponibili agli amministratori:

- **Importa lingue** mette in coda la ricerca delle directory di lingue supportate. I codici esistenti vengono ignorati.
- **Importa traduzioni** mette in coda importazioni PHP, JSON, dei pacchetti se abilitate e dei modelli configurati. L'operazione comprende l'insieme di lingue configurato, non solo i risultati visibili della ricerca.
- **Cerca traduzioni mancanti** mette in coda la creazione di record tramite identificatore di traduzione condiviso. Usa come sorgente la lingua di `app.locale`, oppure la prima se il record manca. Copia solo gli identificatori di origine nelle altre lingue: non cerca chiamate di traduzione nel codice e non unisce le chiavi di ogni lingua in quella di origine. Le voci corrispondenti già presenti rimangono inalterate. Con OpenAI attivo, i valori mancanti vengono proposti alla traduzione automatica; altrimenti viene copiato il testo sorgente da modificare in seguito.

L'importazione dei pacchetti legge le personalizzazioni pubblicate sotto `lang/vendor/{namespace}/` prima della directory di traduzione registrata, mantenendo quelle già importate. I file PHP devono restituire array e i JSON devono essere decodificabili in array. Le chiavi annidate vengono appiattite per l'archiviazione; le sottodirectory PHP restano nel nome del gruppo.

### Approvazione, esportazione e annullamento {#approve-export-and-cancel}

**Approva traduzioni di tutte le lingue** mette in coda l'approvazione di tutti i record non approvati, indipendentemente da ricerca e filtri. L'approvazione cancella gli indicatori Da tradurre e Modificato, scarta il vecchio valore salvato e registra l'amministratore che approva. L'approvazione in blocco non richiede valori non vuoti: controlla prima le voci mancanti.

Con `db_loader` disattivato, **Esporta tutte le lingue** mette in coda le lingue con righe approvate, non modificate e non esportate. Esporta file dell'applicazione, dei pacchetti e traduzioni dei modelli. Se non esistono righe idonee, una notifica segnala che non è stato esportato nulla.

Con `db_loader` attivo, **Esporta tutti i modelli tradotti** limita sia la selezione iniziale sia ogni job di esportazione alle righe dei modelli. Le righe PHP/JSON vengono lasciate alla distribuzione tramite database.

Dopo il completamento corretto di un batch di esportazione dell'interfaccia, senza annullamento, il coordinamento tra host, se attivo, richiede esportazioni forzate sugli altri host configurati. Il normale comando di esportazione non propaga queste richieste.

**Annulla elaborazione in blocco in corso** contrassegna come annullati i batch incompleti del pacchetto e rimuove i job dalla sua coda su database. Con coordinamento attivo, richiede anche l'annullamento sugli altri host. Il risultato riporta job e batch locali interessati. Vedi **Job in background e code** per i limiti dell'annullamento.

## Traduzioni {#translations}

Apri una lingua tramite **Lingue > Visualizza**. La route predefinita è `/translator/translations/{language}`, denominata `interpresso.translations`. Un traduttore non amministratore deve essere assegnato alla lingua per accedere.

### Tabella, ricerca e filtri a selezione multipla {#table-search-and-multi-select-filters}

La tabella mostra ID, Contenuto del pacchetto, Namespace, Gruppo, Da tradurre, Approvato, Approvato da, Modificato, Modificato da, Esportato, Chiave, Contenuto e Contenuto precedente. Le colonne degli autori mostrano nomi, mentre i filtri propongono indirizzi email. I valori sono testo semplice; apri **Traduci** per modificarli per intero. La tabella ha venti righe per pagina.

**Cerca** usa ID, namespace, gruppo, chiave, valore attuale e precedente. Una nuova ricerca inviata riparte dalla prima pagina.

Tre menu a selezione multipla restringono i risultati:

- **Tipo**: PHP, JSON o Modello. Le voci Modello rappresentano le colonne di traduzione Eloquent configurate.
- **Modificato da**: traduttori registrati come autori della modifica attuale.
- **Approvato da**: traduttori registrati come autori dell'approvazione attuale.

Più opzioni nello stesso menu corrispondono a una qualsiasi delle scelte. Menu diversi, filtri di stato e ricerca si combinano. Deseleziona tutte le opzioni di un menu per rimuovere il relativo filtro. Non esiste una scelta separata per righe senza autore di modifica o approvazione.

### Cinque filtri a tre stati {#five-three-state-filters}

Apri **Filtri di stato**. Ogni pulsante passa da **grigio: nessuna restrizione** a **verde: vero**, poi **rosso: falso** e di nuovo grigio. Filtra la relativa colonna booleana memorizzata:

- **Da tradurre** (`needs_translation`): è stata richiesta una traduzione o creata una voce corrispondente mancante. Falso indica assenza della richiesta, non necessariamente approvazione.
- **Approvato** (`approved`): la riga è approvata. Falso comprende voci richieste/mancanti e testi modificati in attesa di approvazione.
- **Modificato** (`updated_translation`): un valore modificato attende approvazione. È un indicatore del flusso di lavoro, non un filtro per data né una cronologia permanente.
- **Contenuto del pacchetto** (`is_vendor`): la riga è contrassegnata come proveniente da un pacchetto.
- **Esportato** (`exported`): l'indicatore nel database considera la riga esportata. Non controlla i file e non è necessario per servirla dal database.

Per il lavoro richiesto, imposta Da tradurre su verde. Per la revisione dopo una modifica, Approvato rosso e Modificato verde. Per le normali esportazioni di file, usa Approvato verde, Modificato rosso ed Esportato rosso. Questi filtri agiscono solo sulla tabella; approvazione ed esportazione in blocco usano query proprie sull'intera lingua.

La ricerca viene inviata dopo una breve pausa nella digitazione o con Invio. I cambiamenti dei filtri di stato e multipli inviano moduli GET, ricaricano la pagina e azzerano la paginazione. Le URL conservano `search`, `page`, `needs_translation`, `approved`, `updated_translation`, `is_vendor`, `exported`, `types[]`, `updatedBy[]` e `approvedBy[]`. Il pulsante di stato porta il parametro da assente a `true`, poi `false`, poi assente. Segnalibri, ricaricamento e navigazione Indietro/Avanti ripristinano le selezioni. Il filtro delle assegnazioni usa `selectedLanguages[]`.

### Lingua di riferimento e finestra di traduzione {#example-language-and-translate-modal}

Seleziona **Lingua di riferimento** prima di aprire **Traduci** su una riga. Determina l'esempio sorgente, non la lingua da modificare. Usa `app.fallback_locale` se consentita, altrimenti la lingua attuale. Se l'esempio scelto manca o è vuoto, l'editor usa il valore consentito della lingua di fallback.

La finestra mostra identificatore, esempio, area di testo con il valore attuale e riepiloghi espandibili per codice lingua degli esempi con lo stesso identificatore. Per i non amministratori, selettore, esempi e ricerca di fallback sono limitati alle lingue assegnate. Gli esempi vengono sottoposti a escaping anziché interpretati come HTML.

Usa **Chiudi finestra**, Esc o un clic all'esterno per chiudere senza salvare le modifiche dell'area di testo. Il focus torna al pulsante Traduci.

### Aggiornamento e azioni OpenAI {#update-and-openai-actions}

**Aggiorna traduzione** salva solo se il testo differisce dal valore memorizzato. All'inizio di una modifica conserva il valore precedente e gli autori di modifica/approvazione, registra il traduttore attuale come autore, rimuove l'approvatore corrente e segna la riga come modificata, non approvata e non esportata. Cancella anche Da tradurre. Ulteriori modifiche prima dell'approvazione mantengono lo stesso valore originale. Il salvataggio non approva né esporta automaticamente.

**Traduci con OPENAI** appare se OpenAI è attivo e c'è un esempio consentito non vuoto. Usa come sorgente la lingua effettiva dell'esempio, anche dopo il fallback. Un suggerimento riempie un'area ancora intatta senza salvare. Se hai già modificato la bozza o la modifichi durante la richiesta, il suggerimento appare separatamente con **Applica suggerimento**. La bozza resta finché non la sostituisci esplicitamente. Rileggi e usa **Aggiorna traduzione** per salvare. Caricamento ed errori lasciano utilizzabile l'editor; chiudere la finestra annulla la richiesta pendente.

Una risposta vuota o malformata, o un suggerimento senza testo, mostra un errore e conserva la bozza. Puoi riprovare o salvare il tuo testo.

**Aggiorna e traduci automaticamente le altre lingue (OPENAI)** è riservato agli amministratori e appare con OpenAI attivo quando la lingua attuale è quella di origine. Se il valore è cambiato, salva una volta la modifica di origine e mette in coda gli aggiornamenti delle altre traduzioni esistenti con lo stesso identificatore. Non crea quelle assenti: usa prima Cerca traduzioni mancanti. I risultati rimangono non approvati e non esportati.

OpenAI è facoltativo e richiede `openai-php/laravel`, impostazioni API valide in `config/openai.php` e l'attivazione della funzione. Il pacchetto invia il testo sorgente a OpenAI e chiede di preservare segnaposto Laravel come `:name`: verifica il risultato. `interpresso.open_ai_model` seleziona il modello richiesto e `interpresso.max_open_ai_missing_trans` controlla la dimensione dei gruppi di richieste per le traduzioni mancanti. Richieste fallite, integrazione assente o risposte con chiavi mancanti/aggiuntive o valori non stringa conservano l'input originale del servizio. Nella finestra questo è l'esempio, quindi una frase sorgente invariata non dimostra una traduzione riuscita. Lingue mancanti o input null tornano invariati senza richiesta; le richieste con array conservano stringhe vuote e `"0"`.

### Approvazione, richiesta, rimozione della richiesta e ripristino {#approve-request-remove-request-and-restore}

Gli amministratori vedono le seguenti azioni per riga:

- **Approva** appare per una riga non approvata con valore attuale non vuoto. Accetta il testo, registra l'approvatore, cancella Da tradurre e Modificato, rimuove Contenuto precedente e i vecchi riferimenti agli autori e invalida la cache. Non scrive file né imposta Esportato a vero. La stringa `"0"` è valida. Il pulsante manca per valori vuoti; l'approvazione in blocco non fa questo controllo.
- **Richiedi traduzione** appare se Da tradurre è falso. Lo imposta a vero e Approvato a falso. Non salva un valore precedente né invia autonomamente email.
- **Rimuovi richiesta di traduzione** appare se Da tradurre è vero. Lo imposta a falso e Approvato a vero, lasciando invariati gli altri indicatori. Non equivale all'azione completa Approva.
- **Ripristina** appare per una riga non approvata con Contenuto precedente salvato, anche vuoto o `"0"`. Ripristina valore e autori precedenti, cancella lo storico salvato e Modificato e segna la riga come approvata ed esportata. Non scrive file né cancella esplicitamente Da tradurre. Esiste un solo vecchio valore, non una cronologia di revisioni: quando l'approvazione lo rimuove, Ripristina non è più disponibile.

### Approvazione ed esportazione di un'intera lingua {#approve-and-export-a-whole-language}

**Approva traduzioni ({codice lingua})** mette in coda l'approvazione di tutte le righe non approvate della lingua attuale, anche fuori dai filtri o con valori vuoti.

In modalità file, **Esporta lingua** mette in coda righe approvate con Modificato falso ed Esportato falso, incluse le traduzioni di modelli idonee. In modalità database, **Esporta modelli tradotti** limita sia la verifica sia il job ai modelli. Nessun pulsante forza la riesportazione di record già esportati. Usa l'opzione CLI `--force=1` quando necessario. Queste azioni sono disponibili agli amministratori.

## Traduttori {#translators}

### Consultazione e filtri degli account {#browse-and-filter-accounts}

Questa schermata, normalmente `/translator/translators`, è riservata agli amministratori. Mostra ID, nome, cognome, email, telefono, stato amministratore e lingue assegnate, con dieci account per pagina.

**Cerca** usa ID, nome, cognome, email o telefono. La selezione multipla delle lingue trova i traduttori assegnati a **tutte le lingue selezionate**. Svuotarla rimuove il vincolo. L'accesso automatico di un amministratore a tutte le lingue non crea assegnazioni: il filtro può quindi escluderlo.

### Creazione, modifica, assegnazione delle lingue ed eliminazione {#create-edit-assign-languages-and-delete}

Apri il modulo di creazione e inserisci email, telefono, nome, cognome, password, conferma, assegnazioni e diritti di amministratore. L'email deve essere valida e univoca; nome e cognome devono avere almeno due caratteri. Il telefono è facoltativo ma, se fornito, deve essere univoco. La password richiede almeno otto caratteri e conferma identica.

Un non amministratore deve avere almeno un'assegnazione valida. ID duplicati o inesistenti vengono rifiutati. Gli amministratori possono essere salvati senza assegnazioni e accedere a tutte le lingue. Le assegnazioni esplicite continuano a determinare le notifiche ricevute per lingua.

Dopo la scelta, chiudi il menu delle assegnazioni con Lingue e invia il modulo. Gli errori di validazione appaiono accanto ai campi, comprese lingue e conferme password. I moduli rifiutati conservano i dati del profilo; le password vanno reinserite.

**Crea** salva un nuovo account. **Modifica** ne apre uno esistente; **Aggiorna** salva profilo, diritti e lista di assegnazioni, sostituendo la precedente. **Chiudi** nasconde il modulo. La creazione non invia inviti né email con password.

Usa **Elimina** sulla riga per rimuovere un account. Il traduttore con ID `1` non dispone del comando e il relativo endpoint rifiuta l'azione. Le altre eliminazioni vengono inviate direttamente senza conferma.

### Password e notifiche di traduzioni in sospeso {#password-changes-and-pending-notifications}

Durante la modifica di un account, **Cambia password** apre un modulo separato. Inserisci nuova password e conferma, quindi premi il suo pulsante di aggiornamento. **Chiudi** torna al profilo. Questo cambio amministrativo non richiede la password attuale. I non amministratori non dispongono di una schermata autonoma per cambiare o reimpostare la password.

Con `enable_pending_notifications` attivo, il modulo mostra il pulsante di promemoria. Controlla ogni lingua assegnata esplicitamente e mette in coda una notifica se contiene righe con `needs_translation=true`. La consegna usa email e canale notifiche su database. Non conta tutte le righe non approvate; un'assegnazione senza traduzioni richieste non produce invii. Configura il trasporto email e avvia il worker del pacchetto. Il messaggio di successo conferma la richiesta, non la consegna dell'email.

I promemoria automatici usano un comando e un'opzione separati, descritti nella sezione CLI. Nessuna delle due impostazioni invia inviti, cambia assegnazioni o approva traduzioni.

## Impostazioni {#settings}

La schermata Impostazioni, normalmente `/translator/settings`, è riservata agli amministratori. Mostra il dominio principale configurato in sola lettura, otto interruttori e il campo Domini. Cambia il server principale tramite `INTERPRESSO_MAIN_SERVER_DOMAIN` o la configurazione dell'applicazione.

Ogni campo ha un modulo POST e una validazione propri. Con JavaScript, cambiare un interruttore o uscire da Domini invia quel campo e ricarica la pagina. Senza JavaScript, usa il relativo pulsante Salva. Un campo non valido non impedisce di salvarne un altro valido. Dopo ogni scrittura riuscita il server aggiorna la cache delle impostazioni. Gli errori conservano il valore tentato per correggerlo; un nuovo ricaricamento mostra lo stato salvato. Il coordinamento richiede Domini già salvati: inseriscili prima di attivarlo.

### Riferimento delle impostazioni {#settings-reference}

Queste sono le nove colonne modificabili. I valori predefiniti riguardano una nuova installazione, non una configurazione mantenuta dopo un aggiornamento.

- **`db_loader`**, predefinito `true`: seleziona il loader di traduzioni dal database anziché quello ordinario di Laravel da file. Permette di pubblicare le traduzioni approvate direttamente, senza esportazioni periodiche di file. Disattivalo se servono file di lingua durante l'esecuzione. Interfaccia ed esportazioni CLI normali esportano solo modelli quando è attivo; vedi le eccezioni per l'esportazione forzata. Le installazioni esistenti conservano il valore vero o falso salvato quando cambia quello predefinito.
- **`import_vendor`**, predefinito `false`: include i namespace registrati dei pacchetti nell'importazione. Con caricamento da database attivo e questa opzione disattivata, le traduzioni dei pacchetti continuano a usare il loader padre da file. Attivandola usano anch'esse il database: importale prima di affidarti a questa modalità. Usala per far gestire i testi dei pacchetti ai traduttori. Disattivarla non elimina record già importati né esclude le righe esistenti dall'esportazione su file.
- **`enable_open_ai_translations`**, predefinito `false`: abilita chiamate OpenAI per generare traduzioni mancanti e usare le azioni dell'editor, mostrando i relativi pulsanti. Configura prima l'integrazione facoltativa. Non traduce automaticamente tutte le righe esistenti né approva i testi generati; senza integrazione il servizio continua a restituire l'input.
- **`enable_pending_notifications`**, predefinito `false`: mostra l'azione manuale di promemoria nel modulo del traduttore. Consente agli amministratori di richiedere promemoria per singoli account. Non li pianifica e non viene controllata dal comando automatico.
- **`enable_automatic_pending_notifications`**, predefinito `false`: permette al comando automatico di scorrere le assegnazioni esplicite di tutti i traduttori. Usala con una tua pianificazione. È indipendente da `enable_pending_notifications`; il pacchetto non registra alcuna pianificazione attiva.
- **`import_only_from_root_language`**, predefinito `false`: limita le importazioni di traduzioni, inclusi modelli e pacchetti, alla lingua di `app.locale`. Usala quando le sorgenti di questa lingua sono autorevoli e le altre vengono mantenute nel pannello. Non limita Importa lingue né elimina righe esistenti delle altre lingue. Cerca traduzioni mancanti può creare in seguito le voci corrispondenti.
- **`allow_deleting_languages`**, predefinito `false`: mostra agli amministratori le azioni Elimina per le lingue. Attivala quando intendi rimuovere lingue e traduzioni. L'endpoint verifica sia i diritti sia questo interruttore.
- **`enable_multi_host`**, predefinito `false`: aggiunge controlli dei job sugli host configurati e consente la propagazione di esportazioni/annullamenti dell'interfaccia. Lasciala disattivata per un solo progetto. I domini salvati non generano allora queste richieste. L'attivazione nelle Impostazioni richiede Domini non vuoto. La migrazione di aggiornamento attiva gli elenchi salvati non vuoti; un elenco presente solo nell'ambiente non abilita la funzione.
- **`domains`**, predefinito `null`: URL di installazioni separate da virgole per richieste tra host. Includi `http://` o `https://`, per esempio `https://one.example,https://two.example`, senza slash finale. Il codice rimuove gli spazi esterni e aggiunge i percorsi API. Inserisci solo installazioni partecipanti. Se il valore salvato è null, si usa `INTERPRESSO_MULTIPLE_DB_HOSTS`; una stringa vuota salvata non usa questo fallback. Disattiva il coordinamento prima di svuotare il campo. La validazione ne richiede la presenza quando attivo, ma non verifica sintassi o raggiungibilità delle URL.

La tabella contiene anche i campi interni `process_running`, `process_owner`, `process_started_at` e `process_expires_at`, oltre a ID e timestamp. Non sono controlli delle Impostazioni: il controller rifiuta campi fuori dalle nove colonne elencate. Un blocco temporaneo impedisce il lavoro solo se attivo e non scaduto. La migrazione aggiunge metadati nullable senza bloccare le impostazioni esistenti.

## Come vengono servite le traduzioni {#how-translations-are-served}

### Modalità database {#database-mode}

`db_loader=true` è il valore predefinito per nuove installazioni. Sia il record iniziale sia il valore predefinito della colonna sono veri. La migrazione di aggiornamento cambia il valore predefinito e cancella la selezione del loader in cache senza sovrascrivere record esistenti; il rollback ripristina il predefinito falso senza cambiare preferenze salvate.

Il loader Laravel legge le voci del database in cache per lingua, gruppo e namespace richiesti. Per righe approvate usa `value`; per le altre, `old_value`. La bozza non viene usata prima dell'approvazione. Una nuova riga non approvata può non avere un valore precedente e quindi non fornire testo approvato. Richiedere una traduzione su una riga approvata non crea un vecchio valore: anche questa può restare senza testo utilizzabile finché non viene approvata. I namespace dei pacchetti usano file quando `import_vendor` è disattivato.

I messaggi di validazione conservano i valori integrati di Laravel e le personalizzazioni `validation.php` dell'applicazione ospitante anche prima dell'importazione. Le voci di validazione nel database li sostituiscono secondo le stesse regole di approvazione/valore precedente. Gli altri gruppi dell'applicazione restano serviti esclusivamente dal database.

Anche i testi inclusi o pubblicati dell'interfaccia del pacchetto restano disponibili con l'importazione dei pacchetti attiva. Le voci revisionate nel database possono sostituirli.

Il caricamento dal database non scrive in `lang/`. Nel flusso normale importi una volta, modifichi e approvi; non ci sono file di traduzione esportati dell'applicazione che un deploy possa sovrascrivere. **`interpresso:export-translations-deployment` non serve per la distribuzione tramite database.** Le traduzioni restano nel database durante i deploy del filesystem. Quelle dei modelli richiedono ancora esportazione nelle colonne JSON del database applicativo.

L'opzione non vieta globalmente le scritture su file: Aggiungi lingua crea una directory; il comando di deploy e l'endpoint di esportazione forzata tra host non passano il vincolo dei soli modelli e possono scrivere anche con `db_loader` attivo.

**In modalità database, un database funzionante diventa indispensabile per il rendering delle richieste web.** I guasti si propagano durante la registrazione del loader web e le ricerche non in cache. Il fallback al loader da file per eccezioni di database esiste solo sotto `runningInConsole()` durante la registrazione del loader. Non copre ricerche console successive né offre un fallback web automatico durante un guasto. La cache può soddisfare singole ricerche, ma non sostituisce completamente un database operativo.

### Modalità file {#file-mode}

Con `db_loader=false`, Laravel usa il loader da file durante l'esecuzione. Il database del pacchetto continua a conservare modifiche e stato di revisione. Dopo l'approvazione, esporta PHP/JSON in `lang/` e i modelli nelle loro colonne. L'esportazione normale richiede Approvato vero, Modificato falso ed Esportato falso; quella forzata ignora solo Esportato.

I file PHP vengono esportati in `lang/{locale}/{group}.php`, JSON in `lang/{locale}.json` e le personalizzazioni dei pacchetti sotto `lang/vendor/{namespace}/`. Le chiavi idonee si uniscono al contenuto esistente. Le chiavi PHP con punti diventano array annidati: `a.b` diventa `['a' => ['b' => 'text']]`. Le chiavi letterali con punti corrispondenti di vecchie esportazioni PHP vengono rimosse alla riscrittura; le altre voci restano. JSON conserva chiavi letterali. Usa un'esportazione forzata in modalità file per riscrivere voci approvate già segnate come esportate.

La modalità file produce traduzioni utilizzabili a runtime e includibili in un artefatto di deploy. I file devono restare sincronizzati con il database delle traduzioni. Un deploy che li sostituisce può perdere il testo esportato corrente finché un'esportazione forzata lo ripristina. La modalità database evita questo passaggio, ma richiede disponibilità di database e cache. La modalità file non elimina la dipendenza del pannello dal proprio database.

### Cache e integrazione dei modelli {#cache-and-model-integration}

**`CACHE_DRIVER` non deve essere `database` in modalità database.** Se l'applicazione usa `CACHE_STORE`, vale lo stesso vincolo. Usa Redis o Memcached, oppure cache su file per un solo server. Processi web e worker devono condividere archivio e prefisso di cache. Gli host con traduzioni condivise dovrebbero condividere Redis o Memcached, affinché i cambi di versione raggiungano tutti.

Le voci di cache delle traduzioni non hanno TTL. Le chiavi includono una versione condivisa incrementata dopo il commit delle scritture, comprese importazioni in blocco, approvazioni, esportazioni ed eliminazioni del pacchetto. L'invalidazione mirata rimane. Il codice che esegue scritture in blocco evitando gli eventi dei modelli deve chiamare `Translation::invalidateCacheAfterWrite()` dopo una scrittura riuscita, affinché l'invalidazione avvenga dopo il commit. SQL diretto senza questo hook può lasciare valori obsoleti in cache.

Configura le classi in `interpresso.translatable_models`. Ogni modello deve esporre i nomi delle colonne traducibili, per esempio `public array $translatable = ['name'];`, contenenti oggetti JSON indicizzati per lingua. L'importazione copia i valori esistenti nelle righe Modello. L'esportazione aggiorna la lingua corrispondente nella colonna esistente e segna la traduzione come esportata. Modello e colonna di destinazione devono ancora esistere. Approvare nel pannello non aggiorna da solo i dati dei modelli applicativi.

## Progetto singolo e più host {#single-project-and-multi-host}

### Funzionamento con un solo progetto {#single-project-operation}

Un progetto singolo è il caso predefinito. Lascia `enable_multi_host` disattivato e Domini vuoto. Per l'uso ordinario dell'interfaccia non serve un segreto condiviso tra host. Importazioni, creazione di traduzioni mancanti, approvazioni ed esportazione normale controllano job/batch locali. Esportazione e annullamento restano locali anche se è salvato un vecchio elenco di domini.

### Configurazione degli host partecipanti {#configure-participating-hosts}

Usa il coordinamento per installazioni che richiedono lavoro ed esportazioni coordinati, normalmente sullo stesso database di traduzione tramite `INTERPRESSO_DB_CONNECTION`. Il coordinamento non replica database separati.

1. Collega le installazioni al database previsto e configura l'accesso a coda e cache.
2. Imposta lo stesso `INTERPRESSO_API_SHARED_SECRET` ovunque. Fornisce `interpresso.api_shared_api_key`, inviato come `api_key` nelle richieste in uscita.
3. Salva le URL delle installazioni, con protocollo, in **Impostazioni > Domini**.
4. Attiva **Attiva coordinamento tra host**.
5. Mantieni `INTERPRESSO_MAIN_SERVER_DOMAIN` coerente con l'installazione principale del pannello. Solo l'installazione il cui `app.url` coincide registra le route web; quelle API restano registrate nelle installazioni abilitate.

Il controllo dei job chiede agli altri host elencati se hanno lavoro in corso. Una risposta con attività blocca l'operazione. Host irraggiungibili e risposte non riuscite vengono ignorati: non è un lock distribuito né garantisce che un host indisponibile sia libero. Al termine di un'esportazione dell'interfaccia si richiedono export forzati agli altri host; l'annullamento invia POST ai loro endpoint. Si salta una URL che coincide esattamente con protocollo e host della richiesta attuale.

### API tra host {#inter-host-api}

Il pacchetto espone questi endpoint POST sotto `/api`, indipendentemente dal prefisso del pannello:

- `/api/interpresso-has-jobs-running`: indica se esistono job locali del pacchetto o batch incompleti e non annullati.
- `/api/cancelJobs`: annulla i batch locali ed elimina i job locali della coda su database del pacchetto.
- `/api/interpresso-force-export`: richiede una coda capace di rinviare il lavoro, acquisisce un blocco locale e mette in coda un export forzato per ogni lingua. Connessioni inadatte restituiscono HTTP 503 con un'alternativa CLI prima di iniziare. Con lavoro locale attivo restituisce HTTP 409 con proprietario, inizio e scadenza. Non controlla i blocchi degli altri host perché il chiamante sta ancora completando il proprio export. Può scrivere file anche in modalità database.
- `/api/interpresso-get-languages`: restituisce le lingue per il download di sviluppo.
- `/api/interpresso-get-paginated-translations`: restituisce traduzioni in pagine da 500 per quel download.

Tutti richiedono una stringa `api_key` non vuota uguale al segreto condiviso. **Senza segreto configurato, l'API nega l'accesso con HTTP 503.** Prima avviene la validazione: un `api_key` assente o di tipo errato riceve HTTP 422; con segreto configurato, una chiave diversa riceve HTTP 401. Il GET pubblico `/api/version` riporta solo la versione e non usa questo middleware di autenticazione.

Disattivare il coordinamento ferma le richieste automatiche in uscita, ma non rimuove né disattiva questi endpoint autenticati. Anche il comando di download di sviluppo usa l'API indipendentemente dall'interruttore.

## Job in background e code {#background-jobs-and-queues}

### Operazioni eseguite in background {#what-runs-in-the-background}

L'interfaccia mette importazioni di lingue/traduzioni, generazione delle mancanti, approvazioni in blocco, esportazioni e aggiornamenti con traduzione automatica in batch Laravel. I batch delle traduzioni mancanti aggiungono job man mano che trovano lavoro. I job usano `interpresso.queue_name` (`languageProcessor` predefinito), i batch `interpresso.batch_name` (`languageBatch`). Promemoria e notifiche amministrative di completamento usano la stessa coda.

Le operazioni lunghe di traduzione non vengono mai eseguite in una richiesta HTTP, neppure dopo l'invio della risposta. Prima di scrivere batch o acquisire blocchi, interfaccia ed endpoint di export forzato controllano `queue.default` e `queue.connections.<connection>.driver`. Sono supportati alias di connessione. `sync`, `null`, configurazione assente e il driver Laravel `deferred` vengono rifiutati; failover viene rifiutato se una connessione di riserva è inadatta o ciclica. Un driver asincrono configurato non dimostra che un worker sia attivo: i job attendono che li elabori.

Scegli una delle tre modalità supportate:

1. **Worker:** imposta `QUEUE_CONNECTION=database` (o `redis`) e avvia un worker supervisionato per `languageProcessor` o il tuo `interpresso.queue_name`. HTTP accoda soltanto i batch. L'esecuzione è limitata da `--timeout`, limiti di memoria/tempo di PHP CLI e del gestore processi o hosting. Per esempio, usa `php -d max_execution_time=0 artisan queue:work --queue=languageProcessor --timeout=900 --tries=1`, imposta `retry_after` sopra quel timeout (per esempio 960 secondi) e `INTERPRESSO_PROCESS_LOCK_TTL=1800`. Su SQS imposta il timeout di visibilità equivalente. Dimensiona in base al job più lungo e all'attesa in coda: il timeout è per job, non per batch. Supervisiona il worker e controlla `failed_jobs` e i log in caso di errore.
2. **Cron e comandi, senza worker:** imposta `QUEUE_CONNECTION=sync` e richiama i comandi di importazione, ricerca delle mancanti, approvazione ed esportazione. I servizi girano direttamente nel processo CLI; sync vi consegna anche le notifiche accodate. I limiti sono PHP CLI `max_execution_time`/`memory_limit`, risorse del sistema ed eventuali limiti di hosting o scheduler, senza scadenza PHP-FPM/server web. `php -d max_execution_time=0 artisan ...` elimina il limite PHP CLI, non quello dell'hosting. I comandi condividono il blocco: se occupato, riportano il proprietario e non lavorano. Controlla i log cron, perché un ritorno per occupazione non equivale a completamento riuscito.
3. **Sync solo per piccole installazioni:** mantieni `QUEUE_CONNECTION=sync`, usa l'interfaccia per consultare, modificare e revisionare singole righe ed esegui manualmente i comandi in blocco quando serve. Le normali interazioni HTTP restano limitate da PHP-FPM, timeout web PHP e server web. I pulsanti in blocco sono rifiutati a prescindere dal numero di traduzioni. Un'installazione piccola non abilita mai importazioni, esportazioni, approvazioni in batch o traduzione delle altre lingue dentro HTTP. Con più carico, usa un worker o cron.

Dopo modifiche ad ambiente/configurazione della coda, ricrea la cache di configurazione se usata (`php artisan config:cache`) e riavvia i worker persistenti. Con `null`, i servizi CLI possono funzionare, ma le notifiche accodate vengono scartate: usa `sync` per le modalità senza worker.

Un'azione in blocco rifiutata mostra un avviso con il comando CLI alternativo esatto. Per esempio, **Importa traduzioni** indica: "Non è configurato alcun worker per la coda. L'operazione verrebbe eseguita nella richiesta web e interrotta dal limite di tempo di PHP. Esegui: php artisan interpresso:import-translations". Non viene importato nulla, non parte alcun batch e non si acquisisce il blocco. Aggiornamento con traduzione automatica richiede una coda database/Redis e mostra istruzioni per il worker prima di salvare la bozza di origine. Modifica e approvazione delle singole righe restano disponibili.

| Azione interfaccia/API | Alternativa CLI |
| --- | --- |
| Importa lingue | `php artisan interpresso:import-languages` |
| Importa traduzioni | `php artisan interpresso:import-translations` |
| Cerca traduzioni mancanti | `php artisan interpresso:find-missing-translations` |
| Approva tutte le lingue | `php artisan interpresso:approve-translations --translator=1` |
| Approva una lingua | `php artisan interpresso:approve-translations --translator=1 --language=en` |
| Esporta tutte le lingue | `php artisan interpresso:export-translations` |
| Esporta una lingua | `php artisan interpresso:export-translations --language=en` |
| Esporta modelli | Aggiungi `--only-models` al comando di esportazione appropriato |
| Export forzato da API di un altro host | `php artisan interpresso:export-translations-deployment` |

L'avviso di approvazione fornisce l'ID reale dell'amministratore connesso, quelli per lingua il codice selezionato. L'API autenticata di export forzato restituisce HTTP **503** con una `message` JSON contenente il comando se la connessione non può rinviare il lavoro. `interpresso:export-translations-deployment` riproduce il comportamento dell'API riscrivendo file e modelli anche in modalità database; il comando ordinario con `--force=1` rispetta quella modalità. Ogni host ricevente necessita di una connessione differita e di un worker. Una coda supportata con blocco attivo restituisce comunque **409**.

### Crontab senza worker {#crontab-without-a-worker}

Per una configurazione concreta su Linux, imposta `QUEUE_CONNECTION=sync` nell'ambiente/configurazione dell'applicazione. Questa crontab giornaliera importa nuove sorgenti, crea le voci corrispondenti mancanti e poi esporta traduzioni già approvate. Sostituisci percorso applicativo, eseguibile PHP e orari. Il proprietario deve poter scrivere nei percorsi di storage ed esportazione. `flock` evita sovrapposizioni della sequenza sullo stesso host; il blocco del pacchetto coordina anche comandi singoli e modifiche HTTP.

```cron
SHELL=/bin/sh
PATH=/usr/local/bin:/usr/bin:/bin
15 2 * * * /usr/bin/flock -n /srv/app/storage/interpresso-cron.lock /bin/sh -c 'cd /srv/app && /usr/bin/php -d max_execution_time=0 artisan interpresso:import-languages && /usr/bin/php -d max_execution_time=0 artisan interpresso:import-translations && /usr/bin/php -d max_execution_time=0 artisan interpresso:find-missing-translations && /usr/bin/php -d max_execution_time=0 artisan interpresso:export-translations' >> /srv/app/storage/logs/interpresso-cron.log 2>&1
```

L'approvazione è una revisione deliberata e non viene pianificata nell'esempio. Dopo la revisione, esegui `php artisan interpresso:approve-translations --translator=1` con l'ID reale del traduttore amministratore, eventualmente aggiungendo `--language=en`. Per una lingua usa `php artisan interpresso:export-translations --language=en`; per riscritture forzate, `php artisan interpresso:export-translations --force=1`. Aggiungi `--only-models` per i pulsanti di esportazione dei modelli. La modalità database esporta già solo modelli. I comandi CLI sono locali e non propagano esportazioni: predisponili su ogni host nelle installazioni senza worker.

Gli utenti connessi vedono l'avanzamento dei batch. Il caricamento pagina recupera un batch attivo; il redirect di una nuova azione porta il suo ID. L'avanzamento viene interrogato ogni secondo nelle schede visibili, sospeso in quelle nascoste e fermato quando il batch termina, viene annullato o scompare. Il completamento produce un messaggio. Ricarica la tabella per vedere le righe modificate: il polling non la ricarica automaticamente.

### Perché un'azione può essere bloccata {#why-an-action-may-be-blocked}

Importazioni, ricerca delle mancanti, esportazioni, approvazione in blocco, aggiornamento con traduzione automatica e tutte le modifiche singole (aggiorna, approva, richiedi, rimuovi richiesta, ripristina) non partono se esiste un blocco attivo, un job del pacchetto o un batch incompleto non annullato. Le letture della finestra e i suggerimenti di bozza restano disponibili perché non scrivono traduzioni. I comandi operativi usano la stessa protezione. Il coordinamento aggiunge i controlli remoti descritti sopra; gli host irraggiungibili vengono ignorati.

I comandi controllano blocco locale, job, batch incompleti non annullati e host configurati se il coordinamento è attivo. Acquisiscono il blocco nelle impostazioni con un solo UPDATE condizionale prima di lavorare, anche con `QUEUE_CONNECTION=sync` e cron. Se occupato, riportano proprietario (host, PID, operazione e ID invocazione) e ora di inizio, poi tornano senza eseguire lavoro. Eccezioni ed errori PHP rilasciano il blocco del comando in `finally`.

`interpresso.process_lock_ttl` vale 900 secondi per impostazione predefinita e si configura con `INTERPRESSO_PROCESS_LOCK_TTL`. Blocchi scaduti e vecchi indicatori senza scadenza non impediscono l'acquisizione. Un'importazione lunga rinnova il blocco tra file e gruppi di modelli tramite `ProcessLock::refresh()`; operazioni personalizzate lunghe devono chiamare `refresh()` sul blocco acquisito prima che scada il TTL. Impostalo sopra la più lunga unità ininterrotta di lavoro. Database separati si coordinano al meglio tramite HTTP, non con un blocco atomico distribuito.

Le modifiche dei controller acquisiscono lo stesso blocco prima di scrivere o accodare. I batch ne mantengono la proprietà dopo la risposta HTTP. I callback lo rilasciano al completamento o errore; i job riservati verificano annullamento e proprietà prima di lavorare. Vecchi callback non possono cancellare un nuovo proprietario. I messaggi di blocco includono proprietario e inizio. Annullamento, gestione lingue/account e impostazioni restano disponibili.

La protezione locale e il servizio di annullamento interrogano `jobs` e `job_batches` sulla connessione database predefinita. `interpresso.db_connection` configura modelli e migrazioni del pacchetto ma non reindirizza tutte le query delle tabelle di coda. Mantieni la configurazione coerente con questi accessi. Eliminare righe `jobs` nel database non svuota una coda su un altro archivio.

Le notifiche amministrative di successo vengono consegnate solo per batch riusciti e non annullati; i fallimenti inviano notifiche di errore. I quattro campi di blocco e la cache delle impostazioni vengono puliti a completamento, annullamento, errore di accodamento o di job. La CLI li pulisce anche se un servizio genera un errore PHP.

### Annullamento e manutenzione dei batch {#cancel-and-maintain-batches}

Usa **Lingue > Annulla elaborazione in blocco in corso** per annullare batch incompleti e cancellare le righe `jobs` della coda del pacchetto. Può rimuovere anche notifiche accodate. L'annullamento non ripristina importazioni/modifiche/esportazioni completate né termina un worker che sta già eseguendo un job. I job verificano l'annullamento prima di entrare nei propri handler, anche se già riservati. Non c'è un controllo generale a metà job. Ispeziona il risultato prima di avviare lavoro sostitutivo.

Con coordinamento attivo, lo stesso pulsante richiede annullamento sugli altri host. Non offre un selettore per singolo job né una schermata per riprovare quelli falliti.

Esegui `interpresso:prune-batches` per rimuovere vecchi record di batch completati/annullati. Non annulla lavoro attivo né elimina job accodati/falliti. Il pacchetto non pianifica attivamente pulizia o promemoria automatici: organizzali nello scheduler o negli strumenti operativi dell'applicazione ospitante.

## Permessi e controlli condivisi {#permissions-and-shared-controls}

### Accesso di amministratori e traduttori {#administrator-and-translator-access}

Gli amministratori possono accedere a tutti i record di lingua, alle traduzioni, alla gestione traduttori e alle impostazioni. L'interfaccia include creazione/eliminazione di lingue, importazioni, generazione delle traduzioni mancanti, approvazione/esportazione in blocco, annullamento e controlli di approvazione, richiesta e ripristino.

L'interfaccia ordinaria di un non amministratore permette di:

- Elencare e cercare lingue assegnate e aprirne le traduzioni.
- Cercare, cambiare pagina e usare tutti i filtri di traduzione su queste lingue.
- Scegliere una lingua di riferimento, aprire Traduci, leggere gli esempi e modificare testi per la revisione.
- Usare OpenAI quando l'opzione e le condizioni di sorgente/esempio lo consentono.
- Leggere questo manuale, usare le notifiche, cambiare tema e uscire.

I non amministratori non vedono la barra di azioni in blocco, i controlli di approvazione/richiesta/ripristino, esportazione, gestione traduttori o impostazioni. Non hanno una schermata di gestione account.

Tutti gli endpoint privilegiati impongono l'autorizzazione amministrativa sul server. Ogni azione di riga risolve l'ID attraverso la lingua richiesta e verifica le assegnazioni; un ID estraneo restituisce 403 senza modifiche. I non amministratori possono modificare traduzioni consentite e chiedere suggerimenti di bozza, ma non approvare, richiedere traduzioni, ripristinare, aggiornare in blocco, esportare, gestire account, cambiare impostazioni o svolgere manutenzione delle lingue. Letture, cambi dello stato di lettura e marcatura globale delle notifiche sono limitati al traduttore autenticato e al tipo di destinatario.

### Navigazione, tema e notifiche {#navigation-theme-and-notifications}

La navigazione include Lingue e Manuale per i traduttori connessi, con Traduttori e Impostazioni per gli amministratori. Su schermi piccoli apri il menu compatto. Il pulsante del tema alterna chiaro/scuro e salva la preferenza in un cookie e nell'archivio del browser. Il server applica un cookie salvato prima della prima visualizzazione. Senza scelta, il foglio di stile segue il sistema prima di JavaScript e la pagina continua a seguirne i cambiamenti finché non scegli un tema.

L'interfaccia usa componenti DaisyUI con temi chiaro e scuro per moduli, tabelle, menu, notifiche ed editor. Le impostazioni usano interruttori con caselle visibili; l'editor conserva pulsante di chiusura, Esc e chiusura con clic esterno.

Il pulsante delle notifiche mostra il conteggio delle non lette. Apri il pannello sopra il pulsante per leggerle, chiudilo senza segnarle lette, rimuovi un singolo messaggio per segnarlo letto oppure usa **Segna tutte come lette**. Il pannello scorre quando serve. Le notifiche si aggiornano ogni cinque secondi nelle schede visibili e si fermano temporaneamente in quelle nascoste. Risposte invariate conservano controlli e focus. I messaggi immediati sono separati: successo, eliminazione, informazione o avviso, con pulsante di chiusura e timer.

Una preferenza esistente in `localStorage["color-theme"]` migra nel cookie `interpresso-color-theme` al primo avvio del modulo esterno del tema. Un cookie valido prevale sull'archivio del browser e aggiorna `.dark` e `data-theme`. Alla prima visita dopo un aggiornamento, il server non conosce una preferenza solo locale: questa può sostituire il tema di sistema dopo il caricamento del modulo. Le richieste successive applicano subito il cookie. Il cambio tema funziona anche senza localStorage.

Il cookie usa come percorso il prefisso URL del pacchetto, normalmente `/translator`. Il salvataggio rimuove un duplicato sul percorso con slash finale (`/translator/`) affinché un vecchio cookie più specifico non sovrascriva la nuova scelta alla richiesta successiva.

Il pacchetto abilita per impostazione predefinita header di sicurezza rigorosi per il browser. Script e stili provengono da file esterni, i dati dei messaggi sono in un attributo HTML sottoposto a escaping e l'interfaccia non può essere incorporata in un frame. I deploy CDN devono configurare le origini consentite in `interpresso.security_headers.extra_sources`; vedi [Configurazione](CONFIGURATION.md#browser-security-headers). Gli header si applicano solo alle route web del pacchetto.

## Riferimento CLI {#cli-reference}

Esegui i comandi come `php artisan ...` dalla directory dell'applicazione Laravel ospitante. Qui sono elencate tutte le dieci firme in `src/Console/Commands/`. I comandi operativi acquisiscono il blocco condiviso e possono tornare subito con proprietario e inizio se occupato: questo ritorno non equivale a un'operazione completata. Usano le impostazioni salvate salvo eccezioni indicate sotto.

### interpresso:import-languages {#interpressoimport-languages}

Firma:

```text
interpresso:import-languages
```

Importa directory di lingue supportate direttamente sotto il percorso di Laravel, saltando codici già registrati. Non scopre lingue dai soli file `{locale}.json` né importa testi. Esegue il lavoro in modo sincrono e accoda una notifica di risultato per gli amministratori. Usalo per la prima importazione o dopo l'aggiunta di directory.

```bash
php artisan interpresso:import-languages
```

### interpresso:import-translations {#interpressoimport-translations}

Firma:

```text
interpresso:import-translations
```

Importa sorgenti PHP/JSON e traduzioni dei modelli configurati per lingue esistenti. Rispetta `import_vendor` e `import_only_from_root_language`. Mantiene le coppie lingua/identificatore condiviso presenti; le nuove voci partono approvate/esportate. Riporta totali esistenti e appena inseriti. Usalo dopo nuove chiavi sorgente o valori di modelli, una volta importate le lingue. Non aggiorna traduzioni esistenti da file modificati.

```bash
php artisan interpresso:import-translations
```

### interpresso:find-missing-translations {#interpressofind-missing-translations}

Firma:

```text
interpresso:find-missing-translations
```

Controlla i conteggi per lingua e, se diversi, crea le voci corrispondenti mancanti dalla lingua di origine. Le lingue vuote sono rappresentate separatamente nel controllo. Usa `app.locale` come sorgente, o la prima lingua se assente, e può usare OpenAI. Le nuove righe sono non approvate, da tradurre e non esportate. Riporta i conteggi inseriti e accoda una notifica amministrativa per la lingua di origine se quel record esiste.

Usalo dopo aver importato chiavi di origine o aggiunto lingue. **Limite:** conteggi uguali possono nascondere insiemi di chiavi diversi; la CLI risponde allora `Everything up to date.` senza controllare gli identificatori. L'azione Cerca traduzioni mancanti dell'interfaccia chiama il servizio senza questa scorciatoia.

```bash
php artisan interpresso:find-missing-translations
```

### interpresso:approve-translations {#interpressoapprove-translations}

Firma:

```text
interpresso:approve-translations {--translator=} {--language=}
```

Approva in modo sincrono tutte le traduzioni non approvate, oppure solo quelle di `--language=en`. `--translator=ID` è obbligatorio e deve identificare un traduttore amministratore esistente; le approvazioni vengono attribuite a quell'ID. Attribuzione non valida o lingua sconosciuta terminano con stato 1 senza scritture. Usa il blocco condiviso, lo rinnova tra lingue, invalida le cache con il servizio di approvazione esistente e invia notifiche amministrative. Funziona con `QUEUE_CONNECTION=sync` senza worker. Rivedi i testi prima di eseguirlo.

```bash
php artisan interpresso:approve-translations --translator=1
php artisan interpresso:approve-translations --translator=1 --language=en
```

### interpresso:export-translations {#interpressoexport-translations}

Firma:

```text
interpresso:export-translations {--force=} {--language=} {--only-models}
```

Esporta traduzioni approvate e non modificate. Normalmente include solo righe segnate come non esportate. `--force` accetta un valore convertito in booleano: usa `--force=1` per includere righe già esportate; ometterlo o usare `--force=0` mantiene il comportamento normale. Forzare non aggira approvazione né protezione dai job attivi.

In modalità file esporta PHP/JSON e modelli. In modalità database passa l'opzione dei soli modelli e salta i file. `--only-models` limita ai modelli anche in modalità file. `--language=en` limita conteggio ed esecuzione a quella lingua; un codice sconosciuto fallisce senza esportare. Gira in modo sincrono e accoda notifiche amministrative, senza avviare esportazioni su altri host. I conteggi riguardano righe di traduzione, non file.

Usalo per la pubblicazione ordinaria o, in modalità file, per riscrivere forzatamente file le cui righe sono già segnate esportate.

```bash
php artisan interpresso:export-translations
php artisan interpresso:export-translations --force=1
```

### interpresso:export-translations-deployment {#interpressoexport-translations-deployment}

Firma:

```text
interpresso:export-translations-deployment
```

Esporta in modo sincrono e forzato le traduzioni approvate non modificate di ogni lingua, modelli inclusi, ignorando Esportato. Non accetta `--force`. Usa la protezione condivisa, non propaga export e non passa il vincolo dei soli modelli del loader database: può quindi scrivere file anche con `db_loader=true`.

Usalo dopo un deploy in modalità file che abbia sostituito le traduzioni esportate. Non serve per la distribuzione tramite database: omettilo da quel flusso. Stampa il completamento per ogni lingua anche senza contenuto idoneo.

```bash
php artisan interpresso:export-translations-deployment
```

### interpresso:prune-batches {#interpressoprune-batches}

Firma:

```text
interpresso:prune-batches
```

Elimina le righe corrispondenti a `interpresso.batch_name` da `job_batches` su `interpresso.db_connection` se completate o annullate da più di `interpresso.prune_batch_hours`, 24 ore per impostazione predefinita. Non tocca batch attivi, job in coda o record di errore. Non prevede opzioni specifiche né output di completamento.

Usalo per pulire i batch conservati. Pianificalo nell'applicazione se serve una pulizia ricorrente; il codice di pianificazione del pacchetto è inattivo.

```bash
php artisan interpresso:prune-batches
```

### interpresso:send-automatic-pending-translations-notification {#interpressosend-automatic-pending-translations-notification}

Firma:

```text
interpresso:send-automatic-pending-translations-notification
```

Questo è il nome effettivo implementato da `SendAutomaticPendingNotifications`; `interpresso:send-automatic-pending-notifications` non è un alias registrato.

Se `enable_automatic_pending_notifications` è vero, scorre tutti i traduttori, amministratori inclusi, e le loro assegnazioni esplicite. Per ogni lingua con righe Da tradurre accoda notifiche su database ed email. Zero righe in sospeso non producono invii. Se l'opzione è falsa, non fa nulla. Non richiede `enable_pending_notifications`, usa il blocco condiviso e non stampa un riepilogo di successo.

Usalo per promemoria ricorrenti con una tua pianificazione, trasporto email funzionante e worker del pacchetto. Nessuna pianificazione attiva viene registrata automaticamente; ripetere il comando può inviare un altro promemoria per lo stesso lavoro in sospeso.

```bash
php artisan interpresso:send-automatic-pending-translations-notification
```

### interpresso:developer-download {#interpressodeveloper-download}

Firma:

```text
interpresso:developer-download
```

Scarica lingue e traduzioni paginate da `interpresso.main_server_domain`, configurato tramite `INTERPRESSO_MAIN_SERVER_DOMAIN`, usando `INTERPRESSO_API_SHARED_SECRET`. Sostituisce righe locali di lingue e traduzioni, poi esporta forzatamente il contenuto scaricato approvato e non modificato. La modalità file esporta file e modelli; quella database solo modelli. Impostazioni, account e assegnazioni non vengono scaricati.

Usalo solo per sostituire intenzionalmente una copia di sviluppo con i dati del server principale. **Elimina/sostituisce il lavoro locale di traduzione senza chiedere conferma.** Si applica il blocco condiviso. Non esistono controllo dell'ambiente locale, simulazione, argomento host o modalità di unione. La fase database usa una transazione e istruzioni MySQL/MariaDB `SET FOREIGN_KEY_CHECKS`, quindi non è portabile così com'è su SQLite o PostgreSQL. L'export segue il commit: un errore di esportazione non annulla la sostituzione del database. Le destinazioni locali dei modelli devono esistere.

```bash
php artisan interpresso:developer-download
```

### interpresso:unlock {#interpressounlock}

Firma:

```text
interpresso:unlock {--force}
```

Stampa proprietario e inizio registrati. Senza `--force`, rimuove blocchi scaduti o legacy e rifiuta uno attivo con codice 1. `--force` elimina anche un blocco attivo. Il rilascio riuscito termina con 0 e pulisce `process_running`, `process_owner`, `process_started_at` e `process_expires_at`. Liberare un record già sbloccato è innocuo. La pulizia dei soli blocchi scaduti usa un UPDATE condizionale per non cancellare acquisizioni o rinnovi concorrenti.

```bash
php artisan interpresso:unlock
php artisan interpresso:unlock --force
```

Sbloccare non termina processi PHP, annulla record di coda o ripristina modifiche. Ferma o verifica il vecchio processo prima di forzare un blocco attivo. Job/batch esistenti possono ancora impedire il lavoro: usa **Annulla elaborazione in blocco in corso** per annullarli. L'annullamento libera solo il blocco del batch interessato, non quello di un cron separato.

## Risoluzione dei problemi {#troubleshooting}

### Job che non terminano o segnalazione di un altro processo {#jobs-do-not-finish-or-another-process-is-reported}

Per code asincrone, verifica che un worker elabori la coda configurata, normalmente `languageProcessor`. I comandi cron/artisan con `QUEUE_CONNECTION=sync` non richiedono worker per le traduzioni; le azioni HTTP in blocco rifiutano sempre sync. Controlla proprietario, inizio e scadenza; usa `interpresso:unlock` per metadati obsoleti e `--force` solo dopo aver verificato che la vecchia esecuzione sia ferma. Esamina i `jobs` della coda del pacchetto, i batch `languageBatch` incompleti, log e job falliti. Anche le notifiche pendenti possono occupare la coda. Un worker che ascolta solo la coda predefinita non elabora quella del pacchetto.

Dopo aver controllato se il lavoro è ancora attivo, usa **Annulla elaborazione in blocco in corso** per quello abbandonato. Pulire vecchi batch non è annullarli. Con più host, controlla connessioni e segreti: un host indisponibile viene ignorato nel controllo di occupazione, ma la propagazione dell'export può fallire. Rivedi i limiti sopra descritti delle connessioni delle tabelle e delle code non basate su database.

### Lingua, chiave o esempio sorgente mancante {#a-language-key-or-source-example-is-missing}

Importa lingue riconosce solo directory supportate. Aggiungi esplicitamente una lingua per sorgenti solo JSON. Assicurati che `lang/` esista prima dell'importazione: la modalità database non crea directory sorgente mancanti durante l'importazione. Verifica i record di origine/fallback e aggiorna l'elenco dopo gli import in background.

Importa traduzioni visita solo lingue salvate e le opzioni origine sola/pacchetti possono escludere sorgenti. Reimportare non sovrascrive valori esistenti. Cerca traduzioni mancanti copia identificatori di origine, non chiavi esclusive di altre lingue; la scorciatoia CLI sui conteggi uguali può perdere differenze. Usa in quel caso l'interfaccia. Un record `app.fallback_locale` assente impedisce il montaggio dell'editor: ricrea la lingua.

### Filtri o impostazioni sembrano non salvarsi {#filters-or-settings-appear-not-to-save}

Ricerca e filtri ricaricano con parametri URL. I campi delle impostazioni si inviano singolarmente alla modifica. Attendi la fine della navigazione e controlla gli errori accanto ai campi rifiutati. Salva Domini prima di attivare il coordinamento e disattivalo prima di svuotarli. Se i controlli non rispondono, ricompila e ripubblica le risorse del pacchetto, poi controlla errori JavaScript o di rete. Il polling di batch e notifiche si sospende intenzionalmente nelle schede nascoste.

### Contenuti approvati non visibili o file invariati {#approved-content-is-not-visible-or-files-are-unchanged}

In modalità file, approva le modifiche ed esportale. Gli export normali saltano righe non approvate, modificate o già esportate; usa `--force=1` solo per riesportare contenuti approvati non modificati. Verifica permessi e validità delle sorgenti PHP/JSON. JSON esistente malformato ed errori di codifica interrompono l'export anziché sostituire silenziosamente il file con contenuto non valido.

In modalità database, l'assenza di scritture su file è prevista nel flusso normale. Controlla approvazione, vecchio valore, configurazione cache e prefisso condiviso. Esportato falso non impedisce la distribuzione da database. Le colonne dei modelli necessitano ancora di export. Comando di deploy ed endpoint di export forzato possono scrivere file nonostante l'impostazione database.

### Errori di database, cache o impostazioni {#database-cache-or-settings-failures}

Ripristina la connessione al database in caso di guasto web in modalità database: non esiste fallback automatico ai file. `CACHE_DRIVER`/`CACHE_STORE` devono usare un archivio non database, con configurazione appropriata condivisa tra worker e web. Le scritture del pacchetto invalidano voci versionate dopo il commit; quelle esterne in blocco richiedono l'hook di invalidazione.

Se la tabella delle impostazioni manca o non ha righe, la selezione può usare il loader Laravel da file. Le operazioni che richiedono impostazioni lanciano `MissingSettingsException` se manca il record. Ripristinalo: il pacchetto non lo ricrea né sostituisce automaticamente le preferenze. Dopo una riparazione esterna all'interfaccia, aggiorna la cache con `Setting::getFreshCached()` nel codice di manutenzione.

### OpenAI o promemoria email senza effetto {#openai-or-pending-email-does-nothing}

Controlla opzione, pacchetto OpenAI facoltativo, configurazione API, esempio scelto e log. Un errore OpenAI può restituire il testo sorgente invariato. I pulsanti dipendono dalla lingua di origine e dalla presenza di esempi; il testo generato richiede comunque revisione e approvazione.

I promemoria contano Da tradurre, non tutte le righe non approvate. Verifica assegnazioni esplicite, trasporto email e worker. Le opzioni manuale e automatica sono indipendenti e per la seconda non viene installata alcuna pianificazione. Segnare una notifica letta non modifica lo stato della traduzione.

### Errori di accesso, route e comunicazione tra host {#access-routes-and-inter-host-errors}

Per route dell'interfaccia assenti, controlla `INTERPRESSO_ENABLED`, prefisso e corrispondenza esatta tra server principale e `app.url`. Per un 403 a un non amministratore, verifica assegnazioni e restrizioni della schermata. Per azioni di riga mancanti, controlla condizioni di approvazione/richiesta/valore precedente.

HTTP 503 tra host indica, dopo la validazione della richiesta, un segreto condiviso non configurato; HTTP 401 una mancata corrispondenza e HTTP 422 può indicare `api_key` assente o non valido. Configura lo stesso segreto ovunque e usa URL con protocollo. Il coordinamento disattivato non disabilita l'API.

### Eccezioni di importazione, esportazione modelli o download di sviluppo {#import-model-export-or-developer-download-exceptions}

Controlla percorso e identificatore d'errore nei log per sorgenti non valide o errori di inserimento. Valori sorgente, namespace o gruppi null non sono metadati validi per copiare traduzioni mancanti. Le traduzioni applicative usano namespace vuoto; JSON usa gruppo vuoto. Gli export di modelli richiedono classe Eloquent, record esistente e colonna JSON di traduzione esistente. Destinazioni mancanti generano un'eccezione invece di risultare silenziosamente esportate.

Il download di sviluppo richiede SQL compatibile MySQL/MariaDB, endpoint del server principale raggiungibili e segreto corretto. Sostituisce record locali e fa commit prima dell'export: individua la fase fallita prima di ripetere. Traduttori e assegnazioni locali non vengono sincronizzati dal download.
