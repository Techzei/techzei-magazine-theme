#!/usr/bin/env bash
set -euo pipefail

theme_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
expected_version=""
output=""

while [[ $# -gt 0 ]]; do
	case "$1" in
		--version)
			expected_version="$2"
			shift 2
			;;
		--output)
			output="$2"
			shift 2
			;;
		*)
			echo "Unknown argument: $1" >&2
			exit 2
			;;
	esac
done

metadata_version="$(php "$theme_root/scripts/validate-theme.php" --print-version)"
if [[ -n "$expected_version" && "$expected_version" != "$metadata_version" ]]; then
	echo "Requested package version $expected_version does not match theme metadata $metadata_version." >&2
	exit 1
fi

if [[ -z "$output" ]]; then
	output="$theme_root/techzei-magazine-theme-${metadata_version}.zip"
fi

case "$output" in
	/*) output_path="$output" ;;
	*) output_path="$(pwd)/$output" ;;
esac

mkdir -p "$(dirname "$output_path")"
stage_dir="$(mktemp -d)"
trap 'rm -rf "$stage_dir"' EXIT

package_dir="$stage_dir/techzei-magazine-theme"
mkdir -p "$package_dir"
rsync -a --delete \
	--exclude '.git' \
	--exclude '.github' \
	--exclude 'dist' \
	--exclude '*.zip' \
	--exclude 'tests/***' \
	--exclude 'scripts/fixtures/***' \
	--exclude 'scripts/test-theme.php' \
	--exclude 'scripts/validate-settings.php' \
	--exclude 'scripts/package-theme.sh' \
	--exclude '.DS_Store' \
	"$theme_root/" "$package_dir/"

(
	cd "$stage_dir"
	rm -f "$output_path"
	zip -qr "$output_path" techzei-magazine-theme
)

unzip -tq "$output_path"
zip_entries="$(unzip -Z1 "$output_path")"
top_levels="$(printf '%s\n' "$zip_entries" | awk -F/ 'NF { print $1 }' | sort -u)"
if [[ "$top_levels" != "techzei-magazine-theme" ]]; then
	echo "ZIP must contain exactly one top-level techzei-magazine-theme folder." >&2
	exit 1
fi
if ! printf '%s\n' "$zip_entries" | grep -qx 'techzei-magazine-theme/style.css'; then
	echo "ZIP is missing techzei-magazine-theme/style.css." >&2
	exit 1
fi

echo "$output_path"
