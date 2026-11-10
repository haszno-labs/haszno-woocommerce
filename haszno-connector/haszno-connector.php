<?php
/**
 * Plugin Name:       Haszno Összekötő
 * Plugin URI:        https://haszno.hu
 * Description:       Összeköti a WooCommerce webáruházat a Haszno dropshipping platformmal. Automatikusan létrehozza a szükséges REST API-kulcsokat, és biztonságosan elküldi azokat a Haszno API-nak.
 * Version:           1.0.0
 * Author:            Haszno
 * Author URI:        https://haszno.hu
 * License:           GPL-2.0+
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       haszno-connector
 * Domain Path:       /languages
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * WC requires at least: 7.0
 * WC tested up to:   9.9
 *
 * @package HasznoConnector
 */

defined( 'ABSPATH' ) || exit;

define( 'HASZNO_CONNECTOR_VERSION', '1.0.0' );
define( 'HASZNO_CONNECTOR_FILE', __FILE__ );
define( 'HASZNO_CONNECTOR_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Ellenőrzi, hogy a WooCommerce aktív-e.
 * Ha nem, deaktiválja a bővítményt és hibaüzenetet jelenít meg.
 *
 * @return bool
 */
function haszno_connector_check_woocommerce() {
	if ( class_exists( 'WooCommerce' ) ) {
		return true;
	}

	add_action(
		'admin_notices',
		function () {
			echo '<div class="error notice"><p>';
			printf(
				/* translators: %s = link to plugins page */
				esc_html__( 'A Haszno Összekötő bővítmény nem működhet aktív WooCommerce nélkül. Kérjük, %s telepítsd és aktiváld a WooCommerce-t.', 'haszno-connector' ),
				'<a href="' . esc_url( admin_url( 'plugin-install.php?tab=search&s=woocommerce' ) ) . '">'
				. esc_html__( 'előbb', 'haszno-connector' ) . '</a>'
			);
			echo '</p></div>';
		}
	);

	return false;
}

/**
 * Inicializálja a bővítményt — csak ha a WooCommerce aktív.
 */
function haszno_connector_init() {
	if ( ! haszno_connector_check_woocommerce() ) {
		return;
	}

	require_once HASZNO_CONNECTOR_DIR . 'includes/class-haszno-api-client.php';
	require_once HASZNO_CONNECTOR_DIR . 'includes/class-haszno-wc-integration.php';

	// WooCommerce Integrations szekciójában regisztrálás
	add_filter(
		'woocommerce_integrations',
		function ( $integrations ) {
			$integrations[] = 'Haszno_WC_Integration';
			return $integrations;
		}
	);

	// Szöveges tartomány betöltése
	load_plugin_textdomain(
		'haszno-connector',
		false,
		dirname( plugin_basename( HASZNO_CONNECTOR_FILE ) ) . '/languages'
	);
}
add_action( 'plugins_loaded', 'haszno_connector_init', 11 );

/**
 * Deaktiváláskori teendők (opciók megtartva, csak a kulcs törölhető kézzel).
 */
function haszno_connector_deactivate() {
	// Szándékosan üres: az opciók és a WC API-kulcs az eltávolítás (uninstall.php) során törlődnek.
}
register_deactivation_hook( HASZNO_CONNECTOR_FILE, 'haszno_connector_deactivate' );
