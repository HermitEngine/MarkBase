#!/usr/bin/env bash

# MarkBase full-run curl test configuration.
# Override any value by exporting it before running tests/full-run.sh.

# Base URL to the wiki deployment path (no trailing slash required).
# Examples:
# - http://your-server/wiki
# - http://127.0.0.1:8080/wiki
MARKBASE_BASE_URL="${MARKBASE_BASE_URL:-http://127.0.0.1:8080/wiki}"

# Curl binary path.
MARKBASE_CURL_BIN="${MARKBASE_CURL_BIN:-curl}"

# Per-request curl timeout in seconds.
MARKBASE_TIMEOUT_SECONDS="${MARKBASE_TIMEOUT_SECONDS:-20}"

# Set to 1 for self-signed TLS endpoints.
MARKBASE_INSECURE_TLS="${MARKBASE_INSECURE_TLS:-0}"

# Prefix used for temporary test documents/folders.
MARKBASE_TEST_PREFIX="${MARKBASE_TEST_PREFIX:-markbase-full-run}"

# Set to 1 to keep temporary fixtures when a test fails.
MARKBASE_KEEP_FIXTURES_ON_FAIL="${MARKBASE_KEEP_FIXTURES_ON_FAIL:-0}"
