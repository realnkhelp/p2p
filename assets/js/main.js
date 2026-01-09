/*
File: assets/js/main.js
Purpose: Common UI Logic (Tabs, Copy, Modal)
*/

// --- 1. Toggle Tabs (Buy/Sell) ---
function switchTab(type) {
    const buySec = document.getElementById('buySection');
    const sellSec = document.getElementById('sellSection');
    const btnBuy = document.getElementById('btnBuy');
    const btnSell = document.getElementById('btnSell');

    if (buySec && sellSec) {
        if(type === 'buy') {
            buySec.style.display = 'block';
            sellSec.style.display = 'none';
            if(btnBuy) btnBuy.style.background = '#28a745';
            if(btnSell) btnSell.style.background = '#333';
        } else {
            buySec.style.display = 'none';
            sellSec.style.display = 'block';
            if(btnBuy) btnBuy.style.background = '#333';
            if(btnSell) btnSell.style.background = '#ff4d4d';
        }
    }
}

// --- 2. Copy to Clipboard ---
function copyText(elementId) {
    const text = document.getElementById(elementId).innerText;
    
    // Modern Browser / Telegram Web App Way
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(() => {
            showMsg('Copied: ' + text);
        }).catch(err => {
            console.error('Copy failed', err);
            fallbackCopy(text);
        });
    } else {
        fallbackCopy(text);
    }
}

function fallbackCopy(text) {
    const textArea = document.createElement("textarea");
    textArea.value = text;
    document.body.appendChild(textArea);
    textArea.select();
    document.execCommand("copy");
    document.body.removeChild(textArea);
    showMsg('Copied: ' + text);
}

// --- 3. Show Message (Toast) ---
function showMsg(msg) {
    if(window.Telegram && window.Telegram.WebApp && window.Telegram.WebApp.showPopup) {
        window.Telegram.WebApp.showPopup({ message: msg });
    } else {
        alert(msg);
    }
}

// --- 4. Modal Logic (Settings etc) ---
function openModal(id) {
    const el = document.getElementById(id);
    if(el) el.style.display = 'flex';
}

function closeModal(id) {
    const el = document.getElementById(id);
    if(el) el.style.display = 'none';
}

// --- 5. Disable Right Click (Security) ---
document.addEventListener('contextmenu', event => event.preventDefault());
