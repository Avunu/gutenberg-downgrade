# The Gutenberg plugin release this plugin downgrades the editor to.
#
# This is the ONE version pin in the repository, and it is deliberately not a
# Composer or npm dependency: the `wp-*` IIFE bundles only exist in the
# wordpress.org plugin zip (the npm packages are ESM/CJS), wpackagist's mirror is
# dist-only (composition-c4 cannot fetch it), and no dependency bot can ever
# "helpfully" bump it. Change `version` and `hash` together.
{ fetchzip }:
let
  version = "11.9.1";
in
fetchzip {
  name = "gutenberg-plugin-${version}";
  url = "https://downloads.wordpress.org/plugin/gutenberg.${version}.zip";
  # Hash of the unpacked tree (fetchzip strips the single `gutenberg/` root).
  hash = "sha256-DHE8ciC31MPx5x5D057EINauLjUBv9vTu5wIN5B2j0U=";
  passthru = {
    inherit version;
    # sha256 of the zip file itself, for SOURCE.txt / provenance.
    zipSha256 = "80b88509dcf2910531bc9ae9ab22dc8a4d5cbe0c55fdb85fa664b6c3528acc11";
  };
}
