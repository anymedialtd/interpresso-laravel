<?php

return [
    'saved' => 'Ajuste guardado.',
    'domains_required' => 'Los dominios son obligatorios cuando la coordinación entre servidores está activada.',
    'autosave_info' => 'Cada campo se guarda por separado al cambiarlo. Si JavaScript está desactivado, use el botón Guardar del campo.',
    'main_domain' => [
        'label' => 'Dominio principal',
        'info' => 'Dominio principal donde se procesan las traducciones. Cambie este valor en el archivo de configuración del paquete.',
    ],
    'enable_multi_host' => [
        'label' => 'Activar coordinación entre servidores',
        'info' => 'Déjelo desactivado para un solo proyecto. Actívelo para comprobar tareas en curso, exportar traducciones y cancelar tareas en los servidores participantes.',
    ],
    'domains' => [
        'label' => 'Dominios',
        'info' => 'Solo es obligatorio si la coordinación entre servidores está activada. Añada todos los dominios que comparten el sistema de traducción, separados por comas e incluyendo http:// o https://. Ejemplo: http://example.com,https://example.com.',
    ],
    'import_settings' => 'Configuración de importación',
    'db_loader_text' => 'Cargar traducciones desde la base de datos (importe primero las traducciones desde los archivos)',
    'import_vendor_text' => 'Importar traducciones de paquetes (desactive la carga desde la base de datos, importe los archivos de los paquetes y vuelva a activarla)',
    'enable_pending_translations_notifications' => 'Activar notificaciones de traducciones pendientes.',
    'enable_automatic_pending_translations_notifications' => 'Activar notificaciones automáticas de traducciones pendientes.',
    'enable_open_ai_translations' => 'Activar la traducción con OpenAI al crear las traducciones que faltan.',
    'import_only_from_root_language' => [
        'label' => 'Importar solo el idioma de origen.',
        'info' => 'Active esta opción para importar solo los archivos del idioma de origen (:language).',
    ],
    'allow_deleting_languages' => [
        'label' => 'Permitir eliminar idiomas.',
    ],
];
