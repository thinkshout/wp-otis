<?php

if ( ! class_exists( 'WP_CLI' ) ) {
	return;
}

require_once 'Otis_Importer.php';
require_once 'Otis_Logger_CLI.php';

/**
 * Implements OTIS command.
 */
class Otis_Command extends WP_CLI_Command {
	/**
	 * @var Otis_Importer
	 */
	private $importer;

	/**
	 * Otis_Command constructor.
	 *
	 * @param Otis_Importer $importer
	 */
	public function __construct( $importer ) {
		$this->importer = $importer;
	}

	/**
	 * Import from OTIS.
	 *
	 * @param array $args
	 * @param array $assoc_args
	 */
	function import( $args, $assoc_args ) {
		try {
			$log = $this->importer->import( $args, $assoc_args );
			WP_CLI::log( implode( PHP_EOL, $log ) );
		} catch ( Exception $e ) {
			WP_CLI::error( 'Importer Error: ' . $e->getMessage() );
		}
	}

    /**
     * Remove bulk import status from OTIS.
     */
    function nobulk( ) {
        try {
            $log = $this->importer->nobulk( );
            WP_CLI::log( implode( PHP_EOL, $log ) );
        } catch ( Exception $e ) {
            WP_CLI::error( 'Importer Error: ' . $e->getMessage() );
        }
    }

	/**
	 * Fix errant ACF start_date relationships
	 *
	 * @param array $args
	 * @param array $assoc_args
	 */
	function start_dates( $args, $assoc_args ) {
		try {
			$log = $this->importer->start_dates( $args, $assoc_args );
			WP_CLI::log( implode( PHP_EOL, $log ) );
		} catch ( Exception $e ) {
			WP_CLI::error( 'Importer Error: ' . $e->getMessage() );
		}
	}

	/**
	 * Generate ACF from OTIS.
	 *
	 * @subcommand generate-acf
	 *
	 * @param array $args
	 * @param array $assoc_args
	 */
	function generate_acf( $args, $assoc_args ) {
		try {
			$log = $this->importer->generate_acf();
			WP_CLI::log( implode( PHP_EOL, $log ) );
		} catch ( Exception $e ) {
			WP_CLI::error( 'Importer Error: ' . $e->getMessage() );
		}
	}

	/**
	 * Report on UUID discrepancies between WordPress and OTIS.
	 *
	 * @param array $args
	 * @param array $assoc_args
	 */
	function report( $args, $assoc_args ) {
		try {
			$log = $this->importer->report();
			WP_CLI::log( implode( PHP_EOL, $log ) );
		} catch ( Exception $e ) {
			WP_CLI::error( 'Importer Error: ' . $e->getMessage() );
		}
	}
}

$otis          = new Otis();
$otis_logger   = new Otis_Logger_CLI();
$otis_importer = new Otis_Importer( $otis, $otis_logger );
$otis_command  = new Otis_Command( $otis_importer );
WP_CLI::add_command( 'otis', $otis_command );
