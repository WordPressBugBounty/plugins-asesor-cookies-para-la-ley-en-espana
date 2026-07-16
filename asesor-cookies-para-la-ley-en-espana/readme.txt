=== Asesor de Cookies RGPD para normativa europea ===
Contributors: cdoral
Tags: cookie, cookies, rgpd, gdpr, consent
Requires at least: 3.5
Requires PHP: 7.2
Tested up to: 7.0
Stable tag: 1.0.45
License: GPLv2 or later

Gestiona el consentimiento de cookies con banner, inventario, scripts por categoría y embebidos protegidos.

== Description == 

> **[Para más información visite Web Artesanal](https://webartesanal.com/)**

Asesor de Cookies RGPD permite mostrar un banner de consentimiento con botones para aceptar todo, rechazar las cookies no necesarias o configurar preferencias por categoría.

El plugin no intenta adivinar ni bloquear automáticamente todos los elementos de una web. El propietario debe revisar su sitio y configurar los scripts, embebidos y cookies declaradas. El plugin proporciona las herramientas para hacerlo de forma ordenada.

Características del plugin:

* Banner de cookies con aceptar todo, rechazar y configurar.
* Textos del banner editables en español, inglés, francés y alemán.
* Enlace a la política de cookies añadido automáticamente desde la URL configurada.
* Inventario manual de cookies con biblioteca de servicios habituales.
* Cookie técnica del propio plugin declarada automáticamente.
* Categorías fijas: necesarias, analíticas, marketing y personalización.
* Campos para cargar scripts sólo después del consentimiento correspondiente.
* Shortcode y bloque Gutenberg para proteger vídeos, mapas, iframes y otros embebidos externos.
* Escáner de embebidos para ayudar a localizar contenidos externos no protegidos.
* Auditoría asistida para ayudar a detectar cookies visibles y recursos externos mientras un administrador navega por la web.
* Creación automática de una página de política de cookies con tabla dinámica mediante shortcode.
* Opción para colocar el menú en Herramientas o como menú principal del administrador.
* Botón permanente de preferencias configurable como texto o icono.

Importante: declarar cookies en el inventario documenta su uso, pero no las bloquea. Para bloquear cookies no necesarias hay que controlar los scripts y embebidos que las crean.
 
== Screenshots ==

1. Banner de consentimiento mostrado al visitante.
2. Panel de configuración por categorías.
3. Inventario de cookies y biblioteca de servicios.
4. Herramientas de auditoría asistida y escaneo de embebidos.

== Installation ==

1. Descargue el plugin, descomprímalo y súbalo al directorio /wp-content/plugins/
2. Vaya al apartado Plugins y active Asesor de Cookies RGPD.
3. Vaya a Herramientas, Asesor de Cookies.
4. Revise el texto del banner y configure la URL de la política de cookies.
5. Cree la página de política de cookies desde el plugin o añada el shortcode [cdp_cookies_policy_table] a su página existente.
6. Revise los scripts de la web que instalan cookies y muévalos a la categoría correspondiente dentro del plugin.
7. Proteja vídeos, mapas, iframes y otros embebidos externos con el bloque Gutenberg "Contenido protegido por consentimiento" o con el shortcode [cdp_consent].
8. Complete el inventario de cookies manualmente, con ayuda de la biblioteca de servicios y de la auditoría asistida.
9. Vacíe la caché de WordPress y pruebe la web en una sesión limpia del navegador.

Si lo desea, como método alternativo de instalación puede ir a la sección Plugins y hacer lo siguiente:

1. Pulse 'Añadir nuevo'.
2. En el buscador escriba 'asesor cookies'.
3. Haga click en 'Instalar'.
4. Ahora siga desde el paso 2 de la sección anterior.

== Frequently Asked Questions ==

= ¿El plugin detecta automáticamente todas las cookies de mi web? =

No. La detección automática completa no es fiable en una web WordPress real, especialmente cuando intervienen temas, builders, plugins, cachés, iframes o servicios externos. El plugin incluye una auditoría asistida para ayudarle a localizar cookies visibles y recursos externos, pero siempre requiere revisión manual.

= ¿Declarar una cookie en el inventario la bloquea? =

No. El inventario sirve para documentar las cookies en la política. Para bloquear una cookie no necesaria hay que impedir que se cargue el script o embebido que la crea hasta que el visitante dé su consentimiento.

= ¿Cómo se bloquean scripts de analítica o marketing? =

Debe retirar esos scripts del tema, builder, plugin o código personalizado donde estén cargándose y pegarlos en el campo de scripts de la categoría correspondiente. El plugin sólo cargará esos scripts cuando el visitante acepte esa categoría.

= ¿Cómo se bloquean vídeos, mapas u otros embebidos externos? =

En Gutenberg puede usar el bloque "Contenido protegido por consentimiento" y colocar el embebido dentro. En otros editores puede envolver el contenido con el shortcode [cdp_consent category="personalization" service="Google Maps"]...[/cdp_consent].

= ¿Qué hace la auditoría asistida? =

Permite que un administrador navegue por la web mientras el plugin registra cookies visibles del dominio actual y recursos externos cargados por la página. No detecta cookies HttpOnly ni cookies de terceros guardadas en dominios como Google, YouTube o redes sociales.

= ¿Qué categorías puedo usar? =

El plugin utiliza categorías fijas: necesarias, analíticas, marketing y personalización. En los shortcodes se usan los valores técnicos necessary, analytics, marketing y personalization.

== Changelog ==

= 1.0.45 =
* Permite editar los textos de los botones principales del banner en español, inglés, francés y alemán.
* Presenta los textos editables de los botones en filas identificadas por idioma y con las etiquetas alineadas a la izquierda.
* Permite reabrir el banner desde cualquier enlace que apunte a #cdp-cookies-preferences.
* Destaca el shortcode de la tabla del inventario en Inicio, Política de cookies e Inventario.

= 1.0.43 =
* Mejora la activación de scripts configurados tras aceptar el consentimiento.

= 1.0.42 =
* Evita que el banner de cookies se renderice dentro del administrador, REST, AJAX o editores de widgets/bloques.

= 1.0.41 =
* Ajustes de textos del encabezado del administrador y contador de cookies traducido.

= 1.0.40 =
* Nueva interfaz de administración.
* Banner con aceptar todo, rechazar y configurar.
* Textos del banner en español, inglés, francés y alemán.
* Enlace de política añadido dinámicamente desde la URL configurada.
* Inventario manual de cookies y biblioteca de servicios.
* Cookie técnica del plugin declarada automáticamente.
* Carga de scripts según consentimiento.
* Bloque Gutenberg y shortcode para proteger embebidos externos.
* Auditoría asistida de cookies y recursos externos.
* Escáner de embebidos externos.
* Página de política de cookies con tabla dinámica.
* Compatibilidad revisada con PHP 7.2 o superior.

= 0.34 =
* correcciones seguridad

= 0.33 =
* cambios mínimos

= 0.32 =
* cambios mínimos

= 0.31 =
* cambios mínimos

= 0.30 = 
* Fallo en estilos

= 0.29 =
* Probado en WP 5.3.2
* Fuera publi

= 0.28 =
* Probado en WP 5.0.1

= 0.27 =
* Testeo en versiones modernas WP

= 0.26 =
* Problemas al publicar con svn

= 0.25 =
* Ocultación de solapa por petición de usuarios
 
= 0.24 =
* Problemas al publicar con svn

= 0.23 =
* Problemas al publicar con svn

= 0.22 =
* Ahora el aviso de cookies siempre aparece flotante y en la parte inferior, se eliminan opciones como ponerlo en la parte superior, añadirlo al body como parte del contenido, elección del tipo de botón (cerrar, aceptar) y otras opciones que complicaban la configuración.
* Se añade solapa permanente para mostrar el aviso de cookies en cualquier momento.
* Se elimina la opción de dar el consentimiento de forma automática, ahora el visitante siempre debe pulsar el botón ACEPTAR.

= 0.21 =
* Corregido error que hacía desaparecer los enlaces del resto de plugins.

= 0.20 =
* No se veía la ventana con algunos temas WordPress como Divi

= 0.19 =
* Se añade botón Configuración en la página de plugins para acceder directamente a la configuración del Asesor de Cookies.
* Se elimina una petición ajax al servidor por generar problemas en algunas instalaciones WP.
* Se combinan los 3 archivos JS en uno sólo para mejorar el rendimiento.
* Se arregla la previsualización que no funcionaba correctamente.
* Se resuelve problema cuando hay dos instalaciones WP en el mismo dominio y anidadas. Gracias Mikel!
* Detalles CSS

= 0.18 =
* En algunas instalaciones se producian definiciones duplicadas en traer_aviso.php. Gracias a Mikel Gutierrez por su soporte.
* Se renuevan banners.

= 0.17 =
* Errores al subir al repositorio svn.

= 0.16 =
* Errores al subir al repositorio svn.

= 0.15 =
* Validación W3C, la inclusión de CSS no validaba, gracias por avisar Julio!
* El plugin ahora funciona correctamente si el directorio de administración WP tiene protección .htaccess. Gracias a Antonio Rodríguez por avisar.
* Banner superior en admin.

= 0.14 =
* Opción a incluir un botón CERRAR o ACEPTAR en el aviso.
* Pequeños detalles Javascript para prevención de conflictos con otros plugins.
* Algunos detalles en CSS
* Inclusión de enlace al plugin

= 0.13 =
* El texto del aviso ahora es editable.
* Se puede cambiar el tamaño de fuente.
* Corregido error que aparecía cuando un usuario no administrador entraba al back de WP.

= 0.12 =
* readme.txt actualizado y capturas de pantalla.

= 0.11 =
* Versión inicial.

== Upgrade Notice ==

= 1.0.45 =
Añade la edición multidioma de los botones, permite reabrir la configuración desde enlaces y mejora la visibilidad del shortcode del inventario.

= 1.0.43 =
Mejora la carga de scripts configurados tras aceptar cookies.
Actualiza capturas de pantalla del plugin.

= 1.0.42 =
Corrige la carga del banner en pantallas de administración y mantiene la nueva gestión por consentimiento.

== Troubleshooting ==

Si este plugin no te funciona correctamente prueba a hacer lo siguiente:
* Borra el caché de tu navegador, a veces se quedan versiones antiguas de archivos CSS y JS.
* Si utilizas algún sistema de caché en tu instalación WordPress prueba a borrar dicho caché.

Si te sigue fallando puede ser porque otro plugin genere errores Javascript y esto impide el funcionamiento del Asesor de Cookies. Puedes probar a desactivar otros plugins para saber cuál está dando problemas.

**[Te recomendamos nuestro plugin sobre mantenimiento de un sitio WordPress](https://wordpress.org/plugins/mantenimiento-web/)**
