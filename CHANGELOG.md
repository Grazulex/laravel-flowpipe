# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [v1.3.0] - 2026-09-17

### Added

- Laravel 13 support (`illuminate/support` and `illuminate/contracts` `^12.19|^13.0`, `symfony/yaml` `^7.3|^8.0`).
- CI test matrix now covers PHP 8.3 and 8.4 against Laravel 12 (Testbench 10) and Laravel 13 (Testbench 11), with `prefer-lowest` and `prefer-stable` dependency sets.

### Changed

- PHP 8.3 is the minimum supported version.
- Development dependencies updated: Pest `^3.8|^4.0`, Pest Laravel plugin `^3.2|^4.0`, Orchestra Testbench `^10.0|^11.0`.
- Release workflow now runs its pre-release checks against Laravel 13; code quality workflow runs Pint alongside PHPStan.

## [v1.2.0] - 2025-12-22

### Fixed

- `Flowpipe::send()` and `resolveInitialPayload()` now accept `string|array` payloads (#55).

[Unreleased]: https://github.com/Grazulex/laravel-flowpipe/compare/v1.3.0...HEAD
[v1.3.0]: https://github.com/Grazulex/laravel-flowpipe/compare/v1.2.0...v1.3.0
[v1.2.0]: https://github.com/Grazulex/laravel-flowpipe/compare/v1.1.0...v1.2.0
