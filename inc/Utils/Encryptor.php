<?php
/**
 * WordPress-safe encryption utility.
 *
 * Useful for encrypting sensitive data before storing it in the database.
 *
 * @package rtCamp\WPFramework\Utils
 * @since   0.0.1
 */

declare( strict_types = 1 );

namespace rtCamp\WPFramework\Utils;

/**
 * Class - Encryptor
 *
 * Instance-based, configured with a key (and optional cipher) at construction —
 * so different domains can use different keys, and it is trivially testable.
 *
 * Designed to be a service: construct it with a key, register an instance as
 * Shareable in a consumer's container, or extend it to change the cipher or the
 * key source (e.g. KMS) by overriding the {@see Encryptor::key()} seam.
 *
 * @since 0.0.1
 */
class Encryptor {

	/**
	 * GCM authentication tag length in bytes.
	 */
	protected const TAG_LENGTH = 16;

	/**
	 * IV length for GCM mode in bytes.
	 */
	protected const IV_LENGTH = 12;

	/**
	 * Constructor.
	 *
	 * @param string $key    Encryption key. May be left empty by a subclass that
	 *                       overrides {@see Encryptor::key()} to source it elsewhere.
	 * @param string $cipher OpenSSL cipher method. Default 'aes-256-gcm'.
	 */
	public function __construct(
		protected string $key = '',
		protected string $cipher = 'aes-256-gcm',
	) {}

	/**
	 * Encrypt a value using authenticated encryption (AES-256-GCM by default).
	 *
	 * @param string $raw_value The value to encrypt.
	 *
	 * @return string|false The encrypted value, or false on failure.
	 *
	 * @throws \RuntimeException If no encryption key is available.
	 */
	public function encrypt( string $raw_value ): string|false {
		if ( ! extension_loaded( 'openssl' ) ) {
			_doing_it_wrong(
				__METHOD__,
				'OpenSSL extension is not loaded. Encryption cannot proceed.',
				'0.0.1',
			);
			return false;
		}

		$iv  = random_bytes( static::IV_LENGTH );
		$tag = '';

		$value = openssl_encrypt(
			$raw_value,
			$this->cipher,
			$this->key(),
			OPENSSL_RAW_DATA,
			$iv,
			$tag,
			'',
			static::TAG_LENGTH
		);

		return false !== $value ? base64_encode( $iv . $tag . $value ) : false;
	}

	/**
	 * Decrypt a value produced by {@see Encryptor::encrypt()}.
	 *
	 * @param string $raw_value The encrypted value.
	 *
	 * @return string|false The decrypted value, or false on failure/tampering.
	 *
	 * @throws \RuntimeException If no encryption key is available.
	 */
	public function decrypt( string $raw_value ): string|false {
		if ( ! extension_loaded( 'openssl' ) ) {
			_doing_it_wrong(
				__METHOD__,
				'OpenSSL extension is not loaded. Decryption cannot proceed.',
				'0.0.1',
			);
			return false;
		}

		$decoded_value = base64_decode( $raw_value, true );
		if ( false === $decoded_value ) {
			// Don't leak potentially sensitive data, e.g. an unencrypted value that was accidentally passed in.
			_doing_it_wrong(
				__METHOD__,
				'Invalid input: not a valid base64-encoded string.',
				'0.0.1',
			);
			return false;
		}

		$iv         = substr( $decoded_value, 0, static::IV_LENGTH );
		$tag        = substr( $decoded_value, static::IV_LENGTH, static::TAG_LENGTH );
		$ciphertext = substr( $decoded_value, static::IV_LENGTH + static::TAG_LENGTH );

		return openssl_decrypt(
			$ciphertext,
			$this->cipher,
			$this->key(),
			OPENSSL_RAW_DATA,
			$iv,
			$tag
		);
	}

	/**
	 * Resolve the encryption key.
	 *
	 * Override seam: a subclass can source the key from elsewhere (KMS, a rotated
	 * secret, an env var) without touching the crypto. Must never return an empty
	 * key — encryption must not proceed with a weak/missing key.
	 *
	 * @return string The encryption key.
	 *
	 * @throws \RuntimeException If no key is available.
	 */
	protected function key(): string {
		if ( '' === $this->key ) {
			throw new \RuntimeException(
				'No encryption key provided. Pass a key to the Encryptor constructor or override Encryptor::key().'
			);
		}

		return $this->key;
	}
}
