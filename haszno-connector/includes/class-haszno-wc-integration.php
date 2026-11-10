<?php
/**
 * Haszno WooCommerce Integration osztály.
 *
 * WooCommerce → Beállítások → Integráció → Haszno Összekötő
 *
 * Funkcionalitás:
 *  - Beállítások: API URL, kapcsolódási kód (egyszer használatos)
 *  - Mentéskor: WC REST API-kulcs létrehozása, küldés a Haszno API-nak
 *  - Kapcsolat állapotának megjelenítése
 *  - Lecsatlakozás gomb (WC API-kulcs törlésével)
 *
 * @package HasznoConnector
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Haszno_WC_Integration class.
 */
class Haszno_WC_Integration extends WC_Integration {

	/** @var string Azonosító */
	public $id = 'haszno_connector';

	/** @var string Megjelenített név */
	public $method_title = 'Haszno Összekötő';

	/** @var string Leírás */
	public $method_description = 'Összeköti a WooCommerce webáruházat a Haszno dropshipping platformmal.';

	// WP options kulcsok -------------------------------------------------------

	const OPT_API_URL       = 'haszno_connector_api_url';
	const OPT_STATUS        = 'haszno_connector_status';       // 'nincs_kapcsolva'|'kapcsolva'|'hiba'
	const OPT_STORE_ID      = 'haszno_connector_store_id';     // Haszno connection ID (int)
	const OPT_STORE_NAME    = 'haszno_connector_store_name';   // Haszno connection name
	const OPT_ERROR         = 'haszno_connector_error';        // Utolsó hibaüzenet
	const OPT_WC_KEY_ID     = 'haszno_connector_wc_key_id';    // WC API key sor ID (törléshez)

	const DEFAULT_API_URL   = 'https://api.haszno.hu';

	// -------------------------------------------------------------------------

	/**
	 * Konstruktor — beállítások inicializálása.
	 */
	public function __construct() {
		$this->init_form_fields();
		$this->init_settings();

		// Mentés esemény
		add_action( 'woocommerce_update_options_integration_' . $this->id, array( $this, 'process_admin_options' ) );

		// Lecsatlakozás gomb kezelése
		add_action( 'admin_init', array( $this, 'handle_disconnect' ) );

		// Admin értesítések
		add_action( 'admin_notices', array( $this, 'show_admin_notices' ) );
	}

	// -------------------------------------------------------------------------
	// Beállítások mezők
	// -------------------------------------------------------------------------

	/**
	 * Beállítás-mezők definíciója.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'api_url'      => array(
				'title'       => __( 'Haszno API URL', 'haszno-connector' ),
				'type'        => 'text',
				'description' => __( 'A Haszno API alap URL-je. Általában nem kell módosítani.', 'haszno-connector' ),
				'default'     => self::DEFAULT_API_URL,
				'placeholder' => self::DEFAULT_API_URL,
				'desc_tip'    => true,
			),
			'connect_code' => array(
				'title'       => __( 'Kapcsolódási kód', 'haszno-connector' ),
				'type'        => 'password',
				'description' => __( 'A Haszno irányítópultjáról másold ide az egyszer használatos kapcsolódási kódot, majd kattints a "Változtatások mentése" gombra. A kód a sikeres kapcsolódás után törlődik.', 'haszno-connector' ),
				'default'     => '',
				'placeholder' => __( 'Pl.: hcn_live_xxxxxxxxxxxxxxxx', 'haszno-connector' ),
			),
		);
	}

	// -------------------------------------------------------------------------
	// Beállítások mentése (felülírva)
	// -------------------------------------------------------------------------

	/**
	 * Beállítások feldolgozása és a kapcsolódási folyamat indítása.
	 *
	 * Felülírja a szülőosztály process_admin_options() metódusát, hogy
	 * a kapcsolódási kód beérkezésekor elvégezze a bolt-bekötést.
	 */
	public function process_admin_options() {
		// Jogosultság és nonce ellenőrzése (WC_Integration::process_admin_options() teszi a nonce-t)
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Nincs jogosultságod ehhez a művelethez.', 'haszno-connector' ) );
		}

		// Az API URL-t elmentjük normálisan
		$raw_api_url = isset( $_POST[ $this->plugin_id . $this->id . '_api_url' ] )
			? sanitize_text_field( wp_unslash( $_POST[ $this->plugin_id . $this->id . '_api_url' ] ) )
			: self::DEFAULT_API_URL;

		$api_url = $this->sanitize_api_url( $raw_api_url );
		update_option( self::OPT_API_URL, $api_url );

		// Kapcsolódási kód beolvasása (nem mentjük el)
		$connect_code = isset( $_POST[ $this->plugin_id . $this->id . '_connect_code' ] )
			? sanitize_text_field( wp_unslash( $_POST[ $this->plugin_id . $this->id . '_connect_code' ] ) )
			: '';

		if ( empty( $connect_code ) ) {
			// Nincs kód — csak az URL-t mentettük el, nincs más teendő.
			return;
		}

		// Kapcsolódás indítása
		$this->run_connect_flow( $api_url, $connect_code );
	}

	// -------------------------------------------------------------------------
	// Kapcsolódási folyamat
	// -------------------------------------------------------------------------

	/**
	 * Teljes kapcsolódási folyamat:
	 *  1. WC REST API-kulcs létrehozása
	 *  2. POST /api/connect/stores
	 *  3. PUT  /api/connect/stores/:id/domain
	 *  4. PUT  /api/connect/stores/:id/credentials
	 *  5. POST /api/connect/stores/:id/test
	 *
	 * @param string $api_url      Haszno API alap URL.
	 * @param string $connect_code Egyszer használatos kapcsolódási kód.
	 */
	private function run_connect_flow( $api_url, $connect_code ) {
		// Lépés 1: WC REST API-kulcs létrehozása
		$wc_keys = $this->create_wc_api_keys();
		if ( is_wp_error( $wc_keys ) ) {
			$this->save_error( $wc_keys->get_error_message() );
			return;
		}

		$client = new Haszno_API_Client( $api_url, $connect_code );

		// Bolt neve
		$store_name = get_bloginfo( 'name' );
		if ( empty( $store_name ) ) {
			$store_name = get_site_url();
		}

		// Lépés 2: Bolt-kapcsolat létrehozása
		$store = $client->create_store( $store_name );
		if ( is_wp_error( $store ) ) {
			// Kulcsot visszavonjuk, mert a kapcsolódás nem sikerült
			$this->delete_wc_api_key( $wc_keys['key_id'] );
			$this->save_error( $store->get_error_message() );
			return;
		}

		$store_id = isset( $store['id'] ) ? (int) $store['id'] : 0;
		if ( $store_id <= 0 ) {
			$this->delete_wc_api_key( $wc_keys['key_id'] );
			$this->save_error( __( 'A Haszno API érvénytelen bolt-kapcsolat azonosítót adott vissza.', 'haszno-connector' ) );
			return;
		}

		// Lépés 3: Domain beállítása
		$home_url = home_url();
		$parsed   = wp_parse_url( $home_url );
		$domain   = isset( $parsed['host'] ) ? $parsed['host'] : '';

		if ( ! empty( $domain ) ) {
			$domain_result = $client->set_domain( $store_id, $domain );
			if ( is_wp_error( $domain_result ) ) {
				// Nem végzetes — továbblépünk, a hibát naplózzuk
				// A connect agent a domain-t így is be tudja állítani később.
			}
		}

		// Lépés 4: Hitelesítési adatok küldése (kulcsok SOSEM kerülnek mentésre WP-ben)
		$creds_result = $client->set_credentials( $store_id, $wc_keys['consumer_key'], $wc_keys['consumer_secret'] );

		// Kulcsok azonnal törlése a memóriából
		$consumer_key    = $wc_keys['consumer_key'];
		$consumer_secret = $wc_keys['consumer_secret'];
		unset( $wc_keys['consumer_key'], $wc_keys['consumer_secret'] );

		if ( is_wp_error( $creds_result ) ) {
			$this->delete_wc_api_key( $wc_keys['key_id'] );
			$this->save_error( $creds_result->get_error_message() );
			return;
		}

		// Lépés 5: Kapcsolat tesztelése
		$test_result = $client->test_connection( $store_id );
		if ( is_wp_error( $test_result ) ) {
			// Teszthiba nem végzetes — a kapcsolat felépült, csak a teszt nem sikerült
			update_option( self::OPT_STATUS, 'hiba' );
			update_option( self::OPT_ERROR, $test_result->get_error_message() );
		} elseif ( isset( $test_result['ok'] ) && false === $test_result['ok'] ) {
			update_option( self::OPT_STATUS, 'hiba' );
			update_option( self::OPT_ERROR, isset( $test_result['error'] ) ? $test_result['error'] : __( 'Ismeretlen teszthiba.', 'haszno-connector' ) );
		} else {
			update_option( self::OPT_STATUS, 'kapcsolva' );
			delete_option( self::OPT_ERROR );
		}

		// Kapcsolat sikeresen felépült — adatok mentése
		update_option( self::OPT_STORE_ID, $store_id );
		update_option( self::OPT_STORE_NAME, sanitize_text_field( isset( $store['name'] ) ? $store['name'] : $store_name ) );
		update_option( self::OPT_WC_KEY_ID, $wc_keys['key_id'] );
		update_option( self::OPT_API_URL, $api_url );

		// A kapcsolódási kód egyszer használatos — nem tároljuk.
		// Az API URL-t megtartjuk, de a kód mezőt nem jelenítjük meg kitöltve.
		set_transient( 'haszno_connect_success', 1, 60 );
	}

	// -------------------------------------------------------------------------
	// WC REST API-kulcs kezelés
	// -------------------------------------------------------------------------

	/**
	 * WooCommerce REST API-kulcspár létrehozása az aktuális adminisztrátorhoz.
	 *
	 * A `consumer_key` visszaadott értéke az egyszer látható nyílt szöveg;
	 * az adatbázisban hash-elve tárolódik. A `consumer_secret` tisztán tárolódik.
	 * SOSEM mentjük ezeket WP-opcióba.
	 *
	 * @return array|WP_Error {
	 *     @type int    $key_id          WC API key adatbázis-azonosítója.
	 *     @type string $consumer_key    Nyílt ck_… érték (csak egyszer elérhető).
	 *     @type string $consumer_secret cs_… érték.
	 * }
	 */
	private function create_wc_api_keys() {
		global $wpdb;

		if ( ! function_exists( 'wc_rand_hash' ) || ! function_exists( 'wc_api_hash' ) ) {
			return new WP_Error(
				'haszno_wc_missing_functions',
				__( 'A WooCommerce belső függvényei nem elérhetőek. Győződj meg arról, hogy a WooCommerce aktív.', 'haszno-connector' )
			);
		}

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return new WP_Error(
				'haszno_no_user',
				__( 'Nincs bejelentkezett felhasználó. Jelentkezz be rendszergazdaként.', 'haszno-connector' )
			);
		}

		if ( ! user_can( $user_id, 'manage_woocommerce' ) ) {
			return new WP_Error(
				'haszno_insufficient_caps',
				__( 'A WooCommerce API-kulcs létrehozásához manage_woocommerce jogosultság szükséges.', 'haszno-connector' )
			);
		}

		// Esetleges korábbi kulcs törlése (újrakapcsolódáskor)
		$old_key_id = (int) get_option( self::OPT_WC_KEY_ID, 0 );
		if ( $old_key_id > 0 ) {
			$this->delete_wc_api_key( $old_key_id );
		}

		$consumer_key    = 'ck_' . wc_rand_hash();
		$consumer_secret = 'cs_' . wc_rand_hash();

		$table  = $wpdb->prefix . 'woocommerce_api_keys';
		$result = $wpdb->insert(
			$table,
			array(
				'user_id'         => $user_id,
				'description'     => 'Haszno Összekötő',
				'permissions'     => 'read_write',
				'consumer_key'    => wc_api_hash( $consumer_key ),
				'consumer_secret' => $consumer_secret,
				'truncated_key'   => substr( $consumer_key, -7 ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $result ) {
			return new WP_Error(
				'haszno_key_insert_failed',
				sprintf(
					/* translators: %s = DB error */
					__( 'WooCommerce API-kulcs létrehozása sikertelen: %s', 'haszno-connector' ),
					$wpdb->last_error
				)
			);
		}

		return array(
			'key_id'          => (int) $wpdb->insert_id,
			'consumer_key'    => $consumer_key,
			'consumer_secret' => $consumer_secret,
		);
	}

	/**
	 * WooCommerce REST API-kulcs törlése adatbázis-azonosító alapján.
	 *
	 * @param int $key_id WC API key adatbázis-azonosítója.
	 */
	private function delete_wc_api_key( $key_id ) {
		global $wpdb;
		$key_id = (int) $key_id;
		if ( $key_id > 0 ) {
			$wpdb->delete(
				$wpdb->prefix . 'woocommerce_api_keys',
				array( 'key_id' => $key_id ),
				array( '%d' )
			);
		}
	}

	// -------------------------------------------------------------------------
	// Lecsatlakozás
	// -------------------------------------------------------------------------

	/**
	 * Lecsatlakozás gomb kezelése.
	 * Törli a WC API-kulcsot és nullázza az összes kapcsolat-opciót.
	 */
	public function handle_disconnect() {
		if ( ! isset( $_POST['haszno_disconnect'] ) ) {
			return;
		}

		if ( ! isset( $_POST['haszno_disconnect_nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['haszno_disconnect_nonce'] ) ), 'haszno_disconnect' )
		) {
			wp_die( esc_html__( 'Érvénytelen biztonsági token.', 'haszno-connector' ) );
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Nincs jogosultságod ehhez a művelethez.', 'haszno-connector' ) );
		}

		// WC API-kulcs törlése
		$key_id = (int) get_option( self::OPT_WC_KEY_ID, 0 );
		if ( $key_id > 0 ) {
			$this->delete_wc_api_key( $key_id );
		}

		// Kapcsolat-opciók törlése
		delete_option( self::OPT_STATUS );
		delete_option( self::OPT_STORE_ID );
		delete_option( self::OPT_STORE_NAME );
		delete_option( self::OPT_ERROR );
		delete_option( self::OPT_WC_KEY_ID );

		set_transient( 'haszno_disconnect_success', 1, 60 );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'wc-settings',
					'tab'     => 'integration',
					'section' => $this->id,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	// -------------------------------------------------------------------------
	// Admin értesítések
	// -------------------------------------------------------------------------

	/**
	 * Admin értesítések megjelenítése (siker/hiba).
	 */
	public function show_admin_notices() {
		if ( get_transient( 'haszno_connect_success' ) ) {
			delete_transient( 'haszno_connect_success' );
			echo '<div class="notice notice-success is-dismissible"><p>';
			esc_html_e( 'Haszno kapcsolódás sikeres! A WooCommerce webáruház össze van kötve a Haszno platformmal.', 'haszno-connector' );
			echo '</p></div>';
		}

		if ( get_transient( 'haszno_disconnect_success' ) ) {
			delete_transient( 'haszno_disconnect_success' );
			echo '<div class="notice notice-info is-dismissible"><p>';
			esc_html_e( 'A Haszno kapcsolat megszakítva. A WooCommerce API-kulcs törölve.', 'haszno-connector' );
			echo '</p></div>';
		}
	}

	// -------------------------------------------------------------------------
	// Beállítások HTML megjelenítése
	// -------------------------------------------------------------------------

	/**
	 * Az integrációs beállítások oldalának megjelenítése.
	 * Felülírja a szülőosztály generate_settings_html() hívását,
	 * hogy a kapcsolat állapotát is megjelenítse.
	 */
	public function admin_options() {
		echo '<h2>' . esc_html( $this->get_method_title() ) . '</h2>';
		echo '<p>' . esc_html( $this->get_method_description() ) . '</p>';

		$this->render_connection_status();

		echo '<table class="form-table">';
		$this->generate_settings_html();
		echo '</table>';
	}

	/**
	 * Kapcsolat állapotának HTML-blokk megjelenítése.
	 */
	private function render_connection_status() {
		$status     = get_option( self::OPT_STATUS, 'nincs_kapcsolva' );
		$store_name = get_option( self::OPT_STORE_NAME, '' );
		$error      = get_option( self::OPT_ERROR, '' );
		$store_id   = (int) get_option( self::OPT_STORE_ID, 0 );

		echo '<div class="haszno-status-card" style="'
			. 'background:#fff;border:1px solid #c3c4c7;border-left:4px solid '
			. esc_attr( $this->status_color( $status ) )
			. ';padding:16px 20px;margin:16px 0;border-radius:3px;max-width:600px;">';

		echo '<p style="margin:0 0 4px;font-weight:600;font-size:14px;">'
			. esc_html__( 'Kapcsolat állapota:', 'haszno-connector' )
			. ' <span style="color:' . esc_attr( $this->status_color( $status ) ) . ';">'
			. esc_html( $this->status_label( $status ) )
			. '</span></p>';

		if ( 'kapcsolva' === $status && $store_name ) {
			echo '<p style="margin:0;color:#50575e;font-size:13px;">'
				. esc_html__( 'Bolt neve:', 'haszno-connector' ) . ' '
				. esc_html( $store_name );

			if ( $store_id > 0 ) {
				echo ' &mdash; '
					. esc_html__( 'Haszno azonosító:', 'haszno-connector' ) . ' '
					. esc_html( (string) $store_id );
			}

			echo '</p>';
		}

		if ( 'hiba' === $status && $error ) {
			echo '<p style="margin:8px 0 0;color:#d63638;font-size:13px;">'
				. esc_html( $error )
				. '</p>';
		}

		if ( 'kapcsolva' === $status || 'hiba' === $status ) {
			echo '<form method="post" style="margin-top:12px;" action="">';
			wp_nonce_field( 'haszno_disconnect', 'haszno_disconnect_nonce' );
			echo '<input type="hidden" name="haszno_disconnect" value="1" />';
			echo '<button type="submit" class="button button-secondary" '
				. 'onclick="return confirm(\''
				. esc_js( __( 'Biztosan megszakítod a Haszno kapcsolatot? A WooCommerce API-kulcs törlődni fog.', 'haszno-connector' ) )
				. '\')">'
				. esc_html__( 'Kapcsolat megszakítása', 'haszno-connector' )
				. '</button>';
			echo '</form>';
		}

		echo '</div>';

		if ( 'kapcsolva' === $status ) {
			// Ha már kapcsolva van, a kód mező megjelenítésével ne zavarjuk a felhasználót.
			echo '<div class="notice notice-info inline"><p>';
			esc_html_e( 'A bolt jelenleg össze van kötve a Haszno platformmal. Újrakapcsoláshoz add meg az új kapcsolódási kódot a mezőben, majd mentsd el a beállításokat.', 'haszno-connector' );
			echo '</p></div>';
		}
	}

	// -------------------------------------------------------------------------
	// Segédmetódusok
	// -------------------------------------------------------------------------

	/**
	 * Állapot magyar felirata.
	 *
	 * @param string $status Állapot kód.
	 * @return string
	 */
	private function status_label( $status ) {
		$labels = array(
			'nincs_kapcsolva' => __( 'Nincs kapcsolva', 'haszno-connector' ),
			'kapcsolva'       => __( 'Kapcsolva', 'haszno-connector' ),
			'hiba'            => __( 'Hiba', 'haszno-connector' ),
		);
		return isset( $labels[ $status ] ) ? $labels[ $status ] : __( 'Ismeretlen', 'haszno-connector' );
	}

	/**
	 * Állapot CSS-szín kódja.
	 *
	 * @param string $status Állapot kód.
	 * @return string
	 */
	private function status_color( $status ) {
		switch ( $status ) {
			case 'kapcsolva':
				return '#00a32a';
			case 'hiba':
				return '#d63638';
			default:
				return '#c3c4c7';
		}
	}

	/**
	 * API URL sanitizálása.
	 *
	 * @param string $url URL string.
	 * @return string
	 */
	private function sanitize_api_url( $url ) {
		$url = sanitize_text_field( $url );
		$url = rtrim( $url, '/' );

		if ( empty( $url ) ) {
			return self::DEFAULT_API_URL;
		}

		// Csak HTTPS vagy localhost http engedélyezett
		if ( strpos( $url, 'https://' ) !== 0 && strpos( $url, 'http://localhost' ) !== 0 ) {
			return self::DEFAULT_API_URL;
		}

		return $url;
	}

	/**
	 * Hibaállapot mentése.
	 *
	 * @param string $message Hibaüzenet.
	 */
	private function save_error( $message ) {
		update_option( self::OPT_STATUS, 'hiba' );
		update_option( self::OPT_ERROR, $message );
	}
}
