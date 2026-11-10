<?php
/**
 * Haszno API kliens.
 *
 * A Haszno REST API-val kommunikál a bolt-bekötési folyamat során.
 * A kapcsolódási kódot az x-api-key fejlécben küldi el (egyszer használatos token).
 * A WooCommerce hitelesítési adatokat SOSEM tárolja — csak elküldi a Haszno API-nak,
 * amely titkosítva menti azokat.
 *
 * @package HasznoConnector
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Haszno_API_Client osztály.
 */
class Haszno_API_Client {

	/**
	 * Haszno API alap URL-je (pl. https://api.haszno.hu).
	 *
	 * @var string
	 */
	private $api_url;

	/**
	 * Kapcsolódási kód (x-api-key fejlécként küldve).
	 *
	 * @var string
	 */
	private $connect_code;

	/**
	 * Konstruktor.
	 *
	 * @param string $api_url      Haszno API alap URL.
	 * @param string $connect_code Egyszer használatos kapcsolódási kód.
	 */
	public function __construct( $api_url, $connect_code ) {
		$this->api_url      = rtrim( $api_url, '/' );
		$this->connect_code = $connect_code;
	}

	// -------------------------------------------------------------------------
	// Publikus metódusok
	// -------------------------------------------------------------------------

	/**
	 * 1. lépés: Bolt-kapcsolat létrehozása.
	 *
	 * @param string $name     Bolt neve (pl. get_bloginfo('name')).
	 * @return array|WP_Error  StoreConnection tömb vagy WP_Error.
	 */
	public function create_store( $name ) {
		return $this->request(
			'POST',
			'/api/connect/stores',
			array(
				'platform' => 'woocommerce',
				'name'     => $name,
			)
		);
	}

	/**
	 * 2. lépés: Domain beállítása.
	 *
	 * @param int    $store_id  Haszno bolt-kapcsolat ID.
	 * @param string $domain    Nyilvános bolt domain (pl. mybolt.hu).
	 * @return array|WP_Error
	 */
	public function set_domain( $store_id, $domain ) {
		return $this->request(
			'PUT',
			"/api/connect/stores/{$store_id}/domain",
			array( 'domain' => $domain )
		);
	}

	/**
	 * 3. lépés: Hitelesítési adatok (WC API-kulcsok) küldése.
	 * Az adatok SOSEM kerülnek mentésre a WordPress-ben.
	 *
	 * @param int    $store_id        Haszno bolt-kapcsolat ID.
	 * @param string $consumer_key    WooCommerce Consumer Key (ck_…).
	 * @param string $consumer_secret WooCommerce Consumer Secret (cs_…).
	 * @return array|WP_Error
	 */
	public function set_credentials( $store_id, $consumer_key, $consumer_secret ) {
		return $this->request(
			'PUT',
			"/api/connect/stores/{$store_id}/credentials",
			array(
				'credentials' => array(
					'consumerKey'    => $consumer_key,
					'consumerSecret' => $consumer_secret,
				),
			)
		);
	}

	/**
	 * 4. lépés: Kapcsolat tesztelése.
	 *
	 * @param int $store_id Haszno bolt-kapcsolat ID.
	 * @return array|WP_Error TestConnectionResult tömb vagy WP_Error.
	 */
	public function test_connection( $store_id ) {
		return $this->request( 'POST', "/api/connect/stores/{$store_id}/test" );
	}

	/**
	 * Bolt-kapcsolat adatainak lekérése.
	 *
	 * @param int $store_id Haszno bolt-kapcsolat ID.
	 * @return array|WP_Error
	 */
	public function get_store( $store_id ) {
		return $this->request( 'GET', "/api/connect/stores/{$store_id}" );
	}

	// -------------------------------------------------------------------------
	// Privát segédmetódus
	// -------------------------------------------------------------------------

	/**
	 * HTTP kérés a Haszno API felé.
	 *
	 * @param string     $method HTTP metódus (GET|POST|PUT).
	 * @param string     $path   API útvonal (perjellel kezdődik).
	 * @param array|null $body   JSON kérés törzse (opcionális).
	 * @return array|WP_Error   Dekódolt válasz tömb vagy WP_Error.
	 */
	private function request( $method, $path, $body = null ) {
		$url = $this->api_url . $path;

		// Az API URL-nek HTTPS-nek kell lennie élesben.
		if ( strpos( $url, 'https://' ) !== 0 && strpos( $url, 'http://localhost' ) !== 0 ) {
			return new WP_Error(
				'haszno_insecure_url',
				__( 'A Haszno API URL-nek HTTPS protokollt kell használnia.', 'haszno-connector' )
			);
		}

		$args = array(
			'method'  => $method,
			'timeout' => 15,
			'headers' => array(
				'Content-Type' => 'application/json',
				'Accept'       => 'application/json',
				'x-api-key'    => $this->connect_code,
			),
		);

		if ( null !== $body ) {
			$encoded = wp_json_encode( $body );
			if ( false === $encoded ) {
				return new WP_Error( 'haszno_json_error', __( 'JSON kódolási hiba.', 'haszno-connector' ) );
			}
			$args['body'] = $encoded;
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'haszno_network_error',
				sprintf(
					/* translators: %s = error message */
					__( 'Hálózati hiba a Haszno API elérésekor: %s', 'haszno-connector' ),
					$response->get_error_message()
				)
			);
		}

		$status = wp_remote_retrieve_response_code( $response );
		$raw    = wp_remote_retrieve_body( $response );
		$data   = json_decode( $raw, true );

		if ( $status >= 400 ) {
			$message = isset( $data['error'] ) ? $data['error'] : $raw;
			return new WP_Error(
				'haszno_api_error',
				sprintf(
					/* translators: 1: HTTP status, 2: error message */
					__( 'Haszno API hiba (%1$s): %2$s', 'haszno-connector' ),
					$status,
					$message
				)
			);
		}

		if ( ! is_array( $data ) ) {
			return new WP_Error(
				'haszno_invalid_response',
				__( 'A Haszno API érvénytelen választ adott vissza.', 'haszno-connector' )
			);
		}

		return $data;
	}
}
