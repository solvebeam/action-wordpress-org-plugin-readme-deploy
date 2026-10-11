<?php

/**
 * Autoload.
 */
require __DIR__ . '/vendor/autoload.php';

use SolveBeam\GitHubActionsToolkit\Core;
use SolveBeam\GitHubActionsToolkit\Shell;
use SolveBeam\GitHubActionsToolkit\Style;

/**
 * Functions.
 */
function run_command( $command ) {
	global $commands, $group, $core, $shell;

	$commands[ $group ][] = $command;

	try {
		$shell->must_run( $command );
	} catch ( RuntimeException $e ) {
		$core->set_failed( $e->getMessage() );
	}
}

/**
 * Add job summary.
 *
 * @link https://docs.github.com/en/actions/writing-workflows/choosing-what-your-workflow-does/workflow-commands-for-github-actions#adding-a-job-summary
 * @link https://github.blog/news-insights/product-news/supercharging-github-actions-with-job-summaries/
 * @param string $markdown
 * @return void
 */
function add_job_summary( $markdown ) {
	$file = getenv( 'GITHUB_STEP_SUMMARY' );

	if ( false === $file || '' === $file ) {
		return;
	}

	file_put_contents( $file, $markdown . PHP_EOL, FILE_APPEND );
}

/**
 * Setup.
 */
$core  = new Core();
$style = new Style();
$shell = new Shell();

$commands = [];
$group    = '';

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
$group = 'ℹ️ Deploy readme.txt to WordPress.org';

$core->start_group( $group );

echo '• ', $style->bold( 'Public URL:' ), ' ', $public_url, PHP_EOL;
echo '• ', $style->bold( 'Subversion URL:' ), ' ', $svn_url, PHP_EOL;
echo '• ', $style->bold( 'Subversion tag URL:' ), ' ', $svn_tag_url, PHP_EOL;
echo '• ', $style->bold( 'Subversion username:' ), ' ', $username, PHP_EOL;
echo '• ', $style->bold( 'Subversion checkout directory:' ), ' ', $svn_checkout_dir, PHP_EOL;
echo '• ', $style->bold( 'Path readme.txt:' ), ' ', $readme_file, PHP_EOL;
echo '• ', $style->bold( 'Path assets:' ), ' ', $assets_dir, PHP_EOL;
echo '• ', $style->bold( 'Stable tag:' ), ' ', $stable_tag, PHP_EOL;

$core->end_group();

/**
 * Subversion.
 * 
 * @link https://stackoverflow.com/a/122291
 */
$group = '⬇ Subversion checkout WordPress.org';

$core->start_group( $group );

run_command( "svn checkout $svn_url $svn_checkout_dir --depth empty" );

run_command( "cd $svn_checkout_dir" );

chdir( $svn_checkout_dir );

run_command( 'svn update trunk --depth=empty' );
run_command( 'svn update trunk/readme.txt' );
run_command( 'svn update assets' );

if ( '' !== $stable_tag ) {
	run_command( "svn update tags --depth=empty" );
	run_command( "svn update tags/$stable_tag --depth=empty" );
	run_command( "svn update tags/$stable_tag/readme.txt" );
}

$core->end_group();

/**
 * Synchronize.
 */
$group = '🔄 Synchronize';

$core->start_group( $group );

run_command( "cp $readme_file trunk/readme.txt" );

if ( '' !== $stable_tag ) {
	run_command( "cp $readme_file tags/$stable_tag/readme.txt" );
}

if ( is_dir( $assets_dir ) ) {
	run_command( "rsync --recursive --checksum $assets_dir/ assets/ --delete --delete-excluded" );
}

$core->end_group();

/**
 * Subversion modifications.
 */
$group = '💾 Subversion modifications';

$core->start_group( $group );

run_command( 'svn status' );

$output = shell_exec( 'svn status --xml' );

$xml = simplexml_load_string( $output );

if ( false === $xml ) {
	$core->error( 'A problem occurred while reading the `svn status --xml`.' );

	exit( 1 );
}

$changes = [];

foreach ( $xml->target->entry as $entry ) {
	$path = (string) $entry['path'];

	$wc_status = (string) $entry->{'wc-status'}['item'];

	switch ( $wc_status ) {
		case 'missing':
			run_command( "svn rm $path" );

			$changes[] = [ '➖ Removed', $path ];

			break;
		case 'modified':
			// Modified entry will be commited.

			$changes[] = [ '✏️ Modified', $path ];

			break;
		case 'unversioned':
			run_command( "svn add $path" );

			$changes[] = [ '➕ Added', $path ];

			break;
		default:
			$core->error( "Unsupport working copy status: $wc_status - $path." );

			exit( 1 );
	}
}

$core->end_group();

/**
 * Fix screenshots getting force downloaded when clicking them.
 * 
 * @link https://developer.wordpress.org/plugins/wordpress-org/plugin-assets/
 */
$group = '🐛 Fix downloading assets images issue';

$core->start_group( $group );

$mime_types = [
	'png'  => 'image/png',
	'jpg'  => 'image/jpeg',
	'jpeg' => 'image/jpeg',
	'gif'  => 'image/gif',
	'svg'  => 'image/svg+xml',
];

foreach ( $mime_types as $ext => $type ) {
	foreach ( glob( 'assets/*.' . $ext ) as $file ) {
		run_command( "svn propset svn:mime-type '$type' '$file'" );
	}
}

$core->end_group();

/**
 * Commit.
 */
$group = '⬆ Subversion commit WordPress.org';

$core->start_group( $group );

run_command( "svn commit --message 'Update readme.txt' --non-interactive --username '$username' --password '$password'" );

$core->end_group();

/**
 * Clean up.
 */
$group = '🗑️ Clean up';

$core->start_group( $group );

run_command( "rm -f -R $svn_checkout_dir" );

$core->end_group();

/**
 * Job summary.
 */
$summary = [
	'## Deploy `readme.txt` to WordPress.org',
	'',
	"- **Plugin:** <$public_url>",
	"- **Subversion:** <$svn_url>",
	"- **Trac:** <$trac_url>",
];

if ( '' !== $stable_tag ) {
	$summary[] = "- **Stable tag:** <$svn_tag_url>";
}

$summary[] = '';
$summary[] = '### Subversion changes';
$summary[] = '';

if ( [] === $changes ) {
	$summary[] = 'No changes.';
} else {
	$summary[] = '| Status | Path |';
	$summary[] = '| --- | --- |';

	foreach ( $changes as list( $status, $path ) ) {
		$summary[] = "| $status | `$path` |";
	}
}

$summary[] = '';
$summary[] = '### Shell commands';
$summary[] = '';
$summary[] = 'A deploy is just a series of shell commands, these are the ones this run executed:';
$summary[] = '';
$summary[] = '```shell';

foreach ( $commands as $name => $group_commands ) {
	$summary[] = "# $name";

	foreach ( $group_commands as $command ) {
		// Never write the Subversion password to the job summary.
		$summary[] = str_replace( "'$password'", "'●●●●●●●●'", $command );
	}

	$summary[] = '';
}

array_pop( $summary );

$summary[] = '```';

add_job_summary( implode( PHP_EOL, $summary ) );
