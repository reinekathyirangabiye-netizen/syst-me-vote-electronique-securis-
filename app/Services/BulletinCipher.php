<?php

namespace App\Services;

use RuntimeException;

class BulletinCipher
{
    private const VERSION = "\x01";
    private const NONCE_LENGTH = 12;
    private const TAG_LENGTH = 16;

    public function chiffrer(int $idCandidat): string
    {
        if ($idCandidat < 1) {
            throw new RuntimeException('Identifiant de candidat invalide.');
        }

        $nonce = random_bytes(self::NONCE_LENGTH);
        $tag = '';
        $chiffre = openssl_encrypt(
            (string) $idCandidat,
            'aes-256-gcm',
            $this->key(),
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            self::VERSION,
            self::TAG_LENGTH
        );

        if ($chiffre === false || strlen($tag) !== self::TAG_LENGTH) {
            throw new RuntimeException('Le bulletin ne peut pas être chiffré.');
        }

        return base64_encode(self::VERSION . $nonce . $tag . $chiffre);
    }

    public function validerCle(): void
    {
        $this->key();
    }

    public function dechiffrer(string $bulletin): int
    {
        $contenu = base64_decode($bulletin, true);
        $tailleEntete = 1 + self::NONCE_LENGTH + self::TAG_LENGTH;

        if ($contenu === false || strlen($contenu) <= $tailleEntete || $contenu[0] !== self::VERSION) {
            throw new RuntimeException('Format de bulletin chiffré invalide.');
        }

        $nonce = substr($contenu, 1, self::NONCE_LENGTH);
        $tag = substr($contenu, 1 + self::NONCE_LENGTH, self::TAG_LENGTH);
        $chiffre = substr($contenu, $tailleEntete);
        $clair = openssl_decrypt(
            $chiffre,
            'aes-256-gcm',
            $this->key(),
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            self::VERSION
        );

        if ($clair === false || !ctype_digit($clair) || (int) $clair < 1) {
            throw new RuntimeException('Authentification du bulletin impossible.');
        }

        return (int) $clair;
    }

    private function key(): string
    {
        $configured = (string) config('services.vote_encryption_key', '');
        if (!str_starts_with($configured, 'base64:')) {
            throw new RuntimeException('CLE_CHIFFREMENT_SCRUTIN doit être une clé base64 de 32 octets.');
        }

        $key = base64_decode(substr($configured, 7), true);
        if ($key === false || strlen($key) !== 32) {
            throw new RuntimeException('CLE_CHIFFREMENT_SCRUTIN doit être une clé base64 de 32 octets.');
        }

        return $key;
    }
}
