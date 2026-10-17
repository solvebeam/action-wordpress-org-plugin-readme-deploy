# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.1] - 2026-10-17

### Changed

- Rename the action to SolveBeam WordPress.org Plugin Readme Deploy and clarify its description.
- Capitalize the author name in the action metadata.
- Use the SolveBeam project name in the README heading.

## [1.0.0] - 2026-10-17

### Added

- Deploy `readme.txt` to `trunk` and to the tag named in the `Stable tag` header.
- Synchronize the `.wordpress-org` directory with `assets`, including removed files.
- Sparse Subversion checkout of only `trunk/readme.txt`, `tags/{stable}/readme.txt` and `assets`.
- Set the correct `svn:mime-type` on PNG, JPEG, GIF and SVG assets.
- `username`, `password` and `slug` inputs.
- `dry-run` input to run every step except the Subversion commit.
- `public-url`, `svn-url` and `svn-tag-url` outputs.
- Job summary with the result, links, Subversion changes and executed commands.
- Collapsible log groups for every step.

[unreleased]: https://github.com/solvebeam/action-wordpress-org-plugin-readme-deploy/compare/v1.0.1...HEAD
[1.0.1]: https://github.com/solvebeam/action-wordpress-org-plugin-readme-deploy/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/solvebeam/action-wordpress-org-plugin-readme-deploy/releases/tag/v1.0.0
