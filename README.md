# SolveBeam WordPress.org Plugin Readme Deploy

**Update your plugin page on WordPress.org without shipping a new release.**

Bumped `Tested up to`, fixed a typo in the FAQ, or made a new banner? This action puts your `readme.txt` and `.wordpress-org` assets in the WordPress.org plugin directory, and touches nothing else. No full `trunk` checkout, no comparing your whole repository with Subversion, no strict branching strategy needed.

```
readme.txt + .wordpress-org/ ──► sparse SVN checkout ──► trunk/readme.txt + tags/{stable}/readme.txt + assets/ on WordPress.org
```

## Why this action?

The popular [10up/action-wordpress-plugin-asset-update](https://github.com/10up/action-wordpress-plugin-asset-update) checks out your complete `trunk`, copies your *whole repository* over it and then refuses to deploy if anything other than the readme or assets changed. This works, as long as your branch never contains unreleased code. In practice that means a strict `trunk` vs. `develop` workflow, or seeing the action fail with *"Other files have been modified; changes not deployed"*. This action takes a different approach:

- 🎯 **Only `readme.txt` and assets, by design.** The action only reads `readme.txt` and `.wordpress-org`, so unreleased code in your branch never gets in the way. Update your plugin page from any branch, at any moment.
- ⚡ **Sparse, fast Subversion checkout.** Only `trunk/readme.txt`, `tags/{stable}/readme.txt` and `assets` are checked out, not your complete plugin. That makes a real difference for plugins with a large `vendor` or `build` directory.
- 🪶 **Minimal workflow checkout.** A sparse `actions/checkout` of `readme.txt` and `.wordpress-org` is all it needs. No build step, no Composer or npm install.
- 🏷️ **Stable tag included.** The `Stable tag` is read from your `readme.txt`, and the readme in that tag is updated too, because that is the one WordPress.org actually shows.
- 🖼️ **Screenshots that open in the browser.** The correct `svn:mime-type` is set on PNG, JPEG, GIF and SVG assets, so screenshots don't get force downloaded.
- 🧪 **Dry run.** With `dry-run: true` every step and check runs as usual, but the Subversion commit is only printed, not executed. Perfect for checking what would change before it goes live.
- 🧾 **Job summary.** Every run writes a summary with the result, links to the plugin page, Subversion, Trac and the stable tag, a table of the added, modified and removed paths and every command that was run.
- 🚦 **Made for GitHub Environments.** The `public-url` output links your deployment straight to the plugin page, and with protection rules you can require approval before anything goes live.
- 🧹 **No side effects in your repository.** No temporary git commits by a bot user in your workspace.
- 🐘 **Written in PHP, for WordPress developers.** The deploy logic is a readable PHP script instead of a bash script, so it's easy to understand, debug and contribute to.
- 📋 **Tidy logs.** Every step runs in its own collapsible log group, and your Subversion password is masked in the summary.

### Compared to 10up/action-wordpress-plugin-asset-update

| | This action | 10up |
|---|---|---|
| What gets compared with WordPress.org | Only `readme.txt` and `.wordpress-org` | Your whole repository against the whole `trunk` |
| Unreleased code in your branch | ✅ No problem, it's ignored | ❌ Fails, unless `IGNORE_OTHER_FILES` is set |
| Subversion checkout | Sparse: readme files and `assets` only | Complete `trunk` and `assets` |
| Updates `readme.txt` in the stable tag | ✅ Yes | ✅ Yes |
| Fixes screenshot MIME types | ✅ Yes | ✅ Yes |
| Dry run | ✅ Yes | ❌ No |
| Job summary with links and Subversion changes | ✅ Yes | ❌ No |
| Outputs for GitHub Environments | ✅ `public-url`, `svn-url`, `svn-tag-url` | ❌ No |
| Git commits in your workspace | ✅ Never | ⚠️ Commits a `.gitattributes` file when none exists |
| Credentials | Action inputs (`username` / `password`) | Environment variables (`SVN_USERNAME` / `SVN_PASSWORD`) |
| Custom readme name or assets directory | ❌ `readme.txt` and `.wordpress-org` | ✅ `README_NAME` and `ASSETS_DIR` |

**In short:** if you want to update your WordPress.org plugin page whenever *you* want, without restructuring your Git workflow around it, this action is for you.

### Companion action

To deploy a complete new version of your plugin, use [solvebeam/action-wordpress-org-plugin-deploy](https://github.com/solvebeam/action-wordpress-org-plugin-deploy). It deploys the exact ZIP attached to your GitHub release to `trunk` and `tags/{version}`.

## Usage

Add the workflow below to `.github/workflows/deploy-readme-to-wordpress-org.yml` and run it from the *Actions* tab whenever your `readme.txt` or assets changed. Prefer it fully automatic? Add a `push` trigger with a `paths` filter on `readme.txt` and `.wordpress-org/**`.

```yml
name: Deploy readme.txt to WordPress.org

on:
  workflow_dispatch:
    inputs:
      dry-run:
        description: 'Dry run, without committing on WordPress.org'
        required: false
        default: false
        type: boolean

jobs:
  deploy:
    runs-on: ubuntu-latest

    environment:
      name: WordPress.org plugin directory
      url: ${{ steps.deploy.outputs.public-url }}

    steps:
      - name: Checkout
        uses: actions/checkout@v7
        with:
          sparse-checkout: |
            .github
            .wordpress-org
            readme.txt

      - name: Deploy
        id: deploy
        uses: solvebeam/action-wordpress-org-plugin-readme-deploy@v1
        with:
          username: ${{ vars.WORDPRESS_ORG_SOLVEBEAM_USERNAME }}
          password: ${{ secrets.WORDPRESS_ORG_SOLVEBEAM_PASSWORD }}
          slug: solvebeam-diagrams-with-mermaid
          dry-run: ${{ inputs.dry-run || false }}
```

### Versions

This action follows [Semantic Versioning](https://semver.org/). The `v1` tag always points to the latest `1.x.x` release, so you get fixes and new features without breaking changes. Prefer full control? Pin an exact release such as `@v1.0.1`, or a commit SHA. See the [changelog](CHANGELOG.md) for what changed.

### Inputs

| Input | Required | Description |
| --- | --- | --- |
| `username` | Yes | WordPress.org Subversion username, case sensitive. |
| `password` | Yes | WordPress.org Subversion password. |
| `slug` | Yes | WordPress.org plugin slug, for example `solvebeam-diagrams-with-mermaid`. |
| `dry-run` | No | `true` to run every step except the Subversion commit. Defaults to `false`. |

### Outputs

| Output | Description |
| --- | --- |
| `public-url` | Public URL of the plugin on WordPress.org, for example `https://wordpress.org/plugins/solvebeam-diagrams-with-mermaid/`. |
| `svn-url` | Subversion repository URL of the plugin, for example `https://plugins.svn.wordpress.org/solvebeam-diagrams-with-mermaid`. |
| `svn-tag-url` | Subversion URL of the deployed tag, for example `https://plugins.svn.wordpress.org/solvebeam-diagrams-with-mermaid/tags/1.0.0`. Empty if `readme.txt` has no `Stable tag`. |

### Actions secrets and variables

For the WordPress.org login details it's convenient to create a variable and secret at organization level, so you manage them in one place instead of per repository or environment. For example, SolveBeam uses:

```
${{ vars.WORDPRESS_ORG_SOLVEBEAM_USERNAME }}
${{ secrets.WORDPRESS_ORG_SOLVEBEAM_PASSWORD }}
```

### Environments

Treat the WordPress.org plugin directory as a deployment environment. GitHub then shows your deployments on the repository homepage, links them to the plugin page through the `public-url` output, and with protection rules you can require approval before anything goes live. See the [companion action](https://github.com/solvebeam/action-wordpress-org-plugin-deploy#environments) for screenshots.

## Dry run

With `dry-run: true` every step and check runs as usual, but the Subversion commit command is only printed, not executed. The job summary then shows which paths would have been added, modified or removed.

## Job summary

After every run the action adds a [job summary](https://docs.github.com/en/actions/writing-workflows/choosing-what-your-workflow-does/workflow-commands-for-github-actions#adding-a-job-summary) to the workflow run, with the result, links to the plugin on WordPress.org, the Subversion repository, Trac and the stable tag, plus a list of the added, modified and removed Subversion paths.

A deploy is essentially a series of shell commands, so the summary also lists every command the action executed, and in a dry run the skipped commit command. This makes it easy to see exactly what happened during a deploy. The Subversion password is replaced by `●●●●●●●●`.

## In the wild

- [solvebeam/solvebeam-diagrams-with-mermaid-for-wordpress](https://github.com/solvebeam/solvebeam-diagrams-with-mermaid-for-wordpress) ([workflow](https://github.com/solvebeam/solvebeam-diagrams-with-mermaid-for-wordpress/blob/main/.github/workflows/deploy-readme-to-wordpress-org.yml))

Using this action in your plugin? Open a pull request to add it to this list.

## Inspiration

- https://github.com/marketplace/actions/wordpress-plugin-readme-assets-update
  - https://github.com/10up/action-wordpress-plugin-asset-update
- https://github.com/marketplace/actions/wordpress-plugin-svn-deploy
  - https://github.com/nk-o/action-wordpress-plugin-deploy
- https://github.com/marketplace/actions/deploy-to-wordpress-org-svn-repository
  - https://github.com/richard-muvirimi/deploy-wordpress-plugin

## Development

```
composer install
env INPUT_USERNAME=test INPUT_PASSWORD=test INPUT_SLUG=salesfeed INPUT_DRY-RUN=true php deploy.php
```

## Links

- https://developer.wordpress.org/plugins/wordpress-org/how-your-readme-txt-works/
- https://developer.wordpress.org/plugins/wordpress-org/plugin-assets/
- https://developer.wordpress.org/plugins/wordpress-org/how-to-use-subversion/
- https://svnbook.red-bean.com/
- https://docs.github.com/en/actions/creating-actions/metadata-syntax-for-github-actions
