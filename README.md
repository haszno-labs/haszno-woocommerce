# Haszno Összekötő — WooCommerce Plugin

> _Összeköti a WooCommerce webáruházat a [Haszno](https://haszno.hu) dropshipping platformmal._

A **Haszno Összekötő** WordPress bővítmény egyetlen kapcsolódási kóddal csatlakoztatja a WooCommerce webáruházat a Haszno dropshipping-rendszerhez:

- Automatikus WooCommerce REST API-kulcs-létrehozás (nem kell manuálisan másolni a kulcsokat)
- A hitelesítési adatok titkosítva csak a Haszno szerveren tárolódnak — a WordPress-adatbázisban NEM maradnak
- Kapcsolat állapotának megjelenítése (Kapcsolva / Hiba / Nincs kapcsolva)
- Lecsatlakozás gomb (a WC API-kulcs azonnali törlésével)
- Teljes eltávolításkor minden beállítás és kulcs törlődik

## Telepítés

### ZIP-ből (ajánlott)

1. Töltsd le a legújabb `haszno-connector.zip` fájlt a Releases-ből.
2. WordPress adminfelület → **Bővítmények → Új bővítmény → Zip feltöltése**
3. Válaszd ki a zip fájlt és kattints az **Azonnali telepítés** gombra.
4. Aktiváld a bővítményt.
5. Menj a **WooCommerce → Beállítások → Integráció → Haszno Összekötő** oldalra.

### Manuálisan

```bash
unzip haszno-connector.zip -d /wp-content/plugins/
```

## Zip csomag elkészítése (fejlesztőknek)

```bash
bash build.sh
# Kimenet: dist/haszno-connector.zip
```

Követelmények: `bash`, `zip`, `rsync`. Ha `php` is elérhető, a build automatikusan lefuttatja a szintaktikai ellenőrzést minden PHP fájlon.

## Szükséges PHP verzió

PHP 7.4+, WordPress 5.8+, WooCommerce 7.0+

## Licenc

GPL-2.0-or-later — lásd [LICENSE](./LICENSE).

The Haszno platform itself (haszno.hu) is a commercial service. This plugin is open-source under the GPL so it can be freely audited, forked, and distributed alongside WooCommerce.
