# The Gutenberg plugin release this plugin downgrades the editor to.
#
# This is the ONE version pin in the repository, and it is deliberately not a
# Composer or npm dependency: the `wp-*` IIFE bundles only exist in the
# wordpress.org plugin zip (the npm packages are ESM/CJS), wpackagist's mirror is
# dist-only (composition-c4 cannot fetch it), and no dependency bot can ever
# "helpfully" bump it. Change `version` and `hash` together.
{ fetchzip }:
let
  version = "18.5.0";
in
fetchzip {
  name = "gutenberg-plugin-${version}";
  url = "https://downloads.wordpress.org/plugin/gutenberg.${version}.zip";
  # Hash of the unpacked tree (fetchzip strips the single `gutenberg/` root).
  hash = "sha256-Euhm20y6uI0DqUrtnr1jbmVTueg6m/ftspKQe0PNHZo=";
  passthru = {
    inherit version;
    # sha256 of the zip file itself, for SOURCE.txt / provenance.
    zipSha256 = "3e17d219ddd5b11cb436a2a2661fd88de7cd7315b4d927a82c5e2c73c74db23a";
  };
}
