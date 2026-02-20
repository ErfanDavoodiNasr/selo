#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TARGET_DIR="${1:-$ROOT_DIR}"
OWNER="${2:-}"
GROUP="${3:-}"

if [[ ! -d "$TARGET_DIR" ]]; then
  echo "Target directory does not exist: $TARGET_DIR" >&2
  exit 1
fi

echo "Applying permissions in: $TARGET_DIR"

# Safe defaults for shared hosting.
find "$TARGET_DIR" -type d -exec chmod 755 {} +
find "$TARGET_DIR" -type f -exec chmod 644 {} +

# Runtime writable directories for app.
for dir in \
  "$TARGET_DIR/config" \
  "$TARGET_DIR/storage" \
  "$TARGET_DIR/storage/logs" \
  "$TARGET_DIR/storage/uploads" \
  "$TARGET_DIR/storage/uploads/media"; do
  if [[ -d "$dir" ]]; then
    chmod 775 "$dir"
  fi
done

# Keep script files executable when present.
if [[ -d "$TARGET_DIR/scripts" ]]; then
  find "$TARGET_DIR/scripts" -type f -name "*.sh" -exec chmod 755 {} +
fi

# Optional ownership fix (for VPS/dedicated; usually unavailable on shared hosting).
if [[ -n "$OWNER" && -n "$GROUP" ]]; then
  chown -R "$OWNER:$GROUP" "$TARGET_DIR"
fi

echo "Permission fix completed."
