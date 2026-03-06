#!/usr/bin/env bash
set -euo pipefail

HOOKS_DIR="$(cd "$(dirname "$0")" && pwd)"

# Configura o diretório de hooks do git
git config core.hooksPath "$HOOKS_DIR"

# Torna hooks executáveis
chmod +x "$HOOKS_DIR"/pre-commit "$HOOKS_DIR"/pre-push 2>/dev/null || true

echo "✅ Git hooks instalados (.githooks)"
