document.addEventListener('DOMContentLoaded', () => {
    // Navigation
    const navItems = document.querySelectorAll('.nav-item');
    const sections = document.querySelectorAll('.content-section');

    navItems.forEach(item => {
        item.addEventListener('click', () => {
            const targetSetion = item.getAttribute('data-section');

            navItems.forEach(nav => nav.classList.remove('active'));
            item.classList.add('active');

            sections.forEach(section => {
                if (section.id === `section-${targetSetion}`) {
                    section.classList.remove('hidden');
                } else {
                    section.classList.add('hidden');
                }
            });
        });
    });

    // Wallet Logic
    const pairingStatus = document.getElementById('pairingStatus');
    const walletAddress = document.getElementById('walletAddress');
    const pairedControls = document.getElementById('pairedControls');
    const unpairedControls = document.getElementById('unpairedControls');
    const pairWalletBtn = document.getElementById('pairWalletBtn');
    const unpairWalletBtn = document.getElementById('unpairWalletBtn');

    const updateUI = () => {
        chrome.runtime.sendMessage({ type: 'GET_PAIRING_STATUS' }, (response) => {
            if (response && response.pairedAddress) {
                pairingStatus.textContent = 'Paired';
                pairingStatus.className = 'status-pill paired';
                walletAddress.textContent = response.pairedAddress;
                pairedControls.classList.remove('hidden');
                unpairedControls.classList.add('hidden');
            } else {
                pairingStatus.textContent = 'Not Paired';
                pairingStatus.className = 'status-pill unpaired';
                pairedControls.classList.add('hidden');
                unpairedControls.classList.remove('hidden');
            }
        });
    };

    updateUI();

    pairWalletBtn.addEventListener('click', () => {
        const bridgeUrl = `https://gudtek.lol/solsigner/pair.php?extId=${chrome.runtime.id}`;
        chrome.tabs.create({ url: bridgeUrl });
    });

    unpairWalletBtn.addEventListener('click', () => {
        if (confirm('Are you sure you want to disconnect this wallet?')) {
            chrome.runtime.sendMessage({ type: 'UNPAIR_WALLET' }, (response) => {
                if (response && response.success) {
                    updateUI();
                }
            });
        }
    });

    // Listen for storage changes to update UI in real-time
    chrome.storage.onChanged.addListener((changes, area) => {
        if (area === 'local' && changes.pairedAddress) {
            updateUI();
        }
    });
});
