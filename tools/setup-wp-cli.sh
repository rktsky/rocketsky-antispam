#!/usr/bin/env bash
# Install the original WP-CLI 2.12.0 into this disposable CI job. Since 0.5.2.
# The immutable official build matches the digest published for release v2.12.0.
# Verify bytes before granting execution or adding the tool to the job PATH.
set -euo pipefail

: "${RUNNER_TEMP:?A disposable GitHub runner directory is required.}"
: "${GITHUB_PATH:?The native GitHub PATH handoff file is required.}"
tool_directory=$(mktemp -d "$RUNNER_TEMP/rocketsky-wpcli.XXXXXX")
tool_file="$tool_directory/wp"
tool_url='https://raw.githubusercontent.com/wp-cli/builds/ad68e4ad3b29c3d3bdb361e5ed9b37e709123a9b/phar/wp-cli.phar'
tool_sha256='ce34ddd838f7351d6759068d09793f26755463b4a4610a5a5c0a97b68220d85c'

curl --fail --silent --show-error --location --retry 3 --retry-delay 1 \
	--connect-timeout 15 --max-time 120 --output "$tool_file" "$tool_url"
php -r 'exit(hash_equals($argv[2], hash_file("sha256", $argv[1])) ? 0 : 1);' "$tool_file" "$tool_sha256"
chmod 0755 "$tool_file"
test "$("$tool_file" --version)" = 'WP-CLI 2.12.0'
printf '%s\n' "$tool_directory" >> "$GITHUB_PATH"
printf '%s\n' 'Verified official WP-CLI 2.12.0 is ready.'
