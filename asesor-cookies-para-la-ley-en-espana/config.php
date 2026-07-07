<?php

	// Por seguridad
	if ( ! defined( 'ABSPATH' ) )
		exit;

	//
	define( "CDP_COOKIES_DIR_RAIZ", dirname( __FILE__ ) . '/' );
	define( "CDP_COOKIES_URL_RAIZ", plugins_url( '/', __FILE__ ) );
	define( 'CDP_COOKIES_VERSION', '1.0.42' );
	define( 'CDP_COOKIES_BASENAME', plugin_basename( CDP_COOKIES_DIR_RAIZ . 'plugin.php' ) );
	define( "CDP_COOKIES_DIR_HTML", CDP_COOKIES_DIR_RAIZ . 'html/' );
	define( 'CDP_COOKIES_URL_HTML', CDP_COOKIES_URL_RAIZ . 'html/' );
	define( "CDP_COOKIES_LOG_ACTIVO", 0 );
	define( "CDP_COOKIES_ARCHIVO_LOG", CDP_COOKIES_DIR_RAIZ . "log/cdp_cookies.log" );
	     
