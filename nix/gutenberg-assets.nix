# assets/gutenberg/: the subset of the Gutenberg release the plugin serves.
#
#   build/            every package bundle (index.min.js + index.min.asset.php,
#                     unminified index.js for SCRIPT_DEBUG, stylesheets, per-block
#                     block.json + CSS + view scripts, the dynamic-block PHP)
#   vendor/           React 17.0.1 UMD builds, renamed from their hashed names
#   manifest.php      generated inventory read by GutenbergDowngrade\Manifest
#   SOURCE.txt        provenance
#
# The release's lib/ (the 11.9 plugin's own PHP: FSE, global styles, REST
# shims, experiments) is exactly what clashes with modern core, so it is not
# copied. The plugin's src/ reimplements the parts that still matter.
{
  runCommand,
  php,
  gutenbergRelease,
  pluginSrc,
}:
runCommand "gutenberg-downgrade-assets-${gutenbergRelease.version}"
  {
    nativeBuildInputs = [ php ];
    inherit (gutenbergRelease) version;
    zipSha256 = gutenbergRelease.passthru.zipSha256;
  }
  ''
    set -euo pipefail

    mkdir -p "$out/vendor"
    cp -r ${gutenbergRelease}/build "$out/build"
    chmod -R u+w "$out/build"

    # gutenberg_register_vendor_script() cached the unpkg UMD files under
    # vendor/<handle>[.min].<md5-prefix>.js; stable names keep the manifest simple.
    pick() {
      local pattern="$1" target="$2"
      local matches
      matches=$(ls ${gutenbergRelease}/vendor/ | grep -E "$pattern" || true)
      [ "$(echo "$matches" | grep -c .)" -eq 1 ] \
        || { echo "expected exactly one vendor file matching $pattern, got: $matches" >&2; exit 1; }
      cp "${gutenbergRelease}/vendor/$matches" "$out/vendor/$target"
    }
    pick '^react\.min\.[0-9a-f]+\.js$'     react.min.js
    pick '^react\.[0-9a-f]+\.js$'          react.js
    pick '^react-dom\.min\.[0-9a-f]+\.js$' react-dom.min.js
    pick '^react-dom\.[0-9a-f]+\.js$'      react-dom.js

    for f in react.min.js react-dom.min.js; do
      grep -q 'React v17\.0\.1' "$out/vendor/$f" \
        || { echo "$f is not React 17.0.1" >&2; exit 1; }
    done

    php ${pluginSrc}/bin/generate-manifest.php \
      --assets "$out" \
      --gutenberg-version "$version" \
      --out "$out/manifest.php"

    cat > "$out/SOURCE.txt" <<EOF
    Gutenberg plugin $version
    https://downloads.wordpress.org/plugin/gutenberg.$version.zip
    zip sha256: $zipSha256
    Source: https://github.com/WordPress/gutenberg/tree/v$version
    License: GPL-2.0-or-later (see the plugin's LICENSE)

    Only build/ and the React 17.0.1 vendor files are included; see
    nix/gutenberg-assets.nix in https://github.com/Avunu/gutenberg-downgrade.
    EOF
  ''
