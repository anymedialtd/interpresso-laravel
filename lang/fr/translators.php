<?php

return [
    'button_toggle_create_form' => 'Créer un traducteur',
    'button_filter_languages' => 'Filtrer par langues',
    'table' => [
        'head' => [
            'id' => 'ID',
            'first_name' => 'Prénom',
            'last_name' => 'Nom',
            'email' => 'E-mail',
            'phone' => 'Téléphone',
            'admin' => 'Administrateur',
            'languages' => 'Langues',
        ],
    ],
    'form' => [
        'update_password_title' => 'Modifier le mot de passe de :email',
        'label' => [
            'first_name' => 'Prénom',
            'last_name' => 'Nom',
            'email' => 'E-mail',
            'password' => 'Mot de passe',
            'password_confirmation' => 'Confirmer le mot de passe',
            'phone' => 'Téléphone',
            'admin' => 'Droits d\'administration',
            'languages' => 'Langues',
        ],
        'button' => [
            'create' => 'Créer',
            'edit' => 'Modifier',
            'close' => 'Fermer',
            'update' => 'Mettre à jour',
            'update_password' => 'Modifier le mot de passe',
            'pending_translations_notification' => 'Envoyer un rappel des traductions en attente',
        ],
        'info' => [
            'admin' => 'Si cette option est cochée, le traducteur dispose d\'un accès complet à l\'interface de gestion des traductions.',
            'languages' => 'Sélectionnez les langues que le traducteur doit prendre en charge. Si aucune langue n\'est disponible, créez-les d\'abord dans la section Langues.',
        ],
    ],
    'created' => 'Traducteur créé',
    'updated' => 'Traducteur mis à jour',
    'deleted' => 'Traducteur supprimé',
    'password_updated_success' => 'Mot de passe de :email modifié.',
];
