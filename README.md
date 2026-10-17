# Github action WordPress.org `readme.txt` deploy

With this GitHub action you can easily deploy your WordPress plugin `readme.txt` file in the WordPress plugin directory.

## Introduction

### Streamline your WordPress plugin deployment

This GitHub action simplifies deploying your WordPress plugin's `readme.txt` file directly in the WordPress plugin directory.

### Built for WordPress developers

Leveraging PHP instead of bash scripting, this action caters to the preferences of many WordPress developers, offering a familiar and potentially more convenient workflow.

## Example

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
        uses: solvebeam/action-wordpress-plugin-readme-update@main
        with:
          username: solvebeam
          password: ${{ secrets.SVN_PASSWORD }}
          slug: solvebeam-diagrams-with-mermaid
          dry-run: ${{ inputs.dry-run || false }}
```

## Dry run

With `dry-run: true` every step and check runs as usual, but the Subversion commit command is only printed, not executed. The job summary then shows which paths would have been added, modified or removed.

## Outputs

| Output | Description |
| --- | --- |
| `public-url` | Public URL of the plugin on WordPress.org, for example `https://wordpress.org/plugins/solvebeam-diagrams-with-mermaid/`. |
| `svn-url` | Subversion repository URL of the plugin, for example `https://plugins.svn.wordpress.org/solvebeam-diagrams-with-mermaid`. |
| `svn-tag-url` | Subversion URL of the deployed tag, for example `https://plugins.svn.wordpress.org/solvebeam-diagrams-with-mermaid/tags/1.0.0`. Empty if `readme.txt` has no `Stable tag`. |

## Job summary

After every run the action adds a [job summary](https://docs.github.com/en/actions/writing-workflows/choosing-what-your-workflow-does/workflow-commands-for-github-actions#adding-a-job-summary) to the workflow run, with the result, links to the plugin on WordPress.org, the Subversion repository, Trac and the stable tag, plus a list of the added, modified and removed Subversion paths.

A deploy is essentially a series of shell commands, so the summary also lists every command the action executed, and in a dry run the skipped commit command. This makes it easier to understand what happens during a deploy, the Subversion password is replaced by `●●●●●●●●`.

## In the wild

- [SolveBeam Diagrams with Mermaid](https://github.com/solvebeam/solvebeam-diagrams-with-mermaid-for-wordpress) — [`.github/workflows/deploy-readme-to-wordpress-org.yml`](https://github.com/solvebeam/solvebeam-diagrams-with-mermaid-for-wordpress/blob/main/.github/workflows/deploy-readme-to-wordpress-org.yml)

## Inspiration

- https://github.com/marketplace/actions/wordpress-plugin-svn-deploy
  - https://github.com/nk-o/action-wordpress-plugin-deploy
- https://github.com/marketplace/actions/wordpress-plugin-readme-assets-update
  - https://github.com/10up/action-wordpress-plugin-asset-update
- https://github.com/marketplace/actions/deploy-to-wordpress-org-svn-repository
  - https://github.com/richard-muvirimi/deploy-wordpress-plugin

## Development

```
SVN_USERNAME=test SVN_PASSWORD=test WP_SLUG=salesfeed php deploy.php
```

## Links

- https://developer.wordpress.org/plugins/wordpress-org/how-your-readme-txt-works/
- https://developer.wordpress.org/plugins/wordpress-org/how-to-use-subversion/
- https://svnbook.red-bean.com/
- https://docs.github.com/en/actions/creating-actions/metadata-syntax-for-github-actions
