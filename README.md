# Solsigner

Solsigner is a Chrome extension that provides a secure, one-click mechanism for proving Solana wallet ownership via cryptographic message signing. It uses a bridge architecture to remain compatible with Phantom wallet's security requirements while maintaining a premium, emerald-themed user interface.

## Features

-   **Secure Pairing**: Cryptographically verify wallet ownership using Ed25519 signatures.
-   **One-Click Proof**: Generate a fresh "Proof of Life" signature on demand.
-   **Anti-Replay Protection**: Uses nonce-based challenges to prevent signature reuse.
-   **Premium UI**: Sleek emerald/slate design system.
-   **Bridge Tech**: Custom HTTPS bridge to ensure reliable wallet injection.

## Installation

1.  Clone this repository.
2.  Open Chrome and navigate to `chrome://extensions`.
3.  Enable **Developer mode**.
4.  Click **Load unpacked** and select the extension directory.

## Server Setup

For the pairing to work, you must host the provided `server/pair.php` on an HTTPS-enabled domain.

1.  Upload `server/pair.php` to your server (e.g., `https://yourdomain.com/solsigner/pair.php`).
2.  Update the `bridgeUrl` in `popup.js` and `options.js` if necessary.
3.  Add your domain to the `externally_connectable` section in `manifest.json`.

## How it Works

Solsigner generates a random nonce in the background service worker. This nonce is sent to the pairing bridge where the user signs a message containing it. The background worker then uses the `tweetnacl` library to verify the signature against the provided public key. If valid, the pairing is saved.

## Security

-   **Cryptographic Verification**: Signatures are verified locally within the extension.
-   **No Private Keys**: Solsigner never touches your private keys; it only requests signatures from the Phantom provider.
-   **External Connectivity**: The extension only accepts messages from specified, trusted domains.

## Libraries Used

-   [TweetNaCl.js](https://github.com/dchest/tweetnacl-js) - For Ed25519 signature verification.
-   Custom Base58 Decoder - For handling Solana addresses.
