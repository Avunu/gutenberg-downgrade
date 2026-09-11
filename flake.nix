{
  description = "Gutenberg Downgrade — load the Gutenberg 11.9.1 block editor on current WordPress";

  inputs = {
    nixpkgs.url = "github:NixOS/nixpkgs/nixos-unstable";
    flake-utils.url = "github:numtide/flake-utils";
    composition-c4.url = "github:fossar/composition-c4";
    git-hooks = {
      url = "github:cachix/git-hooks.nix";
      inputs.nixpkgs.follows = "nixpkgs";
    };
  };

  outputs =
    {
      self,
      nixpkgs,
      flake-utils,
      composition-c4,
      git-hooks,
    }:
    flake-utils.lib.eachDefaultSystem (
      system:
      let
        pkgs = import nixpkgs {
          inherit system;
          overlays = [ composition-c4.overlays.default ];
        };
        inherit (pkgs) lib stdenvNoCC;

        mkPhp =
          base:
          base.buildEnv {
            extensions =
              { enabled, all }:
              enabled
              ++ (with all; [
                curl
                mbstring
                openssl
                tokenizer
                fileinfo
              ]);
            # PHPStan parses the full WordPress stubs; the 128M default is not enough.
            extraConfig = ''
              memory_limit = 2G
              error_reporting = E_ALL
            '';
          };

        # The toolchain runs on the newest supported PHP; php83 exists only to
        # prove the plugin still runs on the declared floor (Requires PHP).
        php = mkPhp pkgs.php84;
        php83 = mkPhp pkgs.php83;

        nodejs = pkgs.nodejs_22;

        # ------------------------------------------------------------------ #
        # Plugin metadata, read once and shared by packages/checks.           #
        # composer.json owns the version (Release Please bumps it); the       #
        # plugin header owns the WordPress compatibility range.               #
        # ------------------------------------------------------------------ #
        composerData = builtins.fromJSON (builtins.readFile ./composer.json);
        inherit (composerData) version;

        pname = "gutenberg-downgrade";
        mainFile = "gutenberg-downgrade.php";
        src = self;

        # Read a plugin header field. The main file is the single source of
        # truth for WordPress compatibility: plugin-update-checker fetches it
        # from the git tag and its headers override everything else.
        pluginHeader =
          field:
          let
            lines = lib.splitString "\n" (builtins.readFile (./. + "/${mainFile}"));
            pattern = "[[:space:]]*\\*[[:space:]]*${field}:[[:space:]]*([^[:space:]]+)[[:space:]]*";
            hit = lib.findFirst (l: builtins.match pattern l != null) null lines;
          in
          if hit == null then
            throw "${mainFile} is missing the plugin header line ' * ${field}: <value>'"
          else
            builtins.head (builtins.match pattern hit);

        # plugin-update-checker's fixSupportedWordpressVersion() silently
        # discards anything that is not bare major.minor, and WordPress then
        # reports "Compatibility: Unknown".
        requireMajorMinor =
          field: value:
          if builtins.match "[0-9]+\\.[0-9]+" value == null then
            throw "${mainFile} '${field}: ${value}' must be bare major.minor (e.g. 7.1)"
          else
            value;

        wpTested = requireMajorMinor "Tested up to" (pluginHeader "Tested up to");
        wpRequires = requireMajorMinor "Requires at least" (pluginHeader "Requires at least");

        # ------------------------------------------------------------------ #
        # The Gutenberg 11.9.1 release and the asset tree assembled from it.  #
        # ------------------------------------------------------------------ #
        gutenbergRelease = pkgs.callPackage ./nix/gutenberg-release.nix { };
        gutenbergAssets = pkgs.callPackage ./nix/gutenberg-assets.nix {
          inherit php gutenbergRelease;
          pluginSrc = src;
        };

        # ------------------------------------------------------------------ #
        # PHP / Composer vendor dependencies.                                 #
        # c4.fetchComposerDeps reads composer.lock per-package — no hash.     #
        # It fetches packages-dev too (the PHPStan WordPress stubs), which     #
        # the checks rely on; `composer install --no-dev` keeps them out of   #
        # the zip.                                                            #
        # ------------------------------------------------------------------ #
        composerDeps = pkgs.c4.fetchComposerDeps { inherit src; };

        # The test runners (PHPUnit, Brain Monkey) live in their own Composer
        # project so they are never fetched for `nix build .#zip`.
        testTools = stdenvNoCC.mkDerivation {
          pname = "${pname}-test-tools";
          inherit version;
          src = ./tests/tools;

          composerDeps = pkgs.c4.fetchComposerDeps {
            lockFile = ./tests/tools/composer.lock;
          };

          nativeBuildInputs = [
            php
            php.packages.composer
            pkgs.c4.composerSetupHook
          ];

          buildPhase = ''
            runHook preBuild
            composer --no-ansi install --no-interaction
            runHook postBuild
          '';

          installPhase = ''
            runHook preInstall
            mkdir -p "$out"
            # -L: c4 installs vendor as symlinks into the store.
            cp -rL vendor "$out/"
            runHook postInstall
          '';
        };

        # ------------------------------------------------------------------ #
        # Final plugin assembly: PHP + composer runtime deps + Gutenberg.      #
        # ------------------------------------------------------------------ #
        pluginPackage = stdenvNoCC.mkDerivation {
          inherit
            pname
            version
            src
            composerDeps
            ;

          nativeBuildInputs = [
            php
            php.packages.composer
            pkgs.c4.composerSetupHook
          ];

          buildPhase = ''
            runHook preBuild
            composer --no-ansi install --no-dev --no-interaction --optimize-autoloader
            runHook postBuild
          '';

          installPhase = ''
            runHook preInstall

            pluginDir="$out/share/wordpress/plugins/${pname}"
            mkdir -p "$pluginDir/assets"

            cp ${mainFile} README.md LICENSE "$pluginDir/"
            # -L dereferences: composition-c4 installs vendor/ as symlinks into the
            # Nix store; the distributable plugin must contain real, self-contained files.
            cp -rL src config vendor "$pluginDir/"
            cp -r assets/js "$pluginDir/assets/js"
            cp -r ${gutenbergAssets} "$pluginDir/assets/gutenberg"
            chmod -R u+w "$pluginDir"

            # A `sed` that matches nothing still exits 0, so guard every header we stamp.
            for header in 'Version' 'Tested up to' 'Requires at least'; do
              grep -qE "^[[:space:]]*\* $header:" "$pluginDir/${mainFile}" \
                || { echo "${mainFile} is missing the '$header:' plugin header line" >&2; exit 1; }
            done

            # composer.json is the single source of truth for the version (Release
            # Please bumps it); WordPress and the update checker read the header.
            sed -i -E "s|^([[:space:]]*\* Version:[[:space:]]*).*|\1${version}|" "$pluginDir/${mainFile}"
            sed -i -E "s|^([[:space:]]*\* Tested up to:[[:space:]]*).*|\1${wpTested}|" "$pluginDir/${mainFile}"
            sed -i -E "s|^([[:space:]]*\* Requires at least:[[:space:]]*).*|\1${wpRequires}|" "$pluginDir/${mainFile}"

            runHook postInstall
          '';

          meta = {
            inherit (composerData) description;
            license = lib.licenses.gpl2Plus;
            platforms = lib.platforms.all;
          };
        };

        pluginDir = "${pluginPackage}/share/wordpress/plugins/${pname}";

        # vendor/ with the dev packages (WordPress stubs, phpstan-wordpress) for
        # the static-analysis checks; the plugin package itself is --no-dev.
        devVendor = stdenvNoCC.mkDerivation {
          pname = "${pname}-dev-vendor";
          inherit version src composerDeps;

          nativeBuildInputs = [
            php
            php.packages.composer
            pkgs.c4.composerSetupHook
          ];

          buildPhase = ''
            runHook preBuild
            composer --no-ansi install --no-interaction
            runHook postBuild
          '';

          installPhase = ''
            runHook preInstall
            mkdir -p "$out"
            cp -rL vendor "$out/"
            runHook postInstall
          '';
        };

        # ------------------------------------------------------------------ #
        # git-hooks.nix: the same gates as CI, before every commit.           #
        # Run by prek (a pre-commit reimplementation: no Python, fast,        #
        # reads the same .pre-commit-config.yaml).                            #
        # Not wired into `checks`: the JS hooks shell out to node_modules,    #
        # which is gitignored; checks.* below cover PHP hermetically.          #
        # ------------------------------------------------------------------ #
        pre-commit-check = git-hooks.lib.${system}.run {
          src = self;
          package = pkgs.prek;
          hooks = {
            oxfmt = {
              enable = true;
              package = null;
              settings.binPath = "./node_modules/.bin/oxfmt";
            };
            oxlint = {
              enable = true;
              package = null;
              settings.binPath = "./node_modules/.bin/oxlint";
            };
            tsc = {
              enable = true;
              name = "tsc";
              description = "TypeScript type-check of the shim and the playground harness.";
              entry = "./node_modules/.bin/tsc --noEmit";
              files = "\\.(m?ts|js|json)$";
              pass_filenames = false;
            };
            tsc-harness = {
              enable = true;
              name = "tsc (playground harness)";
              entry = "./node_modules/.bin/tsc --noEmit -p tests/playground/tsconfig.json";
              files = "^tests/playground/.*\\.m?ts$";
              pass_filenames = false;
            };
            phpstan = {
              enable = true;
              package = pkgs.phpstan;
              entry = "${pkgs.phpstan}/bin/phpstan analyse --memory-limit=2G";
              pass_filenames = false;
            };
            phpcs-psr12 = {
              enable = true;
              name = "phpcs";
              description = "PSR-12 (phpcs.xml.dist).";
              entry = "${pkgs.php84Packages.php-codesniffer}/bin/phpcs -q --standard=phpcs.xml.dist";
              files = "\\.php$";
              pass_filenames = false;
            };
            nixfmt.enable = true;
            statix.enable = true;
            deadnix.enable = true;
          };
        };

        # Copies the assembled Gutenberg build into the working tree so the
        # plugin can run from a checkout (Playground mounts, `composer phpstan`,
        # the block audit). The directory is gitignored.
        syncAssets = pkgs.writeShellApplication {
          name = "sync-assets";
          text = ''
            target="''${1:-assets/gutenberg}"
            stamp="${gutenbergAssets}"
            if [ -f "$target/SOURCE.txt" ] && [ "$(cat "$target/.nix-store-path" 2>/dev/null)" = "$stamp" ]; then
              echo "assets/gutenberg is up to date ($stamp)"
              exit 0
            fi
            rm -rf "$target"
            mkdir -p "$(dirname "$target")"
            cp -r "$stamp" "$target"
            chmod -R u+w "$target"
            echo "$stamp" > "$target/.nix-store-path"
            echo "synced Gutenberg ${gutenbergRelease.version} assets into $target"
          '';
        };

        # Working tree for the checks: source + vendor + assets + test tools.
        # vendor/ is copied in rather than pointed at in the store: composer's
        # optimised classmap resolves the plugin namespace relative to
        # dirname(vendor), so a store vendor would analyse two copies.
        checkWorkTree = vendor: ''
          cp -r "$src" ./work
          chmod -R u+w ./work
          cd ./work
          cp -rL ${vendor}/vendor ./vendor
          chmod -R u+w ./vendor
          cp -r ${gutenbergAssets} ./assets/gutenberg
          chmod -R u+w ./assets/gutenberg
          ln -s ${testTools}/vendor tests/tools/vendor
          export HOME="$TMPDIR"
        '';

        # The PHPUnit suite under a given PHP build.
        unitCheck =
          name: phpPkg:
          pkgs.runCommand name
            {
              nativeBuildInputs = [ phpPkg ];
              inherit src;
            }
            ''
              set -euo pipefail
              ${checkWorkTree pluginDir}
              # Through the interpreter: vendor/bin/phpunit's shebang needs
              # /usr/bin/env, which the sandbox does not have.
              php tests/tools/vendor/bin/phpunit -c tests/phpunit-unit.xml --do-not-cache-result
              touch "$out"
            '';
      in
      {
        devShells.default = pkgs.mkShell {
          packages = pre-commit-check.enabledPackages ++ [
            php
            php.packages.composer
            pkgs.phpstan
            pkgs.php84Packages.php-codesniffer
            nodejs
            syncAssets
          ];

          shellHook = ''
            ${pre-commit-check.shellHook}
            sync-assets
            echo "PHP $(php --version | head -1)"
            echo "Node $(node --version)"
            echo "Browser tests use CHROME_PATH (default: google-chrome-stable / chromium on PATH)."
          '';
        };

        apps.sync-assets = {
          type = "app";
          program = "${syncAssets}/bin/sync-assets";
        };

        packages = {
          default = pluginPackage;
          gutenberg-assets = gutenbergAssets;
          gutenberg-release = gutenbergRelease;

          # Deterministic, ready-to-install zip (top-level gutenberg-downgrade/).
          # nix build .#zip -> result/gutenberg-downgrade.zip
          zip = stdenvNoCC.mkDerivation {
            name = "${pname}-zip-${version}";
            nativeBuildInputs = [ pkgs.zip ];
            buildCommand = ''
              mkdir -p tmp/${pname}
              cp -r ${pluginDir}/. tmp/${pname}/
              chmod -R u+w tmp
              mkdir -p "$out"
              (cd tmp && zip -q -r -X "$out/${pname}.zip" ${pname})
            '';
          };
        };

        checks = {
          # The committed header must already be right: plugin-update-checker
          # reads the main file from the git tag, not from the built zip.
          plugin-header = pkgs.runCommand "check-plugin-header" { inherit src; } ''
            set -euo pipefail
            get() {
              sed -n -E "s|^[[:space:]]*\* $1:[[:space:]]*([^[:space:]]+)[[:space:]]*$|\1|p" "$src/${mainFile}"
            }
            fail=0
            check() {
              if [ "$2" != "$3" ]; then
                echo "${mainFile} '$1: $2' does not match $4 ($3)" >&2
                fail=1
              fi
            }
            check 'Version'           "$(get 'Version')"           '${version}'    'composer.json version'
            check 'Tested up to'      "$(get 'Tested up to')"      '${wpTested}'   'the value Nix parsed'
            check 'Requires at least' "$(get 'Requires at least')" '${wpRequires}' 'the value Nix parsed'
            [ "$fail" -eq 0 ] || exit 1
            echo "${mainFile} header is consistent (version ${version}, tested up to ${wpTested})"
            touch "$out"
          '';

          phpstan =
            pkgs.runCommand "check-phpstan"
              {
                nativeBuildInputs = [
                  php
                  (pkgs.phpstan.override { inherit php; })
                ];
                inherit src;
              }
              ''
                set -euo pipefail
                ${checkWorkTree devVendor}
                phpstan analyse --no-progress --no-ansi --memory-limit=2G
                touch "$out"
              '';

          phpcs =
            pkgs.runCommand "check-phpcs"
              {
                nativeBuildInputs = [
                  php
                  pkgs.php84Packages.php-codesniffer
                ];
                inherit src;
              }
              ''
                set -euo pipefail
                cd "$src"
                export HOME="$TMPDIR"
                phpcs -q -d memory_limit=1G --standard=phpcs.xml.dist --report=full
                touch "$out"
              '';

          unit = unitCheck "check-unit" php;
          # The same suite on the declared floor (Requires PHP: 8.3).
          unit-php83 = unitCheck "check-unit-php83" php83;

          # Static audit of the vendored 11.9 block PHP against current core:
          # undefined functions/classes (PHPStan level 0 with the WordPress
          # stubs) and PHP 8.4 compile-time deprecations (php -l). Every
          # finding must be answered by config/blocks.php.
          block-audit =
            pkgs.runCommand "check-block-audit"
              {
                nativeBuildInputs = [
                  php
                  (pkgs.phpstan.override { inherit php; })
                ];
                inherit src;
              }
              ''
                set -euo pipefail
                ${checkWorkTree devVendor}
                phpstan analyse -c tests/audit/phpstan-blocks.neon --no-progress --no-ansi \
                  --error-format=json --memory-limit=2G > "$TMPDIR/phpstan-blocks.json" || true
                php tests/audit/check-blocks.php --phpstan-json "$TMPDIR/phpstan-blocks.json"
                touch "$out"
              '';

          # The assembled asset tree matches what the runtime expects: every
          # file the manifest and StyleOverrides reference exists, the manifest
          # validates, and the block config covers exactly the shipped blocks.
          manifest =
            pkgs.runCommand "check-manifest"
              {
                nativeBuildInputs = [ php ];
                inherit src;
              }
              ''
                set -euo pipefail
                ${checkWorkTree pluginDir}
                php tests/audit/check-assets.php
                touch "$out"
              '';
        };
      }
    );
}
