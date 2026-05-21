<?php
/**
 * Encryptor tests.
 *
 * @package rtCamp\WPFramework\Tests
 */

declare( strict_types = 1 );

namespace rtCamp\WPFramework\Tests;

use PHPUnit\Framework\TestCase;
use rtCamp\WPFramework\Encryptor;

final class EncryptorTest extends TestCase {

	public function test_encrypt_decrypt_roundtrips_plaintext(): void {
		$plaintext = 'sensitive-secret-value';

		$encrypted = Encryptor::encrypt( $plaintext );

		$this->assertIsString( $encrypted );
		$this->assertNotSame( $plaintext, $encrypted );
		$this->assertSame( $plaintext, Encryptor::decrypt( $encrypted ) );
	}

	public function test_each_encryption_produces_different_ciphertext(): void {
		// GCM uses a random IV per call, so the same plaintext must never
		// produce identical ciphertext — that's a hard correctness invariant.
		$a = Encryptor::encrypt( 'hello world' );
		$b = Encryptor::encrypt( 'hello world' );

		$this->assertIsString( $a );
		$this->assertIsString( $b );
		$this->assertNotSame( $a, $b );
	}

	public function test_decrypt_returns_false_for_invalid_base64(): void {
		$this->assertFalse( Encryptor::decrypt( 'not!valid!base64' ) );
	}

	public function test_decrypt_returns_false_for_tampered_ciphertext(): void {
		$encrypted = Encryptor::encrypt( 'secret' );
		$this->assertIsString( $encrypted );

		// Flip a byte in the middle of the payload.
		$decoded   = base64_decode( $encrypted, true );
		$decoded[20] = $decoded[20] === 'A' ? 'B' : 'A';
		$tampered  = base64_encode( $decoded );

		$this->assertFalse( Encryptor::decrypt( $tampered ) );
	}
}
