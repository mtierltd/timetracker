#!/usr/bin/env bash
# Version checks for releases (see RELEASING.md).
#
#   check-version.sh consistent        info.xml and js/package.json have the same
#                                      MAJOR.MINOR.PATCH version
#   check-version.sh tag <tag>         <tag> is "v" + that version
#   check-version.sh bump <base-ref>   if the version differs from the one on
#                                      <base-ref>, it is higher than that one and
#                                      than the newest v* tag
#
# Run from the repository root. Exits non-zero with a message on failure.
set -euo pipefail

info_version() {
	# the app's own <version>, not the <nextcloud min-version> attributes
	sed -n 's:.*<version>\(.*\)</version>.*:\1:p' "${1:-appinfo/info.xml}" | head -n 1
}

package_version() {
	sed -n 's/^  "version": "\(.*\)",$/\1/p' js/package.json | head -n 1
}

is_semver() {
	[[ "$1" =~ ^(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)$ ]]
}

# true if $1 > $2 (both MAJOR.MINOR.PATCH)
greater() {
	[ "$1" != "$2" ] && [ "$(printf '%s\n%s\n' "$1" "$2" | sort -V | tail -n 1)" = "$1" ]
}

fail() {
	echo "::error::$*" >&2
	exit 1
}

version=$(info_version)
[ -n "$version" ] || fail "No <version> found in appinfo/info.xml"

case "${1:-}" in
	consistent)
		is_semver "$version" || fail "appinfo/info.xml version '$version' isn't MAJOR.MINOR.PATCH"
		pkg=$(package_version)
		[ "$pkg" = "$version" ] || fail "js/package.json version '$pkg' doesn't match appinfo/info.xml version '$version'"
		echo "Version $version (info.xml and package.json)"
		;;
	tag)
		tag="${2:?usage: check-version.sh tag <tag>}"
		[ "$tag" = "v$version" ] || fail "Tag '$tag' doesn't match appinfo/info.xml version '$version' (expected 'v$version')"
		echo "Tag $tag matches version $version"
		;;
	bump)
		base="${2:?usage: check-version.sh bump <base-ref>}"
		base_version=$(git show "$base:appinfo/info.xml" | sed -n 's:.*<version>\(.*\)</version>.*:\1:p' | head -n 1)
		if [ "$version" = "$base_version" ]; then
			echo "Version unchanged ($version)"
			exit 0
		fi
		greater "$version" "$base_version" || fail "Version $version isn't higher than $base_version on $base"
		latest_tag=$(git tag --list 'v*' | sed 's/^v//' | grep -E '^[0-9]+\.[0-9]+\.[0-9]+$' | sort -V | tail -n 1 || true)
		if [ -n "$latest_tag" ]; then
			greater "$version" "$latest_tag" || fail "Version $version isn't higher than the newest tag v$latest_tag"
		fi
		echo "Version $base_version -> $version (newest tag: v${latest_tag:-none})"
		;;
	*)
		echo "usage: check-version.sh consistent | tag <tag> | bump <base-ref>" >&2
		exit 2
		;;
esac
