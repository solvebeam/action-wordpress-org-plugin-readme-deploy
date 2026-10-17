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

$svn_url = "https://plugins.svn.wordpress.org/$slug";

$readme_file = getcwd() . '/readme.txt';
$assets_dir  = getcwd() . '/.wordpress-org';

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

$core->end_group();

$changes = [];

try {
	/**
	 * Subversion.
	 *
	 * @link https://stackoverflow.com/a/122291
	 */
	$core->start_group( '⬇ Subversion checkout WordPress.org' );

	$shell->must_run( "svn checkout $svn_url $svn_checkout_dir --depth empty" );

	chdir( $svn_checkout_dir );

	$shell->must_run( 'svn update trunk --depth=empty' );
	$shell->must_run( 'svn update trunk/readme.txt' );
	$shell->must_run( 'svn update assets' );

	if ( '' !== $stable_tag ) {
		$shell->must_run( "svn update tags --depth=empty" );
		$shell->must_run( "svn update tags/$stable_tag --depth=empty" );
		$shell->must_run( "svn update tags/$stable_tag/readme.txt" );
	}

	$core->end_group();

	/**
	 * Synchronize.
	 */
	$core->start_group( '🔄 Synchronize' );

	$shell->must_run( "cp $readme_file trunk/readme.txt" );

	if ( '' !== $stable_tag ) {
		$shell->must_run( "cp $readme_file tags/$stable_tag/readme.txt" );
	}

	if ( is_dir( $assets_dir ) ) {
		$shell->must_run( "rsync --recursive --checksum $assets_dir/ assets/ --delete --delete-excluded" );
	}

	$core->end_group();

	/**
	 * Subversion modifications.
	 */
	$core->start_group( '💾 Subversion modifications' );

	$shell->must_run( 'svn status' );

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
				$shell->must_run( "svn rm $path" );

				$changes[] = [ '➖ Removed', $path ];

				break;
			case 'modified':
				// Modified entry will be commited.

				$changes[] = [ '✏️ Modified', $path ];

				break;
			case 'unversioned':
				$shell->must_run( "svn add $path" );

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
			$shell->must_run( "svn propset svn:mime-type '$type' '$file'" );
		}
	}

	$core->end_group();

	/**
	 * Commit.
	 */
	$core->start_group( '⬆ Subversion commit WordPress.org' );

	$shell->must_run( "svn commit --message 'Update readme.txt' --non-interactive --username '$username' --password '$password'" );

	$core->end_group();

	/**
	 * Clean up.
	 */
	$core->start_group( '🗑️ Clean up' );

	$shell->must_run( "rm -f -R $svn_checkout_dir" );

	$core->end_group();
} catch ( RuntimeException $e ) {
	$core->set_failed( $e->getMessage() );
}

/**
 * Job summary.
 */
$summary = new Summary();

$summary
	->add_heading( 'Deploy `readme.txt` to WordPress.org', 2 )
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

$summary->write();
