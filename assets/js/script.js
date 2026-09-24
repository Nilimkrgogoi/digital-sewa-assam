// assets/js/script.js - Digital Sewa Assam Client-side Scripts

// ================= MOBILE NAVIGATION =================
function toggleMenu() {
    const navbar = document.getElementById("navbar");
    if (navbar) {
        navbar.classList.toggle("open");
    }
}

// ================= LIVE SERVICE FILTER ON HOMEPAGE =================
function filterServices() {
    const input = document.getElementById("serviceSearch");
    if (!input) return;
    const filter = input.value.toLowerCase();
    const cards = document.querySelectorAll(".service-card");

    cards.forEach(card => {
        const title = card.querySelector("h3") ? card.querySelector("h3").innerText.toLowerCase() : "";
        const desc = card.querySelector("p") ? card.querySelector("p").innerText.toLowerCase() : "";
        if (title.includes(filter) || desc.includes(filter)) {
            card.style.display = "";
        } else {
            card.style.display = "none";
        }
    });
}

// ================= SERVICE SELECTOR FEE UPDATE =================
function updateServiceFee() {
    const select = document.getElementById("serviceSelect");
    const feeDisplay = document.getElementById("serviceFeeDisplay");
    if (!select || !feeDisplay) return;

    const selectedOption = select.options[select.selectedIndex];
    const fee = selectedOption.getAttribute("data-fee");

    if (fee !== null && fee !== "") {
        const numFee = parseFloat(fee);
        feeDisplay.innerText = "₹" + numFee.toFixed(2);
    } else {
        feeDisplay.innerText = "₹0.00";
    }
}

// ================= FILE UPLOAD VALIDATION =================
function validateFileInput(input) {
    const allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png'];
    const maxSizeBytes = 5 * 1024 * 1024; // 5 MB

    if (input.files && input.files[0]) {
        const file = input.files[0];
        const ext = file.name.split('.').pop().toLowerCase();

        if (!allowedExtensions.includes(ext)) {
            alert("Invalid file type: " + file.name + "\nOnly PDF, JPG, JPEG, and PNG files are allowed.");
            input.value = "";
            return false;
        }

        if (file.size > maxSizeBytes) {
            alert("File is too large: " + (file.size / (1024 * 1024)).toFixed(2) + " MB\nMaximum allowed size is 5 MB.");
            input.value = "";
            return false;
        }
    }
    return true;
}

// Attach listener to all file inputs
document.addEventListener("DOMContentLoaded", function () {
    const fileInputs = document.querySelectorAll('input[type="file"]');
    fileInputs.forEach(input => {
        input.addEventListener("change", function () {
            validateFileInput(this);
        });
    });

    // Password Match Validation
    const regForm = document.getElementById("registerForm");
    if (regForm) {
        regForm.addEventListener("submit", function (e) {
            const pass = document.getElementById("password");
            const confirmPass = document.getElementById("confirmPassword");
            if (pass && confirmPass && pass.value !== confirmPass.value) {
                e.preventDefault();
                alert("Passwords do not match! Please check and try again.");
                confirmPass.focus();
            }
        });
    }
});

function openStatusModal(appId, appCode, currentStatus, currentRemarks, paymentStatus, issuedDoc, docStatus, docsData) {
    const modal = document.getElementById("statusModal");
    if (!modal) return;

    document.getElementById("modalAppId").value = appId;
    document.getElementById("modalAppCode").innerText = appCode;
    document.getElementById("modalStatus").value = currentStatus || "submitted";
    document.getElementById("modalRemarks").value = currentRemarks || "";
    if (document.getElementById("modalPaymentStatus")) {
        document.getElementById("modalPaymentStatus").value = paymentStatus || "pending";
    }

    if (document.getElementById("modalDocStatus")) {
        document.getElementById("modalDocStatus").value = docStatus || "pending";
    }

    // Populate uploaded documents checklist inside status modal
    const docCheckboxesDiv = document.getElementById("modalDocCheckboxes");
    const docWrapper = document.getElementById("modalDocListWrapper");
    if (docCheckboxesDiv) {
        docCheckboxesDiv.innerHTML = "";
        let parsedDocs = [];
        try {
            parsedDocs = (typeof docsData === 'string') ? JSON.parse(docsData) : (docsData || []);
        } catch(e) {
            parsedDocs = [];
        }

        if (parsedDocs.length > 0) {
            parsedDocs.forEach(function(d) {
                const isRej = (d.status === 'rejected');
                const label = document.createElement("label");
                label.style.display = "flex";
                label.style.alignItems = "center";
                label.style.gap = "8px";
                label.style.fontSize = "12px";
                label.style.cursor = "pointer";
                label.style.padding = "4px 0";

                const chk = document.createElement("input");
                chk.type = "checkbox";
                chk.name = "rejected_doc_ids[]";
                chk.value = d.id;
                chk.checked = isRej;

                const span = document.createElement("span");
                const statBadge = isRej ? '<span style="color:#b91c1c; font-weight:700;">[❌ Rejected]</span>' : (d.status === 'verified' ? '<span style="color:#15803d; font-weight:700;">[✓ Verified]</span>' : '<span style="color:#92400e;">[⏳ In Review]</span>');
                span.innerHTML = `<strong>${d.name}</strong> ${statBadge} <a href="../${d.file}" target="_blank" style="color:#0284c7; text-decoration:underline; margin-left:6px;">View File ↗</a>`;

                label.appendChild(chk);
                label.appendChild(span);
                docCheckboxesDiv.appendChild(label);
            });
            if (docWrapper) docWrapper.style.display = "block";
        } else {
            if (docWrapper) docWrapper.style.display = "none";
        }
    }

    toggleDocRejectionSection();

    const currentDocBox = document.getElementById("modalCurrentDocBox");
    const currentDocLink = document.getElementById("modalCurrentDocLink");
    if (currentDocBox && currentDocLink) {
        if (issuedDoc && issuedDoc !== 'null' && issuedDoc !== '') {
            currentDocLink.href = '../' + issuedDoc;
            currentDocBox.style.display = 'block';
        } else {
            currentDocBox.style.display = 'none';
        }
    }
    const fileInput = document.getElementById("modalIssuedDocument");
    if (fileInput) fileInput.value = "";

    modal.classList.add("open");
}

function toggleDocRejectionSection() {
    const docStatusSelect = document.getElementById("modalDocStatus");
    const sec = document.getElementById("docRejectionSection");
    if (!docStatusSelect || !sec) return;

    if (docStatusSelect.value === 'rejected') {
        sec.style.display = "block";
        const modalStatus = document.getElementById("modalStatus");
        if (modalStatus && modalStatus.value !== 'rejected') {
            modalStatus.value = 'rejected';
        }
        applyDocRejectionPreset();
    } else {
        sec.style.display = "none";
    }
}

function applyDocRejectionPreset() {
    const reasonSelect = document.getElementById("modalDocRejectReason");
    const remarks = document.getElementById("modalRemarks");
    if (!reasonSelect || !remarks) return;

    const reason = reasonSelect.value;
    const defaultMsg = `⚠️ Document Verification Failed: ${reason}. The uploaded document is false or invalid. Please re-upload your genuine, valid, and clear document to continue processing.`;

    if (!remarks.value || remarks.value.indexOf("Document Verification Failed") !== -1) {
        remarks.value = defaultMsg;
    }
}

function handleModalStatusChange() {
    const status = document.getElementById("modalStatus").value;
    const docStatusSelect = document.getElementById("modalDocStatus");
    if (status === 'rejected' && docStatusSelect && docStatusSelect.value !== 'rejected') {
        docStatusSelect.value = 'rejected';
        toggleDocRejectionSection();
    } else if ((status === 'approved' || status === 'completed') && docStatusSelect && docStatusSelect.value === 'rejected') {
        docStatusSelect.value = 'verified';
        toggleDocRejectionSection();
    }
}

function closeStatusModal() {
    const modal = document.getElementById("statusModal");
    if (modal) {
        modal.classList.remove("open");
    }
}

// Close modal when clicking outside
window.addEventListener("click", function (event) {
    const modal = document.getElementById("statusModal");
    if (event.target === modal) {
        closeStatusModal();
    }
});

// ================= PRINT RECEIPT =================
function printReceipt() {
    window.print();
}
