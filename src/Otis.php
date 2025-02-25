<?php

require_once 'Otis_Exception.php';

/**
 * Otis API Wrapper.
 */
class Otis {
	const API_ROOT = 'https://otis.traveloregon.com/api/v5';
	const AUTH_ROOT = 'https://otis.traveloregon.com/rest-auth';

	private $ch;
	private $ua;

	/**
	 * Otis constructor.
	 */
	public function __construct() {
		$this->ch = curl_init();
		$this->ua = 'Otis-PHP/' . $this->wp_otis_version();

		curl_setopt( $this->ch, CURLOPT_USERAGENT, $this->ua );
		curl_setopt( $this->ch, CURLOPT_HEADER, false );
		curl_setopt( $this->ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $this->ch, CURLOPT_CONNECTTIMEOUT, 30 );
		curl_setopt( $this->ch, CURLOPT_TIMEOUT, 600 );
		curl_setopt( $this->ch, CURLOPT_SSL_VERIFYHOST, false );
		curl_setopt( $this->ch, CURLOPT_SSL_VERIFYPEER, false );

	}

	public function __destruct() {
		if ( is_resource( $this->ch ) ) {
			curl_close( $this->ch );
		}
	}

	protected function wp_otis_version() {
		// Check if the function is available.
		if( ! function_exists( 'get_plugin_data' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		}
		// Retrieve plugin data from the main plugin file.
		$plugin_data = get_plugin_data( WP_OTIS_PLUGIN_PATH . 'wp-otis.php', false, false );
		// Check if the plugin version was retrieved successfully.
		if ( ! isset( $plugin_data['Version'] ) ) {
			return '';
		}
		// Return the plugin version number.
		return $plugin_data['Version'];
	}

	/**
	 * Get the API token.
	 * If the token does not exist, a new one is fetched from the API.
	 *
	 * @param bool $refresh Force token refresh via the API.
	 *
	 * @return string
	 */
	public function token( $refresh = false ) {
		// Mutex to prevent multiple token fetches within a single page load.
		static $token_fetch = false;

		$token = null;
		if ( $refresh ) {
			delete_option( WP_OTIS_TOKEN );
		} else {
			$token = get_option( WP_OTIS_TOKEN, '' );
		}

		if ( ! $token && ! $token_fetch ) {
			$token_fetch = true;

			$params = array(
				'username' => get_option( WP_OTIS_USERNAME, '' ),
				'password' => get_option( WP_OTIS_PASSWORD, '' ),
			);

			$credentials_in_code = apply_filters( 'wp_otis_rest_auth', $params );

			if ( $credentials_in_code['username'] || $credentials_in_code['password'] ) {
				$params = $credentials_in_code;
				// Save the credentials to the database.
				update_option( WP_OTIS_USERNAME, $params['username'] );
				update_option( WP_OTIS_PASSWORD, $params['password'] );
			}

			if ( ! $params['username'] || ! $params['password'] ) {
				throw new Otis_Exception( 'Missing username or password' );
				return '';
			}

			try {
				$result = $this->_fetch( self::AUTH_ROOT . '/login/', array(
					'post' => $params,
				) );

				$token = $result['key'];
				update_option( WP_OTIS_TOKEN, $token );
			} catch ( Otis_Exception $exception ) {
				$token = '';
			}
		}

		return $token;
	}

	/**
	 * @param $path
	 * @param array $params
	 *
	 * @return array
	 * @throws \Otis_Exception
	 */
	public function call( $path, $params = array(), $logger = null, $verbose = false ) {
		if ( $path && substr( $path, - 1 ) !== '/' ) {
			$path .= '/';
		}

		$params['app-nocache'] = 'true';

		$headers[] = 'Accept: application/json';

		$token = $this->token();
		if ( !$token ) {
			return null;
		}

		$headers[] = 'Authorization: Token ' . $token;

		try {
			return $this->_fetch( self::API_ROOT . '/' . $path, array(
				'headers' => $headers,
				'get'     => $params,
			), $logger, $verbose );
		} catch ( Otis_Exception $exception ) {
			if ( $logger ) {
				$logger->log('Otis Exception: ' . $exception->getCode() . ' - ' . $exception->getMessage());
			}
			if ( 401 === $exception->getCode() ) {
				// Try fetching a new token if auth failed.
				$token = $this->token( true );

				if ( $token ) {
					// Try the call one more time.
					return $this->call( $path, $params, $logger );
				}
			}
		}

		return null;
	}

	/**
	 * @param string $url
	 * @param array $options
	 *
	 * @return array
	 * @throws \Otis_Exception
	 */
	private function _fetch( $url, $options = array(), $logger = null, $verbose = false ) {
		if ( ! empty( $options['get'] ) ) {
			$url .= '?' . http_build_query( $options['get'] );
		}
		curl_setopt( $this->ch, CURLOPT_URL, $url );

		if ( ! empty( $options['post'] ) ) {
			curl_setopt( $this->ch, CURLOPT_POSTFIELDS, http_build_query( $options['post'] ) );
		}

		curl_setopt( $this->ch, CURLOPT_HTTPHEADER, $options['headers'] ?? array() );
		curl_setopt( $this->ch, CURLOPT_TIMEOUT, 30 );

		if ($logger) {
			$logger->log("About to call url " . $url . ' User Agent: ' . $this->ua);
		}
		$response_body = curl_exec( $this->ch );
		if ($logger && $verbose) {
			$logger->log("Call returned with options".json_encode($options));
		}

		if ( curl_error( $this->ch ) ) {
			if ($logger) {
				$logger->log("API call to $url failed: " . curl_error( $this->ch ));
			}
			throw new Otis_Exception( "API call to $url failed: " . curl_error( $this->ch ) );
		}

		$info = curl_getinfo( $this->ch );
		if ( $info['http_code'] >= 300 ) {
			if ($logger) {
				$logger->log("API call to $url failed: " . $info['http_code']);
			}
			throw new Otis_Exception( 'We received an unexpected error (code ' . $info['http_code'] . '): ' . $response_body, $info['http_code'] );
		}


		if ($logger && $verbose) {
			$logger->log("Returning from call with options".json_encode($options));
		}
		return json_decode( $response_body, true );
	}
}

