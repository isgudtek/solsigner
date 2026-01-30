document.addEventListener('DOMContentLoaded', () => {
    const connectBtn = document.getElementById('connectBtn');
    const statusDiv = document.getElementById('status');

    const showStatus = (message, type) => {
        statusDiv.textContent = message;
        statusDiv.className = `status ${type}`;
        statusDiv.style.display = 'block';
    };

    const getProvider = () => {
        if ('phantom' in window) {
            const provider = window.phantom?.solana;
            if (provider?.isPhantom) {
                return provider;
            }
        }
        return window.solana;
    };

    const init = async () => {
        let provider = getProvider();

        if (!provider) {
            showStatus('Waiting for Phantom wallet...', 'success');
            // Wait for Phantom to inject itself
            await new Promise((resolve) => {
                const handler = () => {
                    provider = getProvider();
                    if (provider) {
                        window.removeEventListener('phantom#initialized', handler);
                        resolve();
                    }
                };
                window.addEventListener('phantom#initialized', handler);

                // Fallback timeout
                setTimeout(() => {
                    provider = getProvider();
                    resolve();
                }, 1500);
            });
        }

        if (!provider || !provider.isPhantom) {
            showStatus('Phantom wallet not found. Please install the Phantom extension.', 'error');
            connectBtn.disabled = true;
            return;
        }

        showStatus('Phantom detected. Ready to pair.', 'success');
        connectBtn.disabled = false;

        connectBtn.addEventListener('click', async () => {
            try {
                connectBtn.disabled = true;
                showStatus('Connecting to Phantom...', 'success');

                // 1. Connect
                const resp = await provider.connect();
                const publicKey = resp.publicKey.toString();

                // 2. signMessage
                showStatus('Please sign the verification message in Phantom...', 'success');

                const message = `Pair Solsigner with this wallet: ${publicKey}\n\nThis signature confirms you own the private key for this address.`;
                const encodedMessage = new TextEncoder().encode(message);

                const signedMessage = await provider.signMessage(encodedMessage, "utf8");

                // 3. Save to storage via background
                chrome.runtime.sendMessage({
                    type: 'SAVE_PAIRING',
                    address: publicKey
                }, (response) => {
                    if (response && response.success) {
                        showStatus('Success! Wallet paired. Closing this window...', 'success');
                        setTimeout(() => {
                            window.close();
                        }, 2000);
                    } else {
                        showStatus('Failed to save pairing. Please try again.', 'error');
                        connectBtn.disabled = false;
                    }
                });

            } catch (err) {
                console.error(err);
                showStatus(err.message || 'An error occurred during pairing.', 'error');
                connectBtn.disabled = false;
            }
        });
    };

    init();
});
