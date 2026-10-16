# Changelog

## [2.0.0](https://github.com/Avunu/gutenberg-downgrade/compare/v1.1.3...v2.0.0) (2026-10-09)


### ⚠ BREAKING CHANGES

* the bundled editor is Gutenberg 18.5 (WordPress 6.6 series), React 18.3.1; content and third-party scripts targeting the 5.9-era editor are no longer the target.

### Features

* declare php 8.3 support ([9248bbe](https://github.com/Avunu/gutenberg-downgrade/commit/9248bbe5564fbf71836dbcb349ac1947f6fe2b82))
* full site editor support ([b3ffd28](https://github.com/Avunu/gutenberg-downgrade/commit/b3ffd28df1a9aeae3def11ab600cbb669837ddbc))
* vendor Gutenberg 18.5.0 (the WordPress 6.6 series) instead of 11.9.1 ([75cefcd](https://github.com/Avunu/gutenberg-downgrade/commit/75cefcddfd229ea201a6744e9ec1e75cae0b7492))


### Bug Fixes

* compatibility with SCF ([6b1e378](https://github.com/Avunu/gutenberg-downgrade/commit/6b1e3785ef6f6ebd9154e6dbc738f5b4e7bd1ec6))
* composition-c4 PHPStan &gt;= 2.2.13 RAM consumption ([293b380](https://github.com/Avunu/gutenberg-downgrade/commit/293b380e9c3ebf9a25afdb84f106e761ca14db48))
* ensure that auto-updater downloads the build release ([4af01f1](https://github.com/Avunu/gutenberg-downgrade/commit/4af01f1fb1da9cb7c0bc503e0b35d93905bd99b0))
* rankmath conflict ([7a29574](https://github.com/Avunu/gutenberg-downgrade/commit/7a29574ad436db0fd6695c31a9da51ef43734252))


### Miscellaneous Chores

* allow for debug files ([4ddebeb](https://github.com/Avunu/gutenberg-downgrade/commit/4ddebeb82c35d1ebc2b52fda7da2bb2c4dc06886))
* bump @types/node in /tests/playground in the playground group ([#1](https://github.com/Avunu/gutenberg-downgrade/issues/1)) ([89e05a8](https://github.com/Avunu/gutenberg-downgrade/commit/89e05a82899128292f5a790817a4687fbf6f017d))
* bump @types/node in /tests/playground in the playground group ([#14](https://github.com/Avunu/gutenberg-downgrade/issues/14)) ([31b4b22](https://github.com/Avunu/gutenberg-downgrade/commit/31b4b22c1557b18d21cfc89e47568abf82ab938e))
* bump @types/node in /tests/playground in the playground group ([#15](https://github.com/Avunu/gutenberg-downgrade/issues/15)) ([ea97ef3](https://github.com/Avunu/gutenberg-downgrade/commit/ea97ef3da540487e73fdb5188c0c88bdda4a05fa))
* bump @types/node in /tests/playground in the playground group ([#19](https://github.com/Avunu/gutenberg-downgrade/issues/19)) ([2529404](https://github.com/Avunu/gutenberg-downgrade/commit/25294042212b3aa9b15873cc7bf92b97f7149198))
* bump @types/node in /tests/playground in the playground group ([#24](https://github.com/Avunu/gutenberg-downgrade/issues/24)) ([01a40e9](https://github.com/Avunu/gutenberg-downgrade/commit/01a40e970379298fbb960a1a863081d9ec459569))
* bump @types/node in /tests/playground in the playground group ([#7](https://github.com/Avunu/gutenberg-downgrade/issues/7)) ([16ac795](https://github.com/Avunu/gutenberg-downgrade/commit/16ac795d8d1c6a6815075eda9ea8f7c63bd19de1))
* bump @wp-playground/cli ([264a524](https://github.com/Avunu/gutenberg-downgrade/commit/264a52467236155299b5eb1eddd092f0f73334ab))
* bump @wp-playground/cli ([#11](https://github.com/Avunu/gutenberg-downgrade/issues/11)) ([3f1aea2](https://github.com/Avunu/gutenberg-downgrade/commit/3f1aea2494c98e8e46276795d677ae74cee1580b))
* bump @wp-playground/cli ([#16](https://github.com/Avunu/gutenberg-downgrade/issues/16)) ([5ce2e20](https://github.com/Avunu/gutenberg-downgrade/commit/5ce2e20e2fcfa2dae6f206e7bd8a8f4ff09bdd40))
* bump @wp-playground/cli ([#25](https://github.com/Avunu/gutenberg-downgrade/issues/25)) ([34abeb1](https://github.com/Avunu/gutenberg-downgrade/commit/34abeb16c04ef1b84d0f71e9a1c98ecda4d574ed))
* bump @wp-playground/cli from 3.1.55 to 3.1.56 in /tests/playground in the playground group ([0d9d799](https://github.com/Avunu/gutenberg-downgrade/commit/0d9d7998cbb124fbbca0f4d7c159b3f3e876af88))
* bump nixpkgs from `7a0f122` to `151fa4e` in the nix group ([ac8663a](https://github.com/Avunu/gutenberg-downgrade/commit/ac8663ac5d3bf4a7dc8116fd0e3ffe1750c0ad8a))
* bump nixpkgs from `7a0f122` to `151fa4e` in the nix group ([f312746](https://github.com/Avunu/gutenberg-downgrade/commit/f312746a5c22185e0b47262da1e751ecb9cdfde4))
* bump php-stubs/wordpress-stubs ([#27](https://github.com/Avunu/gutenberg-downgrade/issues/27)) ([12da31b](https://github.com/Avunu/gutenberg-downgrade/commit/12da31bc3bce8d18cb5355d11eae94f9b9eff309))
* bump the nix group with 2 updates ([#18](https://github.com/Avunu/gutenberg-downgrade/issues/18)) ([b2d15ad](https://github.com/Avunu/gutenberg-downgrade/commit/b2d15ad2568dddaa48b39d307956bd9fae5802d7))
* bump the nix group with 2 updates ([#23](https://github.com/Avunu/gutenberg-downgrade/issues/23)) ([c3d481d](https://github.com/Avunu/gutenberg-downgrade/commit/c3d481d1e897e122d6b11edd6795b98f1170d7b8))
* bump the npm group across 1 directory with 2 updates ([#17](https://github.com/Avunu/gutenberg-downgrade/issues/17)) ([9684993](https://github.com/Avunu/gutenberg-downgrade/commit/9684993145af2b0d801bb2b40b573676e07a7aa5))
* bump the npm group across 1 directory with 2 updates ([#26](https://github.com/Avunu/gutenberg-downgrade/issues/26)) ([d380a7c](https://github.com/Avunu/gutenberg-downgrade/commit/d380a7c4155ad15c260031352835652c71b44ee0))
* bump the npm group with 2 updates ([#12](https://github.com/Avunu/gutenberg-downgrade/issues/12)) ([80fe8a8](https://github.com/Avunu/gutenberg-downgrade/commit/80fe8a853d66647d2c6718e9490e7aebfe7e86ab))
* bump the npm group with 2 updates ([#20](https://github.com/Avunu/gutenberg-downgrade/issues/20)) ([8ce76cf](https://github.com/Avunu/gutenberg-downgrade/commit/8ce76cf7bc47e77b31cd3f33ada843e3f18083cb))
* **main:** release 0.1.1 ([899197b](https://github.com/Avunu/gutenberg-downgrade/commit/899197be5a6e418fd792db282d57bd66a1da3b59))
* **main:** release 0.1.1 ([bf2b54c](https://github.com/Avunu/gutenberg-downgrade/commit/bf2b54c4d667f16e73d94874fefa4b15c7e34a41))
* **main:** release 1.0.0 ([c53518d](https://github.com/Avunu/gutenberg-downgrade/commit/c53518d197da0e8f3febbd38aacbf18c04e6d949))
* **main:** release 1.0.0 ([9ea2ad4](https://github.com/Avunu/gutenberg-downgrade/commit/9ea2ad402004fb207dc1280b80c2f21f02a9d062))
* **main:** release 1.0.1 ([dc80e8e](https://github.com/Avunu/gutenberg-downgrade/commit/dc80e8ecd341114181337e41fe7cdafa47a3cada))
* **main:** release 1.0.1 ([ce89761](https://github.com/Avunu/gutenberg-downgrade/commit/ce89761e69b71e79fb188c52913ffae1e0719334))
* **main:** release 1.1.0 ([bc6bff9](https://github.com/Avunu/gutenberg-downgrade/commit/bc6bff94789cbd83058bf18e03c17a34f963a499))
* **main:** release 1.1.0 ([f502165](https://github.com/Avunu/gutenberg-downgrade/commit/f502165fafc4259931a7953cf8000801241c814b))
* **main:** release 1.1.1 ([1abc71f](https://github.com/Avunu/gutenberg-downgrade/commit/1abc71f8804dee91b356c2698dc150d23e4d61c5))
* **main:** release 1.1.1 ([7c11431](https://github.com/Avunu/gutenberg-downgrade/commit/7c114310b3106e473d9e501e43e7bf35e602107f))
* **main:** release 1.1.2 ([49bd7e1](https://github.com/Avunu/gutenberg-downgrade/commit/49bd7e1c7cf9b13661ef14c9209d400cb3ed5888))
* **main:** release 1.1.2 ([d69eb9a](https://github.com/Avunu/gutenberg-downgrade/commit/d69eb9a85644e336aecf5553ae9258389b1786ee))
* **main:** release 1.1.3 ([#22](https://github.com/Avunu/gutenberg-downgrade/issues/22)) ([cb5b58d](https://github.com/Avunu/gutenberg-downgrade/commit/cb5b58da6167609609b7cb88b89ddd7ae7ad7f39))
* update flake ([2906e18](https://github.com/Avunu/gutenberg-downgrade/commit/2906e186385ecf7d4ab6704100805467b4ca9e00))

## [1.1.3](https://github.com/Avunu/gutenberg-downgrade/compare/v1.1.2...v1.1.3) (2026-10-09)


### Miscellaneous Chores

* bump @types/node in /tests/playground in the playground group ([#14](https://github.com/Avunu/gutenberg-downgrade/issues/14)) ([31b4b22](https://github.com/Avunu/gutenberg-downgrade/commit/31b4b22c1557b18d21cfc89e47568abf82ab938e))
* bump @types/node in /tests/playground in the playground group ([#15](https://github.com/Avunu/gutenberg-downgrade/issues/15)) ([ea97ef3](https://github.com/Avunu/gutenberg-downgrade/commit/ea97ef3da540487e73fdb5188c0c88bdda4a05fa))
* bump @types/node in /tests/playground in the playground group ([#19](https://github.com/Avunu/gutenberg-downgrade/issues/19)) ([2529404](https://github.com/Avunu/gutenberg-downgrade/commit/25294042212b3aa9b15873cc7bf92b97f7149198))
* bump @types/node in /tests/playground in the playground group ([#24](https://github.com/Avunu/gutenberg-downgrade/issues/24)) ([01a40e9](https://github.com/Avunu/gutenberg-downgrade/commit/01a40e970379298fbb960a1a863081d9ec459569))
* bump @wp-playground/cli ([264a524](https://github.com/Avunu/gutenberg-downgrade/commit/264a52467236155299b5eb1eddd092f0f73334ab))
* bump @wp-playground/cli ([#16](https://github.com/Avunu/gutenberg-downgrade/issues/16)) ([5ce2e20](https://github.com/Avunu/gutenberg-downgrade/commit/5ce2e20e2fcfa2dae6f206e7bd8a8f4ff09bdd40))
* bump @wp-playground/cli ([#25](https://github.com/Avunu/gutenberg-downgrade/issues/25)) ([34abeb1](https://github.com/Avunu/gutenberg-downgrade/commit/34abeb16c04ef1b84d0f71e9a1c98ecda4d574ed))
* bump @wp-playground/cli from 3.1.55 to 3.1.56 in /tests/playground in the playground group ([0d9d799](https://github.com/Avunu/gutenberg-downgrade/commit/0d9d7998cbb124fbbca0f4d7c159b3f3e876af88))
* bump nixpkgs from `7a0f122` to `151fa4e` in the nix group ([ac8663a](https://github.com/Avunu/gutenberg-downgrade/commit/ac8663ac5d3bf4a7dc8116fd0e3ffe1750c0ad8a))
* bump nixpkgs from `7a0f122` to `151fa4e` in the nix group ([f312746](https://github.com/Avunu/gutenberg-downgrade/commit/f312746a5c22185e0b47262da1e751ecb9cdfde4))
* bump php-stubs/wordpress-stubs ([#27](https://github.com/Avunu/gutenberg-downgrade/issues/27)) ([12da31b](https://github.com/Avunu/gutenberg-downgrade/commit/12da31bc3bce8d18cb5355d11eae94f9b9eff309))
* bump the nix group with 2 updates ([#18](https://github.com/Avunu/gutenberg-downgrade/issues/18)) ([b2d15ad](https://github.com/Avunu/gutenberg-downgrade/commit/b2d15ad2568dddaa48b39d307956bd9fae5802d7))
* bump the nix group with 2 updates ([#23](https://github.com/Avunu/gutenberg-downgrade/issues/23)) ([c3d481d](https://github.com/Avunu/gutenberg-downgrade/commit/c3d481d1e897e122d6b11edd6795b98f1170d7b8))
* bump the npm group across 1 directory with 2 updates ([#17](https://github.com/Avunu/gutenberg-downgrade/issues/17)) ([9684993](https://github.com/Avunu/gutenberg-downgrade/commit/9684993145af2b0d801bb2b40b573676e07a7aa5))
* bump the npm group across 1 directory with 2 updates ([#26](https://github.com/Avunu/gutenberg-downgrade/issues/26)) ([d380a7c](https://github.com/Avunu/gutenberg-downgrade/commit/d380a7c4155ad15c260031352835652c71b44ee0))
* bump the npm group with 2 updates ([#20](https://github.com/Avunu/gutenberg-downgrade/issues/20)) ([8ce76cf](https://github.com/Avunu/gutenberg-downgrade/commit/8ce76cf7bc47e77b31cd3f33ada843e3f18083cb))

## [1.1.2](https://github.com/Avunu/gutenberg-downgrade/compare/v1.1.1...v1.1.2) (2026-09-16)


### Bug Fixes

* ensure that auto-updater downloads the build release ([4af01f1](https://github.com/Avunu/gutenberg-downgrade/commit/4af01f1fb1da9cb7c0bc503e0b35d93905bd99b0))

## [1.1.1](https://github.com/Avunu/gutenberg-downgrade/compare/v1.1.0...v1.1.1) (2026-09-16)


### Bug Fixes

* composition-c4 PHPStan &gt;= 2.2.13 RAM consumption ([293b380](https://github.com/Avunu/gutenberg-downgrade/commit/293b380e9c3ebf9a25afdb84f106e761ca14db48))
* rankmath conflict ([7a29574](https://github.com/Avunu/gutenberg-downgrade/commit/7a29574ad436db0fd6695c31a9da51ef43734252))


### Miscellaneous Chores

* allow for debug files ([4ddebeb](https://github.com/Avunu/gutenberg-downgrade/commit/4ddebeb82c35d1ebc2b52fda7da2bb2c4dc06886))
* bump @types/node in /tests/playground in the playground group ([#7](https://github.com/Avunu/gutenberg-downgrade/issues/7)) ([16ac795](https://github.com/Avunu/gutenberg-downgrade/commit/16ac795d8d1c6a6815075eda9ea8f7c63bd19de1))
* update flake ([2906e18](https://github.com/Avunu/gutenberg-downgrade/commit/2906e186385ecf7d4ab6704100805467b4ca9e00))

## [1.1.0](https://github.com/Avunu/gutenberg-downgrade/compare/v1.0.1...v1.1.0) (2026-09-13)


### Features

* full site editor support ([b3ffd28](https://github.com/Avunu/gutenberg-downgrade/commit/b3ffd28df1a9aeae3def11ab600cbb669837ddbc))

## [1.0.1](https://github.com/Avunu/gutenberg-downgrade/compare/v1.0.0...v1.0.1) (2026-09-12)


### Bug Fixes

* compatibility with SCF ([6b1e378](https://github.com/Avunu/gutenberg-downgrade/commit/6b1e3785ef6f6ebd9154e6dbc738f5b4e7bd1ec6))

## [1.0.0](https://github.com/Avunu/gutenberg-downgrade/compare/v0.1.1...v1.0.0) (2026-09-11)


### ⚠ BREAKING CHANGES

* the bundled editor is Gutenberg 18.5 (WordPress 6.6 series), React 18.3.1; content and third-party scripts targeting the 5.9-era editor are no longer the target.

### Features

* declare php 8.3 support ([9248bbe](https://github.com/Avunu/gutenberg-downgrade/commit/9248bbe5564fbf71836dbcb349ac1947f6fe2b82))
* vendor Gutenberg 18.5.0 (the WordPress 6.6 series) instead of 11.9.1 ([75cefcd](https://github.com/Avunu/gutenberg-downgrade/commit/75cefcddfd229ea201a6744e9ec1e75cae0b7492))

## [0.1.1](https://github.com/Avunu/gutenberg-downgrade/compare/v0.1.0...v0.1.1) (2026-09-11)


### Miscellaneous Chores

* bump @types/node in /tests/playground in the playground group ([#1](https://github.com/Avunu/gutenberg-downgrade/issues/1)) ([89e05a8](https://github.com/Avunu/gutenberg-downgrade/commit/89e05a82899128292f5a790817a4687fbf6f017d))

## Changelog
