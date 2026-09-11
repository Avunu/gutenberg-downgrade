# assets/gutenberg/: the subset of the Gutenberg release the plugin serves.
#
#   build/            every package bundle (index.min.js + index.min.asset.php,
#                     unminified index.js for SCRIPT_DEBUG, stylesheets, per-block
#                     block.json + CSS + view scripts, the dynamic-block PHP)
#   vendor/           React 18.3.1 UMD builds and the JSX runtime
#   manifest.php      generated inventory read by GutenbergDowngrade\Manifest
#   SOURCE.txt        provenance
#
# The release's lib/ (the 18.5 plugin's own PHP: the 6.6 compat layer, global
# styles, experiments) is exactly what clashes with modern core, so it is not
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

    mkdir -p "$out"
    cp -r ${gutenbergRelease}/build "$out/build"
    chmod -R u+w "$out/build"

    # Development-only and script-module plumbing the plugin never serves:
    # react-refresh (hot reload), the import-map polyfill, and the source maps
    # (19 MB the browser only fetches with DevTools open).
    rm -rf "$out/build/react-refresh-entry" "$out/build/react-refresh-runtime" "$out/build/modules"
    find "$out/build" -name '*.map' -delete

    # gutenberg_register_vendor_scripts() serves React from build/vendors/;
    # keep it apart from the package bundles under the name the manifest uses.
    mv "$out/build/vendors" "$out/vendor"

    for f in react.min.js react-dom.min.js; do
      grep -q '18\.3\.1' "$out/vendor/$f" \
        || { echo "$f is not React 18.3.1" >&2; exit 1; }
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

    Only build/ (minus source maps and development-only bundles) and the React
    18.3.1 vendor files are included; see nix/gutenberg-assets.nix in
    https://github.com/Avunu/gutenberg-downgrade.
    EOF
  ''
