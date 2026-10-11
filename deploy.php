<?php

/**
 * Autoload.
 */
require __DIR__ . '/vendor/autoload.php';

use SolveBeam\GitHubActionsToolkit\Core;

/**
 * Functions.
 */
function escape_sequence( $code ) {
	return "\e[" . $code . 'm';
}

function format_command( $value ) {
	return escape_sequence( '36' ) . $value . escape_sequence( '0' );
}

function format_error( $value ) {
	return escape_sequence( '31' ) . escape_sequence( '1' ) . 'Error:' . escape_sequence( '0' ) . ' ' . $value;
}

function run_command( $command ) {
	global $commands, $group;

	echo format_command( $command ), PHP_EOL;

	$commands[ $group ][] = $command;

	passthru( $command, $result_code );

	if ( 0 !== $result_code ) {
		exit( $result_code );
	}
}

/**
 * Get input.
 * 
 * @link https://docs.github.com/en/actions/creating-actions/metadata-syntax-for-github-actions#inputs
 * @link https://docs.github.com/en/actions/using-workflows/workflow-syntax-for-github-actions#jobsjob_idstepswith
 * @link https://github.com/actions/checkout/blob/cd7d8d697e10461458bc61a30d094dc601a8b017/dist/index.js#L2699-L2717
 * @param string $name
 * @return string|array|false
 */
function get_input( $name ) {
	$env_name = 'INPUT_' . strtoupper( $name );

	return getenv( $env_name );
}

function get_required_input( $name ) {
	$value = get_input( $name );

	if ( false === $value || '' === $value ) {
		echo format_error( escape_sequence( '90' ) . 'Input required and not supplied:' . escape_sequence( '0' ) . ' ' . $name );

		exit( 1 );
	}

	return $value;
}

/**
 * Set output.
 *
 * @link https://docs.github.com/en/actions/writing-workflows/choosing-what-your-workflow-does/workflow-commands-for-github-actions#setting-an-output-parameter
 * @param string $name
 * @param string $value
 * @return void
 */
function set_output( $name, $value ) {
	$file = getenv( 'GITHUB_OUTPUT' );

	if ( false === $file || '' === $file ) {
		return;
	}

	file_put_contents( $file, $name . '=' . $value . PHP_EOL, FILE_APPEND );
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
$core = new Core();

$commands = [];
$group    = '';

$username = get_required_input( 'username' );
$password = get_required_input( 'password' );

$slug = get_required_input( 'slug' );

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

set_output( 'public-url', $public_url );
set_output( 'svn-url', $svn_url );
set_output( 'svn-tag-url', $svn_tag_url );

/**
 * Start.
 */
$group = 'ℹ️ Deploy readme.txt to WordPress.org';

$core->start_group( $group );

echo '• ', escape_sequence( '1' ), 'Public URL:', escape_sequence( '0' ), ' ', $public_url, PHP_EOL;
echo '• ', escape_sequence( '1' ), 'Subversion URL:', escape_sequence( '0' ), ' ', $svn_url, PHP_EOL;
echo '• ', escape_sequence( '1' ), 'Subversion tag URL:', escape_sequence( '0' ), ' ', $svn_tag_url, PHP_EOL;
echo '• ', escape_sequence( '1' ), 'Subversion username:', escape_sequence( '0' ), ' ', $username, PHP_EOL;
echo '• ', escape_sequence( '1' ), 'Subversion checkout directory:', escape_sequence( '0' ), ' ', $svn_checkout_dir, PHP_EOL;
echo '• ', escape_sequence( '1' ), 'Path readme.txt:', escape_sequence( '0' ), ' ', $readme_file, PHP_EOL;
echo '• ', escape_sequence( '1' ), 'Path assets:', escape_sequence( '0' ), ' ', $assets_dir, PHP_EOL;
echo '• ', escape_sequence( '1' ), 'Stable tag:', escape_sequence( '0' ), ' ', $stable_tag, PHP_EOL;

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
	echo 'A problem occurred while reading the `svn status --xml`.';

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
			echo "Unsupport working copy status: $wc_status - $path.";

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
	'png' => 'image/png',
	'jpg' => 'image/jpeg',
	'gif' => 'image/gif',
	'svg' => 'image/svg+xml',
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
	"- **Plugin:** [$slug]($public_url)",
	"- **Subversion:** <$svn_url>",
	"- **Trac:** <$trac_url>",
];

if ( '' !== $stable_tag ) {
	$summary[] = "- **Stable tag:** [$stable_tag]($svn_tag_url)";
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
