=== Haszno Összekötő ===
Contributors: haszno
Tags: woocommerce, dropshipping, haszno, automatikus árazás, termékimport
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
WC requires at least: 7.0
WC tested up to: 9.9

Összeköti a WooCommerce webáruházat a Haszno dropshipping platformmal — automatikus WC REST API-kulcs-létrehozással.

== Leírás ==

A **Haszno Összekötő** bővítmény egyetlen kapcsolódási kóddal lehetővé teszi, hogy a WooCommerce webáruházad csatlakozzon a [Haszno](https://haszno.hu) dropshipping platformhoz.

**Funkciók:**

* Egyszer használatos kapcsolódási kód alapú, biztonságos párosítás
* Automatikus WooCommerce REST API-kulcs-létrehozás — nem kell manuálisan másolgatni a kulcsokat
* A hitelesítési adatok titkosítva, kizárólag a Haszno szerveren tárolódnak — a WordPress-adatbázisban NEM maradnak
* Kapcsolat állapotának megjelenítése (Kapcsolva / Hiba / Nincs kapcsolva)
* Lecsatlakozás gomb (a létrehozott WC API-kulcs azonnali törlésével)
* Teljes eltávolításkor minden beállítás és kulcs törlődik

**Hogyan működik?**

1. A Haszno irányítópultján generálj egy kapcsolódási kódot.
2. Telepítsd és aktiváld ezt a bővítményt.
3. Menj a **WooCommerce → Beállítások → Integráció → Haszno Összekötő** oldalra.
4. Illeszd be a kapcsolódási kódot, majd kattints a **Változtatások mentése** gombra.
5. A bővítmény automatikusan létrehozza a szükséges WooCommerce REST API-kulcsokat, és biztonságosan elküldi azokat a Haszno API-nak.
6. Kész! A bolt állapota „Kapcsolva" lesz.

== Telepítés ==

**Automatikus telepítés (ajánlott):**

1. A WordPress adminfelületen menj a **Bővítmények → Új bővítmény** oldalra.
2. Keress rá a „Haszno Összekötő" névre.
3. Kattints a **Telepítés most** gombra, majd aktiváld.

**Manuális telepítés:**

1. Töltsd le a `haszno-connector.zip` fájlt.
2. A WordPress adminfelületen menj a **Bővítmények → Új bővítmény** oldalra, majd töltsd fel a zip-et.
3. Aktiváld a bővítményt.

**Első beállítás:**

1. Menj a **WooCommerce → Beállítások → Integráció → Haszno Összekötő** oldalra.
2. Add meg a kapcsolódási kódot (a Haszno irányítópultján találod).
3. Kattints a **Változtatások mentése** gombra.

== Kapcsolódó kérdések (FAQ) ==

= Hol találom a kapcsolódási kódot? =

A Haszno irányítópultján ([app.haszno.hu](https://app.haszno.hu)) a **Bolt bekötése** menüpontban generálhatsz kapcsolódási kódot. A kód egyszer használatos — minden bekötéshez újat kell generálni.

= Tárolódnak a WooCommerce API-kulcsok a WordPress-adatbázisban? =

Nem. A bővítmény létrehozza a kulcsokat, elküldi a Haszno API-nak (titkosítva tárolja ott), majd a kulcsok törlődnek a WordPress memóriájából. A WordPress-adatbázisban csak a létrehozott kulcs sorának azonosítója marad (a lecsatlakozáshoz), de maga a kulcs értéke nem.

= Mi történik lecsatlakozáskor? =

A bővítmény törli a létrehozott WooCommerce REST API-kulcsot, és nullázza az összes kapcsolat-beállítást.

= Biztonságos a kapcsolódási kód megadása? =

Igen. A bővítmény HTTPS-en keresztül kommunikál a Haszno API-val. A kapcsolódási kód egyszer használatos — sikeres bekötés után érvénytelenné válik. A kód nem kerül mentésre a WordPress-adatbázisba.

= Milyen WordPress és WooCommerce verziók szükségesek? =

Minimum WordPress 5.8 és WooCommerce 7.0, PHP 7.4+. Ajánlott: WordPress 6.4+, WooCommerce 8.0+, PHP 8.1+.

== Képernyőképek ==

1. Beállítások oldal (Kapcsolódás előtt)
2. Sikeresen kapcsolva állapot
3. Hibaállapot részlettel

== Változásnapló ==

= 1.0.0 =
* Első nyilvános kiadás.
* WooCommerce REST API-kulcs automatikus létrehozása.
* Biztonságos kapcsolódás a Haszno platformhoz.
* Kapcsolat állapotának megjelenítése.
* Lecsatlakozás gomb (WC API-kulcs törlésével).
* Teljes eltávolításkor minden adat törlése.

== Frissítés ==

A bővítmény a WordPress automatikus frissítési rendszerén keresztül frissíthető.
