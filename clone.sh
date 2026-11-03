#!/bin/bash
# GYMFLEX - traz o código do GitHub (clona na primeira vez, atualiza nas seguintes)
# SEM alterar a configuração local do banco (api/config.local.php).
#
# Uso:  ./clone.sh [branch] [pasta]
#   branch  padrão: main
#   pasta   padrão: gymflex (só usada quando ainda não existe o repositório)
#
# O script fica na raiz do projeto e sempre atualiza o repositório em que ele está, não importa de
# qual pasta você o execute. Se for copiado para fora de um repositório, clona em ./pasta.
# Variável opcional: REPO_URL (padrão: https://github.com/talesCPV/gymflex.git)

set -euo pipefail

REPO_URL="${REPO_URL:-https://github.com/talesCPV/gymflex.git}"
BRANCH="${1:-main}"
DIR="${2:-gymflex}"

# configuração local do banco: se existir (e for sua), nunca é sobrescrita; se não existir, vem do repositório
# (caminhos relativos à raiz do repositório)
PROTEGIDOS=("api/config.local.php" "api/config.php")

erro() { echo "ERRO: $*" >&2; exit 1; }
info() { echo "==> $*"; }

command -v git >/dev/null || erro "git não encontrado"

# ---------- clone (primeira vez) ----------
# raiz do projeto = pasta onde este script está (se for um repositório); senão ./pasta, se já existir
AQUI="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
RAIZ=""
if [ -d "$AQUI/.git" ]; then RAIZ="$AQUI"
elif [ -d "$DIR/.git" ]; then RAIZ="$(cd "$DIR" && pwd)"; fi

if [ -z "$RAIZ" ]; then
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
ALVO="$RAIZ"
cd "$ALVO"

# identidade só para o stash funcionar mesmo sem git configurado (não altera sua configuração)
GITID=(-c "user.name=$(git config user.name || echo clone.sh)" -c "user.email=$(git config user.email || echo clone@localhost)")
EXCLUI=(); for f in "${PROTEGIDOS[@]}"; do EXCLUI+=(":(exclude)$f"); done

BKP="$(mktemp -d)"
STASHED=0
RESULTADO_ALTERACOES=""

restaurar_protegidos() {
  local f
  for f in "${PROTEGIDOS[@]}"; do
    if [ -f "$BKP/$(echo "$f" | tr / _)" ]; then
      mkdir -p "$(dirname "$ALVO/$f")"
      mv -f "$BKP/$(echo "$f" | tr / _)" "$ALVO/$f"
    fi
  done
  rmdir "$BKP" 2>/dev/null || true
}
# devolve as suas alterações locais; se conflitarem com a atualização, deixa o arquivo na versão nova
# e mantém as suas guardadas no stash (nada é perdido)
devolver_alteracoes() {
  [ "$STASHED" = 1 ] || return 0
  STASHED=0
  if git "${GITID[@]}" stash pop -q >/dev/null 2>&1; then RESULTADO_ALTERACOES="ok"
  else RESULTADO_ALTERACOES="conflito"; git reset -q; git checkout -q -- .; fi
}
finalizar() { devolver_alteracoes; restaurar_protegidos; }
trap finalizar EXIT   # roda mesmo se algo falhar no meio

PRESERVOU=0
for f in "${PROTEGIDOS[@]}"; do
  VERSIONADO=0; git ls-files --error-unmatch "$f" >/dev/null 2>&1 && VERSIONADO=1
  if [ -f "$f" ]; then
    if [ "$VERSIONADO" = 1 ] && git diff --quiet HEAD -- "$f"; then
      :   # igual ao do repositório (nunca foi personalizado): segue a atualização normal
    else
      mv "$f" "$BKP/$(echo "$f" | tr / _)"; PRESERVOU=1; info "Configuração local preservada: $f"
    fi
  elif [ "$VERSIONADO" = 1 ]; then
    info "$f não existe aqui; será trazido do repositório."
  fi
  # arquivo versionado: deixa a versão do repositório no lugar, limpa, para a atualização não conflitar
  # (é assim que um arquivo ausente "volta"); o seu, se existia, é devolvido por cima no final
  if [ "$VERSIONADO" = 1 ]; then git checkout -q -- "$f" 2>/dev/null || true; fi
done

# suas edições em arquivos versionados NÃO impedem a atualização: ficam de lado e voltam depois
if [ -n "$(git status --porcelain --untracked-files=no -- . "${EXCLUI[@]}")" ]; then
  echo "Você tem alterações locais em arquivos versionados:"
  git status --short --untracked-files=no -- . "${EXCLUI[@]}"
  git "${GITID[@]}" stash push -q -m "clone.sh: alterações locais $(date '+%F %T')" -- . "${EXCLUI[@]}" && STASHED=1
  info "Guardadas temporariamente; serão reaplicadas depois da atualização."
fi

ANTES="$(git rev-parse HEAD)"
info "Buscando '$BRANCH' em origin"
git fetch origin "$BRANCH"
# uma cópia solta do clone.sh dentro do repositório (não versionada) impediria o checkout
if [ -f clone.sh ] && ! git ls-files --error-unmatch clone.sh >/dev/null 2>&1 && git cat-file -e "origin/$BRANCH:clone.sh" 2>/dev/null; then
  rm -f clone.sh; info "Cópia solta do clone.sh substituída pela versão do repositório."
fi
git checkout "$BRANCH" 2>/dev/null || git checkout -b "$BRANCH" "origin/$BRANCH"
git merge --ff-only "origin/$BRANCH" || erro "não foi possível avançar para origin/$BRANCH (veja a mensagem do git acima: commits locais divergentes ou arquivos seus que seriam sobrescritos). Suas alterações e o seu config foram devolvidos."
DEPOIS="$(git rev-parse HEAD)"

devolver_alteracoes
restaurar_protegidos
trap - EXIT

echo
if [ "$ANTES" = "$DEPOIS" ]; then info "Já estava atualizado ($(git rev-parse --short HEAD))."
else info "Atualizado: $(git rev-parse --short "$ANTES") -> $(git rev-parse --short "$DEPOIS")"; git --no-pager log --oneline "$ANTES..$DEPOIS" | head -20; fi

case "$RESULTADO_ALTERACOES" in
  ok) info "Suas alterações locais foram reaplicadas." ;;
  conflito)
    echo
    echo "ATENÇÃO: suas alterações locais conflitam com a atualização. Os arquivos ficaram na versão NOVA do repositório"
    echo "e as suas edições estão guardadas (nada foi perdido). Para vê-las ou recuperá-las:"
    echo "  git stash list                 # procure 'clone.sh: alterações locais'"
    echo "  git stash show -p              # ver o que você tinha alterado"
    echo "  git stash pop                  # reaplicar e resolver o conflito à mão"
    echo "(O api/config.php e o api/config.local.php nunca entram nesse conflito: são preservados.)" ;;
esac

[ "$PRESERVOU" = 1 ] || [ -f api/config.local.php ] || echo "AVISO: o banco ainda não está configurado: ajuste api/config.php ou copie api/config.example.php para api/config.local.php."
if [ "$ANTES" != "$DEPOIS" ] && ! git diff --quiet "$ANTES" "$DEPOIS" -- sql/; then
  echo
  echo "ATENÇÃO: os arquivos em sql/ mudaram. O banco NÃO foi alterado. Para aplicar (seguro, pode repetir):"
  echo "  mysql -u USUARIO -p NOME_DO_BANCO < sql/schema.sql"
fi
