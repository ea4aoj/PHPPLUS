#!/bin/sh

# ========================================================
# Script: reset_phpplus.sh
# Descripción: Intenta git pull, si falla borra y re-clona
# ========================================================

REPO_URL="http://github.com/ea4aoj/PHPPLUS"
REPO_NAME="PHPPLUS"
DEST_DIR="/home/pi"
REPO_PATH="$DEST_DIR/$REPO_NAME"

echo "========================================"
echo "  Actualizador de repositorio PHPPLUS   "
echo "========================================"
echo ""

if [ -d "$REPO_PATH" ]; then
    echo "📥 Entrando en $REPO_PATH..."
    cd "$REPO_PATH" || exit 1
    
    echo "📥 Ejecutando git pull..."
    if git pull; then
        echo ""
        echo "========================================"
        echo "✅ Repositorio sano"
        echo "========================================"
        exit 0
    else
        echo ""
        echo "⚠️  git pull falló"
        echo "🗑️  Borrando carpeta: $REPO_PATH"
        cd "$DEST_DIR" || exit 1
        sudo rm -rf "$REPO_NAME"
    fi
else
    echo "ℹ️  La carpeta no existe, clonando..."
fi

cd "$DEST_DIR" || exit 1
echo "📥 Clonando desde $REPO_URL ..."
git clone "$REPO_URL"

if [ $? -eq 0 ]; then
    echo ""
    echo "========================================"
    echo "✅ Repositorio clonado correctamente"
    echo "========================================"
else
    echo ""
    echo "========================================"
    echo "❌ Error al clonar"
    echo "========================================"
    exit 1
fi
