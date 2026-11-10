# Haszno Összekötő — WooCommerce bővítmény

Automatikusan összeköti a WooCommerce webáruházat a [Haszno](https://haszno.hu) dropshipping platformmal.

## Funkciók

- **Egyszer használatos kapcsolódási kód** — Nem kell kézzel másolni API-kulcsokat. A Haszno irányítópultján generált egyszeri kódot kell beilleszteni, a bővítmény mindent elvégez automatikusan.
- **Automatikus WC REST API-kulcs-létrehozás** — A bővítmény létrehozza a szükséges `read_write` jogosultságú WooCommerce REST API-kulcspárt, és biztonságosan elküldi a Haszno API-nak.
- **Titkos adatok NEM tárolódnak WordPress-ben** — A WooCommerce fogyasztói kulcs és titkos kulcs értéke nem kerül a WordPress-adatbázisba; csak a Haszno platform tárolja, titkosítva (AES-256-GCM).
- **Kapcsolat állapotának megjelenítése** — Zöld / piros jelzés a WooCommerce → Beállítások → Integráció oldalon.
- **Lecsatlakozás gomb** — Egy gombnyomással megszakítható a kapcsolat; a létrehozott WC API-kulcs azonnal törlődik.
- **Teljes eltávolítás** — Törléskor az összes beállítás és a WC API-kulcs törlődik.

## Rendszerkövetelmények

| Komponens | Minimális verzió |
|---|---|
| PHP | 7.4 |
| WordPress | 5.8 |
| WooCommerce | 7.0 |

## Telepítés

### Zip-ből (ajánlott)

```bash
# Zip elkészítése (a repó gyökeréből)
cd integrations/woocommerce-plugin
bash build.sh
```

A `dist/haszno-connector.zip` fájlt töltsd fel a WordPress adminfelületen:
**Bővítmények → Új bővítmény → Zip feltöltése**.

### Fejlesztői (szimlink / manuális)

```bash
ln -s /path/to/haszno-connector /path/to/wordpress/wp-content/plugins/haszno-connector
```

## Első beállítás

1. Aktiváld a bővítményt a WordPress adminfelületen.
2. Menj a **WooCommerce → Beállítások → Integráció → Haszno Összekötő** oldalra.
3. A Haszno irányítópultján ([app.haszno.hu](https://app.haszno.hu)) a **Bolt bekötése** menüpontban generálj kapcsolódási kódot.
4. Illeszd be a kódot a **Kapcsolódási kód** mezőbe.
5. Kattints a **Változtatások mentése** gombra.
6. Ha minden rendben, a státusz **Kapcsolva** lesz.

## Kapcsolódási folyamat (technikai részletek)

A bővítmény a következő lépéseket hajtja végre mentéskor:

```
1. WC REST API-kulcspár létrehozása (ck_… / cs_…, read_write, jelenlegi admin felhasználóhoz)
2. POST /api/connect/stores  { platform: "woocommerce", name: "<bolt neve>" }
   → Haszno bolt-kapcsolat létrehozása, ID visszakapva
3. PUT  /api/connect/stores/:id/domain  { domain: "<bolt domainje>" }
4. PUT  /api/connect/stores/:id/credentials  { credentials: { consumerKey, consumerSecret } }
   → Titkosítva tárolódik a Haszno szerveren; a bővítmény nem menti el
5. POST /api/connect/stores/:id/test
   → Kapcsolat tesztelése
```

A `x-api-key` fejlécben a kapcsolódási kód kerül elküldésre (egyszer használatos Bearer-token).

## Tárolt WordPress-opciók

| Opció neve | Tartalom |
|---|---|
| `haszno_connector_api_url` | Haszno API alap URL |
| `haszno_connector_status` | `nincs_kapcsolva` \| `kapcsolva` \| `hiba` |
| `haszno_connector_store_id` | Haszno bolt-kapcsolat azonosítója (int) |
| `haszno_connector_store_name` | Haszno-ban rögzített bolt neve |
| `haszno_connector_error` | Utolsó hibaüzenet (ha volt) |
| `haszno_connector_wc_key_id` | A létrehozott WC API-kulcs DB-azonosítója (törléshez) |

**Nem tárolt adatok:** kapcsolódási kód, WC `consumer_key`, WC `consumer_secret`.

## A connect-agent által implementálandó (open issue)

A bővítmény az alábbi Haszno API-végpontokat hívja. A `connect-agent` feladata ezek teljes implementációja (`api/src/connect/routes.ts`, jelenleg 501 stub-ok):

| Végpont | Mikor hívja a bővítmény | Megjegyzés |
|---|---|---|
| `POST /api/connect/stores` | Kapcsolódáskor (1. lépés) | A `x-api-key` fejlécben érkező kapcsolódási kód authentikálja a kérést. A kód egyszeri érvényű token; a connect-agent feladata a tokenvalidáció és a tenanthoz rendelés. |
| `PUT /api/connect/stores/:id/domain` | Kapcsolódáskor (2. lépés) | A domain SSRF-ellenőrzése a connect-agent felelőssége (`isValidPublicHostname`). |
| `PUT /api/connect/stores/:id/credentials` | Kapcsolódáskor (3. lépés) | A `{credentials: {consumerKey, consumerSecret}}` értéket `encryptSecret()` segítségével titkosítva kell tárolni; soha nem szabad visszaadni API-válaszban. |
| `POST /api/connect/stores/:id/test` | Kapcsolódáskor (4. lépés) | A WooCommerce adaptert kell meghívni a tárolt titkosított hitelesítési adatokkal. |
| `GET /api/connect/stores/:id` | Állapot-lekérdezéskor | Csak státusz és metaadatok visszaadása; `credentials_enc` soha. |

## Build script

```bash
cd integrations/woocommerce-plugin
bash build.sh
# → dist/haszno-connector.zip
```

## Licenc

GPLv2 or later — https://www.gnu.org/licenses/gpl-2.0.html
