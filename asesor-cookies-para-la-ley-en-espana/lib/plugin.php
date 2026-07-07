<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class cdp_cookies {
	const CONSENT_COOKIE = 'cdp_cookie_consent';
	const OPTION_COOKIES = 'cdp_cookies_items';
	const OPTION_SCRIPTS = 'cdp_cookies_scripts';
	const OPTION_MENU_LOCATION = 'cdp_cookies_menu_location';
	const OPTION_MIGRATION_VERSION = 'cdp_cookies_migration_version';
	const OPTION_BANNER_TEXTS = 'cdp_cookies_textos_aviso';
	const OPTION_AUDIT_ENABLED = 'cdp_cookies_audit_enabled';
	const OPTION_AUDIT_ITEMS = 'cdp_cookies_audit_items';
	const OPTION_AUDIT_IGNORED_ITEMS = 'cdp_cookies_audit_ignored_items';
	const OPTION_AUDIT_RECORDING = 'cdp_cookies_audit_recording';
	const OPTION_AUDIT_EXTERNAL_RESOURCES = 'cdp_cookies_audit_external_resources';
	const OPTION_PREFERENCES_BUTTON = 'cdp_cookies_preferences_button';
	const OPTION_EMBED_SCAN_RESULTS = 'cdp_cookies_embed_scan_results';
	const OPTION_EMBED_SCAN_DATE = 'cdp_cookies_embed_scan_date';

	private static $nombre_plugin;

	public static function ejecutar() {
		if ( ! ( function_exists( 'add_action' ) && defined( 'ABSPATH' ) ) ) {
			throw new cdp_cookies_error( 'Este plugin no puede ser llamado directamente' );
		}

		self::load_textdomain();
		add_action( 'plugins_loaded', array( __CLASS__, 'load_textdomain' ) );
		add_action( 'init', array( __CLASS__, 'register_blocks' ) );
		add_shortcode( 'cdp_cookies_policy_table', array( __CLASS__, 'shortcode_policy_table' ) );
		add_shortcode( 'cdp_consent', array( __CLASS__, 'shortcode_consent' ) );
		self::run_migrations();

		if ( is_admin() ) {
			add_filter( 'plugin_action_links', array( __CLASS__, 'enlaces_pagina_plugins' ), 10, 2 );
			add_action( 'admin_menu', array( __CLASS__, 'crear_menu_admin' ) );
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'cargar_archivos_admin' ) );
			add_action( 'admin_init', array( __CLASS__, 'handle_admin_request' ) );
			add_action( 'wp_ajax_cdp_cookies_delete_cookie', array( __CLASS__, 'ajax_delete_cookie' ) );
			add_action( 'wp_ajax_cdp_cookies_audit_detected', array( __CLASS__, 'ajax_audit_detected' ) );
			return;
		}

		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'cargar_archivos_front' ) );
		add_action( 'wp_footer', array( __CLASS__, 'renderizar_aviso' ), 20 );
	}

	public static function load_textdomain() {
		load_plugin_textdomain( 'asesor-cookies-para-la-ley-en-espana', false, dirname( CDP_COOKIES_BASENAME ) . '/languages' );
	}

	public static function parametro( $nombre, $valor = null ) {
		$vdef = array(
			'posicion_solapa'          => 'ocultar',
			'alineacion'               => 'izq',
			'tema'                     => 'gris',
			'enlace_politica'          => '',
			'enlace_mas_informacion'   => '',
			'texto_aviso'              => self::get_default_banner_text(),
			'tam_fuente'               => '14px',
		);

		if ( ! array_key_exists( $nombre, $vdef ) ) {
			throw new cdp_cookies_error( sprintf( 'Parámetro desconocido: %s', $nombre ) );
		}

		if ( null === $valor ) {
			return get_option( 'cdp_cookies_' . $nombre, $vdef[ $nombre ] );
		}

		update_option( 'cdp_cookies_' . $nombre, $valor );
	}

	private static function get_supported_banner_languages() {
		return array(
			'es' => 'ES',
			'en' => 'EN',
			'fr' => 'FR',
			'de' => 'DE',
		);
	}

	private static function get_current_language() {
		$locale = determine_locale();
		$lang = substr( $locale, 0, 2 );

		return array_key_exists( $lang, self::get_supported_banner_languages() ) ? $lang : 'en';
	}

	private static function get_default_banner_text( $language = null ) {
		$language = $language && array_key_exists( $language, self::get_supported_banner_languages() ) ? $language : self::get_current_language();
		$texts = array(
			'es' => 'Utilizamos cookies propias y de terceros para garantizar el funcionamiento de la web, medir su uso y mejorar nuestros servicios. Puede aceptar todas las cookies, rechazar las no necesarias o configurar sus preferencias.',
			'en' => 'We use our own and third-party cookies to ensure the website works properly, measure usage, and improve our services. You can accept all cookies, reject non-essential cookies, or configure your preferences.',
			'fr' => 'Nous utilisons nos propres cookies et des cookies tiers pour assurer le bon fonctionnement du site, mesurer son utilisation et améliorer nos services. Vous pouvez accepter tous les cookies, refuser les cookies non essentiels ou configurer vos préférences.',
			'de' => 'Wir verwenden eigene Cookies und Cookies von Drittanbietern, um den Betrieb der Website sicherzustellen, die Nutzung zu messen und unsere Dienste zu verbessern. Sie können alle Cookies akzeptieren, nicht notwendige Cookies ablehnen oder Ihre Präferenzen konfigurieren.',
		);

		return $texts[ $language ];
	}

	private static function get_policy_link_html( $language = null ) {
		$policy_url = get_option( 'cdp_cookies_enlace_politica', '' );
		$language = $language && array_key_exists( $language, self::get_supported_banner_languages() ) ? $language : self::get_current_language();
		$link_labels = array(
			'es' => 'Política de cookies',
			'en' => 'Cookie policy',
			'fr' => 'Politique de cookies',
			'de' => 'Cookie-Richtlinie',
		);

		if ( ! $policy_url ) {
			return '';
		}

		return sprintf(
			'<a href="%s">%s</a>',
			esc_url( $policy_url ),
			esc_html( $link_labels[ $language ] )
		);
	}

	private static function strip_policy_links_from_banner_text( $text ) {
		$labels = array(
			'Política de cookies',
			'Cookie policy',
			'Politique de cookies',
			'Cookie-Richtlinie',
		);

		foreach ( $labels as $label ) {
			$pattern = '/\s*<a\b[^>]*>\s*' . preg_quote( $label, '/' ) . '\s*<\/a>\s*/iu';
			$text = preg_replace( $pattern, ' ', $text );
		}

		return trim( preg_replace( '/[ \t]+/', ' ', (string) $text ) );
	}

	private static function get_banner_texts() {
		$texts = get_option( self::OPTION_BANNER_TEXTS, array() );
		$texts = is_array( $texts ) ? $texts : array();

		foreach ( self::get_supported_banner_languages() as $language => $label ) {
			if ( empty( $texts[ $language ] ) ) {
				$texts[ $language ] = self::get_default_banner_text( $language );
			}

			$texts[ $language ] = self::strip_policy_links_from_banner_text( $texts[ $language ] );
		}

		return $texts;
	}

	private static function get_banner_text() {
		$texts = self::get_banner_texts();
		$language = self::get_current_language();

		$text = ! empty( $texts[ $language ] ) ? $texts[ $language ] : $texts['en'];
		$policy_link = self::get_policy_link_html( $language );

		return $policy_link ? trim( $text ) . ' ' . $policy_link : $text;
	}

	private static function run_migrations() {
		$version = get_option( self::OPTION_MIGRATION_VERSION, '' );

		if ( version_compare( (string) $version, '1.0.2', '>=' ) ) {
			return;
		}

		$texts = array();
		foreach ( self::get_supported_banner_languages() as $language => $label ) {
			$texts[ $language ] = self::get_default_banner_text( $language );
		}

		update_option( self::OPTION_BANNER_TEXTS, $texts, false );
		update_option( 'cdp_cookies_texto_aviso', $texts[ self::get_current_language() ] );
		update_option( self::OPTION_MIGRATION_VERSION, '1.0.2', false );
	}

	public static function get_categories() {
		return array(
			'necessary'       => __( 'Necessary', 'asesor-cookies-para-la-ley-en-espana' ),
			'analytics'       => __( 'Analytics', 'asesor-cookies-para-la-ley-en-espana' ),
			'marketing'       => __( 'Marketing', 'asesor-cookies-para-la-ley-en-espana' ),
			'personalization' => __( 'Personalization', 'asesor-cookies-para-la-ley-en-espana' ),
		);
	}

	public static function get_category_description( $category ) {
		$descriptions = array(
			'necessary'       => __( 'Required for the website to work correctly. They cannot be disabled from this panel.', 'asesor-cookies-para-la-ley-en-espana' ),
			'analytics'       => __( 'Help measure site usage and improve its content.', 'asesor-cookies-para-la-ley-en-espana' ),
			'marketing'       => __( 'Allow advertising, campaign measurement, or ad personalization.', 'asesor-cookies-para-la-ley-en-espana' ),
			'personalization' => __( 'Store preferences or enable non-essential external content.', 'asesor-cookies-para-la-ley-en-espana' ),
		);

		return isset( $descriptions[ $category ] ) ? $descriptions[ $category ] : '';
	}

	private static function get_front_categories() {
		$language = self::get_current_language();
		$labels = array(
			'es' => array(
				'necessary'       => 'Necesarias',
				'analytics'       => 'Analíticas',
				'marketing'       => 'Marketing',
				'personalization' => 'Personalización',
			),
			'en' => array(
				'necessary'       => 'Necessary',
				'analytics'       => 'Analytics',
				'marketing'       => 'Marketing',
				'personalization' => 'Personalization',
			),
			'fr' => array(
				'necessary'       => 'Nécessaires',
				'analytics'       => 'Statistiques',
				'marketing'       => 'Marketing',
				'personalization' => 'Personnalisation',
			),
			'de' => array(
				'necessary'       => 'Notwendig',
				'analytics'       => 'Analyse',
				'marketing'       => 'Marketing',
				'personalization' => 'Personalisierung',
			),
		);

		return $labels[ $language ] ?? $labels['en'];
	}

	private static function get_front_category_description( $category ) {
		$language = self::get_current_language();
		$descriptions = array(
			'es' => array(
				'necessary'       => 'Necesarias para que la web funcione correctamente. No se pueden desactivar desde este panel.',
				'analytics'       => 'Ayudan a medir el uso del sitio y mejorar sus contenidos.',
				'marketing'       => 'Permiten publicidad, medición de campañas o personalización de anuncios.',
				'personalization' => 'Guardan preferencias o permiten contenido externo no necesario.',
			),
			'en' => array(
				'necessary'       => 'Required for the website to work correctly. They cannot be disabled from this panel.',
				'analytics'       => 'Help measure site usage and improve its content.',
				'marketing'       => 'Allow advertising, campaign measurement, or ad personalization.',
				'personalization' => 'Store preferences or enable non-essential external content.',
			),
			'fr' => array(
				'necessary'       => 'Nécessaires au bon fonctionnement du site. Elles ne peuvent pas être désactivées depuis ce panneau.',
				'analytics'       => "Aident à mesurer l'utilisation du site et à améliorer son contenu.",
				'marketing'       => 'Permettent la publicité, la mesure des campagnes ou la personnalisation des annonces.',
				'personalization' => 'Enregistrent des préférences ou activent du contenu externe non essentiel.',
			),
			'de' => array(
				'necessary'       => 'Für die korrekte Funktion der Website erforderlich. Sie können in diesem Bereich nicht deaktiviert werden.',
				'analytics'       => 'Helfen, die Nutzung der Website zu messen und Inhalte zu verbessern.',
				'marketing'       => 'Ermöglichen Werbung, Kampagnenmessung oder Anzeigenpersonalisierung.',
				'personalization' => 'Speichern Präferenzen oder ermöglichen nicht notwendige externe Inhalte.',
			),
		);

		return $descriptions[ $language ][ $category ] ?? $descriptions['en'][ $category ] ?? '';
	}

	private static function get_script_area_label( $category ) {
		$labels = array(
			'analytics'       => __( 'Analytics scripts', 'asesor-cookies-para-la-ley-en-espana' ),
			'marketing'       => __( 'Marketing scripts', 'asesor-cookies-para-la-ley-en-espana' ),
			'personalization' => __( 'Other scripts', 'asesor-cookies-para-la-ley-en-espana' ),
		);

		return $labels[ $category ] ?? __( 'Scripts', 'asesor-cookies-para-la-ley-en-espana' );
	}

	public static function get_menu_location() {
		$location = get_option( self::OPTION_MENU_LOCATION, 'tools' );

		return 'top' === $location ? 'top' : 'tools';
	}

	private static function get_admin_page_url() {
		return add_query_arg(
			'page',
			'cdp_cookies',
			admin_url( 'tools' === self::get_menu_location() ? 'tools.php' : 'admin.php' )
		);
	}

	public static function get_cookie_items() {
		$items = get_option( self::OPTION_COOKIES, array() );
		$items = is_array( $items ) ? self::filter_valid_cookie_items( $items ) : array();

		return self::ensure_consent_cookie_item( $items );
	}

	public static function get_scripts() {
		$scripts = get_option( self::OPTION_SCRIPTS, array() );
		$defaults = array();
		foreach ( self::get_categories() as $key => $label ) {
			if ( 'necessary' !== $key ) {
				$defaults[ $key ] = '';
			}
		}

		return array_merge( $defaults, is_array( $scripts ) ? $scripts : array() );
	}

	private static function get_preferences_button_settings() {
		$settings = get_option( self::OPTION_PREFERENCES_BUTTON, array() );
		$settings = is_array( $settings ) ? $settings : array();
		$defaults = array(
			'type'             => 'text',
			'position'         => 'bottom',
			'bottom_desktop_x' => 95,
			'bottom_tablet_x'  => 90,
			'bottom_mobile_x'  => 85,
			'side_y'           => 60,
		);

		return self::sanitize_preferences_button_settings( array_merge( $defaults, $settings ) );
	}

	private static function sanitize_preferences_button_settings( $settings ) {
		$type = isset( $settings['type'] ) ? sanitize_key( $settings['type'] ) : 'text';
		$position = isset( $settings['position'] ) ? sanitize_key( $settings['position'] ) : 'bottom';

		return array(
			'type'             => in_array( $type, array( 'text', 'icon' ), true ) ? $type : 'text',
			'position'         => in_array( $position, array( 'bottom', 'right', 'left' ), true ) ? $position : 'bottom',
			'bottom_desktop_x' => self::sanitize_percent( $settings['bottom_desktop_x'] ?? 95, 0, 100 ),
			'bottom_tablet_x'  => self::sanitize_percent( $settings['bottom_tablet_x'] ?? 90, 0, 100 ),
			'bottom_mobile_x'  => self::sanitize_percent( $settings['bottom_mobile_x'] ?? 85, 0, 100 ),
			'side_y'           => self::sanitize_percent( $settings['side_y'] ?? 60, 50, 100 ),
		);
	}

	private static function sanitize_percent( $value, $min, $max ) {
		$value = is_numeric( $value ) ? (int) $value : $min;

		return max( $min, min( $max, $value ) );
	}

	private static function is_audit_enabled() {
		return '1' === get_option( self::OPTION_AUDIT_ENABLED, '0' );
	}

	private static function get_audit_items() {
		$items = get_option( self::OPTION_AUDIT_ITEMS, array() );

		return is_array( $items ) ? $items : array();
	}

	private static function get_audit_recording() {
		$recording = get_option( self::OPTION_AUDIT_RECORDING, array() );
		$recording = is_array( $recording ) ? $recording : array();

		return array_merge(
			array(
				'active'     => false,
				'started_at' => '',
				'stopped_at' => '',
			),
			$recording
		);
	}

	private static function is_audit_recording_active() {
		$recording = self::get_audit_recording();

		return ! empty( $recording['active'] );
	}

	private static function get_audit_external_resources() {
		$items = get_option( self::OPTION_AUDIT_EXTERNAL_RESOURCES, array() );

		return is_array( $items ) ? $items : array();
	}

	private static function get_ignored_audit_items() {
		$items = get_option( self::OPTION_AUDIT_IGNORED_ITEMS, array() );

		return is_array( $items ) ? $items : array();
	}

	private static function get_declared_cookie_names() {
		$names = array();

		foreach ( self::get_cookie_items() as $item ) {
			if ( ! empty( $item['name'] ) ) {
				$names[] = $item['name'];
			}
		}

		return $names;
	}

	private static function get_declared_service_patterns() {
		$patterns = array();

		foreach ( self::get_cookie_items() as $item ) {
			if ( empty( $item['service_pattern'] ) ) {
				continue;
			}

			$parts = preg_split( '/[\s,]+/', (string) $item['service_pattern'] );
			foreach ( $parts as $part ) {
				$part = trim( strtolower( $part ) );
				if ( '' !== $part ) {
					$patterns[] = $part;
				}
			}
		}

		return array_values( array_unique( $patterns ) );
	}

	private static function service_pattern_matches_resource( $resource, $patterns ) {
		$url = strtolower( (string) ( $resource['url'] ?? '' ) );
		$host = strtolower( (string) ( $resource['host'] ?? '' ) );
		$haystack = $host . ' ' . $url;

		foreach ( $patterns as $pattern ) {
			if ( '' !== $pattern && false !== strpos( $haystack, $pattern ) ) {
				return true;
			}
		}

		return false;
	}

	private static function get_embed_scan_results() {
		$results = get_option( self::OPTION_EMBED_SCAN_RESULTS, array() );

		return is_array( $results ) ? $results : array();
	}

	private static function get_embed_scan_date() {
		return (string) get_option( self::OPTION_EMBED_SCAN_DATE, '' );
	}

	public static function enlaces_pagina_plugins( $enlaces, $archivo ) {
		if ( ! self::$nombre_plugin ) {
			self::$nombre_plugin = plugin_basename( CDP_COOKIES_DIR_RAIZ . '/plugin.php' );
		}

		if ( $archivo !== self::$nombre_plugin ) {
			return $enlaces;
		}

		$enlace = array(
			sprintf(
				'<a href="%s">%s</a>',
				esc_url( self::get_admin_page_url() ),
				esc_html__( 'Settings', 'asesor-cookies-para-la-ley-en-espana' )
			),
		);

		return array_merge( $enlace, $enlaces );
	}

	public static function crear_menu_admin() {
		if ( 'tools' === self::get_menu_location() ) {
			add_submenu_page(
				'tools.php',
				__( 'Cookie Advisor', 'asesor-cookies-para-la-ley-en-espana' ),
				__( 'Cookie Advisor', 'asesor-cookies-para-la-ley-en-espana' ),
				'manage_options',
				'cdp_cookies',
				array( __CLASS__, 'pag_configuracion' )
			);
			return;
		}

		add_menu_page(
			__( 'Cookie Advisor', 'asesor-cookies-para-la-ley-en-espana' ),
			__( 'Cookie Advisor', 'asesor-cookies-para-la-ley-en-espana' ),
			'manage_options',
			'cdp_cookies',
			array( __CLASS__, 'pag_configuracion' ),
			self::get_menu_icon(),
			58
		);
	}

	private static function get_menu_icon() {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill="#a7aaad" d="M10 1.5a8.5 8.5 0 1 0 8.5 8.5 2.6 2.6 0 0 1-3.4-3.4 2.6 2.6 0 0 1-3.2-3.2A2.6 2.6 0 0 1 10 1.5Zm-3.1 5a1.2 1.2 0 1 1 0 2.4 1.2 1.2 0 0 1 0-2.4Zm3.4 5a1.1 1.1 0 1 1 0 2.2 1.1 1.1 0 0 1 0-2.2Zm3.1-2.8a1.1 1.1 0 1 1 0 2.2 1.1 1.1 0 0 1 0-2.2ZM7.3 13.8a1 1 0 1 1 0 2 1 1 0 0 1 0-2Z"/></svg>';

		return 'data:image/svg+xml;base64,' . base64_encode( $svg );
	}

	public static function cargar_archivos_admin( $hook_suffix ) {
		if ( ! in_array( $hook_suffix, array( 'tools_page_cdp_cookies', 'toplevel_page_cdp_cookies' ), true ) ) {
			return;
		}

		wp_enqueue_style( 'cdp-cookies-admin', CDP_COOKIES_URL_HTML . 'admin/estilos.css', array(), CDP_COOKIES_VERSION );
		wp_enqueue_script( 'cdp-cookies-admin', CDP_COOKIES_URL_HTML . 'admin/principal.js', array(), CDP_COOKIES_VERSION, true );
		wp_localize_script(
			'cdp-cookies-admin',
			'cdpCookiesAdmin',
			array(
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'deleteNonce'   => wp_create_nonce( 'cdp_cookies_delete_cookie' ),
				'deleteError'   => __( 'The cookie could not be deleted.', 'asesor-cookies-para-la-ley-en-espana' ),
				'deleteConfirm' => __( 'Delete this cookie?', 'asesor-cookies-para-la-ley-en-espana' ),
				'presets'       => self::get_preset_cookie_options(),
			)
		);
	}

	public static function register_blocks() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		wp_register_script(
			'cdp-cookies-consent-container-block',
			CDP_COOKIES_URL_HTML . 'blocks/consent-container.js',
			array( 'wp-blocks', 'wp-block-editor', 'wp-components', 'wp-element', 'wp-i18n' ),
			CDP_COOKIES_VERSION,
			true
		);
		wp_localize_script(
			'cdp-cookies-consent-container-block',
			'cdpCookiesConsentBlock',
			array(
				'title'                  => __( 'Consent-protected content', 'asesor-cookies-para-la-ley-en-espana' ),
				'description'            => __( 'Wrap videos, maps, iframes, and other external embeds that should load only after consent.', 'asesor-cookies-para-la-ley-en-espana' ),
				'externalContent'        => __( 'External content', 'asesor-cookies-para-la-ley-en-espana' ),
				'externalContentBlocked' => __( 'External content blocked', 'asesor-cookies-para-la-ley-en-espana' ),
				'consentSettings'        => __( 'Consent settings', 'asesor-cookies-para-la-ley-en-espana' ),
				'consentCategory'        => __( 'Consent category', 'asesor-cookies-para-la-ley-en-espana' ),
				'analytics'              => __( 'Analytics', 'asesor-cookies-para-la-ley-en-espana' ),
				'marketing'              => __( 'Marketing', 'asesor-cookies-para-la-ley-en-espana' ),
				'personalization'        => __( 'Personalization', 'asesor-cookies-para-la-ley-en-espana' ),
				'serviceName'            => __( 'Service name', 'asesor-cookies-para-la-ley-en-espana' ),
				'placeholderTitle'       => __( 'Placeholder title', 'asesor-cookies-para-la-ley-en-espana' ),
				'editorHelp'             => __( 'Place the video, map, iframe, or external embed inside this container.', 'asesor-cookies-para-la-ley-en-espana' ),
			)
		);

		wp_register_style(
			'cdp-cookies-consent-container-editor',
			CDP_COOKIES_URL_HTML . 'admin/estilos.css',
			array(),
			CDP_COOKIES_VERSION
		);

		register_block_type(
			'cdp-cookies/consent-container',
			array(
				'editor_script'   => 'cdp-cookies-consent-container-block',
				'editor_style'    => 'cdp-cookies-consent-container-editor',
				'render_callback' => array( __CLASS__, 'render_consent_block' ),
				'attributes'      => array(
					'category' => array(
						'type'    => 'string',
						'default' => 'personalization',
					),
					'service'  => array(
						'type'    => 'string',
						'default' => __( 'External content', 'asesor-cookies-para-la-ley-en-espana' ),
					),
					'title'    => array(
						'type'    => 'string',
						'default' => __( 'External content blocked', 'asesor-cookies-para-la-ley-en-espana' ),
					),
				),
			)
		);
	}

	public static function cargar_archivos_front() {
		if ( ! self::should_render_front_ui() ) {
			return;
		}

		wp_enqueue_style( 'cdp-cookies-front', CDP_COOKIES_URL_HTML . 'front/estilos.css', array(), CDP_COOKIES_VERSION );
		wp_enqueue_script( 'cdp-cookies-front', CDP_COOKIES_URL_HTML . 'front/principal.js', array(), CDP_COOKIES_VERSION, true );

		$scripts = self::get_scripts();
		unset( $scripts['necessary'] );

		wp_localize_script(
			'cdp-cookies-front',
			'cdpCookiesConfig',
			array(
				'cookieName' => self::CONSENT_COOKIE,
				'maxAge'     => YEAR_IN_SECONDS,
				'categories' => array_keys( self::get_categories() ),
				'scripts'    => $scripts,
			)
		);

		if ( self::is_audit_enabled() && self::is_audit_recording_active() && current_user_can( 'manage_options' ) ) {
			wp_localize_script(
				'cdp-cookies-front',
				'cdpCookiesAudit',
				array(
					'enabled'       => true,
					'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
					'nonce'         => wp_create_nonce( 'cdp_cookies_audit_detected' ),
					'declaredNames' => self::get_declared_cookie_names(),
					'consentCookie' => self::CONSENT_COOKIE,
					'siteHost'      => wp_parse_url( home_url(), PHP_URL_HOST ),
				)
			);
		}
	}

	public static function handle_admin_request() {
		if ( ! is_admin() || empty( $_POST['cdp_cookies_action'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'asesor-cookies-para-la-ley-en-espana' ) );
		}

		check_admin_referer( 'cdp_cookies_admin_action', 'cdp_cookies_nonce' );

		$action = sanitize_key( wp_unslash( $_POST['cdp_cookies_action'] ) );
		$redirect_args = array( 'cdp_cookies_updated' => '1' );
		$redirect_fragment = '';

		if ( 'save_settings' === $action ) {
			self::handle_save_settings();
		} elseif ( 'add_cookie' === $action ) {
			$cookie_status = self::handle_add_cookie();
			$redirect_args['cdp_cookies_cookie_status'] = $cookie_status;
			$redirect_fragment = '#cdp-cookies-inventory';
		} elseif ( 'update_cookie' === $action ) {
			self::handle_update_cookie();
		} elseif ( 'delete_cookie' === $action ) {
			self::handle_delete_cookie();
		} elseif ( 'add_preset_cookie' === $action ) {
			$preset_status = self::handle_add_preset_cookie();
			$redirect_args['cdp_cookies_preset_status'] = $preset_status;
			$redirect_fragment = '#cdp-cookies-inventory';
		} elseif ( 'create_policy_page' === $action ) {
			$policy_result = self::handle_create_policy_page();
			$redirect_args['cdp_cookies_policy_page'] = $policy_result['status'];
			$redirect_fragment = '#cdp-cookies-policy';
		} elseif ( 'clear_audit' === $action ) {
			self::handle_clear_audit();
		} elseif ( 'start_audit_recording' === $action ) {
			self::handle_start_audit_recording();
			$redirect_fragment = '#cdp-cookies-audit';
		} elseif ( 'stop_audit_recording' === $action ) {
			self::handle_stop_audit_recording();
			$redirect_fragment = '#cdp-cookies-audit';
		} elseif ( 'add_audit_cookie' === $action ) {
			self::handle_add_audit_cookie();
		} elseif ( 'ignore_audit_cookie' === $action ) {
			self::handle_ignore_audit_cookie();
		} elseif ( 'scan_embeds' === $action ) {
			self::handle_scan_embeds();
		}

		wp_safe_redirect( add_query_arg( $redirect_args, self::get_admin_page_url() ) . $redirect_fragment );
		exit;
	}

	private static function handle_save_settings() {
		if ( isset( $_POST['texto_aviso'] ) ) {
			self::parametro( 'texto_aviso', self::strip_policy_links_from_banner_text( wp_kses_post( wp_unslash( $_POST['texto_aviso'] ) ) ) );
		}

		if ( isset( $_POST['textos_aviso'] ) && is_array( $_POST['textos_aviso'] ) ) {
			$posted_texts = wp_unslash( $_POST['textos_aviso'] );
			$texts = array();

			foreach ( self::get_supported_banner_languages() as $language => $label ) {
				$texts[ $language ] = isset( $posted_texts[ $language ] ) ? self::strip_policy_links_from_banner_text( wp_kses_post( $posted_texts[ $language ] ) ) : self::get_default_banner_text( $language );
			}

			update_option( self::OPTION_BANNER_TEXTS, $texts, false );
			self::parametro( 'texto_aviso', $texts[ self::get_current_language() ] );
		}

		if ( isset( $_POST['enlace_politica'] ) ) {
			self::parametro( 'enlace_politica', esc_url_raw( wp_unslash( $_POST['enlace_politica'] ) ) );
		}

		if ( isset( $_POST['preferences_button'] ) && is_array( $_POST['preferences_button'] ) ) {
			update_option(
				self::OPTION_PREFERENCES_BUTTON,
				self::sanitize_preferences_button_settings( wp_unslash( $_POST['preferences_button'] ) ),
				false
			);
		}

		if ( isset( $_POST['menu_location'] ) ) {
			$menu_location = sanitize_key( wp_unslash( $_POST['menu_location'] ) );
			update_option( self::OPTION_MENU_LOCATION, in_array( $menu_location, array( 'top', 'tools' ), true ) ? $menu_location : 'tools', false );
		}

		if ( isset( $_POST['audit_enabled'] ) ) {
			update_option( self::OPTION_AUDIT_ENABLED, '1' === sanitize_text_field( wp_unslash( $_POST['audit_enabled'] ) ) ? '1' : '0', false );
		}

		if ( ! isset( $_POST['scripts'] ) || ! is_array( $_POST['scripts'] ) ) {
			return;
		}

		$posted_scripts = wp_unslash( $_POST['scripts'] );
		$scripts = array();
		foreach ( self::get_scripts() as $category => $value ) {
			$script = isset( $posted_scripts[ $category ] ) ? $posted_scripts[ $category ] : '';
			$scripts[ $category ] = current_user_can( 'unfiltered_html' ) ? $script : wp_kses_post( $script );
		}

		update_option( self::OPTION_SCRIPTS, $scripts );
	}

	private static function handle_add_cookie() {
		$items = self::get_cookie_items();
		$name = sanitize_text_field( wp_unslash( $_POST['cookie_name'] ?? '' ) );
		$provider = sanitize_text_field( wp_unslash( $_POST['cookie_provider'] ?? '' ) );
		$service_pattern = self::sanitize_service_pattern( wp_unslash( $_POST['cookie_service_pattern'] ?? '' ) );

		if ( '' === $name ) {
			return 'error';
		}

		foreach ( $items as $item ) {
			if (
				isset( $item['name'], $item['provider'] )
				&& $item['name'] === $name
				&& $item['provider'] === $provider
			) {
				return 'duplicate';
			}
		}

		$items[] = array(
			'id'          => uniqid( 'cookie_', true ),
			'name'        => $name,
			'provider'    => $provider,
			'service_pattern' => $service_pattern,
			'category'    => self::sanitize_category( wp_unslash( $_POST['cookie_category'] ?? 'necessary' ) ),
			'type'        => self::sanitize_cookie_type( wp_unslash( $_POST['cookie_type'] ?? 'own' ) ),
			'duration'    => sanitize_text_field( wp_unslash( $_POST['cookie_duration'] ?? '' ) ),
			'description' => sanitize_textarea_field( wp_unslash( $_POST['cookie_description'] ?? '' ) ),
		);

		update_option( self::OPTION_COOKIES, self::filter_valid_cookie_items( $items ) );

		return 'added';
	}

	private static function handle_update_cookie() {
		$id = sanitize_text_field( wp_unslash( $_POST['cookie_id'] ?? '' ) );

		if ( '' === $id ) {
			return;
		}

		$items = array_map(
			function ( $item ) use ( $id ) {
				if ( ! isset( $item['id'] ) || $item['id'] !== $id ) {
					return $item;
				}

				return array(
					'id'          => $id,
					'name'        => sanitize_text_field( wp_unslash( $_POST['cookie_name'] ?? '' ) ),
					'provider'    => sanitize_text_field( wp_unslash( $_POST['cookie_provider'] ?? '' ) ),
					'service_pattern' => self::sanitize_service_pattern( wp_unslash( $_POST['cookie_service_pattern'] ?? '' ) ),
					'category'    => self::sanitize_category( wp_unslash( $_POST['cookie_category'] ?? 'necessary' ) ),
					'type'        => self::sanitize_cookie_type( wp_unslash( $_POST['cookie_type'] ?? 'own' ) ),
					'duration'    => sanitize_text_field( wp_unslash( $_POST['cookie_duration'] ?? '' ) ),
					'description' => sanitize_textarea_field( wp_unslash( $_POST['cookie_description'] ?? '' ) ),
				);
			},
			self::get_cookie_items()
		);

		update_option( self::OPTION_COOKIES, self::filter_valid_cookie_items( $items ) );
	}

	private static function handle_delete_cookie() {
		$id = sanitize_text_field( wp_unslash( $_POST['cookie_id'] ?? '' ) );
		$items = array_values(
			array_filter(
				self::get_cookie_items(),
				function ( $item ) use ( $id ) {
					return ! isset( $item['id'] ) || $item['id'] !== $id;
				}
			)
		);

		update_option( self::OPTION_COOKIES, $items );
	}

	private static function handle_clear_audit() {
		update_option( self::OPTION_AUDIT_ITEMS, array(), false );
		update_option( self::OPTION_AUDIT_EXTERNAL_RESOURCES, array(), false );
	}

	private static function handle_start_audit_recording() {
		update_option( self::OPTION_AUDIT_ENABLED, '1', false );
		update_option( self::OPTION_AUDIT_ITEMS, array(), false );
		update_option( self::OPTION_AUDIT_EXTERNAL_RESOURCES, array(), false );
		update_option(
			self::OPTION_AUDIT_RECORDING,
			array(
				'active'     => true,
				'started_at' => current_time( 'mysql' ),
				'stopped_at' => '',
			),
			false
		);
	}

	private static function should_render_front_ui() {
		if ( is_admin() || wp_doing_ajax() || ( function_exists( 'wp_is_json_request' ) && wp_is_json_request() ) ) {
			return false;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return false;
		}

		return true;
	}

	private static function handle_stop_audit_recording() {
		$recording = self::get_audit_recording();
		$recording['active'] = false;
		$recording['stopped_at'] = current_time( 'mysql' );

		update_option( self::OPTION_AUDIT_RECORDING, $recording, false );
	}

	private static function handle_add_audit_cookie() {
		$name = sanitize_text_field( wp_unslash( $_POST['audit_cookie_name'] ?? '' ) );
		$provider = sanitize_text_field( wp_unslash( $_POST['audit_cookie_provider'] ?? '' ) );
		$service_pattern = self::sanitize_service_pattern( wp_unslash( $_POST['audit_cookie_service_pattern'] ?? '' ) );
		$category = self::sanitize_category( wp_unslash( $_POST['audit_cookie_category'] ?? 'necessary' ) );
		$type = self::sanitize_cookie_type( wp_unslash( $_POST['audit_cookie_type'] ?? 'own' ) );
		$duration = sanitize_text_field( wp_unslash( $_POST['audit_cookie_duration'] ?? '' ) );
		$description = sanitize_textarea_field( wp_unslash( $_POST['audit_cookie_description'] ?? '' ) );

		if ( '' === $name ) {
			return;
		}

		$items = self::get_cookie_items();
		foreach ( $items as $item ) {
			if ( isset( $item['name'] ) && $item['name'] === $name ) {
				return;
			}
		}

		$items[] = array(
			'id'          => uniqid( 'cookie_', true ),
			'name'        => $name,
			'provider'    => $provider,
			'service_pattern' => $service_pattern,
			'category'    => $category,
			'type'        => $type,
			'duration'    => $duration,
			'description' => $description,
		);

		update_option( self::OPTION_COOKIES, self::filter_valid_cookie_items( $items ) );
	}

	private static function handle_ignore_audit_cookie() {
		$name = sanitize_text_field( wp_unslash( $_POST['audit_cookie_name'] ?? '' ) );

		if ( '' === $name ) {
			return;
		}

		$audit_items = self::get_audit_items();
		$ignored_items = self::get_ignored_audit_items();
		$item = $audit_items[ $name ] ?? array( 'name' => $name );
		$item['name'] = $name;
		$item['ignored_at'] = current_time( 'mysql' );

		$ignored_items[ $name ] = $item;
		unset( $audit_items[ $name ] );

		uasort(
			$ignored_items,
			function ( $a, $b ) {
				return strcmp( $b['ignored_at'] ?? '', $a['ignored_at'] ?? '' );
			}
		);

		update_option( self::OPTION_AUDIT_ITEMS, $audit_items, false );
		update_option( self::OPTION_AUDIT_IGNORED_ITEMS, array_slice( $ignored_items, 0, 300, true ), false );
	}

	private static function handle_scan_embeds() {
		$results = self::scan_embeds();

		update_option( self::OPTION_EMBED_SCAN_RESULTS, $results, false );
		update_option( self::OPTION_EMBED_SCAN_DATE, current_time( 'mysql' ), false );
	}

	private static function scan_embeds() {
		$results = array();
		$post_types = get_post_types( array( 'public' => true ), 'names' );
		unset( $post_types['attachment'] );

		$query = new WP_Query(
			array(
				'post_type'              => array_values( $post_types ),
				'post_status'            => 'publish',
				'posts_per_page'         => 500,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		foreach ( $query->posts as $post_id ) {
			$post = get_post( $post_id );

			if ( ! $post instanceof WP_Post || '' === trim( $post->post_content ) ) {
				continue;
			}

			$results = array_merge( $results, self::scan_content_for_embeds( $post ) );

			if ( count( $results ) >= 500 ) {
				break;
			}
		}

		return array_slice( $results, 0, 500 );
	}

	private static function scan_content_for_embeds( $post ) {
		$content = (string) $post->post_content;
		$protected_ranges = self::get_protected_embed_ranges( $content );
		$embed_tag_ranges = array();
		$signals = array();

		if ( preg_match_all( '/<(iframe|embed|object|script)\b[^>]*(?:>|$)/i', $content, $matches, PREG_OFFSET_CAPTURE ) ) {
			foreach ( $matches[0] as $index => $match ) {
				$embed_tag_ranges[] = array(
					'start' => $match[1],
					'end'   => $match[1] + strlen( $match[0] ),
				);
				$signals[] = self::build_embed_scan_signal(
					$post,
					$content,
					$protected_ranges,
					$match[1],
					self::guess_embed_service( $match[0] ),
					__( 'HTML embed tag', 'asesor-cookies-para-la-ley-en-espana' ),
					false,
					$match[0]
				);
			}
		}

		$known_domain_pattern = self::get_embed_domain_regex();
		if ( preg_match_all( $known_domain_pattern, $content, $matches, PREG_OFFSET_CAPTURE ) ) {
			foreach ( $matches[0] as $match ) {
				if ( self::is_position_in_ranges( $match[1], $embed_tag_ranges ) ) {
					continue;
				}

				$signals[] = self::build_embed_scan_signal(
					$post,
					$content,
					$protected_ranges,
					$match[1],
					self::guess_embed_service( $match[0] ),
					__( 'Known external service URL', 'asesor-cookies-para-la-ley-en-espana' ),
					false,
					$match[0]
				);
			}
		}

		if ( preg_match_all( '/\[[a-zA-Z0-9_-]*(youtube|vimeo|map|maps|iframe|embed|video|calendar|booking)[a-zA-Z0-9_-]*(?:\s[^\]]*)?\]/i', $content, $matches, PREG_OFFSET_CAPTURE ) ) {
			foreach ( $matches[0] as $match ) {
				$signals[] = self::build_embed_scan_signal(
					$post,
					$content,
					$protected_ranges,
					$match[1],
					self::guess_embed_service( $match[0] ),
					__( 'Suspicious shortcode', 'asesor-cookies-para-la-ley-en-espana' ),
					true,
					$match[0]
				);
			}
		}

		return self::deduplicate_embed_scan_signals( $signals );
	}

	private static function get_protected_embed_ranges( $content ) {
		$ranges = array();

		if ( preg_match_all( '/\[cdp_consent\b[^\]]*\].*?\[\/cdp_consent\]/is', $content, $matches, PREG_OFFSET_CAPTURE ) ) {
			foreach ( $matches[0] as $match ) {
				$ranges[] = array(
					'start' => $match[1],
					'end'   => $match[1] + strlen( $match[0] ),
				);
			}
		}

		if ( preg_match_all( '/<!--\s+wp:cdp-cookies\/consent-container\b.*?<!--\s+\/wp:cdp-cookies\/consent-container\s+-->/is', $content, $matches, PREG_OFFSET_CAPTURE ) ) {
			foreach ( $matches[0] as $match ) {
				$ranges[] = array(
					'start' => $match[1],
					'end'   => $match[1] + strlen( $match[0] ),
				);
			}
		}

		return $ranges;
	}

	private static function build_embed_scan_signal( $post, $content, $protected_ranges, $position, $service, $type, $heuristic = false, $raw_match = '' ) {
		$protected = self::is_position_in_ranges( $position, $protected_ranges );
		$status = $protected ? 'protected' : ( $heuristic ? 'review' : 'unprotected' );
		$raw_match = (string) $raw_match;
		$snippet = self::get_embed_scan_snippet( $raw_match, $content, $position );
		$fingerprint = self::get_embed_scan_fingerprint( $raw_match, $service, $post->ID, $position );

		return array(
			'post_id'    => absint( $post->ID ),
			'post_title' => get_the_title( $post ),
			'post_type'  => get_post_type_object( $post->post_type ) ? get_post_type_object( $post->post_type )->labels->singular_name : $post->post_type,
			'edit_url'   => get_edit_post_link( $post->ID, '' ),
			'view_url'   => get_permalink( $post ),
			'service'    => $service ? $service : __( 'Unknown service', 'asesor-cookies-para-la-ley-en-espana' ),
			'type'       => $type,
			'status'     => $status,
			'snippet'    => $snippet,
			'fingerprint' => $fingerprint,
		);
	}

	private static function get_embed_scan_snippet( $raw_match, $content, $position ) {
		$raw_match = trim( (string) $raw_match );

		if ( preg_match( '/^<([a-z0-9]+)\b/i', $raw_match, $tag_match ) ) {
			if ( preg_match( '/\s(?:src|href)=["\']([^"\']+)["\']/i', $raw_match, $url_match ) ) {
				return self::shorten_embed_scan_text( strtolower( $tag_match[1] ) . ': ' . html_entity_decode( $url_match[1], ENT_QUOTES, get_bloginfo( 'charset' ) ) );
			}

			return self::shorten_embed_scan_text( strtolower( $tag_match[1] ) );
		}

		$raw_match = trim( html_entity_decode( wp_strip_all_tags( $raw_match ), ENT_QUOTES, get_bloginfo( 'charset' ) ) );

		if ( '' !== $raw_match ) {
			return self::shorten_embed_scan_text( $raw_match );
		}

		$snippet = trim( html_entity_decode( wp_strip_all_tags( substr( $content, max( 0, $position - 90 ), 220 ) ), ENT_QUOTES, get_bloginfo( 'charset' ) ) );
		return self::shorten_embed_scan_text( $snippet );
	}

	private static function shorten_embed_scan_text( $text ) {
		$text = preg_replace( '/\s+/', ' ', trim( (string) $text ) );
		$text = str_replace(
			array( '\\u0026', '\\u003c', '\\u003e', '\\/' ),
			array( '&', '<', '>', '/' ),
			$text
		);
		$text = trim( $text, " \t\n\r\0\x0B<>" );

		if ( function_exists( 'mb_strlen' ) && mb_strlen( $text ) > 160 ) {
			return mb_substr( $text, 0, 157 ) . '...';
		}

		return strlen( $text ) > 160 ? substr( $text, 0, 157 ) . '...' : $text;
	}

	private static function get_embed_scan_fingerprint( $raw_match, $service, $post_id, $position ) {
		if ( preg_match( self::get_embed_domain_regex(), (string) $raw_match, $url_match ) ) {
			return strtolower( untrailingslashit( strtok( html_entity_decode( $url_match[0], ENT_QUOTES, get_bloginfo( 'charset' ) ), '?' ) ) );
		}

		return strtolower( absint( $post_id ) . '|' . sanitize_key( $service ) . '|' . floor( absint( $position ) / 500 ) );
	}

	private static function is_position_in_ranges( $position, $ranges ) {
		foreach ( $ranges as $range ) {
			if ( $position >= $range['start'] && $position <= $range['end'] ) {
				return true;
			}
		}

		return false;
	}

	private static function get_embed_domain_regex() {
		return '~https?://[^\s"\'<\]]*(youtube\.com|youtu\.be|vimeo\.com|googlevideo\.com|vimeo\.com|player\.vimeo\.com|google\.com/maps|maps\.google\.com|maps\.googleapis\.com|calendly\.com|spotify\.com|soundcloud\.com|instagram\.com|facebook\.com|connect\.facebook\.net|twitter\.com|x\.com|pinterest\.com|pinimg\.com|tiktok\.com|tiktokcdn\.com|linkedin\.com|licdn\.com|google\.com/recaptcha|gstatic\.com/recaptcha|gravatar\.com)[^\s"\'<\]]*~i';
	}

	private static function guess_embed_service( $text ) {
		$text = strtolower( $text );
		$services = array(
			'YouTube'     => array( 'youtube', 'youtu.be' ),
			'Vimeo'       => array( 'vimeo' ),
			'Google Maps' => array( 'google.com/maps', 'maps.google', 'map', 'maps' ),
			'Calendly'    => array( 'calendly' ),
			'Spotify'     => array( 'spotify' ),
			'SoundCloud'  => array( 'soundcloud' ),
			'Instagram'   => array( 'instagram' ),
			'Facebook'    => array( 'facebook' ),
			'X/Twitter'   => array( 'twitter', 'x.com' ),
			'Pinterest'   => array( 'pinterest', 'pinimg' ),
			'TikTok'      => array( 'tiktok', 'tiktokcdn' ),
			'LinkedIn'    => array( 'linkedin', 'licdn' ),
			'reCAPTCHA'   => array( 'google.com/recaptcha', 'gstatic.com/recaptcha', 'recaptcha' ),
			'Gravatar'    => array( 'gravatar.com' ),
			'Embed'       => array( 'iframe', 'embed', 'video', 'calendar', 'booking' ),
		);

		foreach ( $services as $service => $needles ) {
			foreach ( $needles as $needle ) {
				if ( false !== strpos( $text, $needle ) ) {
					return $service;
				}
			}
		}

		return '';
	}

	private static function deduplicate_embed_scan_signals( $signals ) {
		$unique = array();

		foreach ( $signals as $signal ) {
			$key = md5( implode( '|', array( $signal['post_id'], $signal['service'], $signal['status'], $signal['fingerprint'] ) ) );

			if ( ! isset( $unique[ $key ] ) || self::get_embed_signal_priority( $signal ) > self::get_embed_signal_priority( $unique[ $key ] ) ) {
				$unique[ $key ] = $signal;
			}
		}

		return array_map(
			function ( $signal ) {
				unset( $signal['fingerprint'] );
				return $signal;
			},
			array_values( $unique )
		);
	}

	private static function get_embed_signal_priority( $signal ) {
		$type = isset( $signal['type'] ) ? (string) $signal['type'] : '';

		if ( __( 'HTML embed tag', 'asesor-cookies-para-la-ley-en-espana' ) === $type ) {
			return 3;
		}

		if ( __( 'Known external service URL', 'asesor-cookies-para-la-ley-en-espana' ) === $type ) {
			return 2;
		}

		return 1;
	}

	public static function ajax_delete_cookie() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'asesor-cookies-para-la-ley-en-espana' ) ), 403 );
		}

		check_ajax_referer( 'cdp_cookies_delete_cookie', 'nonce' );

		$id = sanitize_text_field( wp_unslash( $_POST['cookie_id'] ?? '' ) );

		if ( '' === $id ) {
			wp_send_json_error( array( 'message' => __( 'The cookie could not be deleted.', 'asesor-cookies-para-la-ley-en-espana' ) ), 400 );
		}

		$before = count( self::get_cookie_items() );
		$_POST['cookie_id'] = $id;
		self::handle_delete_cookie();
		$after = count( self::get_cookie_items() );

		if ( $before === $after ) {
			wp_send_json_error( array( 'message' => __( 'The cookie could not be deleted.', 'asesor-cookies-para-la-ley-en-espana' ) ), 404 );
		}

		wp_send_json_success( array( 'message' => __( 'Cookie deleted.', 'asesor-cookies-para-la-ley-en-espana' ) ) );
	}

	public static function ajax_audit_detected() {
		if ( ! current_user_can( 'manage_options' ) || ! self::is_audit_enabled() || ! self::is_audit_recording_active() ) {
			wp_send_json_error( array( 'message' => __( 'Cookie audit is not available.', 'asesor-cookies-para-la-ley-en-espana' ) ), 403 );
		}

		check_ajax_referer( 'cdp_cookies_audit_detected', 'nonce' );

		$cookie_names = isset( $_POST['cookie_names'] ) && is_array( $_POST['cookie_names'] ) ? wp_unslash( $_POST['cookie_names'] ) : array();
		$resources = isset( $_POST['external_resources'] ) && is_array( $_POST['external_resources'] ) ? wp_unslash( $_POST['external_resources'] ) : array();
		$url = esc_url_raw( wp_unslash( $_POST['url'] ?? '' ) );

		if ( empty( $cookie_names ) && empty( $resources ) ) {
			wp_send_json_success( array( 'message' => __( 'No cookies detected.', 'asesor-cookies-para-la-ley-en-espana' ) ) );
		}

		$declared = self::get_declared_cookie_names();
		$ignored = self::get_ignored_audit_items();
		$audit_items = self::get_audit_items();
		$external_resources = self::get_audit_external_resources();
		$now = current_time( 'mysql' );

		foreach ( array_slice( $cookie_names, 0, 100 ) as $name ) {
			$name = sanitize_text_field( $name );

			if ( '' === $name || in_array( $name, $declared, true ) || isset( $ignored[ $name ] ) || ! self::is_recordable_audit_cookie_name( $name ) ) {
				continue;
			}

			if ( ! isset( $audit_items[ $name ] ) ) {
				$audit_items[ $name ] = array(
					'name'       => $name,
					'first_seen' => $now,
					'last_seen'  => $now,
					'count'      => 0,
					'urls'       => array(),
				);
			}

			$audit_items[ $name ]['last_seen'] = $now;
			$audit_items[ $name ]['count'] = isset( $audit_items[ $name ]['count'] ) ? absint( $audit_items[ $name ]['count'] ) + 1 : 1;

			if ( $url && empty( $audit_items[ $name ]['urls'][ $url ] ) ) {
				$audit_items[ $name ]['urls'][ $url ] = $now;
				$audit_items[ $name ]['urls'] = array_slice( $audit_items[ $name ]['urls'], -10, null, true );
			}
		}

		uasort(
			$audit_items,
			function ( $a, $b ) {
				return strcmp( $b['last_seen'] ?? '', $a['last_seen'] ?? '' );
			}
		);

		foreach ( array_slice( $resources, 0, 150 ) as $resource ) {
			if ( ! is_array( $resource ) ) {
				continue;
			}

			$resource_url = esc_url_raw( $resource['url'] ?? '' );
			if ( '' === $resource_url || ! self::is_external_audit_resource_url( $resource_url ) ) {
				continue;
			}

			$host = strtolower( (string) wp_parse_url( $resource_url, PHP_URL_HOST ) );
			$type = sanitize_key( $resource['type'] ?? 'resource' );
			$service = self::guess_embed_service( $resource_url );
			$key = md5( $host . '|' . strtok( $resource_url, '?' ) );

			if ( ! isset( $external_resources[ $key ] ) ) {
				$external_resources[ $key ] = array(
					'url'        => $resource_url,
					'host'       => $host,
					'service'    => $service ? $service : __( 'Unknown service', 'asesor-cookies-para-la-ley-en-espana' ),
					'type'       => $type ? $type : 'resource',
					'first_seen' => $now,
					'last_seen'  => $now,
					'count'      => 0,
					'urls'       => array(),
				);
			}

			$external_resources[ $key ]['last_seen'] = $now;
			$external_resources[ $key ]['count'] = isset( $external_resources[ $key ]['count'] ) ? absint( $external_resources[ $key ]['count'] ) + 1 : 1;

			if ( $url && empty( $external_resources[ $key ]['urls'][ $url ] ) ) {
				$external_resources[ $key ]['urls'][ $url ] = $now;
				$external_resources[ $key ]['urls'] = array_slice( $external_resources[ $key ]['urls'], -10, null, true );
			}
		}

		uasort(
			$external_resources,
			function ( $a, $b ) {
				return strcmp( $b['last_seen'] ?? '', $a['last_seen'] ?? '' );
			}
		);

		update_option( self::OPTION_AUDIT_ITEMS, array_slice( $audit_items, 0, 300, true ), false );
		update_option( self::OPTION_AUDIT_EXTERNAL_RESOURCES, array_slice( $external_resources, 0, 500, true ), false );

		wp_send_json_success( array( 'message' => __( 'Cookies detected.', 'asesor-cookies-para-la-ley-en-espana' ) ) );
	}

	private static function is_external_audit_resource_url( $url ) {
		$host = wp_parse_url( $url, PHP_URL_HOST );

		if ( ! $host ) {
			return false;
		}

		$host = strtolower( $host );
		$home_host = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		$site_host = strtolower( (string) wp_parse_url( site_url(), PHP_URL_HOST ) );

		return $host !== $home_host && $host !== $site_host;
	}

	private static function is_internal_cookie_name( $name ) {
		$internal_prefixes = array(
			'wordpress_',
			'wp-',
			'wp_',
			'comment_author_',
			'woocommerce_',
			'wp-settings-',
			'wp_lang',
		);
		$internal_names = array( self::CONSENT_COOKIE, 'cdp-cookies-plugin-wp', 'wordpress_test_cookie' );

		if ( in_array( $name, $internal_names, true ) ) {
			return true;
		}

		foreach ( $internal_prefixes as $prefix ) {
			if ( 0 === strpos( $name, $prefix ) ) {
				return true;
			}
		}

		return false;
	}

	private static function is_auditable_cookie_name( $name ) {
		$name = trim( (string) $name );

		if ( ! self::is_recordable_audit_cookie_name( $name ) ) {
			return false;
		}

		$ignored_names = array(
			'CookieConsent',
			'CookieScriptConsent',
			'CookieLawInfoConsent',
			'borlabs-cookie',
			'cli_user_preference',
			'cookielawinfo-checkbox-advertisement',
			'cookielawinfo-checkbox-analytics',
			'cookielawinfo-checkbox-functional',
			'cookielawinfo-checkbox-necessary',
			'hasConsent',
			'hasConsents',
			'moove_gdpr_popup',
			'viewed_cookie_policy',
		);

		if ( in_array( $name, $ignored_names, true ) ) {
			return false;
		}

		$ignored_prefixes = array(
			'cmplz_',
			'wpEmojiSettingsSupports',
		);

		foreach ( $ignored_prefixes as $prefix ) {
			if ( 0 === strpos( $name, $prefix ) ) {
				return false;
			}
		}

		if ( 0 === strpos( $name, 'sbjs_' ) && ! self::is_woocommerce_active() ) {
			return false;
		}

		return true;
	}

	private static function is_recordable_audit_cookie_name( $name ) {
		$name = trim( (string) $name );

		if ( '' === $name || strlen( $name ) > 128 || self::is_internal_cookie_name( $name ) ) {
			return false;
		}

		return (bool) preg_match( '/^[A-Za-z0-9_][A-Za-z0-9_.:-]*$/', $name );
	}

	private static function get_audit_cookie_notice( $name ) {
		if ( self::is_auditable_cookie_name( $name ) ) {
			return '';
		}

		$known_consent_cookies = array(
			'CookieConsent',
			'CookieScriptConsent',
			'CookieLawInfoConsent',
			'borlabs-cookie',
			'cli_user_preference',
			'cookielawinfo-checkbox-advertisement',
			'cookielawinfo-checkbox-analytics',
			'cookielawinfo-checkbox-functional',
			'cookielawinfo-checkbox-necessary',
			'hasConsent',
			'hasConsents',
			'moove_gdpr_popup',
			'viewed_cookie_policy',
		);

		if ( in_array( $name, $known_consent_cookies, true ) ) {
			return __( 'Possible cookie from another consent plugin or previous tests.', 'asesor-cookies-para-la-ley-en-espana' );
		}

		if ( 0 === strpos( $name, 'sbjs_' ) && ! self::is_woocommerce_active() ) {
			return __( 'Possible Sourcebuster/WooCommerce cookie from another local environment.', 'asesor-cookies-para-la-ley-en-espana' );
		}

		if ( 0 === strpos( $name, 'cmplz_' ) || 0 === strpos( $name, 'wpEmojiSettingsSupports' ) ) {
			return __( 'Possible technical cookie from WordPress, another plugin, or previous tests.', 'asesor-cookies-para-la-ley-en-espana' );
		}

		return __( 'Review manually before adding it to the inventory.', 'asesor-cookies-para-la-ley-en-espana' );
	}

	private static function is_woocommerce_active() {
		if ( class_exists( 'WooCommerce' ) ) {
			return true;
		}

		$active_plugins = (array) get_option( 'active_plugins', array() );
		if ( in_array( 'woocommerce/woocommerce.php', $active_plugins, true ) ) {
			return true;
		}

		if ( is_multisite() ) {
			$network_plugins = (array) get_site_option( 'active_sitewide_plugins', array() );
			return isset( $network_plugins['woocommerce/woocommerce.php'] );
		}

		return false;
	}

	private static function handle_add_preset_cookie() {
		$preset_key = sanitize_key( wp_unslash( $_POST['preset_cookie'] ?? '' ) );
		$preset_cookies = self::get_preset_cookie_options();

		if ( ! isset( $preset_cookies[ $preset_key ] ) ) {
			return 'error';
		}

		$items = self::get_cookie_items();
		$cookie = $preset_cookies[ $preset_key ];

		foreach ( $items as $item ) {
			if (
				isset( $item['name'], $item['provider'] )
				&& $item['name'] === $cookie['name']
				&& $item['provider'] === $cookie['provider']
			) {
				return 'duplicate';
			}
		}

		unset( $cookie['label'] );
		$cookie['id'] = uniqid( 'cookie_', true );
		$items[] = $cookie;
		update_option( self::OPTION_COOKIES, self::filter_valid_cookie_items( $items ) );

		return 'added';
	}

	private static function handle_create_policy_page() {
		$page = new cdp_cookies_pagina();
		$page->titulo = __( 'Cookie policy', 'asesor-cookies-para-la-ley-en-espana' );
		$page->html = self::get_policy_page_content();

		if ( $page->crear() ) {
			self::parametro( 'enlace_politica', $page->url );
			return array(
				'status' => $page->ya_existia ? 'existing' : 'created',
				'url'    => $page->url,
			);
		}

		return array(
			'status'  => 'error',
			'message' => $page->mensaje,
		);
	}

	private static function get_policy_url_status( $url ) {
		$url = trim( (string) $url );

		if ( '' === $url ) {
			return 'empty';
		}

		$parts = wp_parse_url( $url );
		if ( empty( $parts['scheme'] ) || empty( $parts['host'] ) || ! in_array( strtolower( $parts['scheme'] ), array( 'http', 'https' ), true ) ) {
			return 'invalid';
		}

		if ( self::is_local_url( $url ) ) {
			$post_id = url_to_postid( $url );

			if ( $post_id ) {
				$post = get_post( $post_id );
				return ( $post && 'trash' !== $post->post_status && 'auto-draft' !== $post->post_status ) ? 'exists' : 'missing';
			}

			return 'missing';
		}

		$response = wp_remote_head(
			$url,
			array(
				'timeout'     => 3,
				'redirection' => 3,
			)
		);

		if ( is_wp_error( $response ) || 405 === wp_remote_retrieve_response_code( $response ) ) {
			$response = wp_remote_get(
				$url,
				array(
					'timeout'     => 3,
					'redirection' => 3,
				)
			);
		}

		if ( is_wp_error( $response ) ) {
			return 'unknown';
		}

		$code = wp_remote_retrieve_response_code( $response );
		return ( $code >= 200 && $code < 400 ) ? 'exists' : 'missing';
	}

	private static function is_local_url( $url ) {
		$url_host = wp_parse_url( $url, PHP_URL_HOST );
		$home_host = wp_parse_url( home_url(), PHP_URL_HOST );
		$site_host = wp_parse_url( site_url(), PHP_URL_HOST );

		return $url_host && ( strtolower( $url_host ) === strtolower( (string) $home_host ) || strtolower( $url_host ) === strtolower( (string) $site_host ) );
	}

	private static function filter_valid_cookie_items( $items ) {
		$clean = array();
		$seen = array();

		foreach ( $items as $item ) {
			$name = isset( $item['name'] ) ? trim( $item['name'] ) : '';
			if ( '' === $name ) {
				continue;
			}

			$provider = sanitize_text_field( $item['provider'] ?? '' );
			$item = self::apply_internal_cookie_defaults( $item, $name, $provider );
			$provider = sanitize_text_field( $item['provider'] ?? '' );
			$dedupe_key = strtolower( sanitize_text_field( $name ) . '|' . $provider );
			if ( isset( $seen[ $dedupe_key ] ) ) {
				continue;
			}
			$seen[ $dedupe_key ] = true;

			$clean[] = array(
				'id'          => isset( $item['id'] ) ? sanitize_text_field( $item['id'] ) : uniqid( 'cookie_', true ),
				'name'        => sanitize_text_field( $name ),
				'provider'    => $provider,
				'service_pattern' => self::sanitize_service_pattern( $item['service_pattern'] ?? '' ),
				'category'    => self::sanitize_category( $item['category'] ?? 'necessary' ),
				'type'        => self::sanitize_cookie_type( $item['type'] ?? 'own' ),
				'duration'    => sanitize_text_field( $item['duration'] ?? '' ),
				'description' => sanitize_textarea_field( $item['description'] ?? '' ),
			);
		}

		return $clean;
	}

	private static function ensure_consent_cookie_item( $items ) {
		foreach ( $items as $item ) {
			if ( isset( $item['name'] ) && self::CONSENT_COOKIE === $item['name'] ) {
				return $items;
			}
		}

		$items[] = self::get_internal_cookie_item( self::CONSENT_COOKIE );

		return $items;
	}

	private static function get_internal_cookie_item( $name ) {
		return array_merge(
			array(
				'id'   => 'internal_' . sanitize_key( $name ),
				'name' => $name,
			),
			self::get_internal_cookie_defaults( $name )
		);
	}

	private static function apply_internal_cookie_defaults( $item, $name, $provider ) {
		$defaults = self::get_internal_cookie_defaults( $name );

		if ( empty( $defaults ) ) {
			return $item;
		}

		foreach ( $defaults as $key => $value ) {
			if ( 'name' === $key ) {
				continue;
			}

			if ( self::CONSENT_COOKIE === $name || ! isset( $item[ $key ] ) || '' === trim( (string) $item[ $key ] ) ) {
				$item[ $key ] = $value;
			}
		}

		if ( '' === trim( (string) $provider ) && ! empty( $defaults['provider'] ) ) {
			$item['provider'] = $defaults['provider'];
		}

		return $item;
	}

	private static function get_internal_cookie_defaults( $name ) {
		$known_names = array(
			self::CONSENT_COOKIE,
			'cdp-cookies-plugin-wp',
		);

		if ( ! in_array( $name, $known_names, true ) ) {
			return array();
		}

		return array(
			'provider'    => __( 'This website', 'asesor-cookies-para-la-ley-en-espana' ),
			'service_pattern' => wp_parse_url( home_url(), PHP_URL_HOST ),
			'category'    => 'necessary',
			'type'        => 'own',
			'duration'    => __( '1 year', 'asesor-cookies-para-la-ley-en-espana' ),
			'description' => __( 'Stores the visitor cookie consent preferences.', 'asesor-cookies-para-la-ley-en-espana' ),
		);
	}

	private static function sanitize_category( $category ) {
		$category = sanitize_key( $category );
		return array_key_exists( $category, self::get_categories() ) ? $category : 'necessary';
	}

	private static function sanitize_cookie_type( $type ) {
		$type = sanitize_key( $type );
		return in_array( $type, array( 'own', 'third_party' ), true ) ? $type : 'own';
	}

	private static function sanitize_service_pattern( $pattern ) {
		$pattern = sanitize_text_field( (string) $pattern );
		$pattern = preg_replace( '/\s+/', ' ', $pattern );

		return trim( $pattern );
	}

	public static function renderizar_aviso() {
		if ( ! self::should_render_front_ui() ) {
			return;
		}

		$text = self::get_banner_text();
		$preferences_button = self::get_preferences_button_settings();

		$categories = self::get_front_categories();
		unset( $categories['necessary'] );

		?>
		<div class="cdp-cookies-banner" data-cdp-cookies-banner hidden>
			<div class="cdp-cookies-banner__body">
				<div class="cdp-cookies-banner__text"><?php echo wp_kses_post( wpautop( $text ) ); ?></div>
				<div class="cdp-cookies-banner__actions">
					<button type="button" class="cdp-cookies-button cdp-cookies-button--primary" data-cdp-cookies-accept-all><?php esc_html_e( 'Accept all', 'asesor-cookies-para-la-ley-en-espana' ); ?></button>
					<button type="button" class="cdp-cookies-button" data-cdp-cookies-reject><?php esc_html_e( 'Reject', 'asesor-cookies-para-la-ley-en-espana' ); ?></button>
					<button type="button" class="cdp-cookies-button" data-cdp-cookies-configure><?php esc_html_e( 'Configure', 'asesor-cookies-para-la-ley-en-espana' ); ?></button>
				</div>
			</div>
			<div class="cdp-cookies-panel" data-cdp-cookies-panel hidden>
				<div class="cdp-cookies-panel__header">
					<strong><?php esc_html_e( 'Configure cookies', 'asesor-cookies-para-la-ley-en-espana' ); ?></strong>
					<button type="button" class="cdp-cookies-button cdp-cookies-button--link" data-cdp-cookies-close><?php esc_html_e( 'close', 'asesor-cookies-para-la-ley-en-espana' ); ?></button>
				</div>
				<div class="cdp-cookies-option">
					<label>
						<input type="checkbox" checked disabled>
						<span><?php echo esc_html( self::get_front_categories()['necessary'] ); ?></span>
					</label>
					<p><?php echo esc_html( self::get_front_category_description( 'necessary' ) ); ?></p>
				</div>
				<?php foreach ( $categories as $key => $label ) : ?>
					<div class="cdp-cookies-option">
						<label>
							<input type="checkbox" value="1" data-cdp-cookies-category="<?php echo esc_attr( $key ); ?>">
							<span><?php echo esc_html( $label ); ?></span>
						</label>
						<p><?php echo esc_html( self::get_front_category_description( $key ) ); ?></p>
					</div>
				<?php endforeach; ?>
				<div class="cdp-cookies-panel__actions">
					<button type="button" class="cdp-cookies-button cdp-cookies-button--primary" data-cdp-cookies-save><?php esc_html_e( 'Save configuration', 'asesor-cookies-para-la-ley-en-espana' ); ?></button>
				</div>
			</div>
		</div>
		<button
			type="button"
			class="<?php echo esc_attr( 'cdp-cookies-preferences cdp-cookies-preferences--' . $preferences_button['type'] . ' cdp-cookies-preferences--' . $preferences_button['position'] ); ?>"
			style="<?php echo esc_attr( self::get_preferences_button_style( $preferences_button ) ); ?>"
			data-cdp-cookies-open-preferences
			aria-label="<?php esc_attr_e( 'Cookie settings', 'asesor-cookies-para-la-ley-en-espana' ); ?>"
			hidden>
			<?php if ( 'icon' === $preferences_button['type'] ) : ?>
				<span class="cdp-cookies-cookie-icon" aria-hidden="true"><?php echo self::get_cookie_icon_svg(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			<?php else : ?>
				<?php esc_html_e( 'Cookies', 'asesor-cookies-para-la-ley-en-espana' ); ?>
			<?php endif; ?>
		</button>
		<?php
	}

	private static function get_preferences_button_style( $settings ) {
		return sprintf(
			'--cdp-pref-x-desktop:%1$d%%;--cdp-pref-x-tablet:%2$d%%;--cdp-pref-x-mobile:%3$d%%;--cdp-pref-side-y:%4$d%%;',
			absint( $settings['bottom_desktop_x'] ),
			absint( $settings['bottom_tablet_x'] ),
			absint( $settings['bottom_mobile_x'] ),
			absint( $settings['side_y'] )
		);
	}

	private static function get_cookie_icon_svg() {
		return '<svg viewBox="0 0 24 24" focusable="false" role="img" xmlns="http://www.w3.org/2000/svg"><path d="M12 2.25a9.75 9.75 0 1 0 9.75 9.75 3.2 3.2 0 0 1-4.25-4.25 3.15 3.15 0 0 1-3.9-3.9A3.2 3.2 0 0 1 12 2.25Zm-3.9 5.9a1.35 1.35 0 1 1 0 2.7 1.35 1.35 0 0 1 0-2.7Zm3.75 6a1.25 1.25 0 1 1 0 2.5 1.25 1.25 0 0 1 0-2.5Zm3.65-3.3a1.25 1.25 0 1 1 0 2.5 1.25 1.25 0 0 1 0-2.5ZM7.75 16.4a1.1 1.1 0 1 1 0 2.2 1.1 1.1 0 0 1 0-2.2Z" fill="currentColor"/></svg>';
	}

	public static function shortcode_consent( $atts, $content = '' ) {
		$atts = shortcode_atts(
			array(
				'category' => 'personalization',
				'service'  => __( 'External content', 'asesor-cookies-para-la-ley-en-espana' ),
				'title'    => __( 'External content blocked', 'asesor-cookies-para-la-ley-en-espana' ),
			),
			(array) $atts,
			'cdp_consent'
		);
		return self::render_consent_wrapper(
			$atts['category'],
			$atts['service'],
			$atts['title'],
			do_shortcode( (string) $content )
		);
	}

	public static function render_consent_block( $attributes, $content ) {
		$content = has_blocks( $content ) ? do_blocks( $content ) : $content;

		return self::render_consent_wrapper(
			$attributes['category'] ?? 'personalization',
			$attributes['service'] ?? __( 'External content', 'asesor-cookies-para-la-ley-en-espana' ),
			$attributes['title'] ?? __( 'External content blocked', 'asesor-cookies-para-la-ley-en-espana' ),
			$content
		);
	}

	private static function render_consent_wrapper( $category, $service, $title, $content ) {
		$category = self::sanitize_category( $category );

		if ( 'necessary' === $category ) {
			$category = 'personalization';
		}

		$service = sanitize_text_field( $service );
		$title = sanitize_text_field( $title );
		$button = sprintf(
			/* translators: %s: consent category name. */
			__( 'Accept %s cookies and load content', 'asesor-cookies-para-la-ley-en-espana' ),
			self::get_front_categories()[ $category ] ?? $category
		);

		ob_start();
		?>
		<div class="cdp-cookies-protected-embed" data-cdp-consent-embed data-cdp-consent-category="<?php echo esc_attr( $category ); ?>">
			<div class="cdp-cookies-protected-embed__placeholder" data-cdp-consent-placeholder>
				<strong><?php echo esc_html( $title ); ?></strong>
				<p><?php echo esc_html( sprintf( __( '%s may install cookies or load external resources.', 'asesor-cookies-para-la-ley-en-espana' ), $service ) ); ?></p>
				<button type="button" class="cdp-cookies-button cdp-cookies-button--primary" data-cdp-consent-embed-accept="<?php echo esc_attr( $category ); ?>"><?php echo esc_html( $button ); ?></button>
			</div>
			<template data-cdp-consent-template><?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></template>
		</div>
		<?php
		return ob_get_clean();
	}

	public static function shortcode_policy_table() {
		$items = self::get_cookie_items();

		if ( empty( $items ) ) {
			return '<p>' . esc_html__( 'There are no declared cookies yet.', 'asesor-cookies-para-la-ley-en-espana' ) . '</p>';
		}

		ob_start();
		?>
		<table class="cdp-cookies-policy-table">
			<thead>
				<tr>
					<th>Cookie</th>
					<th><?php esc_html_e( 'Provider', 'asesor-cookies-para-la-ley-en-espana' ); ?></th>
					<th><?php esc_html_e( 'Type', 'asesor-cookies-para-la-ley-en-espana' ); ?></th>
					<th><?php esc_html_e( 'Category', 'asesor-cookies-para-la-ley-en-espana' ); ?></th>
					<th><?php esc_html_e( 'Duration', 'asesor-cookies-para-la-ley-en-espana' ); ?></th>
					<th><?php esc_html_e( 'Purpose', 'asesor-cookies-para-la-ley-en-espana' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $items as $item ) : ?>
					<tr>
						<td><?php echo esc_html( $item['name'] ); ?></td>
						<td><?php echo esc_html( $item['provider'] ); ?></td>
						<td><?php echo esc_html( 'third_party' === $item['type'] ? __( 'Third-party', 'asesor-cookies-para-la-ley-en-espana' ) : __( 'First-party', 'asesor-cookies-para-la-ley-en-espana' ) ); ?></td>
						<td><?php echo esc_html( self::get_categories()[ $item['category'] ] ?? $item['category'] ); ?></td>
						<td><?php echo esc_html( self::translate_known_cookie_text( $item['duration'] ) ); ?></td>
						<td><?php echo esc_html( self::translate_known_cookie_text( $item['description'] ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
		return ob_get_clean();
	}

	private static function get_embed_scan_status_label( $status ) {
		$labels = array(
			'protected'   => __( 'Protected', 'asesor-cookies-para-la-ley-en-espana' ),
			'unprotected' => __( 'Not protected', 'asesor-cookies-para-la-ley-en-espana' ),
			'review'      => __( 'Review manually', 'asesor-cookies-para-la-ley-en-espana' ),
		);

		return $labels[ $status ] ?? $status;
	}

	private static function get_embed_scan_status_class( $status ) {
		return in_array( $status, array( 'protected', 'unprotected', 'review' ), true ) ? $status : 'review';
	}

	public static function pag_configuracion() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$items = self::get_cookie_items();
		$scripts = self::get_scripts();
		$categories = self::get_categories();
		$menu_location = self::get_menu_location();
		$banner_texts = self::get_banner_texts();
		$current_language = self::get_current_language();
		$audit_items = self::get_audit_items();
		$ignored_audit_items = self::get_ignored_audit_items();
		$audit_recording = self::get_audit_recording();
		$audit_external_resources = self::get_audit_external_resources();
		$declared_cookie_names = self::get_declared_cookie_names();
		$declared_service_patterns = self::get_declared_service_patterns();
		$preferences_button = self::get_preferences_button_settings();
		$embed_scan_results = self::get_embed_scan_results();
		$embed_scan_date = self::get_embed_scan_date();
		?>
		<div class="wrap cdp-cookies-admin">
			<h1><?php esc_html_e( 'Cookie Advisor', 'asesor-cookies-para-la-ley-en-espana' ); ?></h1>
			<p class="cdp-cookies-intro"><?php esc_html_e( 'Manage consent, declare the cookies used by this site, and load non-essential scripts only after visitor consent.', 'asesor-cookies-para-la-ley-en-espana' ); ?></p>

			<?php if ( isset( $_GET['cdp_cookies_preset_status'] ) ) : ?>
				<?php $preset_status = sanitize_key( wp_unslash( $_GET['cdp_cookies_preset_status'] ) ); ?>
				<?php if ( 'duplicate' === $preset_status ) : ?>
					<div class="notice notice-warning inline"><p><?php esc_html_e( 'That library service or cookie is already in the inventory. No duplicate was added.', 'asesor-cookies-para-la-ley-en-espana' ); ?></p></div>
				<?php elseif ( 'added' === $preset_status ) : ?>
					<div class="cdp-cookies-ok"><?php esc_html_e( 'Library service or cookie added to the inventory.', 'asesor-cookies-para-la-ley-en-espana' ); ?></div>
				<?php else : ?>
					<div class="notice notice-error inline"><p><?php esc_html_e( 'The selected library service or cookie could not be added.', 'asesor-cookies-para-la-ley-en-espana' ); ?></p></div>
				<?php endif; ?>
			<?php elseif ( isset( $_GET['cdp_cookies_cookie_status'] ) ) : ?>
				<?php $cookie_status = sanitize_key( wp_unslash( $_GET['cdp_cookies_cookie_status'] ) ); ?>
				<?php if ( 'duplicate' === $cookie_status ) : ?>
					<div class="notice notice-warning inline"><p><?php esc_html_e( 'That cookie is already in the inventory. No duplicate was added.', 'asesor-cookies-para-la-ley-en-espana' ); ?></p></div>
				<?php elseif ( 'added' === $cookie_status ) : ?>
					<div class="cdp-cookies-ok"><?php esc_html_e( 'Cookie added to the inventory.', 'asesor-cookies-para-la-ley-en-espana' ); ?></div>
				<?php else : ?>
					<div class="notice notice-error inline"><p><?php esc_html_e( 'The cookie could not be added.', 'asesor-cookies-para-la-ley-en-espana' ); ?></p></div>
				<?php endif; ?>
			<?php elseif ( isset( $_GET['cdp_cookies_updated'] ) && ! isset( $_GET['cdp_cookies_policy_page'] ) ) : ?>
				<div class="cdp-cookies-ok"><?php esc_html_e( 'Changes saved successfully.', 'asesor-cookies-para-la-ley-en-espana' ); ?></div>
			<?php endif; ?>

			<div class="cdp-cookies-toolbar">
				<span class="cdp-cookies-badge"><?php esc_html_e( 'Consent management', 'asesor-cookies-para-la-ley-en-espana' ); ?></span>
				<span class="cdp-cookies-pill"><?php echo esc_html( sprintf( __( '%s cookies in inventory', 'asesor-cookies-para-la-ley-en-espana' ), number_format_i18n( count( $items ) ) ) ); ?></span>
			</div>

			<div class="cdp-cookies-tabs">
				<a class="cdp-cookies-tab is-active" href="#cdp-cookies-start"><?php esc_html_e( 'Start', 'asesor-cookies-para-la-ley-en-espana' ); ?></a>
				<a class="cdp-cookies-tab" href="#cdp-cookies-general"><?php esc_html_e( 'Banner', 'asesor-cookies-para-la-ley-en-espana' ); ?></a>
				<a class="cdp-cookies-tab" href="#cdp-cookies-policy"><?php esc_html_e( 'Cookie policy', 'asesor-cookies-para-la-ley-en-espana' ); ?></a>
				<a class="cdp-cookies-tab" href="#cdp-cookies-inventory"><?php esc_html_e( 'Cookie inventory', 'asesor-cookies-para-la-ley-en-espana' ); ?></a>
				<a class="cdp-cookies-tab" href="#cdp-cookies-audit"><?php esc_html_e( 'Assisted audit', 'asesor-cookies-para-la-ley-en-espana' ); ?></a>
				<a class="cdp-cookies-tab" href="#cdp-cookies-embeds"><?php esc_html_e( 'Protected embeds', 'asesor-cookies-para-la-ley-en-espana' ); ?></a>
				<a class="cdp-cookies-tab" href="#cdp-cookies-settings"><?php esc_html_e( 'Settings', 'asesor-cookies-para-la-ley-en-espana' ); ?></a>
			</div>

			<div class="cdp-cookies-section-stack">
				<div id="cdp-cookies-start" class="cdp-cookies-card">
					<div class="cdp-cookies-card-header">
						<h2><?php esc_html_e( 'How to use this plugin', 'asesor-cookies-para-la-ley-en-espana' ); ?></h2>
					</div>
					<p><?php esc_html_e( 'This plugin has two main functions: helping you detect cookies and helping you block them until the visitor gives consent.', 'asesor-cookies-para-la-ley-en-espana' ); ?></p>
					<p><?php esc_html_e( 'Detection helps you complete the cookie policy with the cookies and external services used by the website. Blocking requires: move cookie-setting scripts into this plugin, wrap external embeds, and manually declare the rest of the cookies. The Assisted audit tab can help you detect cookies more easily, although your review will always be required.', 'asesor-cookies-para-la-ley-en-espana' ); ?></p>
					<h3><?php esc_html_e( 'Embeds', 'asesor-cookies-para-la-ley-en-espana' ); ?></h3>
					<p><?php esc_html_e( 'Wrap every video, map, iframe, or external embed that may install cookies. In Gutenberg, use the "Consent-protected content" block and place the embed inside it. If this website does not use Gutenberg, place the opening and closing consent shortcodes manually around the embed.', 'asesor-cookies-para-la-ley-en-espana' ); ?></p>
					<code class="cdp-cookies-shortcode-example">[cdp_consent category="personalization" service="YouTube"]...[/cdp_consent]</code>
					<p class="description"><?php esc_html_e( 'The category attribute is technical and must use one of these fixed values: necessary, analytics, marketing, personalization. For external embeds, personalization is usually the right category; necessary is not recommended for non-essential external content. The service attribute is only the visible provider name shown to visitors, for example YouTube, Google Maps, or Vimeo.', 'asesor-cookies-para-la-ley-en-espana' ); ?></p>
					<details class="cdp-cookies-examples">
						<summary><?php esc_html_e( 'Examples', 'asesor-cookies-para-la-ley-en-espana' ); ?></summary>
						<div class="cdp-cookies-examples-grid">
							<div>
								<strong><?php esc_html_e( 'YouTube video', 'asesor-cookies-para-la-ley-en-espana' ); ?></strong>
								<code>[cdp_consent category="personalization" service="YouTube"]...[/cdp_consent]</code>
							</div>
							<div>
								<strong><?php esc_html_e( 'Google Maps map', 'asesor-cookies-para-la-ley-en-espana' ); ?></strong>
								<code>[cdp_consent category="personalization" service="Google Maps"]...[/cdp_consent]</code>
							</div>
							<div>
								<strong><?php esc_html_e( 'Marketing embed', 'asesor-cookies-para-la-ley-en-espana' ); ?></strong>
								<code>[cdp_consent category="marketing" service="Instagram"]...[/cdp_consent]</code>
							</div>
							<div>
								<strong><?php esc_html_e( 'Analytics embed', 'asesor-cookies-para-la-ley-en-espana' ); ?></strong>
								<code>[cdp_consent category="analytics" service="Analytics provider"]...[/cdp_consent]</code>
							</div>
						</div>
					</details>
					<h3><?php esc_html_e( 'Scripts', 'asesor-cookies-para-la-ley-en-espana' ); ?></h3>
					<p><?php esc_html_e( 'Move every script on your website that installs cookies into this plugin. Paste each script in its consent category so it only loads after the visitor accepts it.', 'asesor-cookies-para-la-ley-en-espana' ); ?></p>
					<p class="description"><?php esc_html_e( 'This plugin guarantees that scripts configured in these fields only load after the matching consent. The website owner must remove any other copy of those scripts loaded by the theme, another plugin, a builder, or custom code.', 'asesor-cookies-para-la-ley-en-espana' ); ?></p>
					<p><?php esc_html_e( 'Declaring cookies in the inventory documents them, but it does not block them. Blocking requires controlling the scripts and embeds that create them.', 'asesor-cookies-para-la-ley-en-espana' ); ?></p>
					<form method="post" class="cdp-cookies-field-row-form cdp-cookies-start-scripts-form">
						<?php wp_nonce_field( 'cdp_cookies_admin_action', 'cdp_cookies_nonce' ); ?>
						<input type="hidden" name="cdp_cookies_action" value="save_settings">

						<?php foreach ( $scripts as $category => $script ) : ?>
							<label for="start_script_<?php echo esc_attr( $category ); ?>"><?php echo esc_html( self::get_script_area_label( $category ) ); ?></label>
							<div>
								<textarea id="start_script_<?php echo esc_attr( $category ); ?>" name="scripts[<?php echo esc_attr( $category ); ?>]" rows="5" spellcheck="false"><?php echo esc_textarea( $script ); ?></textarea>
								<p class="description"><?php esc_html_e( 'Loaded only if the visitor accepts this category. Do not paste scripts required for basic website functionality here.', 'asesor-cookies-para-la-ley-en-espana' ); ?></p>
							</div>
						<?php endforeach; ?>

						<div class="cdp-cookies-field-row-actions">
							<button type="submit" class="button button-primary"><?php esc_html_e( 'Save scripts', 'asesor-cookies-para-la-ley-en-espana' ); ?></button>
						</div>
					</form>
				</div>

				<div id="cdp-cookies-general" class="cdp-cookies-card">
					<div class="cdp-cookies-card-header">
						<h2><?php esc_html_e( 'Cookie banner', 'asesor-cookies-para-la-ley-en-espana' ); ?></h2>
					</div>
					<form method="post" class="cdp-cookies-field-row-form">
						<?php wp_nonce_field( 'cdp_cookies_admin_action', 'cdp_cookies_nonce' ); ?>
						<input type="hidden" name="cdp_cookies_action" value="save_settings">

						<label><?php esc_html_e( 'Notice shown to the visitor', 'asesor-cookies-para-la-ley-en-espana' ); ?></label>
						<div>
							<div class="cdp-cookies-lang-tabs" data-cdp-banner-lang-tabs>
								<?php foreach ( self::get_supported_banner_languages() as $language => $label ) : ?>
									<button type="button" class="cdp-cookies-lang-tab <?php echo $language === $current_language ? 'is-active' : ''; ?>" data-cdp-banner-lang="<?php echo esc_attr( $language ); ?>"><?php echo esc_html( $label ); ?></button>
								<?php endforeach; ?>
							</div>
							<?php foreach ( self::get_supported_banner_languages() as $language => $label ) : ?>
								<textarea id="texto_aviso_<?php echo esc_attr( $language ); ?>" name="textos_aviso[<?php echo esc_attr( $language ); ?>]" rows="5" data-cdp-banner-text="<?php echo esc_attr( $language ); ?>" <?php echo $language === $current_language ? '' : 'hidden'; ?>><?php echo esc_textarea( $banner_texts[ $language ] ); ?></textarea>
							<?php endforeach; ?>
								<p class="description"><?php esc_html_e( 'Write the banner text here. The cookie policy link is added automatically from the URL field below.', 'asesor-cookies-para-la-ley-en-espana' ); ?></p>
						</div>

						<label for="enlace_politica"><?php esc_html_e( 'Cookie policy page URL', 'asesor-cookies-para-la-ley-en-espana' ); ?></label>
						<div>
							<input id="enlace_politica" type="url" name="enlace_politica" value="<?php echo esc_attr( self::parametro( 'enlace_politica' ) ); ?>">
						</div>

						<label><?php esc_html_e( 'Preferences button style', 'asesor-cookies-para-la-ley-en-espana' ); ?></label>
						<div class="cdp-cookies-inline-fields">
							<label class="cdp-cookies-checkbox-row">
								<input type="radio" name="preferences_button[type]" value="text" <?php checked( $preferences_button['type'], 'text' ); ?>>
								<?php esc_html_e( 'Text', 'asesor-cookies-para-la-ley-en-espana' ); ?>
							</label>
							<label class="cdp-cookies-checkbox-row">
								<input type="radio" name="preferences_button[type]" value="icon" <?php checked( $preferences_button['type'], 'icon' ); ?>>
								<?php esc_html_e( 'Cookie icon', 'asesor-cookies-para-la-ley-en-espana' ); ?>
							</label>
						</div>

						<label><?php esc_html_e( 'Preferences button position', 'asesor-cookies-para-la-ley-en-espana' ); ?></label>
						<div class="cdp-cookies-position-controls">
							<div class="cdp-cookies-inline-fields">
								<label class="cdp-cookies-checkbox-row">
									<input type="radio" name="preferences_button[position]" value="bottom" <?php checked( $preferences_button['position'], 'bottom' ); ?>>
									<?php esc_html_e( 'Bottom', 'asesor-cookies-para-la-ley-en-espana' ); ?>
								</label>
								<label class="cdp-cookies-checkbox-row">
									<input type="radio" name="preferences_button[position]" value="right" <?php checked( $preferences_button['position'], 'right' ); ?>>
									<?php esc_html_e( 'Right side', 'asesor-cookies-para-la-ley-en-espana' ); ?>
								</label>
								<label class="cdp-cookies-checkbox-row">
									<input type="radio" name="preferences_button[position]" value="left" <?php checked( $preferences_button['position'], 'left' ); ?>>
									<?php esc_html_e( 'Left side', 'asesor-cookies-para-la-ley-en-espana' ); ?>
								</label>
							</div>
							<div class="cdp-cookies-number-grid">
								<label>
									<span><?php esc_html_e( 'Desktop horizontal %', 'asesor-cookies-para-la-ley-en-espana' ); ?></span>
									<input type="number" min="0" max="100" name="preferences_button[bottom_desktop_x]" value="<?php echo esc_attr( $preferences_button['bottom_desktop_x'] ); ?>">
								</label>
								<label>
									<span><?php esc_html_e( 'Tablet horizontal %', 'asesor-cookies-para-la-ley-en-espana' ); ?></span>
									<input type="number" min="0" max="100" name="preferences_button[bottom_tablet_x]" value="<?php echo esc_attr( $preferences_button['bottom_tablet_x'] ); ?>">
								</label>
								<label>
									<span><?php esc_html_e( 'Mobile horizontal %', 'asesor-cookies-para-la-ley-en-espana' ); ?></span>
									<input type="number" min="0" max="100" name="preferences_button[bottom_mobile_x]" value="<?php echo esc_attr( $preferences_button['bottom_mobile_x'] ); ?>">
								</label>
								<label>
									<span><?php esc_html_e( 'Side vertical %', 'asesor-cookies-para-la-ley-en-espana' ); ?></span>
									<input type="number" min="50" max="100" name="preferences_button[side_y]" value="<?php echo esc_attr( $preferences_button['side_y'] ); ?>">
								</label>
							</div>
							<p class="description"><?php esc_html_e( 'Bottom mode uses a horizontal percentage for desktop, tablet, and mobile. Side modes use the vertical percentage from the middle of the page downward.', 'asesor-cookies-para-la-ley-en-espana' ); ?></p>
						</div>

						<div class="cdp-cookies-field-row-actions">
							<button type="submit" class="button button-primary"><?php esc_html_e( 'Save configuration', 'asesor-cookies-para-la-ley-en-espana' ); ?></button>
						</div>
					</form>
				</div>

				<div id="cdp-cookies-policy" class="cdp-cookies-card">
					<div class="cdp-cookies-card-header">
						<h2><?php esc_html_e( 'Cookie policy', 'asesor-cookies-para-la-ley-en-espana' ); ?></h2>
					</div>
					<?php $policy_url = self::parametro( 'enlace_politica' ); ?>
					<?php $policy_url_status = self::get_policy_url_status( $policy_url ); ?>
					<?php if ( isset( $_GET['cdp_cookies_policy_page'] ) ) : ?>
						<?php $policy_page_status = sanitize_key( wp_unslash( $_GET['cdp_cookies_policy_page'] ) ); ?>
						<?php if ( 'created' === $policy_page_status ) : ?>
							<div class="cdp-cookies-ok"><?php esc_html_e( 'Cookie policy page created. Its URL has been saved as the official cookie policy page.', 'asesor-cookies-para-la-ley-en-espana' ); ?></div>
						<?php elseif ( 'existing' === $policy_page_status ) : ?>
							<div class="cdp-cookies-ok"><?php esc_html_e( 'A cookie policy page already existed. Its URL has been saved as the official cookie policy page.', 'asesor-cookies-para-la-ley-en-espana' ); ?></div>
						<?php else : ?>
							<div class="notice notice-error inline"><p><?php esc_html_e( 'The cookie policy page could not be created. Please create it manually and paste its URL here.', 'asesor-cookies-para-la-ley-en-espana' ); ?></p></div>
						<?php endif; ?>
					<?php endif; ?>
					<?php if ( 'empty' === $policy_url_status ) : ?>
						<div class="notice notice-warning inline"><p><?php esc_html_e( 'No cookie policy page URL has been configured yet. Create the page automatically or paste the URL of an existing cookie policy page.', 'asesor-cookies-para-la-ley-en-espana' ); ?></p></div>
					<?php elseif ( 'missing' === $policy_url_status ) : ?>
						<div class="notice notice-warning inline"><p><?php esc_html_e( 'The configured cookie policy page was not found. Create the page automatically or paste the URL of an existing cookie policy page.', 'asesor-cookies-para-la-ley-en-espana' ); ?></p></div>
					<?php elseif ( 'invalid' === $policy_url_status ) : ?>
						<div class="notice notice-warning inline"><p><?php esc_html_e( 'The configured cookie policy URL is not valid. Paste a complete URL or create the page automatically.', 'asesor-cookies-para-la-ley-en-espana' ); ?></p></div>
					<?php elseif ( 'unknown' === $policy_url_status ) : ?>
						<div class="notice notice-warning inline"><p><?php esc_html_e( 'The configured cookie policy URL could not be checked. Open it manually and confirm that it exists.', 'asesor-cookies-para-la-ley-en-espana' ); ?></p></div>
					<?php endif; ?>
					<form method="post" class="cdp-cookies-field-row-form">
						<?php wp_nonce_field( 'cdp_cookies_admin_action', 'cdp_cookies_nonce' ); ?>
						<input type="hidden" name="cdp_cookies_action" value="save_settings">
						<label for="enlace_politica_policy"><?php esc_html_e( 'Cookie policy page URL', 'asesor-cookies-para-la-ley-en-espana' ); ?></label>
						<div>
							<input id="enlace_politica_policy" type="url" name="enlace_politica" value="<?php echo esc_attr( $policy_url ); ?>">
							<p class="description"><?php esc_html_e( 'If you already have a cookie policy page, paste its URL here.', 'asesor-cookies-para-la-ley-en-espana' ); ?></p>
						</div>
						<div class="cdp-cookies-field-row-actions">
							<button type="submit" class="button button-primary"><?php esc_html_e( 'Save policy URL', 'asesor-cookies-para-la-ley-en-espana' ); ?></button>
							<?php if ( $policy_url ) : ?>
								<a class="button" href="<?php echo esc_url( $policy_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View policy', 'asesor-cookies-para-la-ley-en-espana' ); ?></a>
							<?php endif; ?>
						</div>
					</form>
					<form method="post">
						<?php wp_nonce_field( 'cdp_cookies_admin_action', 'cdp_cookies_nonce' ); ?>
						<input type="hidden" name="cdp_cookies_action" value="create_policy_page">
						<p><?php esc_html_e( 'The created page includes the shortcode [cdp_cookies_policy_table], which will show the cookies declared in this plugin, both current and future ones. If you already have a previous cookie page, you only need to add the shortcode wherever you prefer to show the cookie table.', 'asesor-cookies-para-la-ley-en-espana' ); ?></p>
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Create cookie policy page', 'asesor-cookies-para-la-ley-en-espana' ); ?></button>
						<p class="description"><?php esc_html_e( 'If this admin page has been open for many hours, reload it before creating the page to refresh the security token.', 'asesor-cookies-para-la-ley-en-espana' ); ?></p>
					</form>
				</div>

				<div id="cdp-cookies-inventory" class="cdp-cookies-card">
					<div class="cdp-cookies-card-header">
						<h2><?php esc_html_e( 'Cookie inventory', 'asesor-cookies-para-la-ley-en-espana' ); ?></h2>
					</div>
					<p class="description"><?php esc_html_e( 'Declaring cookies does not block cookies; to block them, you must control the code that creates them. Service patterns help connect detected external domains with the services declared in this inventory.', 'asesor-cookies-para-la-ley-en-espana' ); ?></p>

					<form method="post" class="cdp-cookies-two-col-form">
						<?php wp_nonce_field( 'cdp_cookies_admin_action', 'cdp_cookies_nonce' ); ?>
						<input type="hidden" name="cdp_cookies_action" value="add_cookie">
						<div class="cdp-cookies-field cdp-cookies-field-full">
							<label for="cookie_preset"><?php esc_html_e( 'Service library', 'asesor-cookies-para-la-ley-en-espana' ); ?></label>
							<select id="cookie_preset" data-cdp-cookie-preset>
								<option value=""><?php esc_html_e( 'Select a service to fill the form', 'asesor-cookies-para-la-ley-en-espana' ); ?></option>
								<?php foreach ( self::get_presets() as $preset_key => $preset ) : ?>
									<optgroup label="<?php echo esc_attr( $preset['label'] ); ?>">
										<?php foreach ( $preset['cookies'] as $index => $cookie ) : ?>
											<?php $option_key = $preset_key . '_' . $index; ?>
											<option value="<?php echo esc_attr( $option_key ); ?>"><?php echo esc_html( $cookie['name'] ); ?></option>
										<?php endforeach; ?>
									</optgroup>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'The library is organized by services. Each service may suggest one or more cookies and an external domain pattern.', 'asesor-cookies-para-la-ley-en-espana' ); ?></p>
						</div>
						<div class="cdp-cookies-field">
							<label for="cookie_name"><?php esc_html_e( 'Name', 'asesor-cookies-para-la-ley-en-espana' ); ?></label>
							<input id="cookie_name" type="text" name="cookie_name" required>
						</div>
						<div class="cdp-cookies-field">
							<label for="cookie_provider"><?php esc_html_e( 'Provider', 'asesor-cookies-para-la-ley-en-espana' ); ?></label>
							<input id="cookie_provider" type="text" name="cookie_provider">
						</div>
						<div class="cdp-cookies-field cdp-cookies-field-full">
							<label for="cookie_service_pattern"><?php esc_html_e( 'Service/domain pattern', 'asesor-cookies-para-la-ley-en-espana' ); ?></label>
							<input id="cookie_service_pattern" type="text" name="cookie_service_pattern" placeholder="youtube.com, googletagmanager.com">
							<p class="description"><?php esc_html_e( 'Used internally to match detected external resources with this declared service. You can enter one or more domains separated by commas.', 'asesor-cookies-para-la-ley-en-espana' ); ?></p>
						</div>
						<div class="cdp-cookies-field">
							<label for="cookie_category"><?php esc_html_e( 'Category', 'asesor-cookies-para-la-ley-en-espana' ); ?></label>
							<select id="cookie_category" name="cookie_category">
								<?php foreach ( $categories as $key => $label ) : ?>
									<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="cdp-cookies-field">
							<label for="cookie_type"><?php esc_html_e( 'Type', 'asesor-cookies-para-la-ley-en-espana' ); ?></label>
							<select id="cookie_type" name="cookie_type">
								<option value="own"><?php esc_html_e( 'First-party', 'asesor-cookies-para-la-ley-en-espana' ); ?></option>
								<option value="third_party"><?php esc_html_e( 'Third-party', 'asesor-cookies-para-la-ley-en-espana' ); ?></option>
							</select>
						</div>
						<div class="cdp-cookies-field">
							<label for="cookie_duration"><?php esc_html_e( 'Duration', 'asesor-cookies-para-la-ley-en-espana' ); ?></label>
							<input id="cookie_duration" type="text" name="cookie_duration">
						</div>
						<div class="cdp-cookies-field cdp-cookies-field-full">
							<label for="cookie_description"><?php esc_html_e( 'Purpose', 'asesor-cookies-para-la-ley-en-espana' ); ?></label>
							<textarea id="cookie_description" name="cookie_description" rows="3"></textarea>
						</div>
						<div class="cdp-cookies-field-full">
							<button type="submit" class="button"><?php esc_html_e( 'Add cookie/service', 'asesor-cookies-para-la-ley-en-espana' ); ?></button>
						</div>
					</form>

					<div class="cdp-cookies-table-wrap">
						<table class="cdp-cookies-table">
							<thead>
								<tr>
									<th>Cookie</th>
									<th><?php esc_html_e( 'Provider', 'asesor-cookies-para-la-ley-en-espana' ); ?></th>
									<th><?php esc_html_e( 'Service pattern', 'asesor-cookies-para-la-ley-en-espana' ); ?></th>
									<th><?php esc_html_e( 'Category', 'asesor-cookies-para-la-ley-en-espana' ); ?></th>
									<th><?php esc_html_e( 'Type', 'asesor-cookies-para-la-ley-en-espana' ); ?></th>
									<th><?php esc_html_e( 'Duration', 'asesor-cookies-para-la-ley-en-espana' ); ?></th>
									<th><?php esc_html_e( 'Purpose', 'asesor-cookies-para-la-ley-en-espana' ); ?></th>
									<th class="cdp-cookies-actions-column"><?php esc_html_e( 'Actions', 'asesor-cookies-para-la-ley-en-espana' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php if ( empty( $items ) ) : ?>
									<tr><td colspan="8"><?php esc_html_e( 'There are no declared cookies yet.', 'asesor-cookies-para-la-ley-en-espana' ); ?></td></tr>
									<?php endif; ?>
									<?php foreach ( $items as $item ) : ?>
										<?php $is_required_internal_cookie = isset( $item['name'] ) && self::CONSENT_COOKIE === $item['name']; ?>
										<tr data-cdp-cookie-row="<?php echo esc_attr( $item['id'] ); ?>">
											<td><code><?php echo esc_html( $item['name'] ); ?></code></td>
											<td><?php echo esc_html( $item['provider'] ); ?></td>
											<td><?php echo ! empty( $item['service_pattern'] ) ? '<code>' . esc_html( $item['service_pattern'] ) . '</code>' : ''; ?></td>
										<td><?php echo esc_html( $categories[ $item['category'] ] ?? $item['category'] ); ?></td>
										<td><?php echo esc_html( 'third_party' === $item['type'] ? __( 'Third-party', 'asesor-cookies-para-la-ley-en-espana' ) : __( 'First-party', 'asesor-cookies-para-la-ley-en-espana' ) ); ?></td>
										<td><?php echo esc_html( self::translate_known_cookie_text( $item['duration'] ) ); ?></td>
											<td><?php echo esc_html( self::translate_known_cookie_text( $item['description'] ) ); ?></td>
											<td class="cdp-cookies-actions-column">
												<?php if ( ! $is_required_internal_cookie ) : ?>
													<div class="cdp-cookies-row-actions">
														<button type="button" class="button cdp-cookies-icon-button" data-cdp-edit-cookie="<?php echo esc_attr( $item['id'] ); ?>" aria-label="<?php esc_attr_e( 'Edit cookie', 'asesor-cookies-para-la-ley-en-espana' ); ?>">
															<span class="dashicons dashicons-edit"></span>
														</button>
														<form method="post" data-cdp-delete-cookie-form>
															<?php wp_nonce_field( 'cdp_cookies_admin_action', 'cdp_cookies_nonce' ); ?>
															<input type="hidden" name="cdp_cookies_action" value="delete_cookie">
															<input type="hidden" name="cookie_id" value="<?php echo esc_attr( $item['id'] ); ?>">
															<button type="submit" class="button cdp-cookies-icon-button" aria-label="<?php esc_attr_e( 'Delete cookie', 'asesor-cookies-para-la-ley-en-espana' ); ?>">
																<span class="dashicons dashicons-trash"></span>
															</button>
														</form>
													</div>
												<?php endif; ?>
											</td>
										</tr>
										<?php if ( ! $is_required_internal_cookie ) : ?>
											<tr class="cdp-cookies-edit-row" data-cdp-edit-cookie-row="<?php echo esc_attr( $item['id'] ); ?>" hidden>
												<td colspan="8">
													<form method="post" class="cdp-cookies-audit-edit-form">
														<?php wp_nonce_field( 'cdp_cookies_admin_action', 'cdp_cookies_nonce' ); ?>
														<input type="hidden" name="cdp_cookies_action" value="update_cookie">
														<input type="hidden" name="cookie_id" value="<?php echo esc_attr( $item['id'] ); ?>">
														<div class="cdp-cookies-audit-edit-grid">
													<label>
														<span><?php esc_html_e( 'Name', 'asesor-cookies-para-la-ley-en-espana' ); ?></span>
														<input type="text" name="cookie_name" value="<?php echo esc_attr( $item['name'] ); ?>" required>
													</label>
													<label>
														<span><?php esc_html_e( 'Provider', 'asesor-cookies-para-la-ley-en-espana' ); ?></span>
														<input type="text" name="cookie_provider" value="<?php echo esc_attr( $item['provider'] ); ?>">
													</label>
													<label>
														<span><?php esc_html_e( 'Service/domain pattern', 'asesor-cookies-para-la-ley-en-espana' ); ?></span>
														<input type="text" name="cookie_service_pattern" value="<?php echo esc_attr( $item['service_pattern'] ?? '' ); ?>">
													</label>
													<label>
														<span><?php esc_html_e( 'Category', 'asesor-cookies-para-la-ley-en-espana' ); ?></span>
														<select name="cookie_category">
															<?php foreach ( $categories as $key => $label ) : ?>
																<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $item['category'], $key ); ?>><?php echo esc_html( $label ); ?></option>
															<?php endforeach; ?>
														</select>
													</label>
													<label>
														<span><?php esc_html_e( 'Type', 'asesor-cookies-para-la-ley-en-espana' ); ?></span>
														<select name="cookie_type">
															<option value="own" <?php selected( $item['type'], 'own' ); ?>><?php esc_html_e( 'First-party', 'asesor-cookies-para-la-ley-en-espana' ); ?></option>
															<option value="third_party" <?php selected( $item['type'], 'third_party' ); ?>><?php esc_html_e( 'Third-party', 'asesor-cookies-para-la-ley-en-espana' ); ?></option>
														</select>
													</label>
													<label>
														<span><?php esc_html_e( 'Duration', 'asesor-cookies-para-la-ley-en-espana' ); ?></span>
														<input type="text" name="cookie_duration" value="<?php echo esc_attr( $item['duration'] ); ?>">
													</label>
													<label class="cdp-cookies-audit-field-full">
														<span><?php esc_html_e( 'Purpose', 'asesor-cookies-para-la-ley-en-espana' ); ?></span>
														<textarea name="cookie_description" rows="2"><?php echo esc_textarea( $item['description'] ); ?></textarea>
													</label>
												</div>
												<div class="cdp-cookies-audit-actions">
													<button type="submit" class="button button-primary"><?php esc_html_e( 'Save cookie', 'asesor-cookies-para-la-ley-en-espana' ); ?></button>
													<button type="button" class="button" data-cdp-cancel-edit-cookie="<?php echo esc_attr( $item['id'] ); ?>"><?php esc_html_e( 'Cancel', 'asesor-cookies-para-la-ley-en-espana' ); ?></button>
												</div>
											</form>
											</td>
										</tr>
									<?php endif; ?>
									<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				</div>

				<div id="cdp-cookies-audit" class="cdp-cookies-card">
					<div class="cdp-cookies-card-header">
						<h2><?php esc_html_e( 'Assisted cookie audit', 'asesor-cookies-para-la-ley-en-espana' ); ?></h2>
					</div>
					<p><?php esc_html_e( 'When audit mode is enabled, administrators can browse the public website while logged in and this plugin records visible cookie names that are not yet declared.', 'asesor-cookies-para-la-ley-en-espana' ); ?></p>
					<p class="description"><?php esc_html_e( 'The audit reads document.cookie on the current domain. Browser extensions normally cannot be read, but an extension or injected script could create cookies on this website and appear in the results.', 'asesor-cookies-para-la-ley-en-espana' ); ?></p>
					<p class="description"><?php esc_html_e( 'Third-party cookies created inside embeds such as YouTube may be stored on Google or YouTube domains and cannot be read from this website with document.cookie. Use the embed scanner and the cookie library to declare those services.', 'asesor-cookies-para-la-ley-en-espana' ); ?></p>
					<div class="cdp-cookies-audit-recorder">
						<div>
							<strong><?php esc_html_e( 'Audit recording', 'asesor-cookies-para-la-ley-en-espana' ); ?></strong>
							<p class="description"><?php esc_html_e( 'Close other tabs for this domain, start recording, browse the public site, accept the consents you want to test, and stop recording when finished.', 'asesor-cookies-para-la-ley-en-espana' ); ?></p>
							<?php if ( ! empty( $audit_recording['active'] ) ) : ?>
								<span class="cdp-cookies-status-badge cdp-cookies-status-badge--warning"><?php esc_html_e( 'Recording active', 'asesor-cookies-para-la-ley-en-espana' ); ?></span>
								<span class="description"><?php echo esc_html( sprintf( __( 'Started: %s', 'asesor-cookies-para-la-ley-en-espana' ), $audit_recording['started_at'] ) ); ?></span>
							<?php elseif ( ! empty( $audit_recording['stopped_at'] ) ) : ?>
								<span class="description"><?php echo esc_html( sprintf( __( 'Last recording stopped: %s', 'asesor-cookies-para-la-ley-en-espana' ), $audit_recording['stopped_at'] ) ); ?></span>
							<?php endif; ?>
						</div>
						<div class="cdp-cookies-audit-recorder__actions">
							<?php if ( empty( $audit_recording['active'] ) ) : ?>
								<form method="post">
									<?php wp_nonce_field( 'cdp_cookies_admin_action', 'cdp_cookies_nonce' ); ?>
									<input type="hidden" name="cdp_cookies_action" value="start_audit_recording">
									<button type="submit" class="button button-primary"><?php esc_html_e( 'Start recording', 'asesor-cookies-para-la-ley-en-espana' ); ?></button>
								</form>
							<?php else : ?>
								<form method="post">
									<?php wp_nonce_field( 'cdp_cookies_admin_action', 'cdp_cookies_nonce' ); ?>
									<input type="hidden" name="cdp_cookies_action" value="stop_audit_recording">
									<button type="submit" class="button"><?php esc_html_e( 'Stop recording', 'asesor-cookies-para-la-ley-en-espana' ); ?></button>
								</form>
							<?php endif; ?>
						</div>
					</div>
					<div class="cdp-cookies-table-wrap">
						<table class="cdp-cookies-table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Cookie', 'asesor-cookies-para-la-ley-en-espana' ); ?></th>
									<th><?php esc_html_e( 'Detections', 'asesor-cookies-para-la-ley-en-espana' ); ?></th>
									<th><?php esc_html_e( 'Last seen', 'asesor-cookies-para-la-ley-en-espana' ); ?></th>
									<th><?php esc_html_e( 'First page', 'asesor-cookies-para-la-ley-en-espana' ); ?></th>
									<th><?php esc_html_e( 'Actions', 'asesor-cookies-para-la-ley-en-espana' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php if ( empty( $audit_items ) ) : ?>
									<tr><td colspan="5"><?php esc_html_e( 'No audited cookies have been detected yet.', 'asesor-cookies-para-la-ley-en-espana' ); ?></td></tr>
								<?php endif; ?>
								<?php foreach ( $audit_items as $audit_item ) : ?>
									<?php
									$audit_cookie_name = $audit_item['name'] ?? '';
									$is_declared_cookie = in_array( $audit_cookie_name, $declared_cookie_names, true );
									$ignore_form_id = 'cdp-cookies-ignore-' . md5( $audit_cookie_name );
									$audit_urls = isset( $audit_item['urls'] ) && is_array( $audit_item['urls'] ) ? array_keys( $audit_item['urls'] ) : array();
									$first_audit_url = reset( $audit_urls );
									$audit_cookie_notice = self::get_audit_cookie_notice( $audit_cookie_name );
									?>
									<tr>
										<td>
											<code><?php echo esc_html( $audit_cookie_name ); ?></code>
											<?php if ( $is_declared_cookie ) : ?>
												<span class="cdp-cookies-status-badge"><?php esc_html_e( 'Already declared', 'asesor-cookies-para-la-ley-en-espana' ); ?></span>
											<?php endif; ?>
											<?php if ( $audit_cookie_notice ) : ?>
												<span class="cdp-cookies-status-badge cdp-cookies-status-badge--warning"><?php esc_html_e( 'Review', 'asesor-cookies-para-la-ley-en-espana' ); ?></span>
												<p class="description"><?php echo esc_html( $audit_cookie_notice ); ?></p>
											<?php endif; ?>
										</td>
										<td><?php echo esc_html( absint( $audit_item['count'] ?? 0 ) ); ?></td>
										<td><?php echo esc_html( $audit_item['last_seen'] ?? '' ); ?></td>
										<td>
											<?php if ( $first_audit_url ) : ?>
												<code class="cdp-cookies-url-code"><?php echo esc_html( $first_audit_url ); ?></code>
											<?php endif; ?>
										</td>
										<td class="cdp-cookies-audit-action-cell">
											<?php if ( $is_declared_cookie ) : ?>
												<span><?php esc_html_e( 'Already in inventory', 'asesor-cookies-para-la-ley-en-espana' ); ?></span>
											<?php endif; ?>
											<?php if ( ! $is_declared_cookie ) : ?>
												<form method="post" class="cdp-cookies-audit-actions">
													<?php wp_nonce_field( 'cdp_cookies_admin_action', 'cdp_cookies_nonce' ); ?>
													<input type="hidden" name="cdp_cookies_action" value="add_audit_cookie">
													<input type="hidden" name="audit_cookie_name" value="<?php echo esc_attr( $audit_cookie_name ); ?>">
													<button type="submit" class="button button-primary"><?php esc_html_e( 'Add to inventory', 'asesor-cookies-para-la-ley-en-espana' ); ?></button>
													<button type="submit" class="button" form="<?php echo esc_attr( $ignore_form_id ); ?>"><?php esc_html_e( 'Ignore', 'asesor-cookies-para-la-ley-en-espana' ); ?></button>
												</form>
											<?php endif; ?>
											<form id="<?php echo esc_attr( $ignore_form_id ); ?>" method="post" class="cdp-cookies-audit-ignore-form <?php echo $is_declared_cookie ? '' : 'cdp-cookies-screen-reader-form'; ?>">
												<?php wp_nonce_field( 'cdp_cookies_admin_action', 'cdp_cookies_nonce' ); ?>
												<input type="hidden" name="cdp_cookies_action" value="ignore_audit_cookie">
												<input type="hidden" name="audit_cookie_name" value="<?php echo esc_attr( $audit_cookie_name ); ?>">
												<?php if ( $is_declared_cookie ) : ?>
													<button type="submit" class="button"><?php esc_html_e( 'Ignore', 'asesor-cookies-para-la-ley-en-espana' ); ?></button>
												<?php endif; ?>
											</form>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
					<form method="post" class="cdp-cookies-compact-form-actions">
						<?php wp_nonce_field( 'cdp_cookies_admin_action', 'cdp_cookies_nonce' ); ?>
						<input type="hidden" name="cdp_cookies_action" value="clear_audit">
						<button type="submit" class="button"><?php esc_html_e( 'Clear audit results', 'asesor-cookies-para-la-ley-en-espana' ); ?></button>
					</form>
					<h3><?php esc_html_e( 'External services detected during recording', 'asesor-cookies-para-la-ley-en-espana' ); ?></h3>
					<p class="description"><?php esc_html_e( 'These are external domains loaded by the page. They do not prove which cookies were installed, but they help identify services that may need consent and inventory declaration.', 'asesor-cookies-para-la-ley-en-espana' ); ?></p>
					<div class="cdp-cookies-table-wrap">
						<table class="cdp-cookies-table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Service', 'asesor-cookies-para-la-ley-en-espana' ); ?></th>
									<th><?php esc_html_e( 'Domain', 'asesor-cookies-para-la-ley-en-espana' ); ?></th>
									<th><?php esc_html_e( 'Resource', 'asesor-cookies-para-la-ley-en-espana' ); ?></th>
									<th><?php esc_html_e( 'Type', 'asesor-cookies-para-la-ley-en-espana' ); ?></th>
									<th><?php esc_html_e( 'Detections', 'asesor-cookies-para-la-ley-en-espana' ); ?></th>
									<th><?php esc_html_e( 'First page', 'asesor-cookies-para-la-ley-en-espana' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php if ( empty( $audit_external_resources ) ) : ?>
									<tr><td colspan="6"><?php esc_html_e( 'No external services have been detected during the current recording.', 'asesor-cookies-para-la-ley-en-espana' ); ?></td></tr>
								<?php endif; ?>
								<?php foreach ( $audit_external_resources as $resource ) : ?>
									<?php
									$resource_urls = isset( $resource['urls'] ) && is_array( $resource['urls'] ) ? array_keys( $resource['urls'] ) : array();
									$first_resource_page = reset( $resource_urls );
									$is_declared_service = self::service_pattern_matches_resource( $resource, $declared_service_patterns );
									?>
									<tr>
										<td>
											<?php echo esc_html( $resource['service'] ?? '' ); ?>
											<?php if ( $is_declared_service ) : ?>
												<span class="cdp-cookies-status-badge"><?php esc_html_e( 'Already declared', 'asesor-cookies-para-la-ley-en-espana' ); ?></span>
											<?php endif; ?>
										</td>
										<td><code><?php echo esc_html( $resource['host'] ?? '' ); ?></code></td>
										<td><code class="cdp-cookies-url-code"><?php echo esc_html( $resource['url'] ?? '' ); ?></code></td>
										<td><?php echo esc_html( $resource['type'] ?? '' ); ?></td>
										<td><?php echo esc_html( absint( $resource['count'] ?? 0 ) ); ?></td>
										<td>
											<?php if ( $first_resource_page ) : ?>
												<code class="cdp-cookies-url-code"><?php echo esc_html( $first_resource_page ); ?></code>
											<?php endif; ?>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
					<h3><?php esc_html_e( 'Ignored cookies', 'asesor-cookies-para-la-ley-en-espana' ); ?></h3>
					<div class="cdp-cookies-table-wrap">
						<table class="cdp-cookies-table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Cookie', 'asesor-cookies-para-la-ley-en-espana' ); ?></th>
									<th><?php esc_html_e( 'Ignored on', 'asesor-cookies-para-la-ley-en-espana' ); ?></th>
									<th><?php esc_html_e( 'Last seen', 'asesor-cookies-para-la-ley-en-espana' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php if ( empty( $ignored_audit_items ) ) : ?>
									<tr><td colspan="3"><?php esc_html_e( 'There are no ignored cookies.', 'asesor-cookies-para-la-ley-en-espana' ); ?></td></tr>
								<?php endif; ?>
								<?php foreach ( $ignored_audit_items as $ignored_item ) : ?>
									<tr>
										<td><code><?php echo esc_html( $ignored_item['name'] ?? '' ); ?></code></td>
										<td><?php echo esc_html( $ignored_item['ignored_at'] ?? '' ); ?></td>
										<td><?php echo esc_html( $ignored_item['last_seen'] ?? '' ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				</div>

				<div id="cdp-cookies-embeds" class="cdp-cookies-card">
					<div class="cdp-cookies-card-header">
						<h2><?php esc_html_e( 'Protected embeds', 'asesor-cookies-para-la-ley-en-espana' ); ?></h2>
					</div>
					<p><?php esc_html_e( 'Use the wrapper shortcode around external embeds that may install cookies or load third-party resources.', 'asesor-cookies-para-la-ley-en-espana' ); ?></p>
					<code class="cdp-cookies-shortcode-example">[cdp_consent category="personalization" service="YouTube"]...[/cdp_consent]</code>
					<p class="description"><?php esc_html_e( 'This tool helps locate possible external embeds and check whether they are wrapped with the consent shortcode. It does not guarantee detecting all content generated by themes, builders, plugins, or custom code. This tool will help you detect embeds on your website, but it must never replace manual supervision by the website owner or by a professional specialized in data protection.', 'asesor-cookies-para-la-ley-en-espana' ); ?></p>

					<form method="post" class="cdp-cookies-scan-action-row">
						<?php wp_nonce_field( 'cdp_cookies_admin_action', 'cdp_cookies_nonce' ); ?>
						<input type="hidden" name="cdp_cookies_action" value="scan_embeds">
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Scan embeds', 'asesor-cookies-para-la-ley-en-espana' ); ?></button>
						<?php if ( $embed_scan_date ) : ?>
							<span class="cdp-cookies-scan-date"><?php echo esc_html( sprintf( __( 'Last scan: %s', 'asesor-cookies-para-la-ley-en-espana' ), $embed_scan_date ) ); ?></span>
						<?php endif; ?>
					</form>

					<div class="cdp-cookies-table-wrap">
						<table class="cdp-cookies-table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Content', 'asesor-cookies-para-la-ley-en-espana' ); ?></th>
									<th><?php esc_html_e( 'Service', 'asesor-cookies-para-la-ley-en-espana' ); ?></th>
									<th><?php esc_html_e( 'Signal', 'asesor-cookies-para-la-ley-en-espana' ); ?></th>
									<th><?php esc_html_e( 'Status', 'asesor-cookies-para-la-ley-en-espana' ); ?></th>
									<th><?php esc_html_e( 'Snippet', 'asesor-cookies-para-la-ley-en-espana' ); ?></th>
									<th><?php esc_html_e( 'Actions', 'asesor-cookies-para-la-ley-en-espana' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php if ( empty( $embed_scan_results ) ) : ?>
									<tr><td colspan="6"><?php esc_html_e( 'No embed scan results yet.', 'asesor-cookies-para-la-ley-en-espana' ); ?></td></tr>
								<?php endif; ?>
								<?php foreach ( $embed_scan_results as $result ) : ?>
									<tr>
										<td>
											<strong><?php echo esc_html( $result['post_title'] ?? '' ); ?></strong>
											<div class="description"><?php echo esc_html( $result['post_type'] ?? '' ); ?></div>
										</td>
										<td><?php echo esc_html( $result['service'] ?? '' ); ?></td>
										<td><?php echo esc_html( $result['type'] ?? '' ); ?></td>
										<td>
											<span class="<?php echo esc_attr( 'cdp-cookies-scan-status cdp-cookies-scan-status--' . self::get_embed_scan_status_class( $result['status'] ?? 'review' ) ); ?>">
												<?php echo esc_html( self::get_embed_scan_status_label( $result['status'] ?? 'review' ) ); ?>
											</span>
										</td>
										<td><code class="cdp-cookies-url-code"><?php echo esc_html( $result['snippet'] ?? '' ); ?></code></td>
										<td class="cdp-cookies-audit-action-cell">
											<?php if ( ! empty( $result['edit_url'] ) ) : ?>
												<a class="button" href="<?php echo esc_url( $result['edit_url'] ); ?>"><?php esc_html_e( 'Edit', 'asesor-cookies-para-la-ley-en-espana' ); ?></a>
											<?php endif; ?>
											<?php if ( ! empty( $result['view_url'] ) ) : ?>
												<a class="button" href="<?php echo esc_url( $result['view_url'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View', 'asesor-cookies-para-la-ley-en-espana' ); ?></a>
											<?php endif; ?>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				</div>

				<div id="cdp-cookies-settings" class="cdp-cookies-card">
					<h2><?php esc_html_e( 'Settings', 'asesor-cookies-para-la-ley-en-espana' ); ?></h2>
					<form method="post" class="cdp-cookies-form">
						<?php wp_nonce_field( 'cdp_cookies_admin_action', 'cdp_cookies_nonce' ); ?>
						<input type="hidden" name="cdp_cookies_action" value="save_settings">
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><?php esc_html_e( 'Menu location', 'asesor-cookies-para-la-ley-en-espana' ); ?></th>
								<td>
									<label>
										<input type="radio" name="menu_location" value="top" <?php checked( $menu_location, 'top' ); ?>>
										<?php esc_html_e( 'Show Cookie Advisor in the main WordPress menu', 'asesor-cookies-para-la-ley-en-espana' ); ?>
									</label>
									<br>
									<label>
										<input type="radio" name="menu_location" value="tools" <?php checked( $menu_location, 'tools' ); ?>>
										<?php esc_html_e( 'Show Cookie Advisor as a submenu under Tools', 'asesor-cookies-para-la-ley-en-espana' ); ?>
									</label>
									<p class="description"><?php esc_html_e( 'The change will apply after reloading the admin.', 'asesor-cookies-para-la-ley-en-espana' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Assisted audit mode', 'asesor-cookies-para-la-ley-en-espana' ); ?></th>
								<td>
									<label>
										<input type="hidden" name="audit_enabled" value="0">
										<input type="checkbox" name="audit_enabled" value="1" <?php checked( self::is_audit_enabled() ); ?>>
										<?php esc_html_e( 'Record visible cookie names while administrators browse the public website.', 'asesor-cookies-para-la-ley-en-espana' ); ?>
									</label>
									<p class="description"><?php esc_html_e( 'This mode does not detect HttpOnly cookies or guarantee legal classification. It is a semi-automatic tool to make cookie detection easier.', 'asesor-cookies-para-la-ley-en-espana' ); ?></p>
								</td>
							</tr>
						</table>
						<?php submit_button( __( 'Save settings', 'asesor-cookies-para-la-ley-en-espana' ) ); ?>
					</form>
				</div>
			</div>
		</div>
		<?php
	}

	public static function get_policy_page_content() {
		return '<h1>' . esc_html__( 'Cookie policy', 'asesor-cookies-para-la-ley-en-espana' ) . '</h1>
<p>' . esc_html__( 'On this website we use first-party and third-party cookies to ensure its operation, measure site usage and, where applicable, show content or communications based on the preferences accepted by the user.', 'asesor-cookies-para-la-ley-en-espana' ) . '</p>
<h2>' . esc_html__( 'Cookies used', 'asesor-cookies-para-la-ley-en-espana' ) . '</h2>
<p>' . esc_html__( 'The following table shows the cookies we use on this website.', 'asesor-cookies-para-la-ley-en-espana' ) . '</p>
[cdp_cookies_policy_table]
<h2>' . esc_html__( 'Consent management', 'asesor-cookies-para-la-ley-en-espana' ) . '</h2>
<p>' . esc_html__( 'You can accept, reject, or configure cookies from the banner shown when accessing the site. You can also delete or block cookies from your browser settings.', 'asesor-cookies-para-la-ley-en-espana' ) . '</p>';
	}

	public static function get_presets() {
		return array(
			'ga4' => array(
				'label'           => 'Google Analytics 4',
				'service_pattern' => 'google-analytics.com, googletagmanager.com',
				'cookies' => array(
					array( 'name' => '_ga', 'provider' => 'Google', 'category' => 'analytics', 'type' => 'third_party', 'duration' => __( '2 years', 'asesor-cookies-para-la-ley-en-espana' ), 'description' => __( 'Distinguishes users to compile site usage statistics.', 'asesor-cookies-para-la-ley-en-espana' ) ),
					array( 'name' => '_ga_*', 'provider' => 'Google', 'category' => 'analytics', 'type' => 'third_party', 'duration' => __( '2 years', 'asesor-cookies-para-la-ley-en-espana' ), 'description' => __( 'Maintains session status and Google Analytics 4 metrics.', 'asesor-cookies-para-la-ley-en-espana' ) ),
				),
			),
			'gtm' => array(
				'label'           => 'Google Tag Manager',
				'service_pattern' => 'googletagmanager.com, googleadservices.com',
				'cookies' => array(
					array( 'name' => '_gcl_*', 'provider' => 'Google', 'category' => 'marketing', 'type' => 'third_party', 'duration' => __( '90 days', 'asesor-cookies-para-la-ley-en-espana' ), 'description' => __( 'Measures conversions and advertising attribution when Google tags are used.', 'asesor-cookies-para-la-ley-en-espana' ) ),
				),
			),
			'meta_pixel' => array(
				'label'           => 'Meta Pixel',
				'service_pattern' => 'facebook.com, connect.facebook.net',
				'cookies' => array(
					array( 'name' => '_fbp', 'provider' => 'Meta', 'category' => 'marketing', 'type' => 'third_party', 'duration' => __( '3 months', 'asesor-cookies-para-la-ley-en-espana' ), 'description' => __( 'Identifies browsers for advertising measurement and remarketing.', 'asesor-cookies-para-la-ley-en-espana' ) ),
					array( 'name' => '_fbc', 'provider' => 'Meta', 'category' => 'marketing', 'type' => 'third_party', 'duration' => __( '3 months', 'asesor-cookies-para-la-ley-en-espana' ), 'description' => __( 'Stores information from Facebook ad clicks.', 'asesor-cookies-para-la-ley-en-espana' ) ),
				),
			),
			'clarity' => array(
				'label'           => 'Microsoft Clarity',
				'service_pattern' => 'clarity.ms',
				'cookies' => array(
					array( 'name' => '_clck', 'provider' => 'Microsoft', 'category' => 'analytics', 'type' => 'third_party', 'duration' => __( '1 year', 'asesor-cookies-para-la-ley-en-espana' ), 'description' => __( 'Stores a Clarity user identifier for behavior analysis.', 'asesor-cookies-para-la-ley-en-espana' ) ),
					array( 'name' => '_clsk', 'provider' => 'Microsoft', 'category' => 'analytics', 'type' => 'third_party', 'duration' => __( '1 day', 'asesor-cookies-para-la-ley-en-espana' ), 'description' => __( 'Groups several page views into the same user session.', 'asesor-cookies-para-la-ley-en-espana' ) ),
				),
			),
			'hotjar' => array(
				'label'           => 'Hotjar',
				'service_pattern' => 'hotjar.com, hotjar.io',
				'cookies' => array(
					array( 'name' => '_hjSessionUser_*', 'provider' => 'Hotjar', 'category' => 'analytics', 'type' => 'third_party', 'duration' => __( '1 year', 'asesor-cookies-para-la-ley-en-espana' ), 'description' => __( 'Keeps a user identifier for experience analytics.', 'asesor-cookies-para-la-ley-en-espana' ) ),
					array( 'name' => '_hjSession_*', 'provider' => 'Hotjar', 'category' => 'analytics', 'type' => 'third_party', 'duration' => __( '30 minutes', 'asesor-cookies-para-la-ley-en-espana' ), 'description' => __( 'Keeps session data for navigation analysis.', 'asesor-cookies-para-la-ley-en-espana' ) ),
				),
			),
			'youtube' => array(
				'label'           => 'YouTube',
				'service_pattern' => 'youtube.com, youtu.be, googlevideo.com',
				'cookies' => array(
					array( 'name' => 'YSC', 'provider' => 'Google/YouTube', 'category' => 'marketing', 'type' => 'third_party', 'duration' => __( 'Session', 'asesor-cookies-para-la-ley-en-espana' ), 'description' => __( 'Records playback of embedded videos.', 'asesor-cookies-para-la-ley-en-espana' ) ),
					array( 'name' => 'VISITOR_INFO1_LIVE', 'provider' => 'Google/YouTube', 'category' => 'marketing', 'type' => 'third_party', 'duration' => __( '6 months', 'asesor-cookies-para-la-ley-en-espana' ), 'description' => __( 'Estimates bandwidth and playback preferences.', 'asesor-cookies-para-la-ley-en-espana' ) ),
				),
			),
			'vimeo' => array(
				'label'           => 'Vimeo',
				'service_pattern' => 'vimeo.com, player.vimeo.com',
				'cookies' => array(
					array( 'name' => 'vuid', 'provider' => 'Vimeo', 'category' => 'marketing', 'type' => 'third_party', 'duration' => __( '2 years', 'asesor-cookies-para-la-ley-en-espana' ), 'description' => __( 'Stores a Vimeo user identifier for embedded video playback and analytics.', 'asesor-cookies-para-la-ley-en-espana' ) ),
				),
			),
			'google_maps' => array(
				'label'           => 'Google Maps',
				'service_pattern' => 'google.com/maps, maps.google.com, maps.googleapis.com',
				'cookies' => array(
					array( 'name' => 'NID', 'provider' => 'Google', 'category' => 'marketing', 'type' => 'third_party', 'duration' => __( '6 months', 'asesor-cookies-para-la-ley-en-espana' ), 'description' => __( 'Stores preferences and information linked to Google services.', 'asesor-cookies-para-la-ley-en-espana' ) ),
				),
			),
			'recaptcha' => array(
				'label'           => 'Google reCAPTCHA',
				'service_pattern' => 'google.com/recaptcha, gstatic.com/recaptcha',
				'cookies' => array(
					array( 'name' => '_GRECAPTCHA', 'provider' => 'Google', 'category' => 'necessary', 'type' => 'third_party', 'duration' => __( '6 months', 'asesor-cookies-para-la-ley-en-espana' ), 'description' => __( 'Helps protect forms against spam and automated abuse.', 'asesor-cookies-para-la-ley-en-espana' ) ),
				),
			),
			'instagram' => array(
				'label'           => 'Instagram',
				'service_pattern' => 'instagram.com',
				'cookies' => array(
					array( 'name' => 'instagram.com', 'provider' => 'Meta/Instagram', 'category' => 'marketing', 'type' => 'third_party', 'duration' => __( 'Session', 'asesor-cookies-para-la-ley-en-espana' ), 'description' => __( 'Loads Instagram embedded content and may support social interaction or advertising measurement.', 'asesor-cookies-para-la-ley-en-espana' ) ),
				),
			),
			'tiktok' => array(
				'label'           => 'TikTok',
				'service_pattern' => 'tiktok.com, tiktokcdn.com',
				'cookies' => array(
					array( 'name' => '_ttp', 'provider' => 'TikTok', 'category' => 'marketing', 'type' => 'third_party', 'duration' => __( '13 months', 'asesor-cookies-para-la-ley-en-espana' ), 'description' => __( 'Measures advertising performance and user interaction with TikTok content.', 'asesor-cookies-para-la-ley-en-espana' ) ),
					array( 'name' => 'ttclid', 'provider' => 'TikTok', 'category' => 'marketing', 'type' => 'third_party', 'duration' => __( '13 months', 'asesor-cookies-para-la-ley-en-espana' ), 'description' => __( 'Stores TikTok advertising click information for attribution.', 'asesor-cookies-para-la-ley-en-espana' ) ),
				),
			),
			'pinterest' => array(
				'label'           => 'Pinterest',
				'service_pattern' => 'pinterest.com, pinimg.com',
				'cookies' => array(
					array( 'name' => '_pin_unauth', 'provider' => 'Pinterest', 'category' => 'marketing', 'type' => 'third_party', 'duration' => __( '1 year', 'asesor-cookies-para-la-ley-en-espana' ), 'description' => __( 'Groups actions for Pinterest advertising and analytics when the visitor is not logged in.', 'asesor-cookies-para-la-ley-en-espana' ) ),
					array( 'name' => '_pinterest_ct_ua', 'provider' => 'Pinterest', 'category' => 'marketing', 'type' => 'third_party', 'duration' => __( '1 year', 'asesor-cookies-para-la-ley-en-espana' ), 'description' => __( 'Supports Pinterest conversion tracking and advertising measurement.', 'asesor-cookies-para-la-ley-en-espana' ) ),
				),
			),
			'x_twitter' => array(
				'label'           => 'X/Twitter',
				'service_pattern' => 'twitter.com, x.com',
				'cookies' => array(
					array( 'name' => 'personalization_id', 'provider' => 'X/Twitter', 'category' => 'marketing', 'type' => 'third_party', 'duration' => __( '2 years', 'asesor-cookies-para-la-ley-en-espana' ), 'description' => __( 'Supports embedded timelines, social interaction, and advertising personalization.', 'asesor-cookies-para-la-ley-en-espana' ) ),
				),
			),
			'linkedin' => array(
				'label'           => 'LinkedIn',
				'service_pattern' => 'linkedin.com, licdn.com',
				'cookies' => array(
					array( 'name' => 'bcookie', 'provider' => 'LinkedIn', 'category' => 'marketing', 'type' => 'third_party', 'duration' => __( '1 year', 'asesor-cookies-para-la-ley-en-espana' ), 'description' => __( 'Identifies browsers for LinkedIn embedded content and advertising measurement.', 'asesor-cookies-para-la-ley-en-espana' ) ),
					array( 'name' => 'li_sugr', 'provider' => 'LinkedIn', 'category' => 'marketing', 'type' => 'third_party', 'duration' => __( '3 months', 'asesor-cookies-para-la-ley-en-espana' ), 'description' => __( 'Supports LinkedIn advertising and matching of browser identifiers.', 'asesor-cookies-para-la-ley-en-espana' ) ),
				),
			),
			'calendly' => array(
				'label'           => 'Calendly',
				'service_pattern' => 'calendly.com',
				'cookies' => array(
					array( 'name' => 'calendly.com', 'provider' => 'Calendly', 'category' => 'personalization', 'type' => 'third_party', 'duration' => __( 'Session', 'asesor-cookies-para-la-ley-en-espana' ), 'description' => __( 'Loads Calendly scheduling widgets and stores interaction state for appointments.', 'asesor-cookies-para-la-ley-en-espana' ) ),
				),
			),
			'gravatar' => array(
				'label'           => 'Gravatar',
				'service_pattern' => 'gravatar.com',
				'cookies' => array(
					array( 'name' => 'gravatar.com', 'provider' => 'Automattic/Gravatar', 'category' => 'personalization', 'type' => 'third_party', 'duration' => __( 'Session', 'asesor-cookies-para-la-ley-en-espana' ), 'description' => __( 'Loads user avatar images from Gravatar.', 'asesor-cookies-para-la-ley-en-espana' ) ),
				),
			),
		);
	}

	public static function get_preset_cookie_options() {
		$options = array();

		foreach ( self::get_presets() as $preset_key => $preset ) {
			foreach ( $preset['cookies'] as $index => $cookie ) {
				$key = $preset_key . '_' . $index;
				$cookie['label'] = $preset['label'] . ' - ' . $cookie['name'];
				$cookie['service'] = $preset['label'];
				$cookie['service_pattern'] = $cookie['service_pattern'] ?? ( $preset['service_pattern'] ?? '' );
				$options[ $key ] = $cookie;
			}
		}

		return $options;
	}

	private static function translate_known_cookie_text( $text ) {
		$known = array(
			'2 years' => true,
			'90 days' => true,
			'3 months' => true,
			'1 year' => true,
			'1 day' => true,
			'30 minutes' => true,
			'Session' => true,
			'6 months' => true,
			'13 months' => true,
			'Distinguishes users to compile site usage statistics.' => true,
			'Maintains session status and Google Analytics 4 metrics.' => true,
			'Measures conversions and advertising attribution when Google tags are used.' => true,
			'Identifies browsers for advertising measurement and remarketing.' => true,
			'Stores information from Facebook ad clicks.' => true,
			'Stores a Clarity user identifier for behavior analysis.' => true,
			'Groups several page views into the same user session.' => true,
			'Keeps a user identifier for experience analytics.' => true,
			'Keeps session data for navigation analysis.' => true,
			'Records playback of embedded videos.' => true,
			'Estimates bandwidth and playback preferences.' => true,
			'Stores a Vimeo user identifier for embedded video playback and analytics.' => true,
			'Stores preferences and information linked to Google services.' => true,
			'Helps protect forms against spam and automated abuse.' => true,
			'Loads Instagram embedded content and may support social interaction or advertising measurement.' => true,
			'Measures advertising performance and user interaction with TikTok content.' => true,
			'Stores TikTok advertising click information for attribution.' => true,
			'Groups actions for Pinterest advertising and analytics when the visitor is not logged in.' => true,
			'Supports Pinterest conversion tracking and advertising measurement.' => true,
			'Supports embedded timelines, social interaction, and advertising personalization.' => true,
			'Identifies browsers for LinkedIn embedded content and advertising measurement.' => true,
			'Supports LinkedIn advertising and matching of browser identifiers.' => true,
			'Loads Calendly scheduling widgets and stores interaction state for appointments.' => true,
			'Loads user avatar images from Gravatar.' => true,
			'Stores the visitor cookie consent preferences.' => true,
		);

		return isset( $known[ $text ] ) ? __( $text, 'asesor-cookies-para-la-ley-en-espana' ) : $text;
	}
}

class cdp_cookies_pagina {
	public $titulo;
	public $html;
	public $ya_existia;
	public $url;
	public $ok;
	public $mensaje;

	public function crear() {
		if ( ! $this->titulo ) {
			$this->ok = false;
			$this->mensaje = 'Falta el título de la página';
			return false;
		}

		$existing = get_page_by_title( $this->titulo );
		if ( $existing ) {
			if ( 'trash' === $existing->post_status ) {
				$this->ok = false;
				$this->mensaje = 'La página de política de cookies está en la papelera, debe eliminarla primero';
				return false;
			}

			$this->ok = true;
			$this->ya_existia = true;
			$this->url = get_permalink( $existing );
			return true;
		}

		$id = wp_insert_post(
			array(
				'post_title'     => $this->titulo,
				'post_content'   => $this->html,
				'post_status'    => 'publish',
				'post_type'      => 'page',
				'comment_status' => 'closed',
				'ping_status'    => 'closed',
			),
			true
		);

		if ( ! $id || is_wp_error( $id ) ) {
			$this->ok = false;
			$this->mensaje = is_wp_error( $id ) ? $id->get_error_message() : 'No es posible crear la página';
			return false;
		}

		$this->ok = true;
		$this->ya_existia = false;
		$this->url = get_permalink( $id );
		return true;
	}
}
