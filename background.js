// Solsigner Service Worker (V4 - Robust)
try {
    importScripts('lib/nacl.min.js', 'lib/bs58_tiny.js');
    console.log('Solsigner: Libraries loaded.');
} catch (e) {
    console.error('Solsigner: Script load error:', e);
}

// State management
const pendingPairings = new Map();

/**
 * Handle nonce generation for both internal and external requests
 */
function handleGetNonce(sendResponse) {
    try {
        const cryptoObj = typeof self !== 'undefined' && self.crypto ? self.crypto : crypto;
        const bytes = cryptoObj.getRandomValues(new Uint8Array(16));
        const binary = Array.from(bytes).map(b => String.fromCharCode(b)).join('');
        const nonce = btoa(binary);

        pendingPairings.set(nonce, { timestamp: Date.now() });
        setTimeout(() => pendingPairings.delete(nonce), 300000); // 5 min expiry

        sendResponse({ nonce });
    } catch (err) {
        console.error('Solsigner: Nonce generation failed', err);
        sendResponse({ error: 'Internal crypto failure' });
    }
}

// Internal Message Handler (Popup/Options)
chrome.runtime.onMessage.addListener((request, sender, sendResponse) => {
    if (request.type === 'GET_PAIRING_STATUS') {
        chrome.storage.local.get(['pairedAddress', 'lastVerified'], (result) => {
            sendResponse({
                pairedAddress: result.pairedAddress || null,
                lastVerified: result.lastVerified || null
            });
        });
        return true;
    }

    if (request.type === 'GET_NONCE') {
        handleGetNonce(sendResponse);
        return true;
    }

    if (request.type === 'UNPAIR_WALLET') {
        chrome.storage.local.remove(['pairedAddress', 'lastVerified'], () => {
            sendResponse({ success: true });
        });
        return true;
    }
});

// External Handshake (from pair.php)
chrome.runtime.onMessageExternal.addListener((request, sender, sendResponse) => {
    console.log('Solsigner: External message received', request.type);

    if (request.type === 'GET_NONCE') {
        handleGetNonce(sendResponse);
        return true;
    }

    if (request.type === 'VERIFY_AND_SAVE') {
        const { address, signature, message, nonce } = request;

        if (!pendingPairings.has(nonce)) {
            sendResponse({ success: false, error: 'Expired or invalid session. Please reload the pairing page.' });
            return;
        }
        pendingPairings.delete(nonce);

        try {
            const publicKeyBytes = self.decodeBase58(address);
            const signatureBytes = self.decodeBase58(signature);
            const messageBytes = new TextEncoder().encode(message);

            const isValid = self.nacl.sign.detached.verify(
                messageBytes,
                signatureBytes,
                publicKeyBytes
            );

            if (isValid) {
                const now = Date.now();
                // Create a truncated proof token for visual verification
                const proofToken = signature.substring(0, 8) + '...' + signature.substring(signature.length - 8);

                // Identity (Address) stays in local, 
                // but the Proof (lastVerified + token) goes into Session (Memory-only)
                chrome.storage.local.set({ pairedAddress: address }, () => {
                    chrome.storage.session.set({
                        lastVerified: now,
                        lastProof: proofToken
                    }, () => {
                        console.log('Solsigner: Verified and locked to memory-only session.');
                        sendResponse({ success: true, timestamp: now, proofToken });
                    });
                });
            } else {
                sendResponse({ success: false, error: 'Cryptographic signature mismatch.' });
            }
        } catch (err) {
            console.error('Solsigner: Verification failure', err);
            sendResponse({ success: false, error: 'Failed to verify signature.' });
        }
        return true;
    }
});
