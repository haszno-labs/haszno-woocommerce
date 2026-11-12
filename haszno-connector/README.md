# Haszno Összekötő — WooCommerce plugin

**English** | [Magyar](README.hu.md)

Automatically connects a WooCommerce store to the [Haszno](https://haszno.hu) dropshipping platform. ("Haszno Összekötő" means "Haszno Connector"; the plugin's admin interface is in Hungarian.)

## Features

- **One-time connection code** — No copying API keys by hand. Paste the one-time code generated on the Haszno dashboard and the plugin does the rest.
- **Automatic WC REST API key creation** — The plugin creates the required WooCommerce REST API key pair with `read_write` permission and sends it securely to the Haszno API.
- **Secrets are NOT stored in WordPress** — The WooCommerce consumer key and consumer secret never reach the WordPress database; only the Haszno platform stores them, encrypted (AES-256-GCM).
- **Connection status display** — Green / red indicator on the WooCommerce → Settings → Integration page.
- **Disconnect button** — One click ends the connection; the WC API key that was created is deleted immediately.
- **Full uninstall** — On deletion all settings and the WC API key are removed.

## Requirements

| Component | Minimum version |
|---|---|
| PHP | 7.4 |
| WordPress | 5.8 |
| WooCommerce | 7.0 |

## Installation

### From a zip (recommended)

```bash
# Build the zip (from the repository root)
bash build.sh
```

Upload `dist/haszno-connector.zip` in the WordPress admin:
**Plugins → Add New → Upload Plugin**.

### Development (symlink / manual)

```bash
ln -s /path/to/haszno-connector /path/to/wordpress/wp-content/plugins/haszno-connector
```

## First-time setup

1. Activate the plugin in the WordPress admin.
2. Go to **WooCommerce → Settings → Integration → Haszno Összekötő**.
3. On the Haszno dashboard ([app.haszno.hu](https://app.haszno.hu)) generate a connection code under **Bolt bekötése** ("Connect a store").
4. Paste the code into the **Kapcsolódási kód** ("Connection code") field.
5. Click **Save changes**.
6. If everything is fine, the status becomes **Kapcsolva** ("Connected").

## Connection flow (technical details)

On save the plugin performs these steps:

```
1. Create a WC REST API key pair (ck_… / cs_…, read_write, for the current admin user)
2. POST /api/connect/stores  { platform: "woocommerce", name: "<store name>" }
   → creates the Haszno store connection, returns its ID
3. PUT  /api/connect/stores/:id/domain  { domain: "<store domain>" }
4. PUT  /api/connect/stores/:id/credentials  { credentials: { consumerKey, consumerSecret } }
   → stored encrypted on the Haszno server; the plugin does not save it
5. POST /api/connect/stores/:id/test
   → tests the connection
```

The connection code is sent in the `x-api-key` header (a one-time Bearer token).

## Stored WordPress options

| Option name | Contents |
|---|---|
| `haszno_connector_api_url` | Haszno API base URL |
| `haszno_connector_status` | `nincs_kapcsolva` \| `kapcsolva` \| `hiba` (not connected \| connected \| error) |
| `haszno_connector_store_id` | ID of the Haszno store connection (int) |
| `haszno_connector_store_name` | Store name recorded in Haszno |
| `haszno_connector_error` | Last error message (if any) |
| `haszno_connector_wc_key_id` | DB ID of the WC API key that was created (for deletion) |

**Not stored:** connection code, WC `consumer_key`, WC `consumer_secret`.

## To be implemented by the connect-agent (open issue)

The plugin calls the Haszno API endpoints below. Implementing them fully is the `connect-agent`'s task (`api/src/connect/routes.ts`, currently 501 stubs):

| Endpoint | When the plugin calls it | Note |
|---|---|---|
| `POST /api/connect/stores` | On connect (step 1) | The connection code in the `x-api-key` header authenticates the request. The code is a single-use token; validating it and assigning it to a tenant is the connect-agent's job. |
| `PUT /api/connect/stores/:id/domain` | On connect (step 2) | SSRF-checking the domain is the connect-agent's responsibility (`isValidPublicHostname`). |
| `PUT /api/connect/stores/:id/credentials` | On connect (step 3) | The `{credentials: {consumerKey, consumerSecret}}` value must be stored encrypted with `encryptSecret()`; it must never be returned in an API response. |
| `POST /api/connect/stores/:id/test` | On connect (step 4) | Call the WooCommerce adapter with the stored encrypted credentials. |
| `GET /api/connect/stores/:id` | On status query | Return status and metadata only; never `credentials_enc`. |

## Build script

```bash
bash build.sh
# → dist/haszno-connector.zip
```

## License

GPLv2 or later — https://www.gnu.org/licenses/gpl-2.0.html
