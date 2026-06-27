// Helper to copy text to clipboard with fallback (HTTP compatible)
function copyTextToClipboard(text, successCb, errorCb) {
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(successCb, function() {
            fallbackCopyTextToClipboard(text, successCb, errorCb);
        });
    } else {
        fallbackCopyTextToClipboard(text, successCb, errorCb);
    }
}

function fallbackCopyTextToClipboard(text, successCb, errorCb) {
    var textArea = document.createElement("textarea");
    textArea.value = text;
    textArea.style.top = "0";
    textArea.style.left = "0";
    textArea.style.position = "fixed";
    textArea.style.opacity = "0";
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();
    try {
        var successful = document.execCommand('copy');
        if (successful) {
            successCb();
        } else {
            errorCb();
        }
    } catch (err) {
        errorCb();
    }
    document.body.removeChild(textArea);
}

// Copy voucher code to clipboard (global fallback)
function copyVoucherCode() {
    var codeText = document.getElementById("voucherCode").innerText.trim();
    copyTextToClipboard(codeText, function() {
        alert("Kode voucher berhasil disalin!");
    }, function() {
        alert("Kode voucher: " + codeText + "\n(Silakan salin secara manual)");
    });
}

// Copy pending order ID to clipboard
function copyPendingOrderId() {
    var el = document.getElementById("pendingOrderId");
    if (!el) return;
    var orderId = el.innerText.replace(/\s+/g, '').trim();
    copyTextToClipboard(orderId, function() {
        var toast = document.getElementById("copyToastPending");
        if (toast) {
            toast.classList.add("show");
            setTimeout(function() {
                toast.classList.remove("show");
            }, 3000);
        } else {
            alert("Order ID berhasil disalin: " + orderId);
        }
    }, function() {
        alert("Order ID: " + orderId + "\n(Silakan salin secara manual)");
    });
}

// Clear voucher history
function clearVoucherHistory() {
    if (confirm("Apakah Anda yakin ingin menghapus seluruh riwayat pembelian voucher di HP ini?")) {
        localStorage.removeItem('mikhtrans_purchase_history');
        var container = document.getElementById("voucherHistoryContainer");
        if (container) {
            container.style.display = 'none';
        }
    }
}

// Copy voucher from history list with button visual feedback
function copyHistoryCode(code, btn) {
    copyTextToClipboard(code, function() {
        var originalHTML = btn.innerHTML;
        btn.innerHTML = '<i class="fa fa-check" style="color: #219653;"></i> Tersalin';
        btn.style.borderColor = '#219653';
        btn.style.color = '#219653';
        setTimeout(function() {
            btn.innerHTML = originalHTML;
            btn.style.borderColor = '';
            btn.style.color = '';
        }, 2000);
    }, function() {
        alert("Gagal menyalin. Kode voucher: " + code);
    });
}

// Prevent double clicks and show loading state on checkout
function handleCheckoutSubmit(form) {
    var btn = form.querySelector('.btn-buy-voucher');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Memproses...';
        btn.style.opacity = '0.7';
        btn.style.cursor = 'not-allowed';
    }
    return true;
}

// Tab switching logic for compliance policies
function switchTab(tabId) {
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
    
    event.target.classList.add('active');
    document.getElementById(tabId).classList.add('active');
}

// Global Polling handler for transaction verification
var checkInterval = null;
function startTransactionPolling(orderId) {
    if (checkInterval) {
        clearInterval(checkInterval);
    }
    checkInterval = setInterval(function() {
        fetch("index.php?check_order=" + orderId)
            .then(response => response.json())
            .then(data => {
                if (data.status === "success") {
                    clearInterval(checkInterval);
                    window.location.href = "index.php?show_voucher=1&order_id=" + orderId + "&session=" + encodeURIComponent(FrontpageConfig.session) + "#paket";
                } else if (data.status === "paid_pending_generate") {
                    clearInterval(checkInterval);
                    var paymentOverlay = document.getElementById("loadingPayment");
                    if (paymentOverlay) {
                        paymentOverlay.innerHTML = `
                            <div class="card" style="width: 100%; max-width: 440px; margin: 0 auto; text-align: center; background: white; border: 1px solid var(--border-color); padding: 24px; border-radius: 16px; box-shadow: var(--shadow-primary); color: var(--text-main, #3E3E3E);">
                                <i class="fa fa-exclamation-triangle" style="color: #F59E0B; font-size: 48px; margin-bottom: 16px; display: block;"></i>
                                <h2 style="font-family: 'Plus Jakarta Sans', sans-serif; color: #1F2937; margin-bottom: 8px; font-size: 20px;">Pembayaran Berhasil!</h2>
                                <p style="font-size: 13px; color: #4B5563; line-height: 1.6; margin-bottom: 16px;">
                                    Terima kasih, pembayaran Anda telah kami terima. Namun saat ini <strong>koneksi ke router sedang mengalami gangguan</strong>.
                                </p>
                                <p style="font-size: 13px; color: #4B5563; line-height: 1.6; margin-bottom: 24px;">
                                    Admin sedang memproses voucher Anda secara manual. Silakan hubungi admin dan tunjukkan <strong>Order ID</strong> berikut:
                                </p>
                                <div style="background: #F3F4F6; padding: 12px; border-radius: 8px; font-family: monospace; font-size: 15px; font-weight: bold; color: #111827; margin-bottom: 20px; display: flex; align-items: center; justify-content: center; gap: 8px; border: 1px solid var(--border-color);">
                                    <span id="pendingOrderId">${orderId}</span>
                                    <i class="fa-regular fa-copy" style="cursor: pointer; color: var(--primary);" onclick="var r = document.createRange(); r.selectNode(document.getElementById('pendingOrderId')); window.getSelection().removeAllRanges(); window.getSelection().addRange(r); document.execCommand('copy'); alert('Order ID disalin!');" title="Salin Order ID"></i>
                                </div>
                                <a href="index.php?session=${encodeURIComponent(FrontpageConfig.session)}" style="display: block; width: 100%; padding: 12px; background: var(--primary); color: white; border-radius: 8px; text-decoration: none; font-weight: bold; text-align: center;">Kembali ke Beranda</a>
                            </div>
                        `;
                        localStorage.removeItem('active_order_id');
                        localStorage.removeItem('active_snap_token');
                    } else {
                        window.location.href = "index.php?show_voucher=1&order_id=" + orderId + "&session=" + encodeURIComponent(FrontpageConfig.session) + "#paket";
                    }
                }
            })
            .catch(err => console.error("Error polling order status: ", err));
    }, 3000);
}

// WebSocket client connection setup with automated fallback
function initWebSocketConnection(orderId) {
    if (!FrontpageConfig.ws.enabled || typeof Pusher === 'undefined') {
        // Fallback directly to polling if WS is not configured or pusher script not loaded
        startTransactionPolling(orderId);
        return;
    }

    var pusherConfig = {};
    if (FrontpageConfig.ws.host) {
        // Soketi (Self-Hosted) config overrides
        var portVal = FrontpageConfig.ws.port ? parseInt(FrontpageConfig.ws.port, 10) : 6001;
        var isSecure = FrontpageConfig.ws.scheme === 'https' || FrontpageConfig.ws.scheme === 'wss';
        pusherConfig = {
            wsHost: FrontpageConfig.ws.host,
            wsPort: portVal,
            wssPort: portVal,
            forceTLS: isSecure,
            disableStats: true,
            enabledTransports: ['ws', 'wss']
        };
    } else {
        // Official Pusher Cloud config
        pusherConfig = {
            cluster: FrontpageConfig.ws.cluster
        };
    }

    try {
        var pusher = new Pusher(FrontpageConfig.ws.key, pusherConfig);
        var channel = pusher.subscribe('order-' + orderId);

        // Fallback timeout of 5 seconds to guarantee customer connection if WS connection lags
        var fallbackTimeout = setTimeout(function() {
            console.warn("WebSocket connection lag. Triggering fallback HTTP Polling...");
            startTransactionPolling(orderId);
        }, 5000);

        channel.bind('paid', function(data) {
            clearTimeout(fallbackTimeout);
            window.location.href = "index.php?show_voucher=1&order_id=" + orderId + "&session=" + encodeURIComponent(FrontpageConfig.session) + "#paket";
        });
    } catch (e) {
        console.error("Failed to connect to WebSocket. Falling back to HTTP Polling...", e);
        startTransactionPolling(orderId);
    }
}

// DOM content load handlers
document.addEventListener("DOMContentLoaded", function() {
    // 1. Bottom Navigation Active highlight on Scroll
    const sections = document.querySelectorAll("section[id], div[id], div[id='home']");
    const navItems = document.querySelectorAll(".bottom-nav .nav-item");

    window.addEventListener("scroll", () => {
        let current = "";
        sections.forEach(section => {
            const sectionTop = section.offsetTop;
            const sectionHeight = section.clientHeight;
            if (pageYOffset >= (sectionTop - 160)) {
                current = section.getAttribute("id");
            }
        });

        navItems.forEach(item => {
            item.classList.remove("active");
            if (item.getAttribute("href").slice(1) === current) {
                item.classList.add("active");
            }
        });
    });

    // 2. Resume flow Midtrans telah dihapus (QRIS dinamis tidak perlu resume payment)

    // 3. Render Purchase History from localStorage if available
    var historyListEl = document.getElementById("voucherHistoryList");
    var historyContainerEl = document.getElementById("voucherHistoryContainer");
    if (historyListEl && historyContainerEl) {
        var purchaseHistory = [];
        try {
            purchaseHistory = JSON.parse(localStorage.getItem('mikhtrans_purchase_history') || '[]');
        } catch (e) {
            console.error('Error reading purchase history:', e);
        }
        
        var isSuccessScreen = document.querySelector('.receipt-card');
        if (purchaseHistory.length > 0 && !isSuccessScreen) {
            var html = '';
            purchaseHistory.forEach(function(item) {
                var loginButton = '';
                if (item.login_url) {
                    loginButton = `<a href="${item.login_url}" class="history-btn-connect" style="text-decoration: none;"><i class="fa fa-wifi"></i> Hubungkan</a>`;
                }
                html += `
                <div class="history-item">
                    <div class="history-item-info">
                        <div class="history-item-title">
                            <span class="history-item-profile">Paket ${item.profile}</span>
                            <span class="history-badge-validity">${item.validity}</span>
                        </div>
                        <div class="history-item-meta">
                            ID: <span class="history-monospace">${item.order_id}</span> · ${item.date}
                        </div>
                    </div>
                    <div class="history-item-actions">
                        <div class="history-item-code">${item.username}</div>
                        <button type="button" class="history-btn-copy" onclick="copyHistoryCode('${item.username}', this)">
                            <i class="fa-regular fa-copy"></i> Salin
                        </button>
                        ${loginButton}
                    </div>
                </div>
                `;
            });
            historyListEl.innerHTML = html;
            historyContainerEl.style.display = 'block';
        }
    }
});
