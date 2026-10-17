<?php

/**
 * Autoload.
 */
require __DIR__ . '/vendor/autoload.php';

use SolveBeam\GitHubActionsToolkit\Core;
use SolveBeam\GitHubActionsToolkit\Shell;
use SolveBeam\GitHubActionsToolkit\Style;
use SolveBeam\GitHubActionsToolkit\Summary;

/**
 * Setup.
 */
$core  = new Core();
$style = new Style();
$shell = new Shell();

$username = $core->get_required_input( 'username' );
$password = $core->get_required_input( 'password' );

$slug = $core->get_required_input( 'slug' );

$dry_run = $core->get_boolean_input( 'dry-run' );

$svn_url = "https://plugins.svn.wordpress.org/$slug";

$readme_file = getcwd() . '/readme.txt';
$assets_dir  = getcwd() . '/.wordpress-org';

if ( ! is_file( $readme_file ) ) {
	$core->set_failed( "The `readme.txt` file could not be found: $readme_file." );
}

$svn_checkout_dir = tempnam( sys_get_temp_dir(), '' );

unlink( $svn_checkout_dir );

mkdir( $svn_checkout_dir );

/**
 * Parse stable tag.
 */
$readme_content = file_get_contents( $readme_file );

$pattern = "/Stable tag: (.*)/";

$stable_tag = '';

if ( 1 === preg_match( $pattern, $readme_content, $matches ) ) {
	$stable_tag = trim( $matches[1] );
}

/**
 * Outputs.
 */
$public_url  = "https://wordpress.org/plugins/$slug/";
$svn_tag_url = '' === $stable_tag ? '' : "$svn_url/tags/$stable_tag";
$trac_url    = "https://plugins.trac.wordpress.org/browser/$slug/";

$core->set_output( 'public-url', $public_url );
$core->set_output( 'svn-url', $svn_url );
$core->set_output( 'svn-tag-url', $svn_tag_url );

/**
 * Start.
 */
$core->start_group( 'ℹ️ Deploy readme.txt to WordPress.org' );

echo '• ', $style->bold( 'Public URL:' ), ' ', $public_url, PHP_EOL;
echo '• ', $style->bold( 'Subversion URL:' ), ' ', $svn_url, PHP_EOL;
echo '• ', $style->bold( 'Subversion tag URL:' ), ' ', $svn_tag_url, PHP_EOL;
echo '• ', $style->bold( 'Subversion username:' ), ' ', $username, PHP_EOL;
echo '• ', $style->bold( 'Subversion checkout directory:' ), ' ', $svn_checkout_dir, PHP_EOL;
echo '• ', $style->bold( 'Path readme.txt:' ), ' ', $readme_file, PHP_EOL;
echo '• ', $style->bold( 'Path assets:' ), ' ', $assets_dir, PHP_EOL;
echo '• ', $style->bold( 'Stable tag:' ), ' ', $stable_tag, PHP_EOL;
echo '• ', $style->bold( 'Dry run:' ), ' ', $dry_run ? 'yes' : 'no', PHP_EOL;

$core->end_group();

$changes = [];

$commands = [];

$error = null;

try {
	/**
	 * Subversion.
	 *
	 * @link https://stackoverflow.com/a/122291
	 */
	$core->start_group( '⬇ Subversion checkout WordPress.org' );

	$command = "svn checkout $svn_url $svn_checkout_dir --depth empty";

	$commands[] = $command;

	$shell->must_run( $command );

	chdir( $svn_checkout_dir );

	$command = 'svn update trunk --depth=empty';

	$commands[] = $command;

	$shell->must_run( $command );

	$command = 'svn update trunk/readme.txt';

	$commands[] = $command;

	$shell->must_run( $command );

	$command = 'svn update assets';

	$commands[] = $command;

	$shell->must_run( $command );

	if ( '' !== $stable_tag ) {
		$command = "svn update tags --depth=empty";

		$commands[] = $command;

		$shell->must_run( $command );

		$command = "svn update tags/$stable_tag --depth=empty";

		$commands[] = $command;

		$shell->must_run( $command );

		$command = "svn update tags/$stable_tag/readme.txt";

		$commands[] = $command;

		$shell->must_run( $command );
	}

	$core->end_group();

	/**
	 * Synchronize.
	 */
	$core->start_group( '🔄 Synchronize' );

	$command = "cp $readme_file trunk/readme.txt";

	$commands[] = $command;

	$shell->must_run( $command );

	if ( '' !== $stable_tag ) {
		$command = "cp $readme_file tags/$stable_tag/readme.txt";

		$commands[] = $command;

		$shell->must_run( $command );
	}

	if ( is_dir( $assets_dir ) ) {
		$command = "rsync --recursive --checksum $assets_dir/ assets/ --delete --delete-excluded";

		$commands[] = $command;

		$shell->must_run( $command );
	}

	$core->end_group();

	/**
	 * Subversion modifications.
	 */
	$core->start_group( '💾 Subversion modifications' );

	$command = 'svn status';

	$commands[] = $command;

	$shell->must_run( $command );

	$output = shell_exec( 'svn status --xml' );

	$xml = simplexml_load_string( $output );

	if ( false === $xml ) {
		throw new RuntimeException( 'A problem occurred while reading the `svn status --xml`.' );
	}

	foreach ( $xml->target->entry as $entry ) {
		$path = (string) $entry['path'];

		$wc_status = (string) $entry->{'wc-status'}['item'];

		switch ( $wc_status ) {
			case 'missing':
				$command = "svn rm $path";

				$commands[] = $command;

				$shell->must_run( $command );

				$changes[] = [ '➖ Removed', $path ];

				break;
			case 'modified':
				// Modified entry will be committed.

				$changes[] = [ '✏️ Modified', $path ];

				break;
			case 'unversioned':
				$command = "svn add $path";

				$commands[] = $command;

				$shell->must_run( $command );

				$changes[] = [ '➕ Added', $path ];

				break;
			default:
				throw new RuntimeException( "Unsupport working copy status: $wc_status - $path." );
		}
	}

	$core->end_group();

	/**
	 * Fix screenshots getting force downloaded when clicking them.
	 *
	 * @link https://developer.wordpress.org/plugins/wordpress-org/plugin-assets/
	 */
	$core->start_group( '🐛 Fix downloading assets images issue' );

	$mime_types = [
		'png'  => 'image/png',
		'jpg'  => 'image/jpeg',
		'jpeg' => 'image/jpeg',
		'gif'  => 'image/gif',
		'svg'  => 'image/svg+xml',
	];

	foreach ( $mime_types as $ext => $type ) {
		foreach ( glob( 'assets/*.' . $ext ) as $file ) {
			$command = "svn propset svn:mime-type '$type' '$file'";

			$commands[] = $command;

			$shell->must_run( $command );
		}
	}

	$core->end_group();

	/**
	 * Commit.
	 */
	$core->start_group( '⬆ Subversion commit WordPress.org' );

	$command = "svn commit --message 'Update readme.txt' --non-interactive --username '$username' --password '$password'";

	$command_masked = str_replace( $password, '●●●●●●●●', $command );

	if ( $dry_run ) {
		$shell->skip( $command );

		$commands[] = "# Skipped (dry run): $command_masked";
	} else {
		$commands[] = $command_masked;

		$shell->must_run( $command );
	}

	$core->end_group();

	/**
	 * Clean up.
	 */
	$core->start_group( '🗑️ Clean up' );

	$command = "rm -f -R $svn_checkout_dir";

	$commands[] = $command;

	$shell->must_run( $command );

	$core->end_group();
} catch ( RuntimeException $e ) {
	$error = $e->getMessage();
}

/**
 * Job summary.
 *
 * Written before `set_failed()`, because that exits.
 */
$status = match ( true ) {
	null !== $error => "❌ Failed: $error",
	$dry_run        => '🧪 Dry run, nothing committed.',
	default         => '✅ Deployed.',
};

$summary = new Summary();

$summary
	->add_heading( 'Deploy `readme.txt` to WordPress.org' . ( $dry_run ? ' (dry run)' : '' ), 2 )
	->add_eol()
	->add_raw( $status, true )
	->add_eol()
	->add_raw( "- **Plugin:** <$public_url>", true )
	->add_raw( "- **Subversion:** <$svn_url>", true )
	->add_raw( "- **Trac:** <$trac_url>", true );

if ( '' !== $stable_tag ) {
	$summary->add_raw( "- **Stable tag:** <$svn_tag_url>", true );
}

$summary
	->add_eol()
	->add_heading( 'Subversion changes', 3 )
	->add_eol();

if ( [] === $changes ) {
	$summary->add_raw( 'No changes.', true );
} else {
	$summary
		->add_raw( '| Status | Path |', true )
		->add_raw( '| --- | --- |', true );

	foreach ( $changes as list( $status, $path ) ) {
		$summary->add_raw( "| $status | `$path` |", true );
	}
}

$summary
	->add_eol()
	->add_heading( 'Commands', 3 )
	->add_eol()
	->add_code_block( implode( PHP_EOL, $commands ), 'shell' )
	->add_eol()
	->add_raw( '---', true )
	->add_eol()
	->add_raw( '<sub>Deployed with [solvebeam/action-wordpress-org-plugin-readme-deploy](https://github.com/solvebeam/action-wordpress-org-plugin-readme-deploy), built on [solvebeam/github-actions-toolkit-php](https://github.com/solvebeam/github-actions-toolkit-php). Made with ❤️ by [SolveBeam](https://www.solvebeam.com/).</sub>', true )
	->write();

if ( null !== $error ) {
	$core->set_failed( $error );
}
