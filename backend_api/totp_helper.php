<?php
/**
 * Carbonova World - Pure PHP Time-Based One-Time Password (TOTP) Library
 * Standard: RFC 6238 / RFC 4226 (HMAC-based & Time-based OTP)
 * Fully compatible with Google Authenticator, Microsoft Authenticator, Authy, Apple iOS, etc.
 * Zero external libraries or composer dependencies required.
 */

class CarbonovaTOTP {
    private static $base32Chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * Generate a cryptographically secure Base32 secret key (16 characters = 80 bits).
     *
     * @param int $length Default 16 characters
     * @return string Base32 string
     */
    public static function generateSecret($length = 16) {
        $secret = '';
        for ($i = 0; $i < $length; $i++) {
            $secret .= self::$base32Chars[random_int(0, 31)];
        }
        return $secret;
    }

    /**
     * Decode a Base32 encoded string into raw binary bytes.
     *
     * @param string $base32
     * @return string Binary string
     */
    public static function base32Decode($base32) {
        $base32 = strtoupper(trim(strval($base32)));
        $binary = '';
        $buffer = 0;
        $bitsLeft = 0;
        
        $len = strlen($base32);
        for ($i = 0; $i < $len; $i++) {
            $char = $base32[$i];
            if ($char === '=' || $char === ' ' || $char === '-') {
                continue;
            }
            $val = strpos(self::$base32Chars, $char);
            if ($val === false) {
                continue;
            }
            
            $buffer = ($buffer << 5) | $val;
            $bitsLeft += 5;
            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $binary .= chr(($buffer >> $bitsLeft) & 0xFF);
            }
        }
        return $binary;
    }

    /**
     * Calculate 6-digit TOTP code for a specific 30-second time slice.
     *
     * @param string $secret Base32 secret key
     * @param int|null $timeSlice Defaults to floor(time() / 30)
     * @return string 6-digit zero-padded string
     */
    public static function getCode($secret, $timeSlice = null) {
        if ($timeSlice === null) {
            $timeSlice = floor(time() / 30);
        }
        $secretKey = self::base32Decode($secret);
        
        // Pack time slice as 64-bit big-endian integer (RFC 4226)
        $timeBytes = pack('N*', 0) . pack('N*', $timeSlice);
        
        // HMAC-SHA1 hash
        $hash = hash_hmac('sha1', $timeBytes, $secretKey, true);
        
        // Dynamic truncation (RFC 4226)
        $offset = ord(substr($hash, -1)) & 0x0F;
        $unpacked = unpack('N', substr($hash, $offset, 4));
        $truncatedHash = $unpacked[1] & 0x7FFFFFFF;
        
        // 6 digits zero-padded
        $code = $truncatedHash % 1000000;
        return str_pad($code, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Verify user-submitted 6-digit code against secret.
     * $discrepancy = 1 allows +- 30 seconds clock drift.
     *
     * @param string $secret Base32 secret key
     * @param string $userCode 6-digit code entered by user
     * @param int $discrepancy Number of 30s windows to check before/after
     * @return bool True if valid, false otherwise
     */
    public static function verifyCode($secret, $userCode, $discrepancy = 1) {
        if (empty($secret)) {
            return false;
        }
        $userCode = trim(strval($userCode));
        if (strlen($userCode) !== 6 || !ctype_digit($userCode)) {
            return false;
        }
        $currentTimeSlice = floor(time() / 30);
        for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
            $expectedCode = self::getCode($secret, $currentTimeSlice + $i);
            if (hash_equals($expectedCode, $userCode)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Format standard otpauth URI.
     *
     * @param string $issuer Platform/App name
     * @param string $accountName User account/email
     * @param string $secret Base32 secret key
     * @return string otpauth:// URI
     */
    public static function getOtpAuthUrl($issuer, $accountName, $secret) {
        $encodedIssuer = rawurlencode($issuer);
        $encodedAccount = rawurlencode($accountName);
        return "otpauth://totp/{$encodedIssuer}:{$encodedAccount}?secret={$secret}&issuer={$encodedIssuer}";
    }

    /**
     * Generate standard QR Code image URL for scanning.
     *
     * @param string $issuer Platform/App name
     * @param string $accountName User account/email
     * @param string $secret Base32 secret key
     * @return string Direct URL to QR code image
     */
    public static function getQrCodeUrl($issuer, $accountName, $secret) {
        $otpUrl = self::getOtpAuthUrl($issuer, $accountName, $secret);
        return "https://api.qrserver.com/v1/create-qr-code/?size=220x220&margin=10&data=" . urlencode($otpUrl);
    }
}
