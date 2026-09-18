# Changelog

## [2.0.0](https://github.com/Avunu/gutenberg-downgrade/compare/v1.1.2...v2.0.0) (2026-09-18)


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
* bump @types/node in /tests/playground in the playground group ([#7](https://github.com/Avunu/gutenberg-downgrade/issues/7)) ([16ac795](https://github.com/Avunu/gutenberg-downgrade/commit/16ac795d8d1c6a6815075eda9ea8f7c63bd19de1))
* bump @wp-playground/cli ([#11](https://github.com/Avunu/gutenberg-downgrade/issues/11)) ([3f1aea2](https://github.com/Avunu/gutenberg-downgrade/commit/3f1aea2494c98e8e46276795d677ae74cee1580b))
* bump the npm group with 2 updates ([#12](https://github.com/Avunu/gutenberg-downgrade/issues/12)) ([80fe8a8](https://github.com/Avunu/gutenberg-downgrade/commit/80fe8a853d66647d2c6718e9490e7aebfe7e86ab))
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
* update flake ([2906e18](https://github.com/Avunu/gutenberg-downgrade/commit/2906e186385ecf7d4ab6704100805467b4ca9e00))

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
