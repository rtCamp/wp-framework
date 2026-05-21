<?php
/**
 * Tests for Encryptor class.
 *
 * @package WPFramework\Tests
 */

declare( strict_types=1 );

namespace WPFramework\Tests;

use PHPUnit\Framework\TestCase;
use WPFramework\Encryptor;

/**
 * @covers \WPFramework\Encryptor
 */
class EncryptorTest extends TestCase {

	protected function setUp(): void {
		// Define constant for encryption key if not already defined.
		if ( ! defined( 'WP_FRAMEWORK_ENCRYPTION_KEY' ) ) {
			define( 'WP_FRAMEWORK_ENCRYPTION_KEY', 'test-encryption-key-32-bytes!!' );
		}
	}

	public function test_encrypt_returns_non_empty_string(): void {
		$encrypted = Encryptor::encrypt( 'hello world' );

		$this->assertIsString( $encrypted );
		$this->assertNotEmpty( $encrypted );
		$this->assertNotSame( 'hello world', $encrypted );
	}

	public function test_decrypt_recovers_original_value(): void {
		$original  = 'sensitive data here';
		$encrypted = Encryptor::encrypt( $original );

		$this->assertIsString( $encrypted );

		$decrypted = Encryptor::decrypt( $encrypted );
		$this->assertSame( $original, $decrypted );
	}

	public function test_encrypt_produces_different_ciphertexts_for_same_input(): void {
		$value = 'same-input';
		$enc1  = Encryptor::encrypt( $value );
		$enc2  = Encryptor::encrypt( $value );

		// Due to random IV, encryptions should differ.
		$this->assertNotSame( $enc1, $enc2 );
	}

	public function test_decrypt_returns_false_for_invalid_base64(): void {
		$result = Encryptor::decrypt( '!!!not-base64!!!' );
		$this->assertFalse( $result );
	}

	public function test_decrypt_returns_false_for_tampered_ciphertext(): void {
		$encrypted = Encryptor::encrypt( 'test' );
		$this->assertIsString( $encrypted );

		// Tamper with the encrypted value.
		$tampered = substr( $encrypted, 0, -4 ) . 'XXXX';
		$result   = Encryptor::decrypt( $tampered );

		$this->assertFalse( $result );
	}

	public function test_encrypt_empty_string(): void {
		$encrypted = Encryptor::encrypt( '' );
		$this->assertIsString( $encrypted );

		$decrypted = Encryptor::decrypt( $encrypted );
		$this->assertSame( '', $decrypted );
	}

	public function test_encrypt_special_characters(): void {
		$original  = "Hello\nWorld\t<script>alert('xss')</script>";
		$encrypted = Encryptor::encrypt( $original );

		$this->assertIsString( $encrypted );

		$decrypted = Encryptor::decrypt( $encrypted );
		$this->assertSame( $original, $decrypted );
	}

	public function test_decrypt_returns_false_for_short_ciphertext(): void {
		// Less than IV_LENGTH + TAG_LENGTH bytes when decoded.
		$short  = base64_encode( 'short' );
		$result = Encryptor::decrypt( $short );

		$this->assertFalse( $result );
	}
}
