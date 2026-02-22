#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
DEFAULT_CONFIG_FILE="${SCRIPT_DIR}/config.sh"
CONFIG_FILE="${MARKBASE_TEST_CONFIG:-$DEFAULT_CONFIG_FILE}"

if [[ ! -f "$CONFIG_FILE" ]]; then
    echo "[full-run][FAIL] Missing config file: $CONFIG_FILE" >&2
    exit 1
fi

# shellcheck disable=SC1090
source "$CONFIG_FILE"

BASE_URL="${MARKBASE_BASE_URL:-http://127.0.0.1:8080/wiki}"
BASE_URL="${BASE_URL%/}"
CURL_BIN="${MARKBASE_CURL_BIN:-curl}"
TIMEOUT_SECONDS="${MARKBASE_TIMEOUT_SECONDS:-20}"
INSECURE_TLS="${MARKBASE_INSECURE_TLS:-0}"
TEST_PREFIX="${MARKBASE_TEST_PREFIX:-markbase-full-run}"
KEEP_FIXTURES_ON_FAIL="${MARKBASE_KEEP_FIXTURES_ON_FAIL:-0}"

CURL_ARGS=(-sS --connect-timeout "$TIMEOUT_SECONDS" --max-time "$TIMEOUT_SECONDS")
if [[ "$INSECURE_TLS" == "1" ]]; then
    CURL_ARGS+=(-k)
fi

TMP_DIR="$(mktemp -d)"
LAST_HEADERS=""
LAST_BODY=""
LAST_CODE=""
STEP_NO=0
RUN_FAILED=0
TEST_ROOT=""
TEST_ROOT_CREATED=0

log() {
    printf '[full-run] %s\n' "$*"
}

fail() {
    RUN_FAILED=1
    printf '[full-run][FAIL] %s\n' "$*" >&2
    exit 1
}

url_for() {
    local script="$1"
    script="${script#/}"
    printf '%s/%s' "$BASE_URL" "$script"
}

new_capture_files() {
    LAST_HEADERS="${TMP_DIR}/headers.$RANDOM.$RANDOM"
    LAST_BODY="${TMP_DIR}/body.$RANDOM.$RANDOM"
}

http_get() {
    local script="$1"
    shift || true
    new_capture_files
    local query_args=()
    while (($# > 0)); do
        query_args+=(--data-urlencode "$1")
        shift
    done
    LAST_CODE="$("$CURL_BIN" "${CURL_ARGS[@]}" -D "$LAST_HEADERS" -o "$LAST_BODY" -w '%{http_code}' --get "$(url_for "$script")" "${query_args[@]}")"
}

http_post() {
    local script="$1"
    shift || true
    new_capture_files
    local form_args=()
    while (($# > 0)); do
        form_args+=(--data-urlencode "$1")
        shift
    done
    LAST_CODE="$("$CURL_BIN" "${CURL_ARGS[@]}" -D "$LAST_HEADERS" -o "$LAST_BODY" -w '%{http_code}' -X POST "$(url_for "$script")" "${form_args[@]}")"
}

http_post_upload_files() {
    local script="$1"
    local upload_path="$2"
    shift 2 || true
    new_capture_files
    local file_args=()
    while (($# > 0)); do
        file_args+=(-F "files[]=@$1")
        shift
    done
    LAST_CODE="$("$CURL_BIN" "${CURL_ARGS[@]}" -D "$LAST_HEADERS" -o "$LAST_BODY" -w '%{http_code}' -X POST "$(url_for "$script")" -F "path=$upload_path" "${file_args[@]}")"
}

header_value() {
    local wanted="$1"
    awk -v key="$(printf '%s' "$wanted" | tr '[:upper:]' '[:lower:]')" '
        {
            gsub(/\r$/, "", $0);
            split($0, parts, ":");
            if (tolower(parts[1]) == key) {
                sub(/^[^:]+:[[:space:]]*/, "", $0);
                print $0;
                exit;
            }
        }
    ' "$LAST_HEADERS"
}

expect_code() {
    local expected="$1"
    if [[ "$LAST_CODE" != "$expected" ]]; then
        fail "Expected HTTP $expected, got $LAST_CODE"
    fi
}

expect_body_contains() {
    local needle="$1"
    if ! grep -Fq -- "$needle" "$LAST_BODY"; then
        fail "Response body missing: $needle"
    fi
}

expect_body_not_contains() {
    local needle="$1"
    if grep -Fq -- "$needle" "$LAST_BODY"; then
        fail "Response body should not contain: $needle"
    fi
}

expect_header_contains() {
    local needle="$1"
    if ! grep -Fqi -- "$needle" "$LAST_HEADERS"; then
        fail "Response headers missing: $needle"
    fi
}

expect_location_contains() {
    local needle="$1"
    local location
    location="$(header_value "Location")"
    if [[ -z "$location" ]]; then
        fail "Response missing Location header"
    fi
    if [[ "$location" != *"$needle"* ]]; then
        fail "Location header missing '$needle': $location"
    fi
}

expect_filter_has_path() {
    local expected="$1"
    if ! php -r '
        $data = json_decode(file_get_contents($argv[1]), true);
        if (!is_array($data) || !isset($data["paths"]) || !is_array($data["paths"])) {
            exit(2);
        }
        exit(in_array($argv[2], $data["paths"], true) ? 0 : 1);
    ' "$LAST_BODY" "$expected"; then
        fail "Filter result missing expected path: $expected"
    fi
}

expect_filter_not_has_path() {
    local expected="$1"
    if ! php -r '
        $data = json_decode(file_get_contents($argv[1]), true);
        if (!is_array($data) || !isset($data["paths"]) || !is_array($data["paths"])) {
            exit(2);
        }
        exit(in_array($argv[2], $data["paths"], true) ? 1 : 0);
    ' "$LAST_BODY" "$expected"; then
        fail "Filter result should not include path: $expected"
    fi
}

expect_upload_ok() {
    if ! php -r '
        $data = json_decode(file_get_contents($argv[1]), true);
        if (!is_array($data)) {
            exit(2);
        }
        exit(($data["ok"] ?? false) === true ? 0 : 1);
    ' "$LAST_BODY"; then
        fail "Upload response did not return ok=true"
    fi
}

expect_upload_has_path() {
    local expected="$1"
    if ! php -r '
        $data = json_decode(file_get_contents($argv[1]), true);
        if (!is_array($data) || !isset($data["uploaded"]) || !is_array($data["uploaded"])) {
            exit(2);
        }
        exit(in_array($argv[2], $data["uploaded"], true) ? 0 : 1);
    ' "$LAST_BODY" "$expected"; then
        fail "Upload response missing expected uploaded path: $expected"
    fi
}

step() {
    STEP_NO=$((STEP_NO + 1))
    log "[$STEP_NO] $*"
}

create_doc() {
    local path="$1"
    local name="$2"
    http_post "create.php" "path=$path" "name=$name"
    expect_code "302"
    expect_location_contains "edit.php?path="
}

save_doc() {
    local path="$1"
    local content="$2"
    http_post "save.php" "path=$path" "content=$content"
    expect_code "302"
    expect_location_contains "view.php?path="
}

view_doc() {
    local path="$1"
    http_get "view.php" "path=$path"
    expect_code "200"
}

delete_doc_best_effort() {
    local path="$1"
    set +e
    "$CURL_BIN" "${CURL_ARGS[@]}" -o /dev/null -X POST "$(url_for "delete.php")" --data-urlencode "path=$path" >/dev/null 2>&1
    set -e
}

cleanup() {
    local exit_code=$?
    if [[ "$TEST_ROOT_CREATED" == "1" && -n "$TEST_ROOT" ]]; then
        if [[ $exit_code -eq 0 || "$KEEP_FIXTURES_ON_FAIL" != "1" ]]; then
            delete_doc_best_effort "$TEST_ROOT"
        else
            log "Keeping test fixtures because MARKBASE_KEEP_FIXTURES_ON_FAIL=1: $TEST_ROOT"
        fi
    fi
    rm -rf "$TMP_DIR"
    exit $exit_code
}
trap cleanup EXIT

RUN_ID="$(date +%Y%m%d%H%M%S)-$$"
SEARCH_TOKEN="searchtoken${RANDOM}${RANDOM}"
MOVE_TOKEN="movetoken${RANDOM}${RANDOM}"
TEST_ROOT="${TEST_PREFIX}-${RUN_ID}"
TEST_ROOT_CREATED=1

PAGE_A="${TEST_ROOT}/PageA"
PAGE_A_RENAMED="${TEST_ROOT}/PageARenamed"
TARGET_PAGE="${TEST_ROOT}/Target"
SUB_TARGET_PAGE="${TEST_ROOT}/Sub/Target"
FOLDER_README_DIR="${TEST_ROOT}/FolderReadme"
LISTING_DIR="${TEST_ROOT}/ListDir"
MOVE_SRC="${TEST_ROOT}/MoveSrc"
MOVE_DST="${TEST_ROOT}/MoveDst"
UPLOAD_DIR="${TEST_ROOT}/Uploads"
UPLOAD_ONE_PATH="${UPLOAD_DIR}/UploadOne"
UPLOAD_TWO_PATH="${UPLOAD_DIR}/UploadTwo"

step "Home page renders with logo/title/footer"
http_get "index.php"
expect_code "200"
expect_body_contains "MarkBase"
expect_body_contains "Icons by"
expect_body_contains "/img-internal/MarkBase.png"

step "Home page hides Move/Delete actions"
expect_body_not_contains "class=\"icon-link move-link js-move-page\""
expect_body_not_contains "class=\"icon-link delete-link js-delete-page\""

step "Home page includes Upload action"
expect_body_contains "class=\"icon-link upload-link js-upload-page\""

step "Footer is visible on edit screen and old rename section is absent"
http_get "edit.php"
expect_code "200"
expect_body_contains "name=\"content\""
expect_body_contains "Icons by"
expect_body_not_contains "Rename/Move"

step "Core static assets are reachable"
http_get "style.css"
expect_code "200"
expect_body_contains ".app-footer"
http_get "img-internal/icons8-search.svg"
expect_code "200"
expect_header_contains "Content-Type: image/"

step "Create baseline pages via create endpoint"
create_doc "$TEST_ROOT" "PageA"
create_doc "$TEST_ROOT" "Target"
create_doc "${TEST_ROOT}/Sub" "Target"

step "Upload endpoint accepts multiple markdown files"
printf '# Upload One\n\nUploaded marker %s\n' "$RUN_ID" > "${TMP_DIR}/UploadOne.md"
printf '# Upload Two\n\nUploaded marker %s\n' "$RUN_ID" > "${TMP_DIR}/UploadTwo.md"
http_post_upload_files "upload.php" "$UPLOAD_DIR" "${TMP_DIR}/UploadOne.md" "${TMP_DIR}/UploadTwo.md"
expect_code "200"
expect_header_contains "Content-Type: application/json"
expect_upload_ok
expect_upload_has_path "$UPLOAD_ONE_PATH"
expect_upload_has_path "$UPLOAD_TWO_PATH"

view_doc "$UPLOAD_ONE_PATH"
expect_body_contains "Uploaded marker ${RUN_ID}"
view_doc "$UPLOAD_TWO_PATH"
expect_body_contains "Uploaded marker ${RUN_ID}"

step "Save markdown content (including wiki links and search token)"
printf -v target_content '# Target\n\nTarget marker for %s\n' "$RUN_ID"
save_doc "$TARGET_PAGE" "$target_content"

printf -v sub_target_content '# Target (Sub)\n\nSub target marker for %s\n' "$RUN_ID"
save_doc "$SUB_TARGET_PAGE" "$sub_target_content"

printf -v page_a_content '# PageA\n\n%s\n\nBacklink: [[%s/Target]]\n\nAmbiguous link: [[Target]]\n' "$SEARCH_TOKEN" "$TEST_ROOT"
save_doc "$PAGE_A" "$page_a_content"

step "View page renders explicit and ambiguous wiki links"
view_doc "$PAGE_A"
expect_body_contains "$SEARCH_TOKEN"
expect_body_contains "Ambiguous link"
expect_body_contains "Backlink:"

step "Disambiguation endpoint returns both matching targets"
http_get "disambiguate.php" "name=Target"
expect_code "200"
expect_body_contains "${TEST_ROOT}/Target"
expect_body_contains "${TEST_ROOT}/Sub/Target"

step "Filter and search endpoints return newly saved content"
http_get "filter.php" "q=PageA"
expect_code "200"
expect_header_contains "Content-Type: application/json"
expect_filter_has_path "$PAGE_A"

http_get "search.php" "q=$SEARCH_TOKEN"
expect_code "200"
expect_body_contains "Search results"
expect_body_contains "PageA"

step "Backlinks are available on target page"
view_doc "$TARGET_PAGE"
expect_body_contains "Backlinks"
expect_body_contains "$PAGE_A"

step "Folder routing uses README.md case-insensitively"
create_doc "$FOLDER_README_DIR" "readme"
printf -v folder_readme_content '# Folder Landing\n\nREADME marker %s\n' "$RUN_ID"
save_doc "${FOLDER_README_DIR}/readme" "$folder_readme_content"
view_doc "$FOLDER_README_DIR"
expect_body_contains "README marker ${RUN_ID}"
expect_body_not_contains "This folder is empty."

step "Folder listing appears when no README exists, and Edit auto-creates README"
create_doc "$LISTING_DIR" "Child"
view_doc "$LISTING_DIR"
expect_body_contains "<h2>Pages</h2>"
expect_body_contains "Child"

http_get "edit.php" "path=$LISTING_DIR"
expect_code "200"
expect_body_contains "_Auto-generated folder index._"

step "Move/Rename page endpoint works and updates search index"
http_post "move.php" "from=$PAGE_A" "path=$TEST_ROOT" "name=PageARenamed"
expect_code "302"
expect_location_contains "PageARenamed"

view_doc "$PAGE_A"
expect_body_contains "Page not found"
view_doc "$PAGE_A_RENAMED"
expect_body_contains "$SEARCH_TOKEN"

http_get "filter.php" "q=PageARenamed"
expect_code "200"
expect_filter_has_path "$PAGE_A_RENAMED"
expect_filter_not_has_path "$PAGE_A"

step "Move folder endpoint moves folder contents recursively"
create_doc "${MOVE_SRC}/Sub" "Child"
printf -v move_child_content '# Move Child\n\n%s\n' "$MOVE_TOKEN"
save_doc "${MOVE_SRC}/Sub/Child" "$move_child_content"

http_post "move.php" "from=$MOVE_SRC" "path=$TEST_ROOT" "name=MoveDst"
expect_code "302"
expect_location_contains "MoveDst"

view_doc "${MOVE_DST}/Sub/Child"
expect_body_contains "$MOVE_TOKEN"
view_doc "${MOVE_SRC}/Sub/Child"
expect_body_contains "Page not found"

step "Delete page endpoint removes the page and search references"
http_post "delete.php" "path=$PAGE_A_RENAMED"
expect_code "302"
view_doc "$PAGE_A_RENAMED"
expect_body_contains "Page not found"

http_get "filter.php" "q=PageARenamed"
expect_code "200"
expect_filter_not_has_path "$PAGE_A_RENAMED"

step "Delete folder endpoint removes folder contents recursively"
http_post "delete.php" "path=$MOVE_DST"
expect_code "302"
view_doc "${MOVE_DST}/Sub/Child"
expect_body_contains "Page not found"

step "Delete top-level test fixture folder"
http_post "delete.php" "path=$TEST_ROOT"
expect_code "302"
expect_location_contains "index.php"
TEST_ROOT_CREATED=0

view_doc "$TEST_ROOT"
expect_body_contains "Page not found"

step "Home page still renders after full run"
http_get "index.php"
expect_code "200"
expect_body_contains "MarkBase"

log "All checks passed."
