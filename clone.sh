#!/bin/bash
# GYMFLEX - traz o código do GitHub (clona na primeira vez, atualiza nas seguintes)
# SEM alterar a configuração local do banco (api/config.local.php).
#
# Uso:  ./clone.sh [branch] [pasta]
#   branch  padrão: main
#   pasta   padrão: gymflex (só usada quando ainda não existe o repositório)
#
# Dentro de um repositório já clonado, atualiza ele mesmo. Fora de um, clona em ./pasta.
# Variável opcional: REPO_URL (padrão: https://github.com/talesCPV/gymflex.git)

set -euo pipefail

REPO_URL="${REPO_URL:-https://github.com/talesCPV/gymflex.git}"
BRANCH="${1:-main}"
DIR="${2:-gymflex}"

# arquivos locais que nunca podem ser sobrescritos (caminhos relativos à raiz do repositório)
PROTEGIDOS=("api/config.local.php")

erro() { echo "ERRO: $*" >&2; exit 1; }
info() { echo "==> $*"; }

command -v git >/dev/null || erro "git não encontrado"

# ---------- clone (primeira vez) ----------
if [ ! -d "$PWD/.git" ] && [ ! -d "$DIR/.git" ]; then
  if [ -e "$DIR" ] && [ -n "$(ls -A "$DIR" 2>/dev/null)" ]; then
    erro "a pasta '$DIR' já existe e não está vazia (e não é um repositório git)"
  fi
  info "Clonando $REPO_URL (branch $BRANCH) em '$DIR'"
  git clone --branch "$BRANCH" "$REPO_URL" "$DIR"
  echo
  info "Pronto. Próximos passos:"
  echo "  1. cd $DIR"
  echo "  2. cp api/config.example.php api/config.local.php   # e ajuste host/db/user/pass"
  echo "  3. mysql -u USUARIO -p NOME_DO_BANCO < sql/schema.sql"
  echo "  4. php api/setup.php \"Seu Nome\" email senha         # cria o usuário Master"
  echo "  5. php -S localhost:8000"
  exit 0
fi

# ---------- atualização ----------
if [ -d "$PWD/.git" ]; then ALVO="$PWD"; else ALVO="$DIR"; fi
cd "$ALVO"
ALVO="$(pwd)"

# não mexe em nada se houver alterações suas em arquivos versionados (os protegidos ficam de fora)
EXCLUI=(); for f in "${PROTEGIDOS[@]}"; do EXCLUI+=(":(exclude)$f"); done
if [ -n "$(git status --porcelain --untracked-files=no -- . "${EXCLUI[@]}")" ]; then
  echo "Há alterações locais em arquivos versionados:" >&2
  git status --short --untracked-files=no -- . "${EXCLUI[@]}" >&2
  erro "salve (commit) ou descarte essas alterações e rode de novo. Nada foi alterado."
fi

# a configuração do banco deve estar fora do git; se não estiver, avisa
for f in "${PROTEGIDOS[@]}"; do
  if git ls-files --error-unmatch "$f" >/dev/null 2>&1; then
    echo "AVISO: '$f' está versionado no git; ele será preservado mesmo assim." >&2
  fi
done

# guarda os arquivos protegidos FORA da pasta e devolve ao sair (mesmo se algo falhar)
BKP="$(mktemp -d)"
restaurar() {
  local f
  for f in "${PROTEGIDOS[@]}"; do
    if [ -f "$BKP/$(echo "$f" | tr / _)" ]; then
      mkdir -p "$(dirname "$ALVO/$f")"
      mv -f "$BKP/$(echo "$f" | tr / _)" "$ALVO/$f"
    fi
  done
  rmdir "$BKP" 2>/dev/null || true
}
trap restaurar EXIT
for f in "${PROTEGIDOS[@]}"; do
  [ -f "$f" ] && mv "$f" "$BKP/$(echo "$f" | tr / _)" && info "Configuração local preservada: $f"
done

ANTES="$(git rev-parse HEAD)"
info "Buscando '$BRANCH' em origin"
git fetch origin "$BRANCH"
git checkout "$BRANCH" 2>/dev/null || git checkout -b "$BRANCH" "origin/$BRANCH"
git merge --ff-only "origin/$BRANCH" || erro "seu branch local divergiu de origin/$BRANCH (há commits locais). Resolva manualmente."
DEPOIS="$(git rev-parse HEAD)"

restaurar
trap - EXIT

echo
if [ "$ANTES" = "$DEPOIS" ]; then info "Já estava atualizado ($(git rev-parse --short HEAD))."
else info "Atualizado: $(git rev-parse --short "$ANTES") -> $(git rev-parse --short "$DEPOIS")"; git --no-pager log --oneline "$ANTES..$DEPOIS" | head -20; fi

[ -f api/config.local.php ] || echo "AVISO: api/config.local.php não existe. Copie api/config.example.php e ajuste o banco."
if [ "$ANTES" != "$DEPOIS" ] && ! git diff --quiet "$ANTES" "$DEPOIS" -- sql/; then
  echo
  echo "ATENÇÃO: os arquivos em sql/ mudaram. O banco NÃO foi alterado. Para aplicar (seguro, pode repetir):"
  echo "  mysql -u USUARIO -p NOME_DO_BANCO < sql/schema.sql"
fi
