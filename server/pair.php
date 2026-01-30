<?php
/**
 * Solsigner - Surgical Pairing Bridge (V2)
 * Cryptographically secure pairing via signature verification.
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solsigner - Secure Pairing</title>
    <style>
        :root {
            --primary: #10b981;
            --primary-dark: #059669;
            --bg: #0f172a;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --border: #334155;
        }

        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background-color: var(--bg);
            background-image: radial-gradient(rgba(255, 255, 255, 0.05) 0.5px, transparent 0.5px);
            background-size: 20px 20px;
            color: var(--text-main);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }

        .container {
            background: #1e293b;
            padding: 40px;
            border-radius: 20px;
            border: 1px solid var(--border);
            text-align: center;
            max-width: 440px;
            width: 90%;
            box-shadow: 0 10px 25px rgba(0,0,0,0.3);
        }

        h1 {
            font-size: 24px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        p {
            color: var(--text-muted);
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 30px;
        }

        .btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 14px 28px;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            width: 100%;
        }

        .btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        .btn:disabled {
            background: #334155;
            cursor: not-allowed;
            transform: none;
        }

        .status {
            margin-top: 20px;
            font-size: 13px;
            padding: 10px;
            border-radius: 8px;
            display: none;
        }

        .status.error {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            display: block;
        }

        .status.success {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
            display: block;
        }

        .logo svg {
            fill: var(--primary);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
            </svg>
        </div>
        <h1 id="mainTitle"><?php echo ($_GET['mode'] === 'sign' ? 'Proof of Ownership' : 'Surgical Pairing'); ?></h1>
        <p id="mainDesc"><?php echo ($_GET['mode'] === 'sign' ? 'Sign this challenge to cryptographically prove your identity to the extension.' : 'Your extension has generated a unique challenge. Sign to prove ownership of your private key.'); ?></p>
        
        <button id="connectBtn" class="btn" disabled>Waiting for Extension...</button>
        
        <div id="status" class="status"></div>
    </div>

    <!-- BS58 Decoder for return values -->
    <script>
        const connectBtn = document.getElementById('connectBtn');
        const statusDiv = document.getElementById('status');

        const showStatus = (message, type) => {
            statusDiv.textContent = message;
            statusDiv.className = `status ${type}`;
            statusDiv.style.display = 'block';
        };

        const urlParams = new URLSearchParams(window.location.search);
        const extId = urlParams.get('extId');
        let nonce = null;

        if (!extId) {
            showStatus('Extension ID missing. Please restart from Solsigner.', 'error');
        }

        const getProvider = () => {
            if ('phantom' in window) {
                const provider = window.phantom?.solana;
                if (provider?.isPhantom) return provider;
            }
            return window.solana;
        };

        // Base58 Encode for signature passing (simpler than full library)
        const BASE58_ALPHABET = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
        function encodeBase58(buffer) {
            let digits = [0];
            for (let i = 0; i < buffer.length; i++) {
                for (let j = 0; j < digits.length; j++) digits[j] <<= 8;
                digits[0] += buffer[i];
                let carry = 0;
                for (let j = 0; j < digits.length; j++) {
                    digits[j] += carry;
                    carry = (digits[j] / 58) | 0;
                    digits[j] %= 58;
                }
                while (carry) {
                    digits.push(carry % 58);
                    carry = (carry / 58) | 0;
                }
            }
            for (let i = 0; i < buffer.length && buffer[i] === 0; i++) digits.push(0);
            return digits.reverse().map(digit => BASE58_ALPHABET[digit]).join('');
        }

        async function init() {
            // 1. Get Nonce from Extension
            try {
                showStatus('Connecting to extension...', 'success');
                chrome.runtime.sendMessage(extId, { type: 'GET_NONCE' }, (response) => {
                    if (chrome.runtime.lastError) {
                        console.error('Runtime Error:', chrome.runtime.lastError);
                        showStatus('Communication error: ' + chrome.runtime.lastError.message, 'error');
                        return;
                    }
                    if (!response?.nonce) {
                        showStatus('Extension returned empty response. Check background script.', 'error');
                        return;
                    }
                    nonce = response.nonce;
                    connectBtn.innerHTML = 'Connect & Sign Challenge';
                    connectBtn.disabled = false;
                    showStatus('Session handshake successful.', 'success');
                });
            } catch (e) {
                console.error('Catch Error:', e);
                showStatus('Handshake failed: ' + e.message, 'error');
            }

            const provider = getProvider() || await new Promise(resolve => {
                window.addEventListener('phantom#initialized', () => resolve(getProvider()));
                setTimeout(() => resolve(getProvider()), 1000);
            });

            if (!provider?.isPhantom) {
                showStatus('Phantom wallet required.', 'error');
                connectBtn.disabled = true;
                return;
            }

            connectBtn.addEventListener('click', async () => {
                try {
                    connectBtn.disabled = true;
                    showStatus('Connecting to Phantom...', 'success');

                    const resp = await provider.connect();
                    const publicKey = resp.publicKey.toString();

                    showStatus('Please sign the surgical challenge...', 'success');
                    // This message must exactly match the one used for verification in the extension
                    const message = `Pair Solsigner securely\nNonce: ${nonce}\nWallet: ${publicKey}`;
                    const encodedMessage = new TextEncoder().encode(message);
                    
                    const signedResult = await provider.signMessage(encodedMessage, "utf8");
                    const signatureBase58 = encodeBase58(signedResult.signature);

                    showStatus('Verifying signature cryptographically...', 'success');

                    chrome.runtime.sendMessage(extId, { 
                        type: 'VERIFY_AND_SAVE', 
                        address: publicKey,
                        signature: signatureBase58,
                        message: message,
                        nonce: nonce
                    }, (response) => {
                        if (response && response.success) {
                            showStatus('100% Verified. Proof of Ownership confirmed.', 'success');
                            connectBtn.innerHTML = '✓ Ownership Verified';
                        } else {
                            showStatus(response?.error || 'Verification failed.', 'error');
                            connectBtn.disabled = false;
                        }
                    });

                } catch (err) {
                    console.error(err);
                    showStatus(err.message || 'An error occurred.', 'error');
                    connectBtn.disabled = false;
                }
            });
        }

        if (extId) init();
    </script>
</body>
</html>
