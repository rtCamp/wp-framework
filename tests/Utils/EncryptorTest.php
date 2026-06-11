<?php
/**
 * Encryptor tests.
 *
 * @package rtCamp\WPFramework\Tests
 */

declare( strict_types = 1 );

namespace rtCamp\WPFramework\Tests\Utils;

use PHPUnit\Framework\TestCase;
use rtCamp\WPFramework\Utils\Encryptor;

/**
 * Tests for Encryptor.
 */
final class EncryptorTest extends TestCase {

	/**
	 * A deterministic 32-byte key for reproducible tests.
	 */
	private const KEY = 'kkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkk';

	private function encryptor( string $key = self::KEY ): Encryptor {
		return new Encryptor( $key );
	}

	public function test_encrypt_decrypt_roundtrips_plaintext(): void {
		$encryptor = $this->encryptor();
		$plaintext = 'sensitive-secret-value';

		$encrypted = $encryptor->encrypt( $plaintext );

		$this->assertIsString( $encrypted );
		$this->assertNotSame( $plaintext, $encrypted );
		$this->assertSame( $plaintext, $encryptor->decrypt( $encrypted ) );
	}

	public function test_each_encryption_produces_different_ciphertext(): void {
		// GCM uses a random IV per call, so the same plaintext must never
		// produce identical ciphertext — that's a hard correctness invariant.
		$encryptor = $this->encryptor();

		$a = $encryptor->encrypt( 'hello world' );
		$b = $encryptor->encrypt( 'hello world' );

		$this->assertIsString( $a );
		$this->assertIsString( $b );
		$this->assertNotSame( $a, $b );
	}

	public function test_value_encrypted_with_one_key_does_not_decrypt_with_another(): void {
		$encrypted = $this->encryptor( str_repeat( 'a', 32 ) )->encrypt( 'secret' );
		$this->assertIsString( $encrypted );

		// A different key (different instance) must not authenticate the tag.
		$this->assertFalse( $this->encryptor( str_repeat( 'b', 32 ) )->decrypt( $encrypted ) );
	}

	public function test_decrypt_returns_false_for_invalid_base64(): void {
		$this->assertFalse( $this->encryptor()->decrypt( 'not!valid!base64' ) );
	}

	public function test_decrypt_returns_false_for_tampered_ciphertext(): void {
		$encryptor = $this->encryptor();
		$encrypted = $encryptor->encrypt( 'secret' );
		$this->assertIsString( $encrypted );

		// Flip a byte in the middle of the payload.
		$decoded     = base64_decode( $encrypted, true );
		$decoded[20] = 'A' === $decoded[20] ? 'B' : 'A';
		$tampered    = base64_encode( $decoded );

		$this->assertFalse( $encryptor->decrypt( $tampered ) );
	}

	public function test_key_seam_can_be_overridden_by_a_subclass(): void {
		// A subclass can source the key from anywhere via the key() seam, while
		// reusing the crypto unchanged.
		$encryptor = new class() extends Encryptor {
			protected function key(): string {
				return str_repeat( 'z', 32 );
			}
		};

		$encrypted = $encryptor->encrypt( 'from-subclass' );
		$this->assertIsString( $encrypted );
		$this->assertSame( 'from-subclass', $encryptor->decrypt( $encrypted ) );
	}

	public function test_missing_key_throws(): void {
		$this->expectException( \RuntimeException::class );

		( new Encryptor() )->encrypt( 'no key configured' );
	}

	public function test_non_gcm_cipher_is_rejected(): void {
		// The payload layout (IV + tag + ciphertext) is GCM-specific, so a
		// non-AEAD cipher must be rejected at construction.
		$this->expectException( \InvalidArgumentException::class );

		new Encryptor( self::KEY, 'aes-256-cbc' );
	}

	public function test_alternate_gcm_cipher_roundtrips(): void {
		$encryptor = new Encryptor( self::KEY, 'aes-128-gcm' );

		$encrypted = $encryptor->encrypt( 'gcm variant' );
		$this->assertIsString( $encrypted );
		$this->assertSame( 'gcm variant', $encryptor->decrypt( $encrypted ) );
	}
}
