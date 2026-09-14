<?php

return [
    'form' => [
        'select' => [
            'placeholder' => 'Seleccionar idioma',
        ],
        'info' => 'Añada solo idiomas nuevos. No se pueden añadir idiomas que ya existen.',
        'button' => [
            'add' => 'Añadir',
            'close' => 'Cerrar',
        ],
    ],
    'button' => [
        'import_translations' => 'Importar traducciones',
        'import_languages' => 'Importar idiomas',
        'add_language' => 'Añadir idioma',
        'find_missing_translations' => 'Buscar traducciones que faltan',
        'chat_gpt_enabled' => '(OPENAI ACTIVADO)',
        'delete_jobs' => 'Cancelar procesamiento por lotes en curso',
    ],
    'table' => [
        'head' => [
            'language_code' => 'Código de idioma',
            'language_name' => 'Idioma',
            'language_native_name' => 'Nombre en el propio idioma',
        ],
    ],
    'import_languages_success' => 'Importación completada. Idiomas importados: :languages',
    'import_languages_success_nothing_imported' => 'Importación completada. No se ha importado ningún idioma.',
    'import_translations_success' => 'Importación (:language_code) completada. Traducciones importadas: :total',
    'find_missing_translations_success' => 'Importación (:language_code) completada. Traducciones añadidas: :total',
    'find_missing_translations_success_nothing_found' => 'Importación de traducciones faltantes completada. No hay nada que importar.',
    'deleted' => '¡Idioma eliminado!',
    'created' => '¡Idioma :language creado!',
    'info_fallback_language' => 'Su idioma predeterminado (configuración: app.locale) es :language. Compruebe este ajuste antes de importar los idiomas. Pulse "Importar idiomas" para empezar a usar la aplicación.',
];
