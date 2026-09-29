#!/usr/bin/env bash
#
# Builds the test database as a copy of the development one.
#
# The suite reads real records, and every test runs inside a transaction that
# is rolled back, so the copy stays as it was made. Run this again after new
# migrations, or whenever the tests should see newer data.
#
# The target is always "<DB_DATABASE>_test". The script refuses to write to the
# development database itself.

set -euo pipefail
cd "$(dirname "$0")/.."

get() { grep -E "^$1=" .env | head -1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//'; }

SOURCE="$(get DB_DATABASE)"
TARGET="${TEST_DB_DATABASE:-${SOURCE}_test}"

if [ -z "$SOURCE" ] || [ "$TARGET" = "$SOURCE" ]; then
    echo "Refusing to build the test database as \"$TARGET\": that is the application's own database." >&2
    exit 1
fi

# Credentials go through a private options file, not the command line.
CNF="$(mktemp)"
chmod 600 "$CNF"
trap 'rm -f "$CNF"' EXIT
printf '[client]\nuser=%s\npassword=%s\nhost=%s\nport=%s\n' \
    "$(get DB_USERNAME)" "$(get DB_PASSWORD)" "$(get DB_HOST)" "$(get DB_PORT)" > "$CNF"

echo "Copying $SOURCE into $TARGET ..."
mysql --defaults-extra-file="$CNF" -e "DROP DATABASE IF EXISTS \`$TARGET\`; CREATE DATABASE \`$TARGET\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysqldump --defaults-extra-file="$CNF" --single-transaction --no-tablespaces --routines "$SOURCE" \
    | mysql --defaults-extra-file="$CNF" "$TARGET"

# The copy is taken from a database that may itself be behind the code.
DB_DATABASE="$TARGET" php artisan migrate --force

echo "Done: $TARGET"
