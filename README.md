# Haszno Összekötő — WooCommerce Plugin

**English** | [Magyar](README.hu.md)

> _Connects a WooCommerce store to the [Haszno](https://haszno.hu) dropshipping platform._

The **Haszno Összekötő** ("Haszno Connector") WordPress plugin connects a WooCommerce store to the Haszno dropshipping system with a single connection code:

- Automatic WooCommerce REST API key creation (no copying keys by hand)
- Credentials are stored encrypted on the Haszno server only — they do NOT remain in the WordPress database
- Connection status display (Connected / Error / Not connected)
- Disconnect button (deletes the WC API key immediately)
- On full uninstall all settings and keys are removed

The plugin's admin interface is in Hungarian.

## Installation

### From a ZIP (recommended)

1. Download the latest `haszno-connector.zip` from Releases.
2. WordPress admin → **Plugins → Add New → Upload Plugin**
3. Choose the zip file and click **Install Now**.
4. Activate the plugin.
5. Go to **WooCommerce → Settings → Integration → Haszno Összekötő**.

### Manually

```bash
unzip haszno-connector.zip -d /wp-content/plugins/
```

## Building the zip package (for developers)

```bash
bash build.sh
# Output: dist/haszno-connector.zip
```

Requirements: `bash`, `zip`, `rsync`. If `php` is available, the build automatically runs a syntax check on every PHP file.

## Required PHP version

PHP 7.4+, WordPress 5.8+, WooCommerce 7.0+

## License

GPL-2.0-or-later — see [LICENSE](./LICENSE).

The Haszno platform itself (haszno.hu) is a commercial service. This plugin is open-source under the GPL so it can be freely audited, forked, and distributed alongside WooCommerce.
