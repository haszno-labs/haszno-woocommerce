<?php
/**
 * Eltávolítás (Uninstall) kezelő.
 *
 * WordPress akkor hívja, ha a felhasználó a bővítménykezelőben
 * a "Törlés" gombra kattint (nem csak deaktiválja a bővítményt).
 *
 * Törli:
 *  - Az összes haszno_connector_* WordPress-opciót
 *  - A bővítmény által létrehozott WooCommerce REST API-kulcsot
 *
 * @package HasznoConnector
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// ---- WooCommerce API-kulcs törlése ----------------------------------------

$key_id = (int) get_option( 'haszno_connector_wc_key_id', 0 );

if ( $key_id > 0 ) {
	global $wpdb;
	$wpdb->delete(
		$wpdb->prefix . 'woocommerce_api_keys',
		array( 'key_id' => $key_id ),
		array( '%d' )
	);
}

// ---- Összes bővítmény-opció törlése ----------------------------------------

$options = array(
	'haszno_connector_api_url',
	'haszno_connector_status',
	'haszno_connector_store_id',
	'haszno_connector_store_name',
	'haszno_connector_error',
	'haszno_connector_wc_key_id',
);

foreach ( $options as $option ) {
	delete_option( $option );
}

// Átmeneti (transient) értékek törlése
delete_transient( 'haszno_connect_success' );
delete_transient( 'haszno_disconnect_success' );

// Multisite esetén minden blog opciójának törlése
if ( is_multisite() ) {
	$sites = get_sites( array( 'fields' => 'ids' ) );
	foreach ( $sites as $site_id ) {
		switch_to_blog( $site_id );
		foreach ( $options as $option ) {
			delete_option( $option );
		}
		delete_transient( 'haszno_connect_success' );
		delete_transient( 'haszno_disconnect_success' );
		restore_current_blog();
	}
}
