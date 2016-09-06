<?php

namespace kullar84\digidocservice\helpers;

/**
 * Helper class for dealing with conversion and manipulation of digital document files.
 *
 * LICENSE:
 *
 * This library is free software; you can redistribute it
 * and/or modify it under the terms of the GNU Lesser General
 * Public License as published by the Free Software Foundation;
 * either version 2.1 of the License, or (at your option) any
 * later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
 *
 * @author        Mihkel Selgal <mihkel.selgal@nortal.com>
 * @author        Kullar Kert <kullar@5dvision.ee>
 * @license       http://www.opensource.org/licenses/lgpl-license.php LGPL
 */
class CertificateHelper
{
    const SHA_1 = 'SHA-1';
    const SHA_224 = 'SHA-224';
    const SHA_256 = 'SHA-256';
    const SHA_384 = 'SHA-384';
    const SHA_512 = 'SHA-512';

    const SHA_1_BYTES = 20;
    const SHA_224_BYTES = 28;
    const SHA_256_BYTES = 32;
    const SHA_384_BYTES = 48;
    const SHA_512_BYTES = 64;

    const SHA_1_LENGTH = 40;
    const SHA_224_LENGTH = 56;
    const SHA_256_LENGTH = 64;
    const SHA_384_LENGTH = 96;
    const SHA_512_LENGTH = 128;

    public static $shaTypeValidationRules = array(
        self::SHA_1 => array(
            'bytes' => self::SHA_1_BYTES,
            'length' => self::SHA_1_LENGTH
        ),
        self::SHA_224 => array(
            'bytes' => self::SHA_224_BYTES,
            'length' => self::SHA_224_LENGTH
        ),
        self::SHA_256 => array(
            'bytes' => self::SHA_256_BYTES,
            'length' => self::SHA_256_LENGTH
        ),
        self::SHA_384 => array(
            'bytes' => self::SHA_384_BYTES,
            'length' => self::SHA_384_LENGTH
        ),
        self::SHA_512 => array(
            'bytes' => self::SHA_512_BYTES,
            'length' => self::SHA_512_LENGTH
        )
    );

    /**
     * Get hastype by hash
     *
     * @param string $hashValue Hash
     *
     * @return null|string Hash name
     */
    public static function getHashType($hashValue)
    {
        $hashLength = strlen($hashValue);

        foreach (self::$shaTypeValidationRules as $hashName => $hasRules) {
            if ($hasRules['length'] === $hashLength && ctype_xdigit($hashValue)) {
                return $hashName;
            }
        }

        return null;
    }
}


