document.addEventListener('DOMContentLoaded', () => {
    const notPairedView = document.getElementById('notPairedView');
    const pairedView = document.getElementById('pairedView');
    const addressDisplay = document.getElementById('addressDisplay');
    const pairBtn = document.getElementById('pairBtn');
    const unpairBtn = document.getElementById('unpairBtn');
    const settingsBtn = document.getElementById('settingsBtn');

    // Update UI based on pairing status
    const updateUI = () => {
        chrome.storage.local.get(['pairedAddress', 'lastVerified'], (result) => {
            if (result.pairedAddress) {
                notPairedView.classList.add('hidden');
                pairedView.classList.remove('hidden');
                addressDisplay.textContent = result.pairedAddress;

                if (result.lastVerified) {
                    const date = new Date(result.lastVerified);
                    const timeStr = date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                    document.getElementById('verifyStatus').textContent = `✓ Verified at ${timeStr}`;
                }
            } else {
                notPairedView.classList.remove('hidden');
                pairedView.classList.add('hidden');
            }
        });
    };

    updateUI();

    const openBridge = (mode = 'pair') => {
        const bridgeUrl = `https://gudtek.lol/solsigner/pair.php?extId=${chrome.runtime.id}&mode=${mode}`;
        chrome.tabs.create({ url: bridgeUrl });
    };

    pairBtn.addEventListener('click', () => openBridge('pair'));

    document.getElementById('verifyNowBtn')?.addEventListener('click', () => openBridge('sign'));

    unpairBtn.addEventListener('click', () => {
        if (confirm('Are you sure you want to unpair this wallet?')) {
            chrome.runtime.sendMessage({ type: 'UNPAIR_WALLET' }, (response) => {
                if (response && response.success) {
                    updateUI();
                }
            });
        }
    });

    settingsBtn.addEventListener('click', () => {
        chrome.runtime.openOptionsPage();
    });
});
