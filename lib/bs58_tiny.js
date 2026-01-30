/**
 * Solsigner - Robust Base58 Decoder
 * Compatible with Window and Service Worker contexts.
 */
(function (root) {
    const ALPHABET = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
    const MAP = {};
    for (let i = 0; i < ALPHABET.length; i++) {
        MAP[ALPHABET[i]] = i;
    }

    root.decodeBase58 = function (string) {
        if (typeof string !== 'string') return new Uint8Array(0);
        if (string.length === 0) return new Uint8Array(0);

        const bytes = [0];
        for (let i = 0; i < string.length; i++) {
            const char = string[i];
            let carry = MAP[char];
            if (carry === undefined) throw new Error('Invalid Base58 character');

            for (let j = 0; j < bytes.length; j++) {
                carry += bytes[j] * 58;
                bytes[j] = carry & 0xff;
                carry >>= 8;
            }

            while (carry > 0) {
                bytes.push(carry & 0xff);
                carry >>= 8;
            }
        }

        for (let k = 0; string[k] === '1' && k < string.length - 1; k++) {
            bytes.push(0);
        }

        return new Uint8Array(bytes.reverse());
    };
})(typeof self !== 'undefined' ? self : (typeof window !== 'undefined' ? window : globalThis));
