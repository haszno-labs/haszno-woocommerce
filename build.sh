#!/usr/bin/env bash
# build.sh — Haszno Összekötő WooCommerce bővítmény zip-csomagjának elkészítése.
#
# Használat:
#   cd integrations/woocommerce-plugin
#   bash build.sh
#
# Kimenet:
#   dist/haszno-connector.zip
#
# Tartalom: haszno-connector/ könyvtár (szükséges fájlok; fejlesztői és teszt
# fájlok kizárva).
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_DIR="$SCRIPT_DIR/haszno-connector"
DIST_DIR="$SCRIPT_DIR/dist"
ZIP_NAME="haszno-connector.zip"
ZIP_PATH="$DIST_DIR/$ZIP_NAME"

# ---- Ellenőrzések -----------------------------------------------------------

if [ ! -d "$PLUGIN_DIR" ]; then
  echo "HIBA: A bővítmény könyvtára nem található: $PLUGIN_DIR" >&2
  exit 1
fi

# ---- PHP szintaktikai ellenőrzés (ha elérhető) ------------------------------

if command -v php > /dev/null 2>&1; then
  echo "PHP szintaktikai ellenőrzés..."
  SYNTAX_OK=true
  while IFS= read -r -d '' php_file; do
    if ! php -l "$php_file" > /dev/null 2>&1; then
      echo "  HIBA: $php_file" >&2
      php -l "$php_file" >&2
      SYNTAX_OK=false
    fi
  done < <(find "$PLUGIN_DIR" -name "*.php" -print0)

  if [ "$SYNTAX_OK" = true ]; then
    echo "  Minden PHP fájl szintaktikailag helyes."
  else
    echo "HIBA: PHP szintaktikai hibák találhatók. Build megszakítva." >&2
    exit 1
  fi
else
  echo "PHP nem elérhető — szintaktikai ellenőrzés kihagyva."
fi

# ---- Dist könyvtár előkészítése --------------------------------------------

mkdir -p "$DIST_DIR"

# Régi zip törlése
if [ -f "$ZIP_PATH" ]; then
  rm "$ZIP_PATH"
  echo "Régi zip törölve: $ZIP_PATH"
fi

# ---- Ideiglenes build könyvtár ---------------------------------------------

TMP_DIR="$(mktemp -d)"
trap 'rm -rf "$TMP_DIR"' EXIT

mkdir -p "$TMP_DIR/haszno-connector"

# ---- Fájlok másolása (kizárásokkal) ----------------------------------------

# Belefoglalandó fájlok és könyvtárak
rsync -a \
  --exclude='.git' \
  --exclude='.gitignore' \
  --exclude='node_modules' \
  --exclude='*.test.php' \
  --exclude='tests/' \
  --exclude='.DS_Store' \
  --exclude='Thumbs.db' \
  --exclude='*.log' \
  --exclude='*.tmp' \
  "$PLUGIN_DIR/" \
  "$TMP_DIR/haszno-connector/"

# ---- Zip elkészítése --------------------------------------------------------

echo "Zip csomagolása: $ZIP_PATH"
(cd "$TMP_DIR" && zip -r "$ZIP_PATH" "haszno-connector/" -x "*.DS_Store")

# ---- Összefoglalás ----------------------------------------------------------

FILE_COUNT=$(unzip -l "$ZIP_PATH" | tail -1 | awk '{print $2}')
ZIP_SIZE=$(du -sh "$ZIP_PATH" | cut -f1)

echo ""
echo "Kész: $ZIP_PATH"
echo "  Fájlok: $FILE_COUNT"
echo "  Méret:  $ZIP_SIZE"
echo ""
echo "Telepítés: WordPress adminfelület → Bővítmények → Új bővítmény → Zip feltöltése"
