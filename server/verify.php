<?php
$extId = $_GET['extId'] ?? ''; 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Digital Agreement | Solsigner</title>
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
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }
        .container {
            background: var(--card);
            padding: 40px;
            border-radius: 24px;
            border: 1px solid #334155;
            max-width: 600px;
            width: 100%;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }
        .agreement-box {
            background: rgba(0,0,0,0.2);
            border: 1px solid #334155;
            padding: 30px;
            border-radius: 12px;
            margin: 24px 0;
            text-align: left;
            max-height: 300px;
            overflow-y: auto;
            font-size: 13px;
            color: #94a3b8;
            line-height: 1.6;
        }
        .agreement-box h3 { color: var(--text); margin-top: 0; }
        .shield-icon {
            width: 48px;
            height: 48px;
            background: rgba(16, 185, 129, 0.1);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
            color: var(--primary);
        }
        h1 { font-size: 28px; margin-bottom: 8px; font-weight: 800; }
        .btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 16px 32px;
            border-radius: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 10px 15px -3px rgba(16, 185, 129, 0.4); }
        .hidden { display: none; }
        .status { margin-top: 15px; font-size: 12px; color: #64748b; text-align: center; }
        #successView { text-align: center; padding: 40px 0; }
        .signature-badge {
            display: inline-block;
            padding: 4px 12px;
            background: rgba(16, 185, 129, 0.1);
            color: var(--primary);
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div id="contractView">
            <div class="shield-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
            </div>
            <h1>Service Agreement</h1>
            <p style="color: #64748b; margin-top: 0;">Protocol Version 1.0.4-SECURE</p>

            <div class="agreement-box">
                <h3>1. Digital Identity Proof</h3>
                <p>By signing this agreement, you cryptographically prove that you are the sole owner of the connected Solana wallet. This proof is valid for the current browser session only.</p>
                
                <h3>2. Zero-Knowledge Intent</h3>
                <p>This signature does not grant Solsigner or this website any access to your funds or private keys. It is a mathematical proof of possession, used solely for authentication purpose within this gated context.</p>
                
                <h3>3. Session Binding</h3>
                <p>Your "Verified" status is held in non-persistent memory and will be automatically purged upon browser termination or session expiration (300 seconds).</p>

                <p style="margin-top: 20px; font-style: italic;">Scroll to bottom to authorize...</p>
            </div>

            <button id="verifyBtn" class="btn">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                Sign Digital Identity Contract
            </button>
            <div id="statusMsg" class="status">Waiting for Solsigner Extension...</div>
        </div>

        <div id="successView" class="hidden">
            <div class="signature-badge">✓ CRYPTOGRAPHICALLY SIGNED</div>
            <h1 style="color: var(--primary);">Agreement Executed</h1>
            <p>Your identity has been verified by the blockchain network. You now have access to the decentralized environment.</p>
            
            <div style="background: rgba(255,255,255,0.03); padding: 20px; border-radius: 12px; border: 1px solid #334155; margin-top: 30px;">
                <p style="color: var(--text); font-weight: 600; margin-bottom: 0; font-family: monospace;">ACCESS_KEY: EMERALD-THETA-99</p>
            </div>
            
            <button onclick="location.reload()" class="btn" style="margin-top: 30px; background: #334155;">Revoke Signature & Logout</button>
        </div>
    </div>

    <script>
        // Automatic extraction of Extension ID if not passed via PHP
        // Fallback to the user's provided ID for seamless testing
        let extId = "<?php echo $extId; ?>" || "abdnnikclmnffenfimeneponffdcjbpj";
        
        if (!extId) {
            console.log("No extId in URL, checking environment...");
        }

        const verifyBtn = document.getElementById('verifyBtn');
        const statusMsg = document.getElementById('statusMsg');

        verifyBtn.onclick = async () => {
            if (!extId) {
                statusMsg.style.color = "#f87171";
                statusMsg.innerText = "Error: Please pass ?extId=... in the URL (found in extension settings)";
                return;
            }

            try {
                statusMsg.innerText = "Requesting session challenge...";
                
                chrome.runtime.sendMessage(extId, { type: 'GET_NONCE' }, async (response) => {
                    if (chrome.runtime.lastError || !response?.nonce) {
                        statusMsg.innerText = "Extension connection failed. Is Solsigner installed?";
                        return;
                    }

                    const nonce = response.nonce;
                    const provider = window.solana;

                    if (!provider) {
                        statusMsg.innerText = "Phantom wallet not found. Please install Phantom.";
                        return;
                    }

                    const resp = await provider.connect();
                    const publicKey = resp.publicKey.toString();

                    statusMsg.innerText = "Waiting for contract signature...";
                    const message = `Solsigner Digital Agreement\nChallenge: ${nonce}\nIdentity: ${publicKey}`;
                    const encodedMessage = new TextEncoder().encode(message);
                    const signedResult = await provider.signMessage(encodedMessage, "utf8");
                    const signature = btoa(String.fromCharCode(...signedResult.signature)); 

                    statusMsg.innerText = "Validating cryptographic proof...";
                    chrome.runtime.sendMessage(extId, { 
                        type: 'VERIFY_AND_SAVE',
                        address: publicKey,
                        signature: signature,
                        message: message,
                        nonce: nonce
                    }, (res) => {
                        if (res && res.success) {
                            document.getElementById('contractView').classList.add('hidden');
                            document.getElementById('successView').classList.remove('hidden');
                        } else {
                            statusMsg.innerText = "Verification failed: " + (res?.error || "Signature Mismatch");
                        }
                    });
                });
            } catch (err) {
                statusMsg.innerText = "Process aborted: " + err.message;
            }
        };
    </script>
</body>
</html>
