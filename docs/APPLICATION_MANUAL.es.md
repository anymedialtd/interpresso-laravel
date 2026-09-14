Esta es una traducción del original en inglés, que sigue siendo la referencia oficial.

# Manual de la aplicación

Este manual se muestra en el panel de traducción en la ruta `interpresso.manual`, normalmente `/translator/manual`. Use el índice de secciones generado para navegar. En pantallas anchas, el índice permanece junto al artículo y se desplaza dentro de la ventana para mantener accesibles todas las secciones. Las cuatro pantallas de trabajo son Idiomas, Traducciones, Traductores y Configuración.

Cada traductor puede elegir el idioma de la interfaz con independencia de `app.locale` de la aplicación anfitriona. Incluye inglés (`en`), alemán (`de`), francés (`fr`), español (`es`) e italiano (`it`). El manual carga `docs/APPLICATION_MANUAL.{locale}.md` para el idioma activo y recurre a `docs/APPLICATION_MANUAL.md` si no hay traducción. El original en inglés sigue siendo la referencia oficial. Los encabezados traducidos conservan las anclas de las secciones del original.

## Primeros pasos {#getting-started}

### Instalar, publicar y migrar {#install-publish-and-migrate}

El paquete requiere PHP 8.2 o posterior dentro de la versión principal 8 y Laravel 12 o 13, según `composer.json`. Ejecute la instalación desde el directorio de su aplicación Laravel:

```bash
composer require anymedialtd/interpresso-laravel
php artisan vendor:publish --tag=interpresso-config
php artisan vendor:publish --tag=interpresso-migrations
php artisan vendor:publish --tag=interpresso-public
php artisan migrate
```

El descubrimiento automático de Composer registra los proveedores del paquete. La publicación de configuración incluye `config/interpresso.php` y `config/openai.php`. Los recursos de ejecución se publican como `public/vendor/interpresso/css/app.css` y `public/vendor/interpresso/js/app.js`. Publicar `interpresso-translations` o `interpresso-views` es opcional y permite personalizar los textos o plantillas de la interfaz. El paquete también carga sus migraciones directamente.

Las instalaciones existentes deben seguir [la adopción del nombre Interpresso](INSTALLATION.md#adopting-the-interpresso-identity) antes de migrar. Las tablas predeterminadas usan ahora `interpresso_`; establezca las opciones `table_*` con los nombres existentes para conservar sus datos. La configuración, integraciones PHP, tareas programadas, personalizaciones publicadas y todos los servidores de la API deben adoptar el nuevo nombre conjuntamente.

Las migraciones crean las tablas de idiomas, traducciones, traductores, asignaciones y configuración, además de las tablas de colas, lotes, tareas fallidas y notificaciones que falten. Defina `INTERPRESSO_DB_CONNECTION` y los nombres personalizados en `config/interpresso.php` antes de migrar. Se crea un idioma correspondiente a `config('app.fallback_locale')`, que debe figurar en la lista de idiomas admitidos.

Las instalaciones nuevas activan `db_loader` y desactivan la coordinación entre servidores. Antes de servir traducciones, elija una caché que no use la base de datos e importe los datos de origen como se describe a continuación. Para las operaciones en cola de la interfaz, configure una conexión asíncrona y un proceso de trabajo que consuma la cola del paquete, normalmente:

```bash
php artisan queue:work --queue=languageProcessor
```

En alojamiento compartido sin Supervisor, use `QUEUE_CONNECTION=database` y active `interpresso.schedule.queue_worker` con [una línea cron](#cron-without-supervisor). Es la configuración recomendada; los botones funcionan normalmente.

El almacenamiento de caché y la conexión de colas son ajustes independientes. Una cola en base de datos es compatible con una caché en otro almacenamiento.

### Iniciar sesión y cambiar la contraseña predeterminada {#sign-in-and-change-the-default-password}

La URL de acceso predeterminada es `/translator/login`. Si no existe ningún traductor, la migración crea este administrador:

- Correo electrónico: `admin@admin.com`
- Contraseña: `aaaaaaaa`
- Nombre y apellidos: `admin`

Inicie sesión, abra **Traductores**, edite el administrador y use **Cambiar contraseña** de inmediato. Introduzca una contraseña de al menos ocho caracteres y una confirmación idéntica. Actualizar solo el perfil no cambia la contraseña. El traductor con ID `1` no puede eliminarse desde la aplicación, pero su perfil y contraseña sí pueden modificarse.

El formulario incluye correo electrónico, contraseña y **Mantener la sesión iniciada**. Las credenciales incorrectas dejan al usuario en la pantalla de acceso. Se permiten diez intentos por dirección IP y minuto; al superarlos, se muestra cuánto debe esperar. Las cuentas usan el guard de sesión `interpresso_translator` del paquete. Use **Cerrar sesión** en la navegación para finalizarla.

Las rutas de la interfaz solo se registran si `config('interpresso.main_server_domain')` coincide exactamente con `config('app.url')`. El servidor principal se obtiene de `INTERPRESSO_MAIN_SERVER_DOMAIN`, con la URL de la aplicación como valor predeterminado. `INTERPRESSO_ENABLED=false` desactiva el registro de la interfaz/API y el cargador personalizado. Los prefijos de rutas y las direcciones de las pantallas se configuran en `config/interpresso.php`.

### Primera importación {#first-import}

El **idioma de origen** es `config('app.locale')`. El **idioma de respaldo** es `config('app.fallback_locale')` y también es el idioma de referencia inicial del editor. Pueden ser distintos.

1. Coloque los archivos de origen en el directorio de idiomas de Laravel, normalmente `lang/`. Compruebe los idiomas de origen y respaldo configurados.
2. Abra **Idiomas** y ejecute **Importar idiomas**. Reconoce directorios admitidos como `lang/en/` y `lang/de/`. Un archivo JSON como `lang/de.json` por sí solo no crea un idioma: use **Añadir idioma** o cree su directorio.
3. Espere a que termine, actualice la lista y ejecute **Importar traducciones**. Importa entradas PHP y JSON de idiomas ya registrados en la base y traducciones de modelos configurados. Ajuste antes las opciones de importación de paquetes y del idioma de origen.
4. Ejecute **Buscar traducciones que faltan** para crear en los demás idiomas las entradas correspondientes al idioma de origen. Revise, traduzca y apruebe estas filas.
5. En modo base de datos, las traducciones de la aplicación aprobadas se sirven desde la base. En modo archivos, exporte tras aprobar. Las traducciones de modelos requieren exportación a las columnas de la aplicación en ambos modos.

Los comandos equivalentes para empezar son:

```bash
php artisan interpresso:import-languages
php artisan interpresso:import-translations
php artisan interpresso:find-missing-translations
```

Las importaciones añaden registros que faltan; no sobrescriben traducciones existentes cuando cambia un archivo de origen. Las entradas importadas de archivos/modelos se marcan inicialmente como aprobadas y exportadas. Las creadas por la búsqueda de traducciones que faltan quedan sin aprobar, pendientes de traducción y sin exportar, aunque OpenAI proporcione el texto.

## Idiomas {#languages}

### Consultar, buscar, añadir y eliminar {#browse-search-add-and-delete}

La lista muestra código, nombre y nombre en el propio idioma, ordenados por código, con diez filas por página. **Buscar** consulta esos tres campos. **Ver** abre las traducciones de una fila. Los administradores ven todos los idiomas; los demás traductores, solo los asignados. La paginación ofrece páginas anterior, siguiente y numeradas cuando corresponde.

**Añadir idioma** abre un selector de idiomas admitidos. Elija un código sin usar y pulse **Añadir**. Los códigos duplicados o no admitidos se rechazan. Se crea el registro y, si falta, su directorio bajo `lang/`, incluso en modo base de datos. No se crean entradas traducidas. **Cerrar** oculta el formulario.

La acción **Eliminar** de la fila solo se muestra a administradores cuando `allow_deleting_languages` está activado. Retira las asignaciones de traductores y elimina el idioma; sus traducciones se borran mediante la clave foránea en cascada. Los archivos y el directorio se conservan, por lo que una importación posterior puede recrearlo. No hay diálogo de confirmación. Conserve el idioma de respaldo si sus valores deben seguir disponibles como ejemplos.

### Importar y completar las entradas que faltan {#import-and-fill-missing-entries}

Estas operaciones de la barra de herramientas están disponibles para administradores:

- **Importar idiomas** pone en cola la búsqueda de directorios de idiomas admitidos. Omite los códigos existentes.
- **Importar traducciones** pone en cola importaciones PHP, JSON, de paquetes si están habilitadas y de modelos configurados. Abarca el conjunto de idiomas configurado, no solo los resultados de búsqueda visibles.
- **Buscar traducciones que faltan** pone en cola la creación de registros por identificador de traducción compartido. Usa el idioma correspondiente a `app.locale` o el primero si ese registro falta. Solo copia identificadores del idioma de origen a otros idiomas. No examina el código de la aplicación buscando llamadas de traducción ni reúne las claves de todos los idiomas en el de origen. Conserva las entradas correspondientes que ya existen. Con OpenAI activo, envía los valores que faltan a traducción automática; en caso contrario, copia el texto de origen para editarlo después.

La importación de paquetes lee las personalizaciones publicadas bajo `lang/vendor/{namespace}/` antes del directorio registrado del paquete, conservando las personalizaciones ya importadas. Los archivos PHP deben devolver arrays y los JSON deben poder decodificarse como arrays. Las claves anidadas se aplanan para almacenarlas y los subdirectorios PHP se conservan en el nombre del grupo.

### Aprobar, exportar y cancelar {#approve-export-and-cancel}

**Aprobar traducciones de todos los idiomas** pone en cola la aprobación de todos los registros sin aprobar, independientemente de búsquedas o filtros. La aprobación desactiva Pendiente de traducción y Modificado, descarta el valor anterior guardado y registra al administrador que aprueba. La aprobación en bloque no exige un valor no vacío; revise antes los textos que faltan.

Con `db_loader` desactivado, **Exportar todos los idiomas** pone en cola idiomas con filas aprobadas, no modificadas y no exportadas. Exporta archivos de la aplicación, de paquetes y traducciones de modelos. Si no hay filas aptas, una notificación indica que no se ha exportado nada.

Con `db_loader` activado, **Exportar todos los modelos traducidos** limita tanto la selección inicial como cada tarea de exportación a los modelos. Las filas PHP/JSON se dejan para su distribución desde la base.

Tras finalizar correctamente un lote de exportación de la interfaz sin cancelación, la coordinación entre servidores, si está activa, solicita exportaciones forzadas a los demás servidores configurados. El comando ordinario de exportación no propaga estas solicitudes.

**Cancelar procesamiento por lotes en curso** marca como cancelados los lotes incompletos del paquete y elimina tareas de su cola en base de datos. Con coordinación activa, también solicita cancelación en los otros servidores. El resultado informa de las tareas y lotes locales afectados. Consulte **Tareas en segundo plano y colas** para conocer las limitaciones.

## Traducciones {#translations}

Abra un idioma mediante **Idiomas > Ver**. La ruta predeterminada es `/translator/translations/{language}`, llamada `interpresso.translations`. Un traductor sin permisos de administrador debe estar asignado al idioma para abrirla.

### Tabla, búsqueda y filtros de selección múltiple {#table-search-and-multi-select-filters}

La tabla muestra ID, Contenido de paquete, Espacio de nombres, Grupo, Pendiente de traducción, Aprobado, Aprobado por, Modificado, Modificado por, Exportado, Clave, Contenido y Contenido anterior. Las columnas de modificación y aprobación muestran nombres; los filtros ofrecen correos electrónicos. Los valores se muestran como texto sin formato. Abra **Traducir** para editar el valor completo. Hay veinte filas por página.

**Buscar** consulta ID, espacio de nombres, grupo, clave, valor actual y anterior. Una búsqueda nueva vuelve a la primera página.

Tres menús de selección múltiple acotan los resultados:

- **Tipo**: PHP, JSON o Modelo. Las entradas de modelo representan columnas de traducción Eloquent configuradas.
- **Modificado por**: traductores registrados como autores de la modificación actual.
- **Aprobado por**: traductores registrados como autores de la aprobación actual.

Varias opciones del mismo menú buscan cualquiera de ellas. Los menús distintos, filtros de estado y búsqueda se combinan. Quite todas las selecciones de un menú para dejar de filtrar ese campo. No hay una opción específica para filas sin autor de modificación o aprobación.

### Cinco filtros de tres estados {#five-three-state-filters}

Abra **Filtros de estado**. Cada botón recorre **gris: sin restricción**, **verde: verdadero**, **rojo: falso** y vuelve al gris. Filtra su columna booleana guardada:

- **Pendiente de traducción** (`needs_translation`): se ha solicitado una traducción o creado una entrada correspondiente que faltaba. Falso significa que no hay solicitud marcada, sin implicar que esté aprobada.
- **Aprobado** (`approved`): la fila está aprobada. Falso incluye entradas solicitadas/ausentes y textos editados pendientes de aprobación.
- **Modificado** (`updated_translation`): un valor editado espera aprobación. Es un indicador del proceso, no un filtro de fecha ni un historial permanente.
- **Contenido de paquete** (`is_vendor`): la fila está marcada como contenido de un paquete.
- **Exportado** (`exported`): el indicador de la base marca la fila como exportada. No comprueba el sistema de archivos ni es necesario para servir desde la base.

Para solicitudes, ponga Pendiente de traducción en verde. Para revisar ediciones, ponga Aprobado en rojo y Modificado en verde. Para exportaciones ordinarias de archivos, use Aprobado verde, Modificado rojo y Exportado rojo. Estos filtros solo afectan a la tabla; la aprobación/exportación en bloque utiliza su propia consulta de todo el idioma.

La búsqueda se envía tras una breve pausa al escribir o con Intro. Los cambios de estado y selección múltiple envían formularios GET, recargan la página y reinician la paginación. Las URL conservan `search`, `page`, `needs_translation`, `approved`, `updated_translation`, `is_vendor`, `exported`, `types[]`, `updatedBy[]` y `approvedBy[]`. Un botón de estado pasa el parámetro de ausente a `true`, luego a `false` y de nuevo a ausente. Marcadores, recarga y navegación Atrás/Adelante restauran las selecciones. El filtro de asignaciones usa `selectedLanguages[]`.

### Idioma de referencia y diálogo de traducción {#example-language-and-translate-modal}

Seleccione **Idioma de referencia** antes de abrir **Traducir** en una fila. Elige el ejemplo de origen, no el idioma editado. Usa `app.fallback_locale` por defecto si está permitido; si no, el idioma actual. Si el ejemplo elegido falta o está vacío, se usa el valor permitido del idioma de respaldo.

El diálogo muestra identificador de traducción, ejemplo, un área de texto con el valor actual y resúmenes desplegables por código de idioma para los ejemplos con el mismo identificador. Para usuarios no administradores, selector, ejemplos y búsqueda de respaldo se limitan a idiomas asignados. Los ejemplos se escapan en lugar de interpretarse como HTML.

Use **Cerrar diálogo**, Escape o un clic fuera para salir sin guardar los cambios del área de texto. El foco vuelve al botón Traducir.

### Actualización y acciones de OpenAI {#update-and-openai-actions}

**Actualizar traducción** solo guarda si el texto difiere del valor almacenado. Al empezar una edición guarda el valor anterior y los datos de quién modificó/aprobó, registra al traductor actual como autor, borra el aprobador actual y marca la fila como modificada, no aprobada y no exportada. También desactiva Pendiente de traducción. Más ediciones antes de aprobar conservan el mismo valor original. Guardar no aprueba ni exporta automáticamente.

**Traducir con OPENAI** aparece con OpenAI activo y un ejemplo permitido no vacío. Usa como origen el idioma real del ejemplo, incluso tras recurrir al respaldo. La sugerencia llena un área de texto aún intacta sin guardar. Si ya editó el borrador o lo cambia durante la solicitud, la sugerencia aparece aparte con **Aplicar sugerencia**. Su borrador se conserva hasta que lo sustituya expresamente. Revise el texto y use **Actualizar traducción** para guardarlo. La carga y los errores mantienen utilizable el editor; cerrar el diálogo cancela la solicitud pendiente.

Una respuesta vacía o mal formada, o una sugerencia sin texto, muestra un error y conserva el borrador. Puede reintentar o guardar su propio texto.

**Actualizar y traducir automáticamente los demás idiomas (OPENAI)** está reservado a administradores y aparece con OpenAI activo cuando el idioma actual es el de origen. Si cambió el valor, guarda la edición de origen una vez y pone en cola actualizaciones de las otras traducciones existentes con el mismo identificador. No crea las que faltan: use antes Buscar traducciones que faltan. Los resultados permanecen sin aprobar ni exportar.

OpenAI es opcional y requiere `openai-php/laravel`, configuración API válida en `config/openai.php` y la función activada. El paquete envía el texto de origen a OpenAI y pide conservar parámetros de Laravel como `:name`; revise el resultado. `interpresso.open_ai_model` elige el modelo solicitado y `interpresso.max_open_ai_missing_trans` controla el tamaño de los grupos de solicitudes para traducciones ausentes. Las solicitudes fallidas, la integración ausente o las respuestas con claves de más/de menos o valores que no sean cadenas conservan la entrada original del servicio. En el diálogo esa entrada es el ejemplo, de modo que una frase de origen sin cambios no demuestra una traducción correcta. Los idiomas ausentes o una entrada null se devuelven sin cambios ni solicitud; las peticiones con arrays conservan cadenas vacías y `"0"`.

### Aprobar, solicitar, retirar una solicitud y restaurar {#approve-request-remove-request-and-restore}

Los administradores disponen de estas acciones por fila:

- **Aprobar** aparece en filas sin aprobar con un valor actual no vacío. Acepta el texto, registra al aprobador, desactiva Pendiente de traducción y Modificado, elimina Contenido anterior y las referencias previas de autores e invalida la caché de traducción. No escribe archivos ni activa Exportado. La cadena `"0"` es válida. No hay botón con valor vacío; la aprobación en bloque no hace esta comprobación.
- **Solicitar traducción** aparece si Pendiente de traducción es falso. Lo establece en verdadero y Aprobado en falso. No guarda un valor anterior ni envía un correo por sí sola.
- **Retirar solicitud de traducción** aparece si Pendiente de traducción es verdadero. Lo pone en falso y Aprobado en verdadero, sin cambiar los demás indicadores. No equivale a la acción completa Aprobar.
- **Restaurar** aparece para filas no aprobadas con Contenido anterior guardado, incluso vacío o `"0"`. Restablece ese valor y los autores previos, elimina el historial guardado y Modificado, y marca la fila como aprobada y exportada. No escribe archivos ni desactiva expresamente Pendiente de traducción. Solo se guarda un valor anterior, no un historial de versiones; tras borrarlo la aprobación, Restaurar deja de estar disponible.

### Aprobar y exportar un idioma completo {#approve-and-export-a-whole-language}

**Aprobar traducciones ({código de idioma})** pone en cola la aprobación de todas las filas sin aprobar del idioma actual, incluidas las que quedan fuera de los filtros o tienen valores vacíos.

En modo archivos, **Exportar idioma** exporta en cola filas aprobadas con Modificado falso y Exportado falso, incluidas las traducciones de modelos aptas. En modo base de datos, **Exportar modelos traducidos** limita la comprobación de aptitud y la tarea a modelos. Ningún botón fuerza la reexportación de registros ya exportados. Use la opción CLI `--force=1` cuando sea necesario. Estas acciones son para administradores.

## Traductores {#translators}

### Consultar y filtrar cuentas {#browse-and-filter-accounts}

Esta pantalla, normalmente `/translator/translators`, es exclusiva para administradores. Muestra ID, nombre, apellidos, correo, teléfono, estado de administrador e idiomas asignados, con diez cuentas por página.

**Buscar** consulta ID, nombre, apellidos, correo o teléfono. La selección múltiple muestra traductores asignados a **todos los idiomas seleccionados**. Vaciarla elimina la restricción. El acceso automático de un administrador a todos los idiomas no crea asignaciones; un filtro de asignaciones puede excluirlo.

### Crear, editar, asignar idiomas y eliminar {#create-edit-assign-languages-and-delete}

Abra el formulario de creación e introduzca correo, teléfono, nombre, apellidos, contraseña, confirmación, asignaciones y permisos de administrador. El correo debe ser válido y único; nombre y apellidos requieren al menos dos caracteres. El teléfono es opcional, pero debe ser único si se indica. Las contraseñas necesitan al menos ocho caracteres y confirmación idéntica.

Un no administrador requiere al menos una asignación válida. Los ID de asignación duplicados o inexistentes se rechazan. Un administrador puede guardarse sin asignaciones y acceder a todos los idiomas. Las asignaciones explícitas siguen determinando de qué idiomas recibe notificaciones.

Tras elegir idiomas, cierre el desplegable con el botón Idiomas y envíe el formulario. Los errores aparecen junto a los campos, incluidas asignaciones y confirmaciones de contraseña. Los formularios rechazados conservan datos de perfil; las contraseñas deben introducirse otra vez.

**Crear** guarda una cuenta nueva. **Editar** abre una existente; **Actualizar** guarda perfil, permisos y lista de asignaciones, sustituyendo las anteriores. **Cerrar** oculta el formulario. Crear una cuenta no envía invitación ni correo de contraseña.

Use **Eliminar** en la fila para borrar una cuenta. El traductor con ID `1` no tiene esta acción y su endpoint la rechaza. Las demás eliminaciones se envían directamente sin confirmación.

### Contraseñas y avisos de traducciones pendientes {#password-changes-and-pending-notifications}

Al editar una cuenta, **Cambiar contraseña** abre un formulario separado. Introduzca la nueva contraseña y su confirmación y use su botón de actualización. **Cerrar** vuelve al perfil. Este cambio administrativo no pide la contraseña actual. Los no administradores no tienen pantalla de autoservicio para cambiarla o restablecerla.

Con `enable_pending_notifications` activo, el formulario muestra el botón de recordatorio. Revisa cada idioma asignado explícitamente y pone en cola una notificación si contiene filas con `needs_translation=true`. La entrega usa correo y el canal de notificaciones de base de datos. No cuenta todas las filas sin aprobar; una asignación sin traducciones solicitadas no produce envío. Configure el transporte de correo y ejecute el proceso de trabajo del paquete. El aviso de éxito confirma la solicitud, no la entrega del correo.

Los recordatorios automáticos usan un comando y ajuste independientes, descritos en la referencia CLI. Ninguna de estas opciones envía invitaciones, cambia asignaciones ni aprueba traducciones.

## Configuración {#settings}

La pantalla Configuración, normalmente `/translator/settings`, está reservada a administradores. Muestra el dominio principal configurado como texto de solo lectura, ocho interruptores y el campo Dominios. Cambie el servidor principal mediante `INTERPRESSO_MAIN_SERVER_DOMAIN` o la configuración de la aplicación.

Cada campo tiene su propio formulario POST y validación. Con JavaScript, cambiar un interruptor o salir de Dominios envía ese campo y recarga Configuración. Sin JavaScript, use su botón Guardar. Un campo inválido no impide guardar otro válido. El servidor actualiza la caché de configuración tras cada escritura correcta. Los errores conservan el valor intentado para corregirlo; volver a recargar muestra el valor guardado. La coordinación requiere dominios guardados: guárdelos primero.

### Referencia de configuración {#settings-reference}

Estas son las nueve columnas editables. Los valores predeterminados corresponden a una instalación nueva, no a ajustes conservados tras una actualización.

- **`db_loader`**, predeterminado `true`: selecciona el cargador de traducciones de base de datos en lugar del cargador de archivos de Laravel. Permite publicar traducciones aprobadas directamente sin exportar archivos regularmente. Desactívelo si necesita archivos de idioma durante la ejecución. La interfaz y los comandos ordinarios exportan solo modelos cuando está activo; consulte las excepciones de exportación forzada. Las instalaciones existentes conservan su valor verdadero o falso al cambiar el predeterminado.
- **`import_vendor`**, predeterminado `false`: incluye los espacios de nombres registrados de paquetes durante la importación. Con carga desde base y esta opción desactivada, sus traducciones siguen usando el cargador de archivos padre. Al activarla también pasan a usar la base: impórtelas antes de depender de ella. Úsela si los traductores deben gestionar textos de paquetes. Desactivarla no elimina registros importados ni excluye filas existentes de las exportaciones de archivos.
- **`enable_open_ai_translations`**, predeterminado `false`: activa las llamadas a OpenAI para crear traducciones que faltan y para las acciones del editor, mostrando sus botones. Configure antes la integración opcional. No traduce automáticamente todas las filas existentes ni aprueba textos generados; sin integración, el servicio sigue devolviendo su entrada.
- **`enable_pending_notifications`**, predeterminado `false`: muestra la acción manual de recordatorio al editar traductores. Permite a administradores solicitar avisos para cuentas concretas. No programa recordatorios ni es comprobado por el comando automático.
- **`enable_automatic_pending_notifications`**, predeterminado `false`: permite al comando automático recorrer las asignaciones explícitas de todos los traductores. Use su programación o active `interpresso.schedule.pending_notifications`. Es independiente de `enable_pending_notifications`; el comando comprueba el ajuste guardado al ejecutarse.
- **`import_only_from_root_language`**, predeterminado `false`: limita las importaciones de traducciones, incluidos modelos y paquetes, al idioma de `app.locale`. Úselo cuando las fuentes de ese idioma sean la referencia oficial y los demás se mantengan en el panel. No limita Importar idiomas ni elimina filas existentes de otros idiomas. Buscar traducciones que faltan puede crear después sus correspondientes entradas.
- **`allow_deleting_languages`**, predeterminado `false`: muestra acciones Eliminar idioma a los administradores. Actívelo cuando quiera borrar idiomas y sus traducciones. El endpoint comprueba permisos de administrador y este interruptor.
- **`enable_multi_host`**, predeterminado `false`: añade comprobaciones de tareas en servidores configurados y permite propagar exportaciones/cancelaciones de la interfaz. Déjelo desactivado para un solo proyecto. Los dominios guardados no originan entonces esas solicitudes. Activarlo desde Configuración exige Dominios no vacío. La migración de actualización activa las listas guardadas no vacías; una lista definida solo en el entorno no activa la función.
- **`domains`**, predeterminado `null`: URL de instalaciones separadas por comas para solicitudes entre servidores. Incluya `http://` o `https://`, por ejemplo `https://one.example,https://two.example`, sin barra final. El código recorta espacios de cada entrada y añade las rutas API. Incluya solo instalaciones participantes. Si el valor guardado es null, se usa `INTERPRESSO_MULTIPLE_DB_HOSTS`; una cadena vacía guardada no utiliza ese respaldo. Desactive la coordinación antes de vaciar el campo. La validación exige presencia cuando está activa, pero no comprueba sintaxis ni accesibilidad de las URL.

La tabla también contiene los campos internos `process_running`, `process_owner`, `process_started_at` y `process_expires_at`, además de ID y marcas de tiempo. No son controles de Configuración; el controlador rechaza cambios fuera de las nueve columnas anteriores. Un bloqueo temporal solo impide trabajo si está activo y no ha caducado. La migración añade metadatos que admiten null sin marcar como bloqueada la configuración existente.

## Cómo se sirven las traducciones {#how-translations-are-served}

### Modo base de datos {#database-mode}

`db_loader=true` es el valor predeterminado en instalaciones nuevas. Tanto el registro inicial como el valor predeterminado de la columna son verdaderos. La migración de actualización cambia el predeterminado y borra la selección de cargador en caché sin sobrescribir filas existentes; revertirla restaura el predeterminado falso sin alterar preferencias guardadas.

El cargador Laravel lee entradas de base de datos en caché para idioma, grupo y espacio de nombres solicitados. En filas aprobadas usa `value`; en las no aprobadas, `old_value`. No usa el borrador hasta aprobarlo. Una fila nueva sin aprobar puede no tener valor anterior y, por tanto, no aportar texto aprobado. Solicitar traducción de una fila aprobada no crea un valor anterior, por lo que también puede quedar sin texto utilizable mientras no esté aprobada. Los espacios de nombres de paquetes usan archivos si `import_vendor` está desactivado.

Los mensajes de validación conservan los valores integrados de Laravel y las personalizaciones `validation.php` de la aplicación anfitriona, incluso antes de importar. Las entradas de validación en base los sustituyen con las mismas reglas de aprobación/valor anterior. Los demás grupos de traducción de la aplicación siguen sirviéndose solo desde la base.

Los textos incluidos o publicados de la propia interfaz del paquete también siguen disponibles con la importación de paquetes activa. Las entradas revisadas de la base pueden sustituirlos.

La carga desde base no escribe en `lang/`. El flujo ordinario consiste en importar una vez, editar y aprobar; no hay archivos exportados de traducción de la aplicación que un despliegue pueda sobrescribir. **`interpresso:export-translations-deployment` no es necesario para servir desde la base.** Las traducciones permanecen en la base durante los despliegues del sistema de archivos. Los modelos siguen necesitando exportación a sus columnas JSON en la base de la aplicación.

Este ajuste no prohíbe globalmente escribir archivos: Añadir idioma crea un directorio; el comando de despliegue y el endpoint de exportación forzada entre servidores no pasan la opción de solo modelos y pueden escribir incluso con `db_loader` activo.

**La base de datos pasa a ser imprescindible para renderizar peticiones web en este modo.** Los fallos se propagan durante el registro del cargador web y las consultas sin caché. El respaldo al cargador de archivos ante excepciones de base solo existe bajo `runningInConsole()` durante el registro del cargador. No cubre consultas posteriores de consola ni ofrece respaldo web automático durante una caída. La caché puede resolver consultas individuales, pero no sustituye por completo una base operativa.

### Modo archivos {#file-mode}

Con `db_loader=false`, Laravel usa su cargador de archivos durante la ejecución. La base del paquete sigue guardando ediciones y estado de revisión. Tras aprobar, exporte PHP/JSON a `lang/` y traducciones de modelos a sus columnas. Una exportación ordinaria exige Aprobado verdadero, Modificado falso y Exportado falso; forzar solo ignora Exportado.

Los archivos PHP se exportan a `lang/{locale}/{group}.php`, JSON a `lang/{locale}.json` y las personalizaciones de paquetes bajo `lang/vendor/{namespace}/`. Las claves aptas se combinan con el contenido existente. Las claves PHP con puntos se escriben como arrays anidados: `a.b` pasa a `['a' => ['b' => 'text']]`. Las claves literales con puntos correspondientes de exportaciones PHP antiguas se eliminan al reescribir; las entradas ajenas se conservan. JSON mantiene claves literales. Use exportación forzada en modo archivos para reescribir entradas aprobadas ya marcadas como exportadas.

El modo archivos produce traducciones de ejecución que pueden incluirse en un artefacto de despliegue. Deben mantenerse sincronizadas con la base de traducción. Un despliegue que las sustituya puede perder el texto exportado actual hasta que lo restaure una exportación forzada. El modo base evita ese paso, pero necesita disponibilidad de base y caché. El modo archivos no elimina la dependencia del panel de su propia base de datos.

### Caché e integración de modelos {#cache-and-model-integration}

**`CACHE_DRIVER` no debe ser `database` en modo base.** Si la aplicación usa `CACHE_STORE`, rige la misma restricción. Use Redis o Memcached, o caché de archivos para un solo servidor. Los procesos web y de colas deben compartir almacenamiento y prefijo de caché. Los servidores que comparten traducciones deberían compartir Redis o Memcached para recibir los cambios de versión.

Las entradas de caché de traducción no tienen TTL. Sus claves incluyen una versión compartida que avanza tras confirmar las escrituras, incluidas importaciones en bloque, aprobaciones, exportaciones y eliminaciones del paquete. Se mantiene la invalidación selectiva. El código que realice escrituras en bloque omitiendo eventos de modelos debe llamar a `Translation::invalidateCacheAfterWrite()` tras una escritura correcta para invalidar después del commit. Modificar con SQL directo sin esta llamada puede dejar valores obsoletos en caché.

Configure las clases en `interpresso.translatable_models`. Cada modelo debe exponer sus columnas traducibles, por ejemplo `public array $translatable = ['name'];`, y estas deben contener objetos JSON indexados por idioma. Las importaciones copian valores existentes en filas Modelo. La exportación actualiza el idioma correspondiente dentro de la columna existente y marca la traducción como exportada. Modelo y columna de destino deben seguir existiendo. Aprobar en el panel no actualiza por sí solo los datos de modelos de la aplicación.

## Un proyecto y varios servidores {#single-project-and-multi-host}

### Funcionamiento con un solo proyecto {#single-project-operation}

Un solo proyecto es el valor predeterminado. Mantenga `enable_multi_host` desactivado y Dominios vacío. No hace falta un secreto compartido para el uso ordinario de la interfaz. Importaciones, creación de traducciones ausentes, aprobaciones y exportación normal comprueban tareas/lotes locales. Exportación y cancelación se quedan en el servidor local aunque conserve una antigua lista de dominios.

### Configurar los servidores participantes {#configure-participating-hosts}

Use coordinación cuando necesite sincronizar trabajo y exportaciones entre instalaciones, normalmente con la misma base de traducción mediante `INTERPRESSO_DB_CONNECTION`. La coordinación no replica bases independientes.

1. Conecte las instalaciones a la base prevista y configure su acceso a colas y caché.
2. Defina el mismo `INTERPRESSO_API_SHARED_SECRET` en todas. Proporciona `interpresso.api_shared_api_key`, enviado como `api_key` en solicitudes salientes.
3. Guarde las URL con su protocolo en **Configuración > Dominios**.
4. Active **Activar coordinación entre servidores**.
5. Mantenga `INTERPRESSO_MAIN_SERVER_DOMAIN` coherente con la instalación principal del panel. Solo la instalación cuyo `app.url` coincida registra sus rutas web; las rutas API siguen registradas en las instalaciones habilitadas.

La comprobación de tareas pregunta a otros servidores de la lista si hay trabajo en curso. Una respuesta con actividad bloquea la operación. Los servidores inaccesibles y las respuestas no satisfactorias se ignoran: no es un bloqueo distribuido ni garantiza que un servidor inaccesible esté libre. Al completar una exportación desde la interfaz se piden exportaciones forzadas a los demás; cancelar envía POST a sus endpoints de cancelación. Se omite una URL que coincida exactamente con el protocolo y servidor de la petición actual.

### API entre servidores {#inter-host-api}

El paquete expone estos endpoints POST bajo `/api`, independientemente del prefijo del panel:

- `/api/interpresso-has-jobs-running`: indica si existen tareas locales del paquete o lotes incompletos sin cancelar.
- `/api/cancelJobs`: cancela lotes locales y elimina tareas locales de la cola en base de datos del paquete.
- `/api/interpresso-force-export`: requiere una cola capaz de diferir trabajo, adquiere un bloqueo local y pone en cola una exportación forzada por idioma. Las conexiones inadecuadas devuelven HTTP 503 con un comando alternativo antes de empezar. Si hay trabajo local, devuelve HTTP 409 con propietario, inicio y caducidad. No comprueba bloqueos remotos porque el servidor llamante aún está terminando su exportación. Puede escribir archivos incluso en modo base.
- `/api/interpresso-get-languages`: devuelve idiomas para la descarga de desarrollo.
- `/api/interpresso-get-paginated-translations`: devuelve traducciones en páginas de 500 para esa descarga.

Todos exigen una cadena `api_key` no vacía que coincida con el secreto compartido. **La API deniega el acceso con HTTP 503 si no hay secreto configurado.** Primero valida la petición: un `api_key` ausente o de tipo incorrecto recibe HTTP 422; con secreto configurado, una clave distinta recibe HTTP 401. El GET público `/api/version` solo informa de la versión y no usa este middleware de autenticación.

Desactivar la coordinación detiene solicitudes automáticas salientes, pero no elimina ni desactiva estos endpoints autenticados. La descarga de desarrollo también usa la API independientemente del interruptor.

## Tareas en segundo plano y colas {#background-jobs-and-queues}

### Qué se ejecuta en segundo plano {#what-runs-in-the-background}

La interfaz pone importaciones de idiomas/traducciones, generación de las que faltan, aprobaciones en bloque, exportaciones y actualización con traducción automática en lotes Laravel. Los lotes que buscan traducciones añaden tareas según encuentran trabajo. Las tareas usan `interpresso.queue_name` (`languageProcessor` por defecto), y los lotes `interpresso.batch_name` (`languageBatch`). Recordatorios y notificaciones administrativas de finalización también usan la cola del paquete.

El trabajo prolongado de traducción nunca se ejecuta dentro de una petición HTTP, ni después de enviar su respuesta. Antes de escribir lotes o adquirir bloqueos, la interfaz y el endpoint remoto de exportación comprueban `queue.default` y `queue.connections.<connection>.driver`. Se admiten alias de conexión. Se rechazan `sync`, `null`, configuración ausente y el driver `deferred` de Laravel; failover se rechaza si algún respaldo es inseguro o cíclico. Un driver asíncrono configurado no demuestra que exista un proceso de trabajo activo: las tareas esperan a que alguien las consuma.

Elija uno de los tres modos admitidos:

1. **Supervisor / proceso de trabajo permanente: la mejor opción para tiempo real.** Configure `QUEUE_CONNECTION=database` (o `redis`) y supervise un proceso que consuma `languageProcessor`, o su `interpresso.queue_name`. Por ejemplo: `php -d max_execution_time=0 artisan queue:work --queue=languageProcessor --timeout=900 --tries=1`. Sitúe `retry_after` por encima del tiempo límite de cada tarea, por ejemplo 960 segundos, y configure `INTERPRESSO_PROCESS_LOCK_TTL=1800`. Para SQS, configure la visibilidad equivalente. Dimensione los límites según la tarea más larga y la espera; revise `failed_jobs` y los registros ante un fallo.
2. **Sin Supervisor: recomendado para alojamiento compartido.** Configure `QUEUE_CONNECTION=database`, active `interpresso.schedule.queue_worker` y añada la única línea cron de cada minuto indicada abajo. Los botones funcionan normalmente. Cron inicia un proceso con duración limitada y procesa las tareas disponibles en un minuto; las tareas largas y los retrasos pueden requerir ejecuciones posteriores. No necesita instalar Supervisor ni mantener un proceso permanente.
3. **Sync: solo instalaciones pequeñas.** Con `QUEUE_CONNECTION=sync`, use la interfaz para consultar y editar o revisar filas individuales. La interfaz rechaza operaciones largas e indica el comando Artisan correspondiente; ejecútelo manualmente en la CLI. Siguen aplicándose los límites de memoria y tiempo de PHP CLI y del alojamiento. Un tamaño pequeño nunca permite trabajo masivo dentro de HTTP.

Tras cambiar la configuración de colas, regenere la caché de configuración si se utiliza (`php artisan config:cache`) y reinicie los procesos permanentes. Con `null`, las notificaciones encoladas se descartan.

Una acción masiva rechazada indica su comando CLI exacto, por ejemplo `php artisan interpresso:import-translations`, y explica cómo una cola de base de datos con el planificador habilita el botón. No se escriben datos, no se inicia un lote ni se adquiere un bloqueo de operación. Actualizar con traducción automática muestra las mismas instrucciones antes de guardar el borrador raíz. La edición y aprobación individuales siguen disponibles.

| Acción de interfaz/API | Alternativa CLI |
| --- | --- |
| Importar idiomas | `php artisan interpresso:import-languages` |
| Importar traducciones | `php artisan interpresso:import-translations` |
| Buscar traducciones que faltan | `php artisan interpresso:find-missing-translations` |
| Aprobar todos los idiomas | `php artisan interpresso:approve-translations --translator=1` |
| Aprobar un idioma | `php artisan interpresso:approve-translations --translator=1 --language=en` |
| Exportar todos los idiomas | `php artisan interpresso:export-translations` |
| Exportar un idioma | `php artisan interpresso:export-translations --language=en` |
| Exportar modelos | Añadir `--only-models` al comando de exportación correspondiente |
| Exportación forzada por API remota | `php artisan interpresso:export-translations-deployment` |

El aviso de aprobación incluye el ID real del administrador conectado, y los específicos de idioma su código seleccionado. La API autenticada de exportación forzada devuelve HTTP **503** con una `message` JSON que contiene el comando si la conexión no puede diferir el trabajo. `interpresso:export-translations-deployment` reproduce el comportamiento de la API reescribiendo archivos y modelos incluso en modo base; el comando ordinario con `--force=1` respeta ese modo. Cada servidor receptor necesita conexión diferida y proceso de trabajo para exportaciones remotas. Una cola compatible con bloqueo activo sigue devolviendo **409**.

### Cron sin Supervisor {#cron-without-supervisor}

**Esta es la configuración recomendada para alojamiento compartido.** En el entorno de la aplicación, active la programación del proceso y utilice una caché persistente:

```dotenv
QUEUE_CONNECTION=database
INTERPRESSO_SCHEDULE_QUEUE_WORKER=true
CACHE_STORE=file
```

Esto activa `interpresso.schedule.queue_worker`, cuyo valor predeterminado es `false`. Al actualizar, añada las opciones que falten a la configuración publicada sin sobrescribir ajustes locales. Ejecute `php artisan migrate` si faltan tablas de colas/lotes y regenere la caché con `php artisan config:cache`. Añada exactamente una línea cron, adaptando la ruta y el programa PHP:

```cron
* * * * * cd /path/to/app && /usr/local/bin/php83 artisan schedule:run >> /path/to/app/storage/logs/cron.log 2>&1
```

Use la ruta explícita de PHP CLI indicada por su proveedor, por ejemplo `/usr/local/bin/php83`. El `php` predeterminado de cron suele ser más antiguo que la versión del sitio, por lo que una operación que funciona en el navegador puede fallar antes de iniciar Laravel. Compruebe `/usr/local/bin/php83 -v` y el registro de cron antes de descartar la salida. El usuario de cron necesita acceso de escritura al almacenamiento y a las rutas de exportación; los procesos en segundo plano son opcionales. Revise la planificación y pruebe una ejecución con el mismo programa:

```bash
/usr/local/bin/php83 artisan schedule:list
/usr/local/bin/php83 artisan interpresso:work
```

`interpresso:work` ejecuta `queue:work` para `interpresso.queue_name` en la conexión predeterminada, con `--stop-when-empty`, `--max-time=50`, `--max-jobs=100`, `--memory=96`, `--timeout=60`, `--sleep=0` y `--tries=1`. Una cola vacía termina inmediatamente con 0. El trabajo restante al alcanzar un límite se recoge en la siguiente ejecución; las tareas retrasadas o reservadas quedan para después.

Configure `interpresso.queue_worker.max_time` (segundos), `interpresso.queue_worker.max_jobs`, `interpresso.queue_worker.memory` (MB) e `interpresso.queue_worker.timeout` (segundos por tarea). Sus variables de entorno son `INTERPRESSO_QUEUE_WORKER_MAX_TIME`, `INTERPRESSO_QUEUE_WORKER_MAX_JOBS`, `INTERPRESSO_QUEUE_WORKER_MEMORY` e `INTERPRESSO_QUEUE_WORKER_TIMEOUT`. Todos deben ser enteros positivos; cero/ilimitado y valores incorrectos se rechazan. Los límites de tiempo y memoria se comprueban entre tareas. PHP CLI necesita PCNTL para que Laravel interrumpa una tarea bloqueada al agotar su tiempo; de lo contrario se necesita un límite de procesos del alojamiento. Configure también tiempos de red finitos. Mantenga `retry_after` por encima del límite de cada tarea, o ajuste la visibilidad SQS, e `interpresso.process_lock_ttl` por encima de la tarea ininterrumpida más larga y la espera prevista.

El proceso se programa cada minuto con `withoutOverlapping`. `interpresso.schedule.worker_background` tiene el valor predeterminado `true` (`INTERPRESSO_SCHEDULE_WORKER_BACKGROUND`). El planificador comprueba tanto `function_exists('proc_open')` como la configuración PHP `disable_functions`. Si no se pueden iniciar procesos o se desactiva el segundo plano, una función con nombre ejecuta `Artisan::call` en primer plano. Quitar únicamente `runInBackground()` seguiría haciendo que Laravel iniciara un subproceso. Las tareas de mantenimiento también usan funciones de retorno cuando `proc_open` no está disponible. El bloqueo de caché caduca tras `ceil((max_time + timeout) / 60) + 1` minutos, tres por defecto, para permitir que termine la última tarea. Una finalización normal lo libera antes. Use caché persistente de archivos en un servidor o compartida entre servidores, nunca caché array/null en memoria. Este bloqueo evita procesos cron solapados. El `ProcessLock` independiente en la base protege las operaciones entre HTTP, CLI y lotes; ambos bloqueos son necesarios. Las ejecuciones manuales no están protegidas por el bloqueo del planificador.

Las exportaciones en cola, incluidas las forzadas y de modelos, aprobaciones, búsquedas de traducciones faltantes e importaciones procesan como máximo `interpresso.chunk_size` filas de origen por tarea y añaden una sucesora con cursor al mismo lote. El valor predeterminado es `100`, configurable con `INTERPRESSO_CHUNK_SIZE`; redúzcalo si el proveedor limita mucho la duración de los procesos. Las traducciones faltantes con IA también respetan `max_open_ai_missing_trans`. Tras reiniciar, el proceso continúa desde el cursor en cola; una reserva terminada abruptamente puede repetir su bloque actual después del plazo `retry_after`. Los bloques completados permanecen guardados. Las tareas con cursor permiten reintentos de reserva, pero una excepción real de procesamiento hace fallar el lote inmediatamente. El progreso usa un total estimado, basado en el tamaño de las fuentes para archivos, y solo alcanza el 100% al terminar.

Las fuentes de base de datos usan cursores ordenados por clave primaria. Los archivos usan posiciones de entradas y rechazan fuentes modificadas entre bloques; mantenga los archivos estables hasta terminar el lote. Las importaciones y la creación de filas faltantes conservan las traducciones existentes al repetirse. Las exportaciones combinan claves y sustituyen archivos de forma atómica. Las entradas PHP/JSON y los archivos exportados existentes todavía requieren leer un archivo completo cada vez. Divida archivos excepcionalmente grandes si uno solo supera el límite de memoria o duración del proveedor. Una solicitud de IA interrumpida puede cobrarse de nuevo si aún no se había guardado su respuesta.

El límite predeterminado de `96` MB deja margen en un servidor de 128-256 MB. `INTERPRESSO_QUEUE_WORKER_MEMORY` o `interpresso:work --memory=64` cambia el límite entre tareas; el `memory_limit` de PHP sigue aplicándose dentro de cada tarea. `interpresso:work --max-time=30` sustituye el presupuesto de tiempo para una ejecución. Reduzca los bloques para acortar cada tarea; `--max-time` se comprueba entre tareas y no interrumpe un bloque en curso.

También funciona un proveedor que permita cron solo cada 5 o 15 minutos: cambie el primer campo a `*/5` o `*/15`. Las tareas pendientes o aplazadas comienzan proporcionalmente más tarde y una acumulación puede necesitar varios ciclos. Cada bloque renueva su `ProcessLock` en la base antes y después de trabajar. Mantenga `INTERPRESSO_PROCESS_LOCK_TTL` por encima del intervalo de cron más una ejecución del proceso y el retraso de planificación. El valor predeterminado de `1800` segundos deja margen para intervalos de 15 minutos. Al actualizar con un TTL guardado de `900` segundos, auméntelo para cron de 15 minutos. No se puede renovar mientras PHP está detenido. La finalización, el error o la cancelación libera el bloqueo propio del lote.

El mismo bloque ofrece opciones independientes: `interpresso.schedule.prune_batches` ejecuta `interpresso:prune-batches` cada minuto; `interpresso.schedule.pending_notifications` ejecuta `interpresso:send-automatic-pending-translations-notification` diariamente a medianoche en la zona horaria del planificador. Actívelas con `INTERPRESSO_SCHEDULE_PRUNE_BATCHES=true` e `INTERPRESSO_SCHEDULE_PENDING_NOTIFICATIONS=true`. Ambas son `false` por defecto, incluso con el proceso activo. El mantenimiento se ejecuta antes de un nuevo proceso programado; un bloqueo existente puede hacer que se omita una ejecución de mantenimiento. Los recordatorios automáticos necesitan además el ajuste guardado `enable_automatic_pending_notifications` y correo operativo. El ajuste se comprueba al ejecutar el comando; registrar la programación no lee la tabla de ajustes.

Se omiten las tres programaciones si la conexión no difiere el trabajo, incluidas sync/null/deferred/ausente o una conmutación insegura. No programan importaciones o aprobaciones por sí mismas: los administradores siguen usando la interfaz. Para desactivar el proceso cron, configure `INTERPRESSO_SCHEDULE_QUEUE_WORKER=false` y regenere la caché; una ejecución iniciada termina dentro de sus límites configurados.

### Por qué puede bloquearse una acción {#why-an-action-may-be-blocked}

Importaciones, búsqueda de traducciones ausentes, exportaciones, aprobación en bloque, actualización con traducción automática y todos los cambios individuales (actualizar, aprobar, solicitar, retirar solicitud, restaurar) no empiezan si hay bloqueo activo, tarea del paquete o lote incompleto sin cancelar. Lecturas del diálogo y sugerencias de borrador siguen disponibles porque no escriben traducciones. Los comandos de trabajo usan la misma protección. La coordinación añade las comprobaciones remotas indicadas; se ignoran servidores inaccesibles.

Los comandos comprueban bloqueo local, tareas, lotes incompletos sin cancelar y servidores configurados si la coordinación está activa. Adquieren el bloqueo de configuración con un único UPDATE condicional antes de trabajar, incluso con `QUEUE_CONNECTION=sync` y cron. Si está ocupado, informan del propietario (servidor, PID, operación e ID de invocación) y hora inicial, y vuelven sin trabajar. Excepciones y errores PHP liberan su bloqueo en `finally`.

`interpresso.process_lock_ttl` vale 1800 segundos por defecto y puede ajustarse mediante `INTERPRESSO_PROCESS_LOCK_TTL`. Los bloqueos caducados y los indicadores antiguos sin caducidad no impiden adquirirlo. Una importación larga lo renueva entre archivos y grupos de modelos mediante `ProcessLock::refresh()`; las operaciones propias prolongadas deben llamar a `refresh()` en su bloqueo antes de vencer el TTL. Fije el TTL por encima de la unidad ininterrumpida más larga. Bases separadas se coordinan mediante comprobaciones HTTP de mejor esfuerzo, no un bloqueo atómico distribuido.

Los cambios de controladores adquieren el mismo bloqueo antes de escribir o encolar. Los lotes mantienen la propiedad después de responder HTTP. Sus callbacks lo liberan al completar o fallar; las tareas reservadas comprueban cancelación y propiedad antes de trabajar. Los callbacks antiguos no pueden borrar un propietario sustituto. Los avisos de bloqueo incluyen propietario e inicio. Cancelación, gestión de idiomas/cuentas y configuración siguen disponibles.

La protección local y el servicio de cancelación consultan `jobs` y `job_batches` en la conexión predeterminada. `interpresso.db_connection` configura modelos y migraciones del paquete, pero no redirige todas las consultas de colas. Mantenga coherente su configuración. Borrar filas `jobs` de base no vacía una cola que usa otro almacenamiento.

Las notificaciones administrativas de éxito se entregan solo para lotes correctos y no cancelados; los fallos envían errores. Los cuatro campos de bloqueo y la caché de configuración se limpian al completar, cancelar, fallar el envío a cola o fallar una tarea. La CLI también los limpia cuando un servicio lanza un error PHP.

### Cancelar y mantener lotes {#cancel-and-maintain-batches}

Use **Idiomas > Cancelar procesamiento por lotes en curso** para marcar lotes incompletos como cancelados y borrar filas `jobs` de la cola del paquete. También puede eliminar notificaciones pendientes. Cancelar no revierte importaciones/ediciones/exportaciones completadas ni termina un proceso que ya ejecuta una tarea. Las tareas comprueban cancelación antes de entrar en su lógica, incluso si ya fueron reservadas. Las tareas con cursor también comprueban la cancelación antes de añadir su sucesora; un bloque en ejecución puede terminar sus escrituras. Revise el resultado antes de iniciar trabajo sustituto.

Con coordinación activa, el mismo botón solicita cancelación a otros servidores. No ofrece selector por tarea ni pantalla para reintentar fallos.

Ejecute `interpresso:prune-batches` para retirar lotes antiguos terminados/cancelados. No cancela trabajo activo ni elimina tareas encoladas/fallidas. Active `interpresso.schedule.prune_batches` para limpiar cada minuto e `interpresso.schedule.pending_notifications` para recordatorios diarios. Ambas opciones son voluntarias y requieren una cola diferida.

## Permisos y controles comunes {#permissions-and-shared-controls}

### Acceso de administradores y traductores {#administrator-and-translator-access}

Los administradores acceden a todos los idiomas, traducciones, gestión de traductores y configuración. Su interfaz incluye creación/eliminación de idiomas, importaciones, generación de traducciones que faltan, aprobación/exportación en bloque, cancelación y acciones de aprobación, solicitud y restauración.

La interfaz normal de un traductor no administrador permite:

- Listar y buscar idiomas asignados y abrir sus traducciones.
- Buscar, paginar y usar todos los filtros en esos idiomas.
- Elegir idioma de referencia, abrir Traducir, leer ejemplos y editar texto para revisión.
- Usar OpenAI si su ajuste y las condiciones de origen/ejemplo se cumplen.
- Leer este manual, gestionar sus notificaciones, cambiar de tema y cerrar sesión.

Los no administradores no ven la barra de acciones en bloque, aprobación/solicitud/restauración, exportación, gestión de traductores ni configuración. No disponen de pantalla de gestión de cuentas.

Todos los endpoints privilegiados exigen autorización de administrador en el servidor. Cada acción sobre una traducción resuelve su ID a través del idioma solicitado y comprueba asignaciones; un ID ajeno devuelve 403 sin cambios. Los no administradores pueden editar traducciones permitidas y pedir sugerencias de borrador, pero no aprobar, solicitar traducciones, restaurar, actualizar en bloque, exportar, gestionar cuentas, cambiar configuración ni mantener idiomas. Lectura, cambios de estado de lectura y marcado global de notificaciones se limitan al traductor autenticado y al tipo de destinatario.

### Navegación, tema y notificaciones {#navigation-theme-and-notifications}

La navegación incluye Idiomas y Manual para traductores conectados, con Traductores y Configuración para administradores. En pantallas pequeñas, abra el menú compacto. El botón de tema alterna claro/oscuro y guarda la preferencia en una cookie y el almacenamiento del navegador. El servidor aplica la cookie antes de mostrar la página. Sin elección guardada, la hoja de estilos sigue el sistema antes de JavaScript y la página continúa siguiendo sus cambios hasta que elija un tema.

La interfaz usa componentes DaisyUI con temas claro y oscuro para formularios, tablas, menús, notificaciones y editor. Los ajustes usan interruptores con casillas visibles; el editor conserva su botón Cerrar, Escape y cierre por clic exterior.

El botón de notificaciones muestra el número de mensajes sin leer. Abra el panel situado encima para leerlos, ciérrelo sin marcarlos, descarte uno para marcarlo leído o use **Marcar todas como leídas**. El panel se desplaza cuando hace falta. Se actualiza cada cinco segundos en pestañas visibles y se pausa en ocultas. Una respuesta sin cambios conserva controles y foco. Los avisos inmediatos son independientes: éxito, eliminación, información o advertencia, con botón de cierre y temporizador.

Una preferencia existente en `localStorage["color-theme"]` se migra a la cookie `interpresso-color-theme` al iniciar por primera vez el módulo externo de tema. Una cookie válida tiene prioridad y actualiza `.dark` y `data-theme`. En la primera visita tras actualizar, el servidor no conoce una preferencia solo local; esta puede sustituir el tema del sistema al cargar el módulo. Las siguientes peticiones aplican inmediatamente la cookie. El cambio de tema funciona aunque localStorage no esté disponible.

La cookie usa como ruta el prefijo URL del paquete, normalmente `/translator`. Guardar elimina un duplicado en la ruta con barra final (`/translator/`) para que una cookie antigua más específica no sobrescriba la nueva elección en la siguiente petición.

El paquete activa cabeceras estrictas de seguridad del navegador por defecto. Scripts y estilos se cargan de archivos externos, los datos de avisos se guardan en un atributo HTML escapado y la interfaz no puede incrustarse en un marco. Los despliegues CDN deben configurar orígenes de recursos permitidos en `interpresso.security_headers.extra_sources`; vea [Configuración](CONFIGURATION.md#browser-security-headers). Estas cabeceras solo se aplican a rutas web del paquete.

### Idioma de la interfaz {#interface-language}

En cualquier página, incluida la de acceso, elija English, Deutsch, Français, Español o Italiano en la navegación y pulse **Cambiar idioma**. La siguiente respuesta muestra inmediatamente el idioma elegido y el manual correspondiente. Este formulario funciona sin JavaScript.

Los cambios con sesión iniciada se guardan en el campo nullable `locale` del traductor y en la cookie `interpresso-locale`. Sin sesión, solo se guarda la cookie. Dura un año y usa el prefijo URL del paquete, normalmente `/translator`; está cifrada y usa HttpOnly, SameSite=Lax y Secure en HTTPS. La preferencia de la cuenta persiste tras cerrar e iniciar sesión, incluso desde otro navegador.

Se usa el primer valor disponible: preferencia del traductor, cookie, `interpresso.locale` y después `app.locale`. Los valores inválidos se ignoran; si ninguno es compatible, se usa inglés, o el primer idioma disponible si se ha eliminado el inglés. Enviar un idioma desconocido se rechaza sin guardar. Las opciones proceden de los directorios `lang/` del paquete y se almacenan en caché durante la vida de la aplicación. Reinicie los procesos persistentes de la aplicación tras añadir traducciones. Defina `global.locale_name` en un catálogo nuevo para mostrar su nombre nativo; en caso contrario se muestra su código.

Los administradores pueden establecer o borrar el **Idioma de la interfaz** en el formulario de creación o edición del traductor. **Navegador / predeterminado** borra la preferencia de la cuenta y vuelve a la cookie y la configuración. La elección conserva las asignaciones de idiomas, el idioma fuente de importación y los ajustes de la aplicación anfitriona.

Las actualizaciones añaden una columna nullable `locale` sin rellenar las cuentas existentes. Ejecute las migraciones del paquete y actualice las vistas publicadas si su aplicación las sobrescribe.

## Referencia CLI {#cli-reference}

Ejecute los comandos como `php artisan ...` desde el directorio de la aplicación Laravel anfitriona. Estas son las once firmas de `src/Console/Commands/`. Los comandos de trabajo adquieren el bloqueo compartido y pueden volver anticipadamente indicando propietario e inicio; ese retorno no significa que la operación se haya completado. Usan los ajustes guardados salvo las excepciones indicadas.

### interpresso:import-languages {#interpressoimport-languages}

Firma:

```text
interpresso:import-languages
```

Importa directorios de idiomas admitidos directamente bajo la ruta de idiomas Laravel y omite códigos registrados. No descubre idiomas solo a partir de `{locale}.json` ni importa contenido. Se ejecuta síncronamente y encola una notificación de resultado para administradores. Úselo para la primera importación o tras añadir directorios.

```bash
php artisan interpresso:import-languages
```

### interpresso:import-translations {#interpressoimport-translations}

Firma:

```text
interpresso:import-translations
```

Importa fuentes PHP/JSON y traducciones de modelos configurados de idiomas existentes. Respeta `import_vendor` e `import_only_from_root_language`. Conserva pares existentes de idioma/identificador compartido; las nuevas entradas empiezan aprobadas/exportadas. Informa del total existente y recién insertado. Úselo tras añadir claves o valores de modelo y después de importar los idiomas. No actualiza traducciones existentes desde archivos modificados.

```bash
php artisan interpresso:import-translations
```

### interpresso:find-missing-translations {#interpressofind-missing-translations}

Firma:

```text
interpresso:find-missing-translations
```

Compara cantidades de traducciones por idioma y, si difieren, crea las correspondientes entradas que faltan desde el idioma de origen. Los idiomas vacíos se representan por separado en esa comprobación. Usa `app.locale` o el primer idioma si falta, y puede usar OpenAI. Las nuevas filas quedan sin aprobar, pendientes de traducción y sin exportar. Informa de inserciones y encola una notificación administrativa del idioma de origen si existe ese registro.

Úselo tras importar claves de origen o añadir idiomas. **Limitación:** cantidades iguales pueden ocultar conjuntos de claves distintos; la CLI responde `Everything up to date.` sin comprobar identificadores. La acción Buscar traducciones que faltan de la interfaz llama al servicio sin este atajo.

```bash
php artisan interpresso:find-missing-translations
```

### interpresso:approve-translations {#interpressoapprove-translations}

Firma:

```text
interpresso:approve-translations {--translator=} {--language=}
```

Aprueba síncronamente todas las traducciones sin aprobar, o solo las de `--language=en`. `--translator=ID` es obligatorio y debe identificar a un traductor administrador existente; las aprobaciones se le atribuyen. Una atribución inválida o idioma desconocido termina con estado 1 sin escrituras. Usa el bloqueo compartido, lo renueva entre idiomas, invalida cachés mediante el servicio de aprobación existente y envía notificaciones administrativas. Funciona con `QUEUE_CONNECTION=sync` sin proceso de trabajo. Revise antes de ejecutarlo.

```bash
php artisan interpresso:approve-translations --translator=1
php artisan interpresso:approve-translations --translator=1 --language=en
```

### interpresso:export-translations {#interpressoexport-translations}

Firma:

```text
interpresso:export-translations {--force=} {--language=} {--only-models}
```

Exporta traducciones aprobadas y no modificadas. Normalmente solo incluye filas marcadas sin exportar. `--force` acepta un valor que se convierte en booleano: use `--force=1` para incluir filas ya exportadas; omitirlo o usar `--force=0` mantiene el comportamiento normal. Forzar no omite aprobación ni protección contra tareas activas.

En modo archivos exporta PHP/JSON y modelos. En modo base pasa la opción de solo modelos y omite archivos. `--only-models` también limita a modelos en modo archivos. `--language=en` restringe tanto el recuento como la ejecución al idioma; un código desconocido falla sin exportar. Se ejecuta síncronamente y encola notificaciones administrativas; no desencadena exportaciones remotas. Los recuentos indican filas de traducción, no archivos.

Úselo para publicación ordinaria o, en modo archivos, para forzar la reescritura de archivos cuyas filas ya están marcadas como exportadas.

```bash
php artisan interpresso:export-translations
php artisan interpresso:export-translations --force=1
```

### interpresso:export-translations-deployment {#interpressoexport-translations-deployment}

Firma:

```text
interpresso:export-translations-deployment
```

Exporta de forma forzada y síncrona las traducciones aprobadas no modificadas de cada idioma, incluidos modelos, ignorando Exportado. No acepta `--force`. Usa la protección compartida, no propaga exportaciones y no pasa la restricción de solo modelos del modo base; por ello puede escribir archivos incluso con `db_loader=true`.

Úselo después de un despliegue en modo archivos que haya sustituido traducciones exportadas. No es necesario para servir desde base; omítalo de ese flujo. Imprime una finalización por idioma aunque no hubiera contenido apto.

```bash
php artisan interpresso:export-translations-deployment
```

### interpresso:work {#interpressowork}

Firma:

```text
interpresso:work [--max-time=SECONDS] [--memory=MB]
```

Procesa solo la cola configurada del paquete y sale cuando está vacía o alcanza el presupuesto de tiempo/tareas. El trabajo restante continúa en el siguiente ciclo de cron. Devuelve 0 ante cola vacía o parada normal por presupuesto, 1 ante conexiones no diferidas o límites inválidos y, en los demás casos, el código del proceso. Puede haber tareas fallidas incluso con salida 0; revise los registros de errores y las tareas fallidas. Use `--max-time` y `--memory` para sustituir los límites en una ejecución; configure los valores predeterminados como se describe en [Cron sin Supervisor](#cron-without-supervisor).

```bash
php artisan interpresso:work
```

### interpresso:prune-batches {#interpressoprune-batches}

Firma:

```text
interpresso:prune-batches
```

Elimina filas coincidentes con `interpresso.batch_name` en `job_batches` sobre `interpresso.db_connection` cuando su finalización o cancelación es anterior a `interpresso.prune_batch_hours`, por defecto 24 horas. No toca lotes activos, tareas de cola ni registros de fallos. No tiene opciones propias ni salida de finalización.

Úselo para limpiar lotes retenidos. Active `interpresso.schedule.prune_batches` para limpieza automática cada minuto, o configure su propia programación.

```bash
php artisan interpresso:prune-batches
```

### interpresso:send-automatic-pending-translations-notification {#interpressosend-automatic-pending-translations-notification}

Firma:

```text
interpresso:send-automatic-pending-translations-notification
```

Este es el nombre real implementado por `SendAutomaticPendingNotifications`; `interpresso:send-automatic-pending-notifications` no es un alias registrado.

Si `enable_automatic_pending_notifications` es verdadero, recorre todos los traductores, incluidos administradores, y sus asignaciones explícitas. En cada idioma con filas pendientes de traducción encola notificaciones para base y correo. Cero filas pendientes no producen entrega. Si está desactivado, no hace nada. No requiere `enable_pending_notifications`, usa el bloqueo compartido y no muestra resumen de éxito.

Úselo para recordatorios recurrentes con correo operativo y un proceso permanente o lanzado por cron. Active `interpresso.schedule.pending_notifications` para la programación diaria, o configure la suya. Repetir el comando puede enviar otro recordatorio del mismo trabajo pendiente.

```bash
php artisan interpresso:send-automatic-pending-translations-notification
```

### interpresso:developer-download {#interpressodeveloper-download}

Firma:

```text
interpresso:developer-download
```

Descarga idiomas y traducciones paginadas desde `interpresso.main_server_domain`, configurado mediante `INTERPRESSO_MAIN_SERVER_DOMAIN`, usando `INTERPRESSO_API_SHARED_SECRET`. Sustituye las filas locales de idiomas y traducciones y después exporta forzosamente el contenido descargado aprobado y no modificado. El modo archivos exporta archivos y modelos; el modo base solo modelos. No descarga configuración, cuentas de traductores ni asignaciones.

Úselo solo para sustituir deliberadamente una copia de desarrollo por los datos del servidor principal. **Elimina/sustituye trabajo de traducción local sin pedir confirmación.** Se aplica el bloqueo compartido. No hay protección de entorno local, simulación, argumento de servidor ni modo de combinación. La fase de base usa una transacción y sentencias MySQL/MariaDB `SET FOREIGN_KEY_CHECKS`, por lo que no es portable tal cual a SQLite o PostgreSQL. La exportación ocurre después del commit; un fallo de exportación no revierte la sustitución de la base. Los destinos de modelos locales deben existir para exportarlos.

```bash
php artisan interpresso:developer-download
```

### interpresso:unlock {#interpressounlock}

Firma:

```text
interpresso:unlock {--force}
```

Muestra propietario e inicio registrados. Sin `--force`, limpia bloqueos caducados o antiguos y rechaza uno activo con código 1. `--force` elimina también uno activo. La liberación correcta devuelve 0 y limpia `process_running`, `process_owner`, `process_started_at` y `process_expires_at`. Limpiar una fila ya desbloqueada es inocuo. La limpieza solo de caducados usa un UPDATE condicional para no borrar una adquisición o renovación simultánea.

```bash
php artisan interpresso:unlock
php artisan interpresso:unlock --force
```

Desbloquear no detiene un proceso PHP, cancela registros de cola ni revierte cambios. Detenga o verifique el proceso antiguo antes de forzar un bloqueo activo. Las tareas/lotes existentes pueden seguir bloqueando; use **Cancelar procesamiento por lotes en curso** para cancelarlos. La cancelación libera solo el bloqueo de ese lote, no el de una ejecución cron independiente.

## Resolución de problemas {#troubleshooting}

### Las tareas no terminan o se informa de otro proceso {#jobs-do-not-finish-or-another-process-is-reported}

Para colas asíncronas, compruebe que un proceso consume la cola configurada, normalmente `languageProcessor`. Los comandos cron/artisan con `QUEUE_CONNECTION=sync` no requieren proceso de trabajo para traducciones; HTTP en bloque siempre rechaza sync. Compruebe propietario, inicio y caducidad; use `interpresso:unlock` para metadatos obsoletos y `--force` solo tras confirmar que el proceso anterior se detuvo. Revise `jobs` de la cola del paquete, lotes `languageBatch` incompletos, registros y tareas fallidas. Las notificaciones pendientes también pueden ocuparla. Un proceso que escucha solo la cola predeterminada no procesa la del paquete.

Tras comprobar si aún se trabaja, use **Cancelar procesamiento por lotes en curso** para trabajo abandonado. Limpiar lotes antiguos no es cancelar. En instalaciones con varios servidores, compruebe conexiones y secretos; un servidor caído se ignora al comprobar ocupación, pero la propagación de exportaciones puede fallar. Revise las limitaciones anteriores de conexiones de tablas y colas fuera de base.

### Falta un idioma, una clave o un ejemplo de origen {#a-language-key-or-source-example-is-missing}

Importar idiomas solo reconoce directorios admitidos. Añada el idioma expresamente para fuentes solo JSON. Compruebe que existe `lang/` antes de importar; el modo base no crea directorios de origen que faltan durante la importación. Verifique los registros de origen/respaldo y actualice la lista tras importaciones en segundo plano.

Importar traducciones solo visita idiomas guardados, y los ajustes de origen único/paquetes pueden excluir fuentes. Reimportar no sobrescribe valores existentes. Buscar traducciones que faltan copia identificadores de origen, no claves exclusivas de otro idioma; el atajo CLI de cantidades iguales puede omitir diferencias. Use entonces la interfaz. La ausencia del registro `app.fallback_locale` impide montar el editor; vuelva a crear ese idioma.

### Parece que los filtros o ajustes no se guardan {#filters-or-settings-appear-not-to-save}

Búsqueda y filtros recargan la página con parámetros de URL. Los ajustes se envían individualmente al cambiar. Espere a que termine la navegación y revise errores junto a campos rechazados. Guarde Dominios antes de activar coordinación y desactívela antes de vaciarlos. Si los controles no responden, reconstruya y publique los recursos del paquete y revise errores JavaScript o de red. Las consultas de lotes/notificaciones se pausan intencionadamente en pestañas ocultas.

### No aparece contenido aprobado o los archivos no cambian {#approved-content-is-not-visible-or-files-are-unchanged}

En modo archivos, apruebe cambios y expórtelos. Las exportaciones normales omiten filas sin aprobar, modificadas o ya exportadas; use `--force=1` solo para reexportar contenido aprobado no modificado. Verifique permisos y validez de fuentes PHP/JSON. JSON existente mal formado y errores de codificación interrumpen la exportación en lugar de sustituir silenciosamente el archivo por contenido inválido.

En modo base, que no se escriban archivos es normal. Compruebe aprobación, valor anterior, caché y prefijo compartido. Exportado falso no impide servir desde base. Las columnas de modelos necesitan exportación. El comando de despliegue y el endpoint de exportación forzada pueden escribir archivos pese a ese ajuste.

### Fallos de base de datos, caché o configuración {#database-cache-or-settings-failures}

Restaure la conexión a base ante una caída web en modo base; no existe respaldo automático a archivos. `CACHE_DRIVER`/`CACHE_STORE` deben usar almacenamiento fuera de base, con configuración apropiada compartida entre web y procesos de trabajo. El paquete invalida entradas versionadas tras commit; las escrituras externas en bloque necesitan la llamada de invalidación.

Si no hay tabla de configuración o está vacía, la selección puede elegir el cargador de archivos Laravel. Las operaciones que requieren ajustes lanzan `MissingSettingsException` si falta su fila. Restáurela; el paquete no la recrea ni sustituye preferencias automáticamente. Tras repararla fuera de la interfaz, actualice su caché mediante `Setting::getFreshCached()` en el código de mantenimiento.

### OpenAI o los recordatorios por correo no hacen nada {#openai-or-pending-email-does-nothing}

Compruebe la opción correspondiente, paquete OpenAI opcional, configuración API, ejemplo elegido y registros. Un fallo de OpenAI puede devolver el origen intacto. Los botones dependen de si es el idioma de origen y de la presencia de ejemplos; los textos generados aún necesitan revisión y aprobación.

Los recordatorios cuentan filas que necesitan traducción, no todas las no aprobadas. Compruebe asignaciones explícitas, correo y proceso de colas. Los ajustes manual y automático son independientes. Para recordatorios diarios, active también `interpresso.schedule.pending_notifications`. Marcar una notificación como leída no cambia el estado de traducción.

### Errores de acceso, rutas y entre servidores {#access-routes-and-inter-host-errors}

Si falta una ruta web, compruebe `INTERPRESSO_ENABLED`, prefijo y coincidencia exacta del servidor principal con `app.url`. Ante 403 de un no administrador, revise asignaciones y restricciones de la pantalla. Si falta una acción de fila, compruebe las condiciones de aprobación/solicitud/valor anterior.

HTTP 503 entre servidores significa, tras validar la petición, que no hay secreto compartido; HTTP 401 indica diferencia y HTTP 422 puede señalar `api_key` ausente o inválido. Use el mismo secreto en todos y URL con protocolo. El interruptor de coordinación no desactiva la API.

### Excepciones de importación, exportación de modelos o descarga de desarrollo {#import-model-export-or-developer-download-exceptions}

Revise rutas e ID de error registrados para fuentes inválidas o errores de inserción. Valores de origen, espacios de nombres o grupos null no son metadatos válidos para copiar traducciones que faltan. Las traducciones de aplicación usan espacio de nombres vacío; JSON usa grupo vacío. Exportar modelos requiere clase Eloquent, registro existente y columna JSON de traducción existente. Los destinos ausentes provocan una excepción en lugar de marcarse silenciosamente exportados.

La descarga de desarrollo requiere SQL compatible con MySQL/MariaDB, endpoints del servidor principal accesibles y secreto compartido correcto. Reemplaza datos locales y confirma antes de exportar; identifique qué fase falló antes de repetir. Los traductores y asignaciones locales no se sincronizan en la descarga.

<!--
UI styling update: preserve the rendered manual wording for the visual-only change.
The colour-based state-filter instructions above refer to the previous styling.
Current state cycle: outlined = unrestricted; filled with tick = true; filled with cross = false.

Los colores de los botones indican sus consecuencias: verde para aprobar, rojo para eliminar o retirar una solicitud de traducción, amarillo para restaurar, azul para exportar y color principal para importar, buscar entradas faltantes y traducir. Cerrar y Buscar usan botones discretos. Los filtros tienen contorno y se rellenan al aplicar una selección. Las acciones de fila son compactas y las de página algo mayores. Las tablas tienen filas alternas y encabezados fijos en la zona de desplazamiento. Las marcas significan Sí y las cruces discretas No, con etiquetas accesibles traducidas. El campo de búsqueda y su botón forman un solo control. Estas convenciones se aplican a los temas claro y oscuro.
-->
