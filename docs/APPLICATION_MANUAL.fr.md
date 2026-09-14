Ceci est une traduction de l'original anglais, qui reste la référence faisant autorité.

# Manuel d'utilisation

Ce manuel s'affiche dans l'interface de traduction à la route `interpresso.manual`, généralement `/translator/manual`. Utilisez le sommaire généré pour naviguer. Sur les écrans larges, il reste à côté de l'article et défile dans la fenêtre pour que toutes les sections restent accessibles. Les quatre espaces de travail sont Langues, Traductions, Traducteurs et Paramètres.

Chaque traducteur peut choisir la langue de l'interface indépendamment de `app.locale` de l'application hôte. L'anglais (`en`), l'allemand (`de`), le français (`fr`), l'espagnol (`es`) et l'italien (`it`) sont inclus. Le manuel charge `docs/APPLICATION_MANUAL.{locale}.md` pour la langue active et utilise `docs/APPLICATION_MANUAL.md` si aucune traduction n'existe. L'original anglais reste la référence. Les titres traduits conservent les ancres des sections de l'original.

## Premiers pas {#getting-started}

### Installer, publier et migrer {#install-publish-and-migrate}

Le package nécessite PHP 8.2 ou ultérieur dans la branche PHP 8 et Laravel 12 ou 13, conformément à `composer.json`. Exécutez les commandes d'installation depuis le répertoire de votre application Laravel :

```bash
composer require anymedialtd/interpresso-laravel
php artisan vendor:publish --tag=interpresso-config
php artisan vendor:publish --tag=interpresso-migrations
php artisan vendor:publish --tag=interpresso-public
php artisan migrate
```

La découverte automatique de Composer enregistre les fournisseurs de services du package. La publication de la configuration comprend `config/interpresso.php` et `config/openai.php`. Les ressources utilisées par l'interface sont publiées dans `public/vendor/interpresso/css/app.css` et `public/vendor/interpresso/js/app.js`. Publier `interpresso-translations` ou `interpresso-views` est facultatif et permet de personnaliser les textes ou les modèles de l'interface. Le package charge également ses migrations directement.

Les installations existantes doivent suivre [le passage à l'identité Interpresso](INSTALLATION.md#adopting-the-interpresso-identity) avant de migrer. Les tables par défaut utilisent désormais `interpresso_` ; définissez les options `table_*` avec les noms existants pour conserver leurs données. Configuration, intégrations PHP, planifications, personnalisations publiées et tous les hôtes API participants doivent adopter la nouvelle identité ensemble.

Les migrations créent les tables des langues, traductions, traducteurs, affectations et paramètres, ainsi que les tables de file d'attente, lots, tâches échouées et notifications si elles manquent. Définissez `INTERPRESSO_DB_CONNECTION` et les noms de tables personnalisés dans `config/interpresso.php` avant de migrer. Une langue correspondant à `config('app.fallback_locale')` est créée ; elle doit figurer dans la liste des langues prises en charge.

Les nouvelles installations activent `db_loader` et désactivent la coordination entre hôtes. Avant de servir des traductions, choisissez un cache hors base de données et importez les sources comme indiqué ci-dessous. Les opérations de l'interface mises en file d'attente nécessitent une connexion asynchrone et un worker consommant la file du package, généralement :

```bash
php artisan queue:work --queue=languageProcessor
```

Sur un hébergement mutualisé sans Supervisor, utilisez `QUEUE_CONNECTION=database` et activez `interpresso.schedule.queue_worker` avec [une ligne cron](#cron-without-supervisor). C'est la configuration recommandée ; les boutons fonctionnent normalement.

Le stockage du cache et la connexion de file d'attente sont deux paramètres distincts. Une file reposant sur une base de données est compatible avec un cache utilisant un autre stockage.

### Se connecter et modifier le mot de passe par défaut {#sign-in-and-change-the-default-password}

L'adresse de connexion par défaut est `/translator/login`. Lorsqu'aucun traducteur n'existe, la migration crée cet administrateur :

- E-mail : `admin@admin.com`
- Mot de passe : `aaaaaaaa`
- Prénom et nom : `admin`

Connectez-vous, ouvrez **Traducteurs**, modifiez l'administrateur et utilisez immédiatement **Modifier le mot de passe**. Choisissez un mot de passe d'au moins huit caractères et confirmez-le à l'identique. Modifier le profil seul ne change pas le mot de passe. Le traducteur d'identifiant `1` ne peut pas être supprimé depuis l'application, mais son profil et son mot de passe restent modifiables.

Le formulaire de connexion comprend l'e-mail, le mot de passe et **Rester connecté**. Des identifiants incorrects vous laissent sur cette page. La connexion est limitée à dix tentatives par adresse IP et par minute ; le délai avant une nouvelle tentative s'affiche au-delà. Les comptes utilisent le guard de session `interpresso_translator` du package. Utilisez **Se déconnecter** dans la navigation pour terminer la session.

Les routes de l'interface ne sont enregistrées que si `config('interpresso.main_server_domain')` correspond exactement à `config('app.url')`. L'adresse du serveur principal provient de `INTERPRESSO_MAIN_SERVER_DOMAIN`, avec l'URL de l'application comme valeur par défaut. `INTERPRESSO_ENABLED=false` désactive l'enregistrement de l'interface et de l'API ainsi que le chargeur personnalisé. Les préfixes de routes et chemins des écrans sont configurables dans `config/interpresso.php`.

### Première importation {#first-import}

La **langue source** correspond à `config('app.locale')`. La **langue de repli** correspond à `config('app.fallback_locale')` ; c'est également la langue de référence initiale de l'éditeur. Elles peuvent être différentes.

1. Placez vos fichiers sources dans le répertoire de langues de Laravel, généralement `lang/`. Vérifiez la configuration des langues source et de repli.
2. Ouvrez **Langues**, puis **Importer les langues**. Les répertoires portant un code pris en charge, comme `lang/en/` ou `lang/de/`, sont reconnus. Un fichier JSON tel que `lang/de.json` ne suffit pas à créer une langue : utilisez **Ajouter une langue** ou créez son répertoire.
3. Attendez la fin du traitement, actualisez la liste, puis lancez **Importer les traductions**. Cette action importe les entrées PHP et JSON des langues déjà enregistrées en base ainsi que les traductions des modèles configurés. Réglez d'abord les options d'importation des packages et de la langue source.
4. Lancez **Rechercher les traductions manquantes** pour créer, dans les autres langues, les entrées correspondant à celles de la langue source. Vérifiez-les, traduisez-les et validez-les.
5. En mode base de données, les traductions d'application validées sont servies depuis la base. En mode fichiers, exportez après validation. Dans les deux modes, les traductions de modèles doivent être exportées vers les colonnes des modèles de l'application.

L'équivalent initial en ligne de commande est :

```bash
php artisan interpresso:import-languages
php artisan interpresso:import-translations
php artisan interpresso:find-missing-translations
```

Les importations ajoutent les enregistrements manquants sans écraser les traductions existantes lorsqu'un fichier source change. Les importations de fichiers et de modèles marquent initialement les entrées comme validées et exportées. Les entrées créées par la recherche des traductions manquantes sont non validées, à traduire et non exportées, même si OpenAI fournit leur texte.

## Langues {#languages}

### Parcourir, rechercher, ajouter et supprimer {#browse-search-add-and-delete}

La liste affiche le code, le nom et le nom dans la langue d'origine, triés par code, avec dix lignes par page. **Rechercher** porte sur ces trois champs. **Afficher** ouvre les traductions d'une langue. Les administrateurs voient toutes les langues ; les autres traducteurs ne voient que leurs affectations. La pagination propose les pages précédente, suivante et numérotées si nécessaire.

**Ajouter une langue** ouvre la liste des langues prises en charge. Choisissez un code inutilisé et cliquez sur **Ajouter**. Les doublons et codes non pris en charge sont refusés. Cette action crée un enregistrement et, s'il manque, son répertoire sous `lang/`, y compris en mode base de données. Elle ne crée pas de traductions. **Fermer** masque le formulaire.

L'action **Supprimer** d'une ligne n'apparaît que pour les administrateurs lorsque `allow_deleting_languages` est activé. Elle retire les affectations et supprime la langue ; ses traductions sont supprimées par la clé étrangère en cascade de la base. Les fichiers et le répertoire de langue restent présents, donc une importation ultérieure peut recréer la langue. Aucun dialogue de confirmation n'est affiché. Conservez la langue de repli si ses valeurs doivent rester disponibles comme exemples.

### Importer et compléter les entrées manquantes {#import-and-fill-missing-entries}

Les opérations suivantes de la barre d'outils sont proposées aux administrateurs :

- **Importer les langues** met en file la recherche des répertoires de langues pris en charge. Les codes existants sont ignorés.
- **Importer les traductions** met en file les importations PHP, JSON, des packages si activées, et des modèles configurés. L'opération couvre les langues configurées, pas seulement les résultats visibles de la recherche.
- **Rechercher les traductions manquantes** met en file la création d'entrées selon leur identifiant de traduction commun. La source est la langue correspondant à `app.locale`, ou la première langue si cet enregistrement manque. Seuls les identifiants de la langue source sont copiés vers les autres langues. Cette action ne recherche pas les appels de traduction dans le code et ne fusionne pas les clés de toutes les langues dans la source. Les entrées correspondantes déjà présentes sont conservées. Avec OpenAI activé, les valeurs manquantes sont proposées à la traduction automatique ; sinon, le texte source est copié pour être modifié plus tard.

L'importation des packages lit les personnalisations publiées sous `lang/vendor/{namespace}/` avant leur répertoire de traduction enregistré, afin de conserver une personnalisation déjà importée. Les fichiers PHP doivent renvoyer des tableaux ; les fichiers JSON doivent pouvoir être décodés en tableaux. Les clés imbriquées sont aplaties pour le stockage et les sous-répertoires PHP sont conservés dans le nom du groupe.

### Valider, exporter et annuler {#approve-export-and-cancel}

**Valider les traductions de toutes les langues** met en file la validation de toutes les entrées non validées, indépendamment des recherches et filtres. La validation efface les indicateurs À traduire et Modifié, supprime l'ancienne valeur sauvegardée et enregistre l'administrateur qui valide. La validation en masse n'exige pas de valeur non vide : vérifiez les textes manquants au préalable.

Lorsque `db_loader` est désactivé, **Exporter toutes les langues** met en file l'exportation des langues contenant des entrées validées, non modifiées et non exportées. Les fichiers de l'application, des packages et les traductions de modèles sont exportés. Sans entrée admissible, une notification indique qu'aucun élément n'a été exporté.

Lorsque `db_loader` est activé, **Exporter tous les modèles traduits** limite la sélection initiale et chaque tâche d'exportation aux entrées de modèles. Les entrées PHP/JSON restent destinées à la diffusion par la base.

Après la réussite d'un lot d'exportation lancé dans l'interface, sans annulation, la coordination entre hôtes, si activée, demande une exportation forcée sur les autres hôtes configurés. La commande d'exportation ordinaire ne propage pas cette demande.

**Annuler le traitement par lot en cours** marque les lots inachevés du package comme annulés et retire les tâches de sa file en base de données. Si la coordination est active, l'annulation est aussi demandée aux autres hôtes configurés. Le résultat indique les tâches et lots locaux concernés. Voir **Tâches en arrière-plan et files d'attente** pour les limites de l'annulation.

## Traductions {#translations}

Ouvrez une langue avec **Langues > Afficher**. La route par défaut est `/translator/translations/{language}`, nommée `interpresso.translations`. Un traducteur non administrateur doit être affecté à cette langue pour accéder à l'écran.

### Tableau, recherche et filtres à sélection multiple {#table-search-and-multi-select-filters}

Le tableau affiche ID, Contenu de package, Espace de noms, Groupe, À traduire, Validé, Validé par, Modifié, Modifié par, Exporté, Clé, Contenu et Ancien contenu. Les auteurs des modifications et validations sont affichés par leur nom ; les filtres les proposent par adresse e-mail. Les valeurs sont affichées en texte brut. Ouvrez **Traduire** pour modifier la valeur complète. Le tableau contient vingt lignes par page.

**Rechercher** porte sur l'ID, l'espace de noms, le groupe, la clé, la valeur actuelle et l'ancienne valeur. Une nouvelle recherche revient à la première page.

Trois menus à sélection multiple précisent les résultats :

- **Type** : PHP, JSON ou Modèle. Les entrées de modèle correspondent aux colonnes de traduction Eloquent configurées.
- **Modifié par** : traducteurs enregistrés comme auteurs de la modification actuelle.
- **Validé par** : traducteurs enregistrés comme auteurs de la validation actuelle.

Plusieurs options dans un même menu correspondent à l'une quelconque des options choisies. Les différents menus, filtres d'état et recherche se cumulent. Désélectionnez toutes les options d'un menu pour supprimer sa restriction. Il n'existe pas de choix distinct pour les lignes sans auteur de modification ou de validation.

### Cinq filtres à trois états {#five-three-state-filters}

Ouvrez **Filtres d'état**. Chaque bouton passe de **gris : aucune restriction** à **vert : vrai**, puis **rouge : faux**, et revient au gris. Chacun filtre sa colonne booléenne enregistrée :

- **À traduire** (`needs_translation`) : une traduction est demandée ou une entrée correspondante manquante a été créée. Faux signifie que l'indicateur de demande est désactivé, sans garantir que la ligne est validée.
- **Validé** (`approved`) : l'entrée est validée. Faux inclut les entrées demandées/manquantes et les textes modifiés en attente de validation.
- **Modifié** (`updated_translation`) : une valeur modifiée attend sa validation. Il s'agit d'un indicateur du processus, pas d'un filtre de date ni d'un historique permanent.
- **Contenu de package** (`is_vendor`) : la ligne est identifiée comme provenant d'un package.
- **Exporté** (`exported`) : l'indicateur en base marque la ligne comme exportée. Il ne vérifie pas les fichiers et n'est pas requis pour la diffusion en mode base de données.

Pour les demandes de traduction, mettez À traduire en vert. Pour relire après modification, mettez Validé en rouge et Modifié en vert. Pour les exportations ordinaires de fichiers, utilisez Validé vert, Modifié rouge et Exporté rouge. Ces filtres affectent uniquement le tableau ; validation et exportation en masse utilisent leurs propres requêtes sur toute la langue.

La recherche est envoyée après une courte pause de saisie ou avec Entrée. Les changements de filtres d'état et de sélection multiple envoient des formulaires GET, rechargent la page et réinitialisent la pagination. Les URL conservent `search`, `page`, `needs_translation`, `approved`, `updated_translation`, `is_vendor`, `exported`, `types[]`, `updatedBy[]` et `approvedBy[]`. Un bouton d'état fait passer le paramètre d'absent à `true`, puis `false`, puis absent. Favoris, rechargement et navigation précédent/suivant restaurent les choix. Le filtre d'affectation utilise `selectedLanguages[]`.

### Langue de référence et fenêtre de traduction {#example-language-and-translate-modal}

Choisissez **Langue de référence** avant d'ouvrir **Traduire** sur une ligne. Ce choix détermine l'exemple source, pas la langue modifiée. Par défaut, il utilise `app.fallback_locale` si cette langue est autorisée, sinon la langue actuelle. Si l'exemple sélectionné manque ou est vide, l'éditeur utilise la valeur autorisée de la langue de repli.

La fenêtre affiche l'identifiant de traduction, le texte de référence, une zone de saisie avec la valeur actuelle et des résumés dépliables par code de langue pour les exemples partageant l'identifiant. Pour les non-administrateurs, le sélecteur, les exemples et la recherche de repli se limitent aux langues affectées. Le texte de référence est échappé et n'est pas interprété comme du HTML.

Utilisez **Fermer la fenêtre**, Échap ou un clic à l'extérieur pour fermer l'éditeur sans enregistrer les modifications de la zone de saisie. Le focus revient au bouton Traduire.

### Mise à jour et actions OpenAI {#update-and-openai-actions}

**Mettre à jour la traduction** n'enregistre que si le texte diffère de la valeur stockée. Au début d'une modification, la valeur précédente et ses auteurs de modification/validation sont sauvegardés. Le traducteur actif devient l'auteur de la modification, l'auteur de validation actuel est effacé et la ligne devient modifiée, non validée et non exportée. À traduire est également désactivé. Les modifications suivantes avant validation conservent la même valeur d'origine. Enregistrer ne valide ni n'exporte automatiquement le texte.

**Traduire avec OPENAI** apparaît si OpenAI est activé et si un exemple autorisé non vide existe. La langue source est celle de l'exemple effectivement utilisé, y compris après repli. Une suggestion remplit une zone encore intacte sans enregistrer. Si vous avez déjà modifié le brouillon ou le modifiez pendant la requête, la suggestion s'affiche séparément avec **Appliquer la suggestion**. Votre brouillon est conservé jusqu'à son remplacement explicite. Relisez, puis utilisez **Mettre à jour la traduction** pour enregistrer. Chargement et erreurs laissent l'éditeur utilisable ; fermer la fenêtre annule la requête en cours.

Une réponse serveur vide ou mal formée, ou une suggestion sans valeur textuelle, affiche une erreur et préserve votre brouillon. Vous pouvez réessayer ou enregistrer votre propre texte.

**Mettre à jour et traduire les autres langues automatiquement (OPENAI)** est réservé aux administrateurs et apparaît lorsque OpenAI est actif et que la langue courante est la langue source. Si le texte a changé, il est enregistré une fois, puis la mise à jour des autres traductions existantes partageant son identifiant est mise en file. Aucun équivalent absent n'est créé ; utilisez d'abord Rechercher les traductions manquantes. Les résultats restent non validés et non exportés.

OpenAI est facultatif et nécessite `openai-php/laravel`, des paramètres API valides dans `config/openai.php` et l'activation de la fonction. Le package envoie le texte source à OpenAI et lui demande de préserver les paramètres Laravel tels que `:name` ; vérifiez le résultat. `interpresso.open_ai_model` choisit le modèle demandé et `interpresso.max_open_ai_missing_trans` règle la taille des groupes de requêtes pour les traductions manquantes. Une requête échouée, une intégration absente ou une réponse comportant des clés manquantes/supplémentaires ou des valeurs non textuelles préserve l'entrée d'origine du service. Dans la fenêtre, cette entrée est l'exemple : une phrase source inchangée ne prouve donc pas la réussite de la traduction. Des langues manquantes ou une entrée null sont renvoyées sans modification ni requête ; les requêtes par tableau conservent les chaînes vides et `"0"`.

### Valider, demander, retirer une demande et restaurer {#approve-request-remove-request-and-restore}

Les administrateurs disposent des actions suivantes :

- **Valider** apparaît pour une ligne non validée dont la valeur actuelle n'est pas vide. L'action accepte le texte, enregistre l'auteur de la validation, efface À traduire et Modifié, supprime Ancien contenu et les anciennes références d'auteurs, puis invalide les caches de traduction. Elle n'écrit aucun fichier et ne met pas Exporté à vrai. La chaîne `"0"` est valide. Le bouton est absent pour une valeur vide ; la validation en masse ne fait pas ce contrôle.
- **Demander une traduction** apparaît lorsque À traduire est faux. L'action met À traduire à vrai et Validé à faux. Elle ne sauvegarde pas d'ancienne valeur et n'envoie pas d'e-mail à elle seule.
- **Retirer la demande de traduction** apparaît lorsque À traduire est vrai. L'action met À traduire à faux et Validé à vrai. Les autres indicateurs ne changent pas ; ce n'est pas l'équivalent de l'action complète Valider.
- **Restaurer** apparaît pour une ligne non validée ayant un Ancien contenu sauvegardé, même vide ou égal à `"0"`. L'action restaure cette valeur et ses auteurs précédents, efface l'historique sauvegardé et Modifié, puis marque la ligne comme validée et exportée. Elle n'écrit aucun fichier et n'efface pas explicitement À traduire. Une seule ancienne valeur est conservée, pas un historique de versions ; après sa suppression par validation, Restaurer n'est plus disponible.

### Valider et exporter toute une langue {#approve-and-export-a-whole-language}

**Valider les traductions ({code de langue})** met en file toutes les lignes non validées de la langue actuelle, y compris hors filtres et avec une valeur vide.

En mode fichiers, **Exporter la langue** met en file l'exportation des lignes validées ayant Modifié faux et Exporté faux, y compris les traductions de modèles admissibles. En mode base de données, **Exporter les modèles traduits** limite le contrôle d'admissibilité et la tâche aux modèles. Aucun bouton ne force la réexportation des entrées déjà exportées. Utilisez l'option CLI `--force=1` si nécessaire. Ces actions de la barre d'outils sont réservées aux administrateurs.

## Traducteurs {#translators}

### Parcourir et filtrer les comptes {#browse-and-filter-accounts}

Cet écran, généralement `/translator/translators`, est réservé aux administrateurs. Il affiche ID, prénom, nom, e-mail, téléphone, statut administrateur et langues affectées, avec dix comptes par page.

**Rechercher** porte sur l'ID, le prénom, le nom, l'e-mail ou le téléphone. La sélection multiple de langues recherche les traducteurs affectés à **toutes les langues sélectionnées**. La vider supprime cette restriction. L'accès automatique d'un administrateur à toutes les langues ne crée pas d'affectations ; il peut donc être exclu par ce filtre.

### Créer, modifier, affecter des langues et supprimer {#create-edit-assign-languages-and-delete}

Ouvrez le formulaire de création pour saisir e-mail, téléphone, prénom, nom, mot de passe, confirmation, affectations de langues et droits d'administration. L'e-mail doit être valide et unique ; prénom et nom doivent comporter au moins deux caractères. Le téléphone est facultatif mais doit être unique s'il est renseigné. Le mot de passe nécessite au moins huit caractères et une confirmation identique.

Un non-administrateur doit avoir au moins une affectation valide. Les identifiants d'affectation dupliqués ou inexistants sont refusés. Les administrateurs peuvent être enregistrés sans affectations et accéder à toutes les langues. Les affectations explicites déterminent néanmoins les notifications de langues reçues.

Après votre choix, fermez le menu des affectations avec le bouton Langues, puis envoyez le formulaire. Les erreurs de validation s'affichent à côté des champs, y compris les langues et confirmations de mot de passe. Les formulaires refusés conservent les valeurs du profil ; les mots de passe doivent être ressaisis.

**Créer** enregistre un nouveau compte. **Modifier** ouvre un compte existant ; **Mettre à jour** enregistre le profil, les droits d'administration et la liste des affectations choisies, qui remplace la précédente. **Fermer** masque le formulaire. La création n'envoie ni invitation ni e-mail de mot de passe.

Utilisez **Supprimer** sur une ligne pour retirer un compte. Le traducteur d'identifiant `1` n'a pas de bouton Supprimer et son endpoint refuse l'opération. Les autres suppressions sont envoyées directement sans confirmation.

### Mots de passe et notifications de traductions en attente {#password-changes-and-pending-notifications}

Lors de la modification d'un compte, **Modifier le mot de passe** ouvre un formulaire distinct. Saisissez le nouveau mot de passe et sa confirmation, puis utilisez son bouton de mise à jour. **Fermer** revient au profil. Cette modification administrative ne demande pas le mot de passe actuel. Aucun écran autonome de changement ou réinitialisation n'est proposé aux non-administrateurs.

Lorsque `enable_pending_notifications` est activé, le formulaire affiche le bouton de rappel des traductions en attente. Il vérifie chaque langue explicitement affectée et met une notification en file si elle contient des lignes avec `needs_translation=true`. La livraison utilise l'e-mail et le canal de notifications en base. Ce calcul ne compte pas toutes les lignes non validées ; une affectation sans demande de traduction ne produit aucun envoi. Configurez le transport mail de l'application et lancez le worker du package. Le message de réussite confirme la demande, pas la livraison de l'e-mail.

Les rappels automatiques utilisent une commande et un réglage distincts décrits dans la référence CLI. Aucun de ces réglages n'envoie d'invitation, ne modifie les affectations ni ne valide des traductions.

## Paramètres {#settings}

L'écran Paramètres, généralement `/translator/settings`, est réservé aux administrateurs. Il affiche le domaine principal configuré en lecture seule, huit interrupteurs et le champ Domaines. Modifiez le serveur principal via `INTERPRESSO_MAIN_SERVER_DOMAIN` ou la configuration de l'application.

Chaque champ possède son propre formulaire POST et sa validation. Avec JavaScript, changer un interrupteur ou quitter Domaines envoie ce seul champ et recharge la page. Sans JavaScript, utilisez son bouton Enregistrer. Un champ incorrect n'empêche pas l'enregistrement d'un autre champ valide. Le serveur actualise le cache des paramètres après chaque écriture réussie. Les erreurs conservent la valeur tentée pour correction ; un nouveau rechargement montre la valeur enregistrée. La coordination exige des domaines enregistrés : renseignez-les d'abord.

### Référence des paramètres {#settings-reference}

Les neuf colonnes suivantes sont modifiables. Les valeurs par défaut décrivent une nouvelle installation, pas une configuration conservée après mise à niveau.

- **`db_loader`**, défaut `true` : choisit le chargeur de traductions en base plutôt que le chargeur de fichiers Laravel. Il permet de diffuser directement les traductions validées sans exportation régulière de fichiers. Désactivez-le si les fichiers de langue sont requis à l'exécution. L'interface et les exportations CLI ordinaires exportent uniquement les modèles quand il est actif ; voir les exceptions d'exportation forcée. Les installations existantes conservent leur valeur vraie ou fausse lors d'un changement du défaut.
- **`import_vendor`**, défaut `false` : inclut les espaces de noms des packages enregistrés dans l'importation. En mode base, si cette option est désactivée, leurs traductions continuent d'utiliser le chargeur de fichiers parent. Son activation les fait aussi charger depuis la base : importez-les avant de vous y fier. Utilisez-la pour faire gérer les textes des packages par les traducteurs. La désactivation ne supprime pas les entrées déjà importées et ne les exclut pas des exportations de fichiers.
- **`enable_open_ai_translations`**, défaut `false` : active les appels OpenAI pour la création des traductions manquantes et les actions de l'éditeur, avec les boutons associés. Configurez d'abord l'intégration facultative. Cela ne traduit pas automatiquement toutes les entrées existantes et ne valide aucun texte généré ; sans intégration, le service continue de renvoyer son entrée.
- **`enable_pending_notifications`**, défaut `false` : affiche l'action manuelle de rappel dans le formulaire du traducteur. Les administrateurs peuvent ainsi demander des rappels pour des comptes précis. Elle ne planifie aucun rappel et n'est pas vérifiée par la commande automatique.
- **`enable_automatic_pending_notifications`**, défaut `false` : autorise la commande automatique à parcourir les affectations explicites de chaque traducteur. Utilisez votre calendrier ou activez `interpresso.schedule.pending_notifications`. Ce réglage est indépendant de `enable_pending_notifications` ; la commande le vérifie à l'exécution.
- **`import_only_from_root_language`**, défaut `false` : limite les importations de traductions, y compris modèles et packages, à la langue correspondant à `app.locale`. À utiliser quand les sources de cette langue font autorité et que les autres langues sont gérées dans l'interface. Cela ne restreint pas Importer les langues et ne supprime pas les entrées des autres langues. Rechercher les traductions manquantes peut ensuite créer leurs équivalents.
- **`allow_deleting_languages`**, défaut `false` : affiche les actions de suppression de langue pour les administrateurs. Activez-le lorsque vous souhaitez supprimer des langues et leurs traductions. L'endpoint contrôle les droits d'administration et cet interrupteur.
- **`enable_multi_host`**, défaut `false` : ajoute les vérifications de tâches sur les hôtes configurés et la propagation des exportations/annulations de l'interface. Laissez-le désactivé pour un projet unique. Les domaines enregistrés ne déclenchent alors aucune de ces requêtes. L'activation dans Paramètres exige un champ Domaines non vide. Les domaines déjà enregistrés et non vides sont activés par la migration de mise à niveau ; une liste définie uniquement dans l'environnement n'active pas la fonction.
- **`domains`**, défaut `null` : URL d'installations séparées par des virgules pour les requêtes entre hôtes. Incluez `http://` ou `https://`, par exemple `https://one.example,https://two.example`, sans barre oblique finale. Le code retire les espaces autour des entrées et ajoute les chemins API. Ne listez que les installations participantes. Si la valeur enregistrée est null, `INTERPRESSO_MULTIPLE_DB_HOSTS` sert de repli ; une chaîne vide enregistrée ne l'utilise pas. Désactivez la coordination avant de vider ce champ. La validation exige sa présence lorsque la fonction est active, sans vérifier la syntaxe ni l'accessibilité des URL.

La table contient aussi les champs internes `process_running`, `process_owner`, `process_started_at` et `process_expires_at`, l'ID et les horodatages. Ils ne sont pas modifiables dans Paramètres ; le contrôleur refuse les champs hors des neuf ci-dessus. Un verrou temporaire ne bloque que s'il est actif et non expiré. La migration ajoute des métadonnées nullables sans verrouiller les paramètres existants.

## Diffusion des traductions {#how-translations-are-served}

### Mode base de données {#database-mode}

`db_loader=true` est le défaut des nouvelles installations. L'enregistrement initial des paramètres et le défaut de colonne valent vrai. La migration de mise à niveau change ce défaut et efface le choix de chargeur en cache sans écraser les lignes existantes. Son annulation rétablit le défaut faux sans modifier les préférences enregistrées.

Le chargeur Laravel lit les entrées en base mises en cache pour la langue, le groupe et l'espace de noms demandés. Pour une ligne validée, il utilise `value` ; sinon, `old_value`. Le brouillon n'est utilisé qu'après validation. Une nouvelle ligne non validée peut n'avoir aucune ancienne valeur : le chargeur ne fournit alors aucun texte validé. Demander une traduction sur une ligne validée ne crée pas d'ancienne valeur et peut aussi la priver de texte utilisable tant qu'elle n'est pas validée. Les espaces de noms des packages utilisent les fichiers lorsque `import_vendor` est désactivé.

Les messages de validation conservent les textes intégrés à Laravel et les personnalisations `validation.php` de l'application hôte, même avant importation. Les entrées de validation en base les remplacent selon les mêmes règles de validation/ancienne valeur. Les autres groupes de traductions de l'application restent diffusés exclusivement par la base.

Les textes intégrés ou publiés de l'interface du package restent aussi disponibles lorsque l'importation des packages est activée. Les entrées relues en base peuvent les remplacer.

Le chargement en base n'écrit rien dans `lang/`. Le fonctionnement ordinaire consiste à importer une fois, modifier, puis valider. Aucun fichier de traduction d'application exporté ne risque d'être écrasé au déploiement. **`interpresso:export-translations-deployment` est inutile pour la diffusion en mode base.** Les traductions restent en base malgré les déploiements de fichiers. Les modèles doivent toujours être exportés vers leurs colonnes JSON dans la base de l'application.

Ce réglage n'interdit pas globalement l'écriture de fichiers : Ajouter une langue crée un répertoire ; la commande de déploiement et l'endpoint d'exportation forcée entre hôtes ne passent pas l'option modèles uniquement et peuvent écrire même avec `db_loader` actif.

**La base de données devient indispensable au rendu des requêtes web en mode base.** Les erreurs de base se propagent lors de l'enregistrement du chargeur web et des recherches absentes du cache. Le repli vers le chargeur de fichiers lors d'une exception de base existe uniquement sous `runningInConsole()` pendant l'enregistrement du chargeur. Il ne couvre pas les recherches console ultérieures et ne fournit aucun repli web automatique en cas de panne. Le cache peut répondre à certaines recherches, mais ne remplace pas entièrement une base fonctionnelle.

### Mode fichiers {#file-mode}

Avec `db_loader=false`, Laravel utilise son chargeur de fichiers à l'exécution. La base du package conserve les modifications et leur état de révision. Après validation, exportez PHP/JSON dans `lang/` et les modèles dans leurs colonnes. L'exportation ordinaire exige Validé vrai, Modifié faux et Exporté faux ; l'exportation forcée ignore seulement Exporté.

Les exports PHP vont dans `lang/{locale}/{group}.php`, JSON dans `lang/{locale}.json` et les personnalisations de packages sous `lang/vendor/{namespace}/`. Les clés admissibles sont fusionnées au contenu existant. Les clés PHP à points deviennent des tableaux imbriqués : `a.b` devient `['a' => ['b' => 'text']]`. Les clés littérales à points correspondantes provenant d'anciens exports PHP sont retirées à la réécriture, les autres entrées étant conservées. JSON conserve les clés littérales. Une exportation forcée en mode fichiers réécrit les entrées validées déjà marquées exportées.

Le mode fichiers produit des traductions utilisables à l'exécution et intégrables dans un artefact de déploiement. Elles doivent rester synchronisées avec la base de traduction. Un déploiement qui les remplace peut faire perdre le texte exporté courant jusqu'à sa restauration par export forcé. Le mode base évite cette étape mais exige la disponibilité de la base et du cache. Le mode fichiers ne supprime pas la dépendance de l'interface de traduction à sa propre base.

### Cache et intégration des modèles {#cache-and-model-integration}

**`CACHE_DRIVER` ne doit pas valoir `database` en mode base.** Si l'application utilise `CACHE_STORE`, la même restriction s'applique. Choisissez Redis ou Memcached, ou un cache de fichiers pour un serveur unique. Web et workers doivent partager le même stockage de cache et préfixe. Les hôtes partageant les traductions devraient partager Redis ou Memcached pour que les changements de version atteignent chacun.

Les entrées du cache de traduction n'ont pas de TTL. Les clés incluent une version commune incrémentée après validation de la transaction d'écriture, y compris importations en masse, validations, exportations et suppressions du package. L'invalidation ciblée reste en place. Le code applicatif qui effectue des écritures en masse sans événements de modèle doit appeler `Translation::invalidateCacheAfterWrite()` après une écriture réussie, afin d'invalider après le commit. Du SQL direct sans cet appel peut laisser des valeurs périmées en cache.

Configurez les classes dans `interpresso.translatable_models`. Chaque modèle doit exposer ses colonnes traduisibles, par exemple `public array $translatable = ['name'];`, contenant des objets JSON indexés par langue. L'importation copie les valeurs existantes dans des entrées Modèle. L'exportation modifie la langue concernée dans la colonne existante et marque la traduction comme exportée. Le modèle cible et la colonne doivent toujours exister. Valider dans l'interface seule ne met pas à jour les données des modèles de l'application.

## Projet unique et plusieurs hôtes {#single-project-and-multi-host}

### Fonctionnement avec un seul projet {#single-project-operation}

Un projet unique est le défaut. Laissez `enable_multi_host` désactivé et Domaines vide. Aucun secret entre hôtes n'est nécessaire à l'utilisation ordinaire de l'interface. Importations, création des traductions manquantes, validations et exportations ordinaires vérifient les tâches/lots locaux. Exportation et annulation restent locales, même si une ancienne liste de domaines est conservée.

### Configurer les hôtes participants {#configure-participating-hosts}

La coordination convient aux installations nécessitant des traitements et exportations coordonnés, généralement sur la même base de traduction via `INTERPRESSO_DB_CONNECTION`. Elle ne réplique pas des bases de traduction distinctes.

1. Connectez les installations à la base voulue et configurez leurs accès aux files et au cache.
2. Définissez le même `INTERPRESSO_API_SHARED_SECRET` partout. Il fournit `interpresso.api_shared_api_key`, envoyé sous le nom `api_key` dans les requêtes sortantes.
3. Enregistrez les URL des installations avec leur protocole dans **Paramètres > Domaines**.
4. Activez **Activer la coordination entre hôtes**.
5. Alignez `INTERPRESSO_MAIN_SERVER_DOMAIN` sur l'installation principale de l'interface. Seule l'installation dont `app.url` correspond à cette valeur enregistre les routes web du panneau ; les routes API restent enregistrées sur les installations activées.

La vérification demande aux autres hôtes listés si des tâches sont en cours. Une réponse signalant une activité bloque l'opération. Les hôtes injoignables et réponses non réussies sont ignorés : ce n'est ni un verrou distribué ni une garantie d'inactivité d'un hôte indisponible. La fin d'une exportation de l'interface demande des exports forcés aux pairs ; l'annulation envoie des POST à leurs endpoints d'annulation. Une URL correspondant exactement au protocole et à l'hôte de la requête courante est ignorée.

### API entre hôtes {#inter-host-api}

Le package expose les endpoints POST suivants sous `/api`, indépendamment du préfixe du panneau :

- `/api/interpresso-has-jobs-running` : indique l'existence de tâches locales du package ou de lots inachevés et non annulés.
- `/api/cancelJobs` : annule les lots locaux du package et supprime ses tâches locales en file de base de données.
- `/api/interpresso-force-export` : exige une file permettant de différer le travail, acquiert un verrou local et met en file un export forcé pour chaque langue. Les connexions inadaptées renvoient HTTP 503 avec une commande de remplacement avant tout traitement. Une activité locale renvoie HTTP 409 avec propriétaire, début et expiration. Les verrous des pairs ne sont pas vérifiés puisque l'appelant termine encore son export. Cet endpoint peut écrire des fichiers même en mode base.
- `/api/interpresso-get-languages` : renvoie les langues pour le téléchargement de développement.
- `/api/interpresso-get-paginated-translations` : renvoie les traductions par pages de 500 pour ce téléchargement.

Tous exigent une chaîne `api_key` non vide correspondant au secret partagé. **L'API refuse l'accès avec HTTP 503 si aucun secret n'est configuré.** La validation de requête précède ce contrôle : un `api_key` absent ou mal typé reçoit HTTP 422 ; avec un secret configuré, une clé différente reçoit HTTP 401. Le GET public `/api/version` ne donne que la version du package et n'utilise pas ce middleware d'authentification.

Désactiver la coordination arrête les requêtes automatiques sortantes sans supprimer ni désactiver ces endpoints authentifiés. La commande de téléchargement de développement utilise aussi l'API indépendamment de ce réglage.

## Tâches en arrière-plan et files d'attente {#background-jobs-and-queues}

### Traitements en arrière-plan {#what-runs-in-the-background}

L'interface met les importations de langues/traductions, la création des traductions manquantes, validations en masse, exportations et mises à jour avec traduction automatique dans des lots Laravel. Les lots de traductions manquantes ajoutent des tâches au fil de la découverte du travail. Les tâches utilisent `interpresso.queue_name` (`languageProcessor` par défaut), les lots `interpresso.batch_name` (`languageBatch`). Les rappels et notifications administratives de fin utilisent la même file.

Aucun traitement long de traduction n'est exécuté dans une requête HTTP, même après l'envoi de la réponse. Avant toute écriture de lot ou acquisition de verrou, l'interface et l'endpoint d'export forcé vérifient `queue.default` et `queue.connections.<connection>.driver`. Les alias de connexion sont pris en charge. `sync`, `null`, une configuration absente et le pilote Laravel `deferred` sont refusés ; le failover est refusé si un repli est inadapté ou cyclique. Un pilote asynchrone configuré ne prouve pas qu'un worker fonctionne : les tâches attendent qu'il les consomme.

Choisissez l'un des trois modes pris en charge :

1. **Supervisor / worker permanent : meilleur choix pour le traitement en temps réel.** Définissez `QUEUE_CONNECTION=database` (ou `redis`) et supervisez un worker consommant `languageProcessor`, ou votre `interpresso.queue_name`. Par exemple : `php -d max_execution_time=0 artisan queue:work --queue=languageProcessor --timeout=900 --tries=1`. Placez le `retry_after` de la connexion au-dessus du délai par tâche, par exemple 960 secondes, et définissez `INTERPRESSO_PROCESS_LOCK_TTL=1800`. Pour SQS, configurez le délai de visibilité équivalent. Dimensionnez les limites selon la tâche la plus longue et l'attente en file ; consultez `failed_jobs` et les journaux en cas d'échec.
2. **Sans Supervisor : recommandé pour un hébergement mutualisé.** Définissez `QUEUE_CONNECTION=database`, activez `interpresso.schedule.queue_worker` et ajoutez l'unique ligne cron exécutée chaque minute ci-dessous. Les boutons fonctionnent normalement. Cron démarre un worker à durée limitée et traite les tâches disponibles dans la minute ; les tâches longues et les files chargées peuvent nécessiter plusieurs passages. Aucune installation de Supervisor ni aucun worker permanent n'est nécessaire.
3. **Sync : petites installations uniquement.** Avec `QUEUE_CONNECTION=sync`, utilisez l'interface pour parcourir et modifier ou vérifier des lignes individuelles. Elle refuse les opérations longues et indique la commande Artisan correspondante, à exécuter manuellement en CLI. Les limites de mémoire et de temps de PHP CLI et de l'hébergement restent applicables. Une petite taille n'autorise jamais les opérations groupées dans une requête HTTP.

Après un changement de configuration de file, reconstruisez le cache de configuration si utilisé (`php artisan config:cache`) et redémarrez les workers permanents. Avec `null`, les notifications en file sont supprimées.

Une action groupée refusée indique sa commande CLI exacte, par exemple `php artisan interpresso:import-translations`, et explique comment une file en base avec le planificateur permet d'utiliser le bouton. Aucune donnée n'est écrite, aucun lot ne démarre et aucun verrou d'opération n'est acquis. La mise à jour avec traduction automatique affiche les mêmes instructions avant d'enregistrer le brouillon source. Les modifications et validations individuelles restent disponibles.

| Action interface/API | Remplacement CLI |
| --- | --- |
| Importer les langues | `php artisan interpresso:import-languages` |
| Importer les traductions | `php artisan interpresso:import-translations` |
| Rechercher les traductions manquantes | `php artisan interpresso:find-missing-translations` |
| Valider toutes les langues | `php artisan interpresso:approve-translations --translator=1` |
| Valider une langue | `php artisan interpresso:approve-translations --translator=1 --language=en` |
| Exporter toutes les langues | `php artisan interpresso:export-translations` |
| Exporter une langue | `php artisan interpresso:export-translations --language=en` |
| Exporter les modèles | Ajouter `--only-models` à la commande d'exportation appropriée |
| Export forcé par API d'un pair | `php artisan interpresso:export-translations-deployment` |

Le message de validation fournit l'ID réel de l'administrateur connecté, les messages propres à une langue son code sélectionné. L'API authentifiée d'export forcé renvoie HTTP **503** avec une `message` JSON contenant la commande d'export forcé si la connexion ne peut pas différer le travail. `interpresso:export-translations-deployment` correspond au comportement de l'API en réécrivant fichiers et modèles même en mode base ; la commande normale avec `--force=1` respecte ce mode. Chaque hôte récepteur nécessite une connexion différée et un worker. Une file compatible avec un verrou actif renvoie toujours **409**.

### Cron sans Supervisor {#cron-without-supervisor}

**Cette configuration est recommandée pour un hébergement mutualisé.** Dans l'environnement de l'application hôte, activez la planification du worker et utilisez un cache persistant :

```dotenv
QUEUE_CONNECTION=database
INTERPRESSO_SCHEDULE_QUEUE_WORKER=true
CACHE_STORE=file
```

Cela active `interpresso.schedule.queue_worker`, dont la valeur par défaut est `false`. Lors des mises à niveau, ajoutez les options manquantes à la configuration publiée sans écraser les réglages locaux. Exécutez `php artisan migrate` si les tables de file/lots manquent et reconstruisez le cache avec `php artisan config:cache`. Ajoutez exactement une ligne cron, en adaptant le chemin et le programme PHP :

```cron
* * * * * cd /path/to/app && /usr/local/bin/php83 artisan schedule:run >> /path/to/app/storage/logs/cron.log 2>&1
```

Utilisez le chemin PHP CLI explicite fourni par votre hébergeur, par exemple `/usr/local/bin/php83`. Le `php` utilisé par cron est souvent plus ancien que la version PHP du site. Une opération fonctionnant dans le navigateur peut donc échouer avant le démarrage de Laravel. Vérifiez `/usr/local/bin/php83 -v` et le journal cron avant de supprimer la sortie. L'utilisateur cron doit pouvoir écrire dans les chemins de stockage et d'exportation ; les processus en arrière-plan sont facultatifs. Vérifiez le calendrier et testez un passage avec le même programme :

```bash
/usr/local/bin/php83 artisan schedule:list
/usr/local/bin/php83 artisan interpresso:work
```

`interpresso:work` appelle `queue:work` pour `interpresso.queue_name` sur la connexion par défaut, avec `--stop-when-empty`, `--max-time=50`, `--max-jobs=100`, `--memory=96`, `--timeout=60`, `--sleep=0` et `--tries=1`. Une file vide renvoie immédiatement 0. Le travail restant après une limite est repris au passage suivant ; les tâches retardées ou réservées restent pour plus tard.

Configurez `interpresso.queue_worker.max_time` (secondes), `interpresso.queue_worker.max_jobs`, `interpresso.queue_worker.memory` (Mo) et `interpresso.queue_worker.timeout` (secondes par tâche). Les variables associées sont `INTERPRESSO_QUEUE_WORKER_MAX_TIME`, `INTERPRESSO_QUEUE_WORKER_MAX_JOBS`, `INTERPRESSO_QUEUE_WORKER_MEMORY` et `INTERPRESSO_QUEUE_WORKER_TIMEOUT`. Toutes doivent être des entiers positifs ; zéro/illimité et les valeurs invalides sont refusés. Les limites de temps et de mémoire sont vérifiées entre les tâches. PHP CLI nécessite PCNTL pour que Laravel interrompe une tâche bloquée à son échéance ; sinon, une limite de processus imposée par l'hébergement est nécessaire. Configurez aussi des délais réseau finis. Gardez `retry_after` au-dessus du délai par tâche, ou réglez la visibilité SQS en conséquence, et `interpresso.process_lock_ttl` au-dessus de la tâche ininterrompue la plus longue et de l'attente prévue.

Le worker est planifié chaque minute avec `withoutOverlapping`. `interpresso.schedule.worker_background` vaut `true` par défaut (`INTERPRESSO_SCHEDULE_WORKER_BACKGROUND`). Le planificateur vérifie à la fois `function_exists('proc_open')` et le réglage PHP `disable_functions`. Si le lancement de processus est indisponible ou l'arrière-plan désactivé, un callback nommé exécute `Artisan::call` au premier plan. Supprimer simplement `runInBackground()` ferait encore démarrer un sous-processus par Laravel. Les tâches de maintenance utilisent aussi des callbacks lorsque `proc_open` est indisponible. Le verrou de cache expire après `ceil((max_time + timeout) / 60) + 1` minutes, soit trois minutes par défaut, pour laisser finir la dernière tâche. Une fin normale le libère plus tôt. Utilisez un cache persistant sur fichiers pour un hôte ou partagé entre plusieurs hôtes, jamais un cache array/null en mémoire. Ce verrou évite le chevauchement des workers cron. Le `ProcessLock` distinct en base protège les opérations entre HTTP, CLI et lots ; les deux verrous sont nécessaires. Les lancements manuels ne bénéficient pas du verrou du planificateur.

Les exportations en file, y compris forcées et de modèles, validations, recherches des traductions manquantes et importations traitent au plus `interpresso.chunk_size` lignes sources par tâche, puis ajoutent un successeur avec curseur au même lot. La valeur par défaut est `100`, réglable avec `INTERPRESSO_CHUNK_SIZE` ; réduisez-la si l'hébergeur impose une courte durée aux processus. Les traductions manquantes avec IA respectent aussi `max_open_ai_missing_trans`. Un worker redémarré reprend au curseur en file ; une réservation interrompue brutalement peut rejouer son bloc courant après le délai `retry_after`. Les blocs terminés restent enregistrés. Les tâches à curseur autorisent la reprise des réservations, mais une véritable exception de traitement fait échouer le lot immédiatement. La progression utilise un total estimé, fondé sur la taille des sources pour les fichiers, et atteint 100% uniquement à la fin.

Les sources en base utilisent des curseurs ordonnés par clé primaire. Les imports de fichiers utilisent la position des entrées et refusent les sources modifiées entre deux blocs ; gardez les fichiers stables jusqu'à la fin du lot. Importer ou créer les lignes manquantes préserve les traductions existantes lors d'une reprise. Les exports fusionnent les clés et remplacent les fichiers de manière atomique. Les entrées PHP/JSON et les fichiers exportés existants doivent encore être lus entièrement, un fichier à la fois. Scindez les fichiers exceptionnellement volumineux si un seul dépasse la mémoire ou la durée permise. Une requête IA interrompue peut être facturée à nouveau si sa réponse n'était pas encore enregistrée.

La limite par défaut du worker, `96` Mo, laisse une marge sur un hôte de 128-256 Mo. `INTERPRESSO_QUEUE_WORKER_MEMORY` ou `interpresso:work --memory=64` modifie la limite entre tâches ; le `memory_limit` de PHP reste applicable dans chaque tâche. `interpresso:work --max-time=30` remplace le budget temporel pour un appel. Réduisez la taille des blocs pour raccourcir les tâches ; `--max-time` est vérifié entre tâches et n'interrompt pas un bloc en cours.

Un hébergeur limitant cron à un passage toutes les 5 ou 15 minutes convient aussi : remplacez le premier champ cron par `*/5` ou `*/15`. Les tâches en attente ou retardées commencent proportionnellement plus tard ; un retard accumulé peut nécessiter plusieurs passages. Chaque bloc renouvelle son `ProcessLock` en base avant et après le travail. Gardez `INTERPRESSO_PROCESS_LOCK_TTL` supérieur à l'intervalle cron plus une exécution du worker et le retard de planification. La valeur par défaut de `1800` secondes laisse une marge pour un intervalle de 15 minutes. Lors d'une mise à niveau conservant un TTL de `900` secondes, augmentez-le pour un cron de 15 minutes. Aucun renouvellement n'est possible quand PHP est arrêté. Fin, échec ou annulation libère le verrou propre au lot.

Le même bloc propose des activations indépendantes : `interpresso.schedule.prune_batches` lance `interpresso:prune-batches` chaque minute ; `interpresso.schedule.pending_notifications` lance `interpresso:send-automatic-pending-translations-notification` chaque jour à minuit dans le fuseau du planificateur. Activez-les avec `INTERPRESSO_SCHEDULE_PRUNE_BATCHES=true` et `INTERPRESSO_SCHEDULE_PENDING_NOTIFICATIONS=true`. Les deux valent `false` par défaut, même si le worker est actif. La maintenance précède un nouveau worker planifié ; un verrou d'opération existant peut néanmoins faire ignorer un passage de maintenance. Les rappels automatiques exigent aussi le réglage enregistré `enable_automatic_pending_notifications` et un transport de courrier fonctionnel. Ce réglage est vérifié à l'exécution ; enregistrer le calendrier ne lit pas la table des paramètres.

Les trois calendriers sont omis si la connexion ne diffère pas le travail, notamment sync/null/deferred/absente ou un basculement non sûr. Ils ne planifient pas eux-mêmes des importations ou validations : les administrateurs continuent d'utiliser l'interface. Pour désactiver le worker cron, définissez `INTERPRESSO_SCHEDULE_QUEUE_WORKER=false` et reconstruisez le cache ; un passage déjà lancé se termine dans ses limites configurées.

### Pourquoi une action peut être bloquée {#why-an-action-may-be-blocked}

Importations, recherche des manquants, exportations, validation en masse, mise à jour avec traduction automatique et toutes les mutations individuelles (modifier, valider, demander, retirer une demande, restaurer) refusent de démarrer en présence d'un verrou actif, d'une tâche du package ou d'un lot inachevé non annulé. Les lectures de fenêtre et suggestions de brouillon restent disponibles puisqu'elles n'écrivent pas de traductions. Les commandes de traitement utilisent le même garde. La coordination ajoute les vérifications des pairs décrites plus haut ; les pairs injoignables sont ignorés.

Les commandes vérifient le verrou local, les tâches, les lots inachevés non annulés et les pairs configurés si la coordination est active. Elles acquièrent le verrou des paramètres par un seul UPDATE conditionnel avant de commencer, y compris sous `QUEUE_CONNECTION=sync` et cron. Une commande bloquée signale propriétaire (hôte, PID, opération, ID d'appel) et heure de début, puis retourne sans traitement. Exceptions et erreurs PHP libèrent le verrou de la commande dans `finally`.

`interpresso.process_lock_ttl` vaut 1800 secondes par défaut et se règle via `INTERPRESSO_PROCESS_LOCK_TTL`. Les verrous expirés et anciens indicateurs sans expiration ne bloquent pas l'acquisition. Une longue importation renouvelle son verrou entre fichiers et groupes de modèles via `ProcessLock::refresh()` ; les opérations personnalisées longues doivent appeler `refresh()` sur leur verrou avant la fin du TTL. Choisissez un TTL supérieur à la plus longue unité de travail ininterrompue. Des bases distinctes se coordonnent au mieux par HTTP, sans verrou atomique distribué.

Les mutations des contrôleurs acquièrent le même verrou avant écriture ou mise en file. Les lots en conservent la propriété après la réponse HTTP. Leurs callbacks le libèrent après réussite ou échec ; les tâches réservées vérifient annulation et propriété avant de travailler. Un ancien callback ne peut pas effacer un nouveau propriétaire. Les messages de blocage indiquent propriétaire et début. Annulation, gestion des langues/comptes et paramètres restent accessibles.

Le garde local et le service d'annulation interrogent `jobs` et `job_batches` sur la connexion de base par défaut. `interpresso.db_connection` configure modèles et migrations du package sans rediriger toutes les requêtes des tables de file. Gardez la configuration files/lots cohérente avec ces accès. Supprimer les lignes `jobs` en base ne vide pas une file utilisant un autre stockage.

Les notifications administratives de réussite ne sont livrées que pour les lots réussis et non annulés. Les échecs produisent des notifications d'erreur. Les quatre champs de verrou et le cache des paramètres sont effacés à la fin, à l'annulation, à l'échec de mise en file ou de tâche ; la CLI les efface aussi si un service déclenche une erreur PHP.

### Annuler et entretenir les lots {#cancel-and-maintain-batches}

Utilisez **Langues > Annuler le traitement par lot en cours** pour marquer les lots inachevés comme annulés et supprimer les lignes `jobs` de la file du package. Des notifications en attente peuvent aussi être supprimées. L'annulation ne revient pas sur les importations/modifications/exportations terminées et n'arrête pas un worker exécutant déjà une tâche. Les tâches vérifient l'annulation avant d'entrer dans leur traitement, même si un worker les a déjà réservées. Les tâches à curseur vérifient aussi l'annulation avant d'ajouter leur successeur ; un bloc déjà en cours peut terminer ses écritures. Inspectez le résultat avant de relancer un traitement.

Si la coordination est active, le même bouton demande l'annulation sur les autres hôtes. Il n'existe ni sélecteur par tâche ni écran de relance des tâches échouées.

Exécutez `interpresso:prune-batches` pour supprimer les anciens lots terminés/annulés. Cela n'annule aucun travail actif et ne supprime aucune tâche en file ou échouée. Activez `interpresso.schedule.prune_batches` pour un nettoyage chaque minute et `interpresso.schedule.pending_notifications` pour des rappels quotidiens. Les deux sont facultatifs et nécessitent une connexion de file différée.

## Autorisations et commandes communes {#permissions-and-shared-controls}

### Accès des administrateurs et traducteurs {#administrator-and-translator-access}

Les administrateurs accèdent à toutes les langues, traductions, à la gestion des traducteurs et aux paramètres. Leur interface comprend création/suppression de langues, importations, génération des traductions manquantes, validation/exportation en masse, annulation et actions de validation, demande et restauration des traductions.

L'interface normale d'un non-administrateur permet de :

- Lister et rechercher ses langues affectées et ouvrir leurs traductions.
- Rechercher, paginer et utiliser tous les filtres de traduction de ces langues.
- Choisir une langue de référence, ouvrir Traduire, lire les exemples disponibles et modifier le texte pour relecture.
- Utiliser OpenAI lorsque son réglage et les conditions de source/exemple sont satisfaits.
- Lire ce manuel, gérer ses notifications, changer de thème et se déconnecter.

Les non-administrateurs ne voient ni barre d'outils de traitement en masse des langues, ni validation/demande/restauration, ni exportation, gestion des traducteurs ou paramètres. Aucun écran de gestion de compte ne leur est proposé.

Tous les endpoints privilégiés imposent les droits d'administration côté serveur. Chaque action sur une traduction résout l'ID dans la langue demandée et vérifie les affectations ; un ID étranger renvoie 403 sans modifier les données. Les non-administrateurs peuvent modifier les traductions autorisées et demander des suggestions de brouillon, mais pas valider, demander une traduction, restaurer, modifier en masse, exporter, gérer des comptes, changer les paramètres ou entretenir les langues. Lecture, changement de statut de lecture et marquage global des notifications sont limités au traducteur authentifié et au type de destinataire.

### Navigation, thème et notifications {#navigation-theme-and-notifications}

La navigation propose Langues et Manuel aux traducteurs connectés, et ajoute Traducteurs et Paramètres pour les administrateurs. Sur petit écran, ouvrez le menu compact. Le bouton de thème bascule clair/sombre et conserve la préférence dans un cookie et le stockage du navigateur. Le serveur applique le cookie avant le premier affichage. Sans choix enregistré, la feuille de style suit le système avant JavaScript et la page continue de suivre ses changements jusqu'à votre choix explicite.

L'interface utilise les composants DaisyUI et des thèmes clairs/sombres pour formulaires, tableaux, menus, notifications et éditeur. Les réglages emploient des interrupteurs à case visible ; l'éditeur garde son bouton de fermeture, Échap et la fermeture par clic extérieur.

Le bouton de notifications indique le nombre de messages non lus. Ouvrez le panneau au-dessus pour les lire, fermez-le sans les marquer, masquez un message pour le marquer lu ou utilisez **Tout marquer comme lu**. Le panneau défile si nécessaire. Les notifications s'actualisent toutes les cinq secondes dans les onglets visibles et se mettent en pause dans les onglets masqués. Une actualisation sans changement préserve les commandes existantes et le focus. Les messages immédiats sont distincts : réussite, suppression, information ou avertissement, avec fermeture et minuterie.

Une préférence existante `localStorage["color-theme"]` migre vers le cookie `interpresso-color-theme` au premier lancement du module externe de thème. Un cookie valide prime sur le stockage local et met à jour `.dark` et `data-theme`. Lors de la première visite après mise à niveau, le serveur ne connaît pas une préférence uniquement locale : elle peut remplacer le thème système après chargement du module. Les requêtes suivantes appliquent immédiatement le cookie. Le changement de thème fonctionne même sans localStorage.

Le chemin du cookie est le préfixe URL du package, généralement `/translator`. L'enregistrement retire un doublon au chemin avec barre finale (`/translator/`) pour empêcher qu'un ancien cookie plus spécifique n'écrase le nouveau choix à la requête suivante.

Le package active des en-têtes de sécurité navigateur stricts par défaut. Scripts et styles proviennent de fichiers externes, les données des messages sont stockées dans un attribut HTML échappé, et l'interface ne peut pas être intégrée dans un cadre. Les déploiements CDN doivent configurer les origines autorisées dans `interpresso.security_headers.extra_sources` ; voir [Configuration](CONFIGURATION.md#browser-security-headers). Ces en-têtes concernent uniquement les routes web du package.

### Langue de l'interface {#interface-language}

Sur chaque page, y compris la connexion, choisissez English, Deutsch, Français, Español ou Italiano dans la navigation et cliquez sur **Changer de langue**. La réponse suivante affiche immédiatement la langue choisie et le manuel correspondant. Ce formulaire fonctionne sans JavaScript.

Les changements après connexion sont enregistrés dans le champ nullable `locale` du traducteur et le cookie `interpresso-locale`. Sans connexion, seul le cookie est enregistré. Il dure un an et suit le préfixe URL du package, généralement `/translator` ; il est chiffré, HttpOnly, SameSite=Lax et Secure sous HTTPS. La préférence du compte persiste après déconnexion et nouvelle connexion, y compris dans un autre navigateur.

Le premier choix disponible est utilisé : préférence du traducteur, cookie, `interpresso.locale`, puis `app.locale`. Les valeurs invalides sont ignorées ; si aucune ne convient, l'anglais est utilisé, ou la première langue disponible si l'anglais a été supprimé. Soumettre une langue inconnue est refusé sans enregistrement. Les choix proviennent des répertoires `lang/` du package, mis en cache pendant la durée de vie de l'application. Redémarrez les processus applicatifs persistants après avoir ajouté des traductions. Définissez `global.locale_name` dans un nouveau catalogue pour son nom natif ; sinon son code est affiché.

Les administrateurs peuvent définir ou effacer la **Langue de l'interface** dans le formulaire de création ou de modification du traducteur. **Navigateur / par défaut** efface la préférence du compte et rétablit le repli sur le cookie et la configuration. Les affectations linguistiques, la langue source des imports et les paramètres de l'application hôte restent inchangés.

Les mises à niveau ajoutent une colonne nullable `locale` sans remplir les comptes existants. Exécutez les migrations du package et actualisez les vues publiées si votre application les remplace.

## Référence CLI {#cli-reference}

Exécutez les commandes sous la forme `php artisan ...` depuis le répertoire de l'application Laravel hôte. Voici les onze signatures de commandes de `src/Console/Commands/`. Les commandes de traitement acquièrent le verrou partagé et peuvent retourner immédiatement avec propriétaire et début si le système est occupé ; ce retour ne signifie pas une opération terminée. Sauf exception ci-dessous, les commandes utilisent les paramètres enregistrés.

### interpresso:import-languages {#interpressoimport-languages}

Signature :

```text
interpresso:import-languages
```

Importe les répertoires de langues pris en charge directement sous le chemin de langues Laravel, en ignorant les codes existants. Ne découvre pas les langues à partir des seuls fichiers `{locale}.json` et n'importe pas de contenu traduit. S'exécute de manière synchrone et met en file une notification de résultat pour les administrateurs. À utiliser pour la première importation ou après ajout de répertoires.

```bash
php artisan interpresso:import-languages
```

### interpresso:import-translations {#interpressoimport-translations}

Signature :

```text
interpresso:import-translations
```

Importe les sources PHP/JSON et traductions des modèles configurés pour les langues existantes. Respecte `import_vendor` et `import_only_from_root_language`. Les couples langue/identifiant partagé existants sont conservés ; les nouvelles entrées sont validées/exportées. Indique le total des traductions existantes et nouvellement insérées. À utiliser après ajout de clés sources ou valeurs de modèles, une fois les langues importées. Ne met pas à jour les traductions existantes à partir de fichiers modifiés.

```bash
php artisan interpresso:import-translations
```

### interpresso:find-missing-translations {#interpressofind-missing-translations}

Signature :

```text
interpresso:find-missing-translations
```

Compare les nombres de traductions par langue et, s'ils diffèrent, crée les équivalents manquants depuis la langue source. Les langues vides sont représentées séparément dans cette vérification. Utilise `app.locale` comme source, ou la première langue si elle manque, et peut appeler OpenAI. Les nouvelles lignes sont non validées, à traduire et non exportées. Indique les nombres insérés et met en file une notification administrative pour la langue source si cet enregistrement existe.

À utiliser après importation de clés sources ou ajout de langues. **Limite :** des nombres égaux peuvent masquer des ensembles de clés différents ; la CLI répond alors `Everything up to date.` sans vérifier les identifiants. L'action Rechercher les traductions manquantes de l'interface appelle le service sans ce raccourci.

```bash
php artisan interpresso:find-missing-translations
```

### interpresso:approve-translations {#interpressoapprove-translations}

Signature :

```text
interpresso:approve-translations {--translator=} {--language=}
```

Valide synchroniquement toutes les traductions non validées, ou seulement celles de `--language=en`. `--translator=ID` est obligatoire et doit désigner un traducteur administrateur existant ; les validations sont attribuées à cet ID. Une attribution invalide ou langue inconnue termine avec le statut 1 sans écriture. La commande utilise le verrou partagé, le renouvelle entre langues, invalide les caches via le service de validation existant et envoie des notifications administratives. Elle fonctionne avec `QUEUE_CONNECTION=sync` sans worker. Relisez les traductions avant de la lancer.

```bash
php artisan interpresso:approve-translations --translator=1
php artisan interpresso:approve-translations --translator=1 --language=en
```

### interpresso:export-translations {#interpressoexport-translations}

Signature :

```text
interpresso:export-translations {--force=} {--language=} {--only-models}
```

Exporte les traductions validées et non modifiées. Normalement, seules les lignes non exportées sont prises en compte. `--force` attend une valeur convertie en booléen : utilisez `--force=1` pour inclure les lignes déjà exportées ; l'absence de l'option ou `--force=0` conserve le comportement ordinaire. Forcer ne contourne ni la validation ni la protection contre les tâches actives.

En mode fichiers, exporte PHP/JSON et modèles. En mode base, demande uniquement les modèles et ignore les fichiers. `--only-models` limite aussi l'exportation aux modèles en mode fichiers. `--language=en` limite le comptage et l'exécution à cette langue ; un code inconnu échoue sans exportation. La commande est synchrone et met les notifications administratives en file, sans déclencher d'exports chez les pairs. Les nombres concernent des lignes de traduction, pas des fichiers.

À utiliser pour la publication régulière ou, en mode fichiers, pour réécrire des fichiers dont les lignes sont déjà marquées exportées.

```bash
php artisan interpresso:export-translations
php artisan interpresso:export-translations --force=1
```

### interpresso:export-translations-deployment {#interpressoexport-translations-deployment}

Signature :

```text
interpresso:export-translations-deployment
```

Force synchroniquement l'exportation des traductions validées et non modifiées de chaque langue, modèles compris, en ignorant Exporté. N'accepte pas `--force`. Utilise le garde partagé, ne propage rien aux pairs et ne transmet pas l'option modèles uniquement du mode base : peut donc écrire des fichiers même avec `db_loader=true`.

À utiliser après un déploiement en mode fichiers ayant remplacé les traductions exportées. Inutile pour la diffusion en mode base : omettez-la de ce déploiement. Un message de fin est affiché pour chaque langue même sans contenu admissible.

```bash
php artisan interpresso:export-translations-deployment
```

### interpresso:work {#interpressowork}

Signature :

```text
interpresso:work [--max-time=SECONDS] [--memory=MB]
```

Traite uniquement la file configurée du package, puis s'arrête lorsqu'elle est vide ou qu'un budget de temps/tâches est atteint. Le travail restant reprend au passage cron suivant. Renvoie 0 pour une file vide ou un arrêt normal sur budget, 1 pour une connexion non différée ou des limites invalides, sinon le code de sortie du worker. Des échecs peuvent être enregistrés même avec le code 0 ; vérifiez les tâches échouées et les journaux. Utilisez `--max-time` et `--memory` pour remplacer ces limites pour un appel ; configurez les valeurs par défaut comme décrit dans [Cron sans Supervisor](#cron-without-supervisor).

```bash
php artisan interpresso:work
```

### interpresso:prune-batches {#interpressoprune-batches}

Signature :

```text
interpresso:prune-batches
```

Supprime les lignes correspondant à `interpresso.batch_name` dans `job_batches` sur `interpresso.db_connection` dont la fin ou l'annulation remonte à plus de `interpresso.prune_batch_hours`, soit 24 heures par défaut. Ne touche ni aux lots actifs, ni aux tâches en file, ni aux enregistrements d'échec. Aucune option propre ni sortie de fin n'est prévue.

Utilisez cette commande pour nettoyer les lots conservés. Activez `interpresso.schedule.prune_batches` pour un nettoyage automatique chaque minute, ou définissez votre propre calendrier.

```bash
php artisan interpresso:prune-batches
```

### interpresso:send-automatic-pending-translations-notification {#interpressosend-automatic-pending-translations-notification}

Signature :

```text
interpresso:send-automatic-pending-translations-notification
```

C'est le nom réel implémenté par `SendAutomaticPendingNotifications` ; `interpresso:send-automatic-pending-notifications` n'est pas un alias enregistré.

Si `enable_automatic_pending_notifications` vaut vrai, parcourt tous les traducteurs, administrateurs compris, et leurs affectations explicites. Pour chaque langue avec des entrées À traduire, met en file des notifications pour la base et l'e-mail. Sans entrée en attente, aucun envoi. Si le réglage est faux, ne fait rien. N'exige pas `enable_pending_notifications`, utilise le garde partagé et n'affiche pas de bilan de réussite.

Utilisez cette commande pour des rappels récurrents avec un transport de courrier fonctionnel et un worker permanent ou lancé par cron. Activez `interpresso.schedule.pending_notifications` pour le calendrier quotidien, ou définissez le vôtre. Relancer la commande peut envoyer un autre rappel pour le même travail.

```bash
php artisan interpresso:send-automatic-pending-translations-notification
```

### interpresso:developer-download {#interpressodeveloper-download}

Signature :

```text
interpresso:developer-download
```

Télécharge les langues et traductions paginées depuis `interpresso.main_server_domain`, configuré par `INTERPRESSO_MAIN_SERVER_DOMAIN`, avec `INTERPRESSO_API_SHARED_SECRET`. Remplace les lignes locales de langues et traductions, puis exporte de force le contenu téléchargé validé et non modifié. Le mode fichiers exporte fichiers et modèles, le mode base uniquement les modèles. Paramètres, comptes de traducteurs et affectations ne sont pas téléchargés.

À utiliser uniquement pour remplacer volontairement une copie de développement par les données du serveur principal. **Le travail de traduction local est supprimé/remplacé sans demande de confirmation.** Le garde partagé s'applique. Aucun contrôle d'environnement local, simulation, argument d'hôte ou mode fusion n'existe. La phase base utilise une transaction et les instructions MySQL/MariaDB `SET FOREIGN_KEY_CHECKS` : elle n'est donc pas portable telle quelle vers SQLite ou PostgreSQL. L'exportation suit le commit ; son échec n'annule pas le remplacement de la base. Les cibles de modèles locales doivent exister pour l'exportation.

```bash
php artisan interpresso:developer-download
```

### interpresso:unlock {#interpressounlock}

Signature :

```text
interpresso:unlock {--force}
```

Affiche propriétaire et heure de début enregistrés. Sans `--force`, supprime les verrous expirés ou anciens et refuse un verrou actif avec le code 1. `--force` supprime aussi un verrou actif. La libération réussie retourne 0 et efface `process_running`, `process_owner`, `process_started_at` et `process_expires_at`. Libérer une ligne déjà déverrouillée est sans effet nuisible. Le nettoyage des seuls verrous expirés utilise un UPDATE conditionnel pour ne pas effacer une acquisition ou un renouvellement concurrent.

```bash
php artisan interpresso:unlock
php artisan interpresso:unlock --force
```

Déverrouiller n'arrête aucun processus PHP, n'annule aucune tâche en file et ne revient pas sur les changements. Arrêtez ou vérifiez l'ancien processus avant de forcer un verrou actif. Les tâches/lots existants peuvent encore bloquer ; utilisez **Annuler le traitement par lot en cours** pour les annuler. L'annulation libère uniquement le verrou du lot concerné, pas celui d'une exécution cron distincte.

## Dépannage {#troubleshooting}

### Des tâches ne se terminent pas ou un autre processus est signalé {#jobs-do-not-finish-or-another-process-is-reported}

Pour les files asynchrones, vérifiez qu'un worker consomme la file configurée, généralement `languageProcessor`. Les commandes cron/artisan sous `QUEUE_CONNECTION=sync` n'ont pas besoin de worker pour les traductions ; les actions HTTP en masse refusent toujours sync. Vérifiez propriétaire, début et expiration signalés ; utilisez `interpresso:unlock` pour les métadonnées périmées et `--force` uniquement après avoir vérifié l'arrêt de l'ancien traitement. Consultez les `jobs` de la file du package, les lots `languageBatch` inachevés, les journaux et les tâches échouées. Les notifications en attente peuvent aussi occuper cette file. Un worker n'écoutant que la file par défaut ne traite pas celle du package.

Après avoir vérifié si le travail tourne encore, utilisez **Annuler le traitement par lot en cours** pour les traitements abandonnés. Nettoyer d'anciens lots n'est pas une annulation. Avec plusieurs hôtes, vérifiez pairs et secrets partagés ; un pair indisponible est ignoré lors du contrôle d'activité, mais la propagation d'export peut échouer. Consultez les limites des connexions de tables et des files hors base décrites plus haut.

### Une langue, une clé ou un exemple source manque {#a-language-key-or-source-example-is-missing}

Importer les langues reconnaît uniquement les répertoires pris en charge. Ajoutez explicitement une langue pour des sources JSON seules. Assurez-vous que `lang/` existe avant d'importer ; le mode base ne crée pas les répertoires sources manquants pendant l'importation. Vérifiez la présence des langues source/de repli et actualisez la liste après les imports en arrière-plan.

Importer les traductions ne visite que les langues enregistrées et les options source seule/packages peuvent exclure des sources. Réimporter n'écrase pas une valeur existante. Rechercher les traductions manquantes copie les identifiants sources, pas les clés propres à une autre langue ; le raccourci CLI sur les nombres égaux peut manquer des différences. Utilisez alors l'interface. L'absence de l'enregistrement `app.fallback_locale` empêche le montage de l'éditeur ; recréez cette langue.

### Les filtres ou paramètres semblent ne pas être enregistrés {#filters-or-settings-appear-not-to-save}

Recherche et filtres rechargent avec des paramètres d'URL. Chaque champ de Paramètres s'envoie séparément lors d'un changement. Attendez la fin de navigation et consultez les erreurs à côté des champs refusés. Enregistrez Domaines avant d'activer la coordination, et désactivez-la avant de les effacer. Si les commandes ne réagissent pas, reconstruisez et republiez les ressources du package, puis cherchez des erreurs JavaScript ou réseau. Les interrogations des lots et notifications se mettent volontairement en pause dans les onglets masqués.

### Le contenu validé n'est pas visible ou les fichiers ne changent pas {#approved-content-is-not-visible-or-files-are-unchanged}

En mode fichiers, validez les modifications puis exportez-les. Les exports normaux ignorent les lignes non validées, modifiées ou déjà exportées ; utilisez `--force=1` seulement pour réexporter du contenu validé et non modifié. Vérifiez les droits d'écriture et la validité des sources PHP/JSON. Un JSON existant mal formé ou un échec d'encodage interrompt l'export plutôt que de remplacer silencieusement le fichier par un contenu invalide.

En mode base, l'absence d'écriture de fichiers est attendue dans le fonctionnement ordinaire. Vérifiez validation, ancienne valeur, configuration de cache et préfixe partagé. Exporté faux n'empêche pas la diffusion en base. Les colonnes de modèles nécessitent toujours un export. La commande de déploiement et l'endpoint d'export forcé peuvent écrire malgré le mode base.

### Erreurs de base, cache ou paramètres {#database-cache-or-settings-failures}

Rétablissez la connexion à la base lors d'une panne web en mode base ; aucun repli automatique vers les fichiers n'existe. `CACHE_DRIVER`/`CACHE_STORE` doivent utiliser un stockage hors base, partagé correctement entre workers et web. Les écritures du package invalident les entrées versionnées après commit ; les écritures externes en masse nécessitent l'appel d'invalidation.

Si la table des paramètres manque ou est vide, le choix du chargeur peut utiliser celui des fichiers Laravel. Les opérations nécessitant les paramètres lèvent `MissingSettingsException` si leur ligne manque. Restaurez-la : le package ne la recrée pas et ne remplace pas les préférences automatiquement. Après réparation hors interface, actualisez le cache via `Setting::getFreshCached()` dans le code de maintenance de l'application.

### OpenAI ou les e-mails de rappel ne produisent rien {#openai-or-pending-email-does-nothing}

Vérifiez le réglage concerné, le package OpenAI facultatif, l'API, l'exemple choisi et les journaux. Un échec OpenAI peut renvoyer le texte source inchangé. Les boutons de l'éditeur dépendent de la langue source et de la présence d'exemples ; les textes générés exigent toujours relecture et validation.

Les rappels comptent les lignes à traduire, pas toutes les lignes non validées. Vérifiez les affectations explicites, le transport de courrier et le worker. Les réglages manuels et automatiques sont indépendants. Pour les rappels quotidiens, activez aussi `interpresso.schedule.pending_notifications`. Marquer une notification comme lue ne change aucun état de traduction.

### Erreurs d'accès, de routes et entre hôtes {#access-routes-and-inter-host-errors}

Pour une route d'interface absente, vérifiez `INTERPRESSO_ENABLED`, le préfixe configuré et l'égalité exacte entre serveur principal et `app.url`. Pour un 403 non administrateur, vérifiez affectations et restrictions de l'écran. Pour une action de ligne absente, vérifiez ses conditions de validation, demande et ancienne valeur.

Entre hôtes, HTTP 503 signifie qu'aucun secret partagé n'est configuré après validation de la requête ; HTTP 401 indique une différence et HTTP 422 peut signaler un `api_key` absent ou invalide. Configurez le même secret partout et utilisez des URL avec protocole. Le réglage de coordination ne désactive pas l'API elle-même.

### Exceptions d'importation, d'export de modèles ou de téléchargement de développement {#import-model-export-or-developer-download-exceptions}

Vérifiez le chemin et l'identifiant d'erreur consignés pour les fichiers sources invalides ou les erreurs d'insertion. Une valeur source, un espace de noms ou un groupe null ne constitue pas une métadonnée valide pour copier les traductions manquantes. Les traductions d'application utilisent un espace de noms vide ; JSON utilise un groupe vide. Les exports de modèles exigent une classe Eloquent, un enregistrement existant et une colonne JSON de traduction existante. Une cible manquante lève une exception au lieu d'être silencieusement marquée exportée.

Le téléchargement de développement exige du SQL MySQL/MariaDB compatible, les endpoints du serveur principal accessibles et le bon secret partagé. Il remplace les données locales et fait le commit avant l'export ; identifiez la phase échouée avant de recommencer. Les traducteurs et affectations locaux ne sont pas synchronisés par ce téléchargement.
