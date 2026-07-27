<?php
/**
 * Encryptor tests.
 *
 * @package rtCamp\WPFramework\Tests
 */

declare( strict_types = 1 );

namespace rtCamp\WPFramework\Tests\Utils;

use rtCamp\WPFramework\Tests\TestCase;
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

	/**
	 * An Encryptor that exposes the derived key so tests can assert on it
	 * directly rather than inferring derivation from roundtrip behaviour.
	 *
	 * @param string $key    Secret of any length.
	 * @param string $cipher Cipher to derive for.
	 */
	private function key_probe( string $key, string $cipher = 'aes-256-gcm' ): Encryptor {
		return new class( $key, $cipher ) extends Encryptor {
			public function derived_key(): string {
				return $this->key();
			}
		};
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
		$this->setExpectedIncorrectUsage( Encryptor::class . '::decrypt' );

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

	/**
	 * @dataProvider data_secret_lengths
	 *
	 * @param string $secret Secret of a length other than the cipher key length.
	 */
	public function test_any_length_secret_derives_a_full_cipher_length_key( string $secret ): void {
		// OpenSSL would NUL-pad a short secret and truncate a long one; the
		// derivation must instead map any length to the full key length.
		$this->assertSame( 32, strlen( $this->key_probe( $secret )->derived_key() ) );

		$encryptor = $this->encryptor( $secret );
		$encrypted = $encryptor->encrypt( 'any-length secret' );

		$this->assertIsString( $encrypted );
		$this->assertSame( 'any-length secret', $encryptor->decrypt( $encrypted ) );
	}

	/**
	 * @return array<string, array{string}>
	 */
	public function data_secret_lengths(): array {
		return [
			'single byte'      => [ 'k' ],
			'shorter than key' => [ 'short-secret' ],
			'exactly key size' => [ self::KEY ],
			'longer than key'  => [ str_repeat( 'k', 100 ) ],
		];
	}

	public function test_derived_key_length_follows_the_cipher(): void {
		$this->assertSame( 16, strlen( $this->key_probe( 'short-secret', 'aes-128-gcm' )->derived_key() ) );
		$this->assertSame( 32, strlen( $this->key_probe( 'short-secret', 'aes-256-gcm' )->derived_key() ) );
	}

	public function test_secrets_differing_only_past_the_key_length_are_not_equivalent(): void {
		// Regression: OpenSSL truncates an over-long key, so rotating only the
		// tail of a long secret used to be a silent no-op. Hashing must make the
		// whole secret significant.
		$shared = str_repeat( 'k', 32 );
		$first  = $shared . 'tail-one';
		$second = $shared . 'tail-two';

		$this->assertNotSame(
			$this->key_probe( $first )->derived_key(),
			$this->key_probe( $second )->derived_key()
		);

		$encrypted = $this->encryptor( $first )->encrypt( 'rotated' );
		$this->assertIsString( $encrypted );
		$this->assertFalse( $this->encryptor( $second )->decrypt( $encrypted ) );
	}

	public function test_short_secret_is_not_equivalent_to_its_nul_padded_form(): void {
		// Regression: OpenSSL NUL-pads a short key, which made 'secret' and
		// 'secret' + NULs the same key and quietly weakened the cipher.
		$secret = 'secret';
		$padded = $secret . str_repeat( "\0", 32 - strlen( $secret ) );

		$this->assertNotSame(
			$this->key_probe( $secret )->derived_key(),
			$this->key_probe( $padded )->derived_key()
		);

		$encrypted = $this->encryptor( $secret )->encrypt( 'not padded' );
		$this->assertIsString( $encrypted );
		$this->assertFalse( $this->encryptor( $padded )->decrypt( $encrypted ) );
	}
}
