<?php
$extId = $_GET['extId'] ?? ''; // Should be passed from extension or configured
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gated Content | Solsigner</title>
    <style>
        :root {
            --primary: #10b981;
            --bg: #0f172a;
            --card: #1e293b;
            --text: #f8fafc;
        }
        body {
            font-family: 'Inter', system-ui, sans-serif;
            background: var(--bg);
            color: var(--text);
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
        }
        .container {
            background: var(--card);
            padding: 40px;
            border-radius: 24px;
            border: 1px solid #334155;
            width: 400px;
            text-align: center;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }
        .shield-icon {
            width: 64px;
            height: 64px;
            background: rgba(16, 185, 129, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            color: var(--primary);
        }
        h1 { font-size: 24px; margin-bottom: 12px; font-weight: 800; }
        p { color: #94a3b8; font-size: 14px; line-height: 1.6; margin-bottom: 30px; }
        .btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 14px 28px;
            border-radius: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            width: 100%;
        }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 10px 15px -3px rgba(16, 185, 129, 0.4); }
        .hidden { display: none; }
        #secretContent {
            background: rgba(16, 185, 129, 0.05);
            border: 1px dashed var(--primary);
            padding: 20px;
            border-radius: 12px;
            margin-top: 20px;
        }
        .status { margin-top: 15px; font-size: 12px; color: #64748b; }
    </style>
</head>
<body>
    <div class="container">
        <div class="shield-icon">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
        </div>
        
        <div id="lockView">
            <h1>Protected Vault</h1>
            <p>This content is cryptographically gated. You must sign a "Proof of Ownership" challenge using your paired Solsigner extension to enter.</p>
            <button id="verifyBtn" class="btn">Verify & Unlock</button>
            <div id="statusMsg" class="status">Waiting for extension...</div>
        </div>

        <div id="unlockView" class="hidden">
            <h1 style="color: var(--primary);">Access Granted</h1>
            <div id="secretContent">
                <p style="color: var(--text); font-weight: 600; margin-bottom: 0;">🗝️ The Secret Code is: EMERALD-V3-SECURE</p>
                <p style="font-size: 10px; margin-top: 10px;">Verification successfully validated by Solsigner Service Worker.</p>
            </div>
            <button onclick="location.reload()" class="btn" style="margin-top: 20px; background: #334155;">Reset Session</button>
        </div>
    </div>

    <script>
        const extId = "<?php echo $extId; ?>";
        const verifyBtn = document.getElementById('verifyBtn');
        const statusMsg = document.getElementById('statusMsg');

        verifyBtn.onclick = async () => {
            if (!extId) {
                alert("Extension ID missing. Please pass ?extId=YOUR_ID in the URL.");
                return;
            }

            try {
                statusMsg.innerText = "Requesting challenge...";
                
                // 1. Get Nonce from Extension
                chrome.runtime.sendMessage(extId, { type: 'GET_NONCE' }, async (response) => {
                    if (chrome.runtime.lastError || !response?.nonce) {
                        statusMsg.innerText = "Error: Could not reach extension.";
                        return;
                    }

                    const nonce = response.nonce;
                    const provider = window.solana;

                    if (!provider) {
                        statusMsg.innerText = "Phantom wallet not found.";
                        return;
                    }

                    // 2. Connect
                    const resp = await provider.connect();
                    const publicKey = resp.publicKey.toString();

                    // 3. Sign
                    statusMsg.innerText = "Waiting for signature...";
                    const message = `Access Protected Content\nNonce: ${nonce}\nWallet: ${publicKey}`;
                    const encodedMessage = new TextEncoder().encode(message);
                    const signedResult = await provider.signMessage(encodedMessage, "utf8");
                    
                    // Convert signature to Base58 (Tiny bit of logic repeated for standalone)
                    const signature = btoa(String.fromCharCode(...signedResult.signature)); 

                    // 4. Verify in Extension
                    statusMsg.innerText = "Validating proof...";
                    chrome.runtime.sendMessage(extId, { 
                        type: 'VERIFY_AND_SAVE',
                        address: publicKey,
                        signature: signature, // Note: background handles the decoding
                        message: message,
                        nonce: nonce
                    }, (res) => {
                        if (res && res.success) {
                            document.getElementById('lockView').classList.add('hidden');
                            document.getElementById('unlockView').classList.remove('hidden');
                        } else {
                            statusMsg.innerText = "Verification failed: " + (res?.error || "Unknown");
                        }
                    });
                });
            } catch (err) {
                statusMsg.innerText = "Error: " + err.message;
            }
        };
    </script>
</body>
</html>
