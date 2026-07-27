let currentMode = 'list';
let cardIndex = 0;

function setMode(mode, btn) {
    currentMode = mode;
    document.querySelectorAll('.mode-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    renderContent();
}

function renderContent() {
    const container = document.getElementById('contentDisplay');
    container.innerHTML = '';

    if (typeof questions === 'undefined' || !questions) {
        container.innerHTML = '<p class="text-danger">Error: Could not load data.</p>';
        return;
    }

    if (currentMode === 'list') {
        let html = '<div class="d-flex justify-content-between mb-3"><h5>Review List</h5><button class="btn btn-sm btn-outline-secondary" onclick="copyContent()"><i class="fas fa-copy"></i></button></div>';
        html += '<ul class="list-group list-group-flush">';
        questions.forEach((q, i) => {
            html += `<li class="list-group-item bg-transparent border-0 border-bottom py-3">
                <span class="fw-bold text-success me-2">${i+1}.</span> ${q.question}
            </li>`;
        });
        html += '</ul>';
        container.innerHTML = html;

    } else if (currentMode === 'card') {
        const q = questions[cardIndex];
        container.innerHTML = `
            <div class="d-flex justify-content-between align-items-center mb-4">
                <span class="badge bg-success rounded-pill">Card ${cardIndex + 1} of ${questions.length}</span>
                <button class="btn btn-sm btn-outline-secondary" onclick="copyContent()"><i class="fas fa-copy"></i></button>
            </div>
            <div class="flashcard mx-auto" onclick="this.classList.toggle('flipped')">
                <div class="flashcard-inner">
                    <div class="flashcard-front bg-white shadow-sm border">
                        <p class="mb-0">${q.question}</p>
                    </div>
                    <div class="flashcard-back shadow-sm">
                        <p class="mb-0">${q.answer}</p>
                    </div>
                </div>
            </div>
            <div class="d-flex justify-content-center gap-3 mt-4">
                <button class="btn btn-outline-dark px-4 rounded-pill" onclick="prevCard()" ${cardIndex === 0 ? 'disabled' : ''}>Prev</button>
                <button class="btn btn-brand px-4 rounded-pill" onclick="nextCard()" ${cardIndex === questions.length - 1 ? 'disabled' : ''}>Next</button>
            </div>
            <p class="text-center text-muted small mt-3">Click card to reveal answer</p>
        `;

    } else if (currentMode === 'notes') {
        let html = '<div class="d-flex justify-content-between mb-3"><h5>Answer Key</h5><button class="btn btn-sm btn-outline-secondary" onclick="copyContent()"><i class="fas fa-copy"></i></button></div>';
        questions.forEach((q, i) => {
            html += `
                <div class="mb-4 p-3 rounded-4 bg-white shadow-sm border-start border-success border-4">
                    <p class="fw-bold mb-1">${i+1}. ${q.question}</p>
                    <p class="mb-0 text-success"><i class="fas fa-check me-2"></i> <span class="blur-reveal">${q.answer}</span></p>
                </div>`;
        });
        html += '<p class="text-center text-muted small">Hover/Click answers to reveal</p>';
        container.innerHTML = html;
    }
}

function nextCard() { if (cardIndex < questions.length - 1) { cardIndex++; renderContent(); } }
function prevCard() { if (cardIndex > 0) { cardIndex--; renderContent(); } }

function copyContent() {
    let text = "";
    if (currentMode === 'list') {
        text = questions.map((q, i) => `${i+1}. ${q.question}`).join('\n');
    } else if (currentMode === 'card') {
        text = `Q: ${questions[cardIndex].question}\nA: ${questions[cardIndex].answer}`;
    } else {
        text = questions.map((q, i) => `${i+1}. ${q.question}\nAnswer: ${q.answer}`).join('\n\n');
    }

    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(() => showToast());
    } else {
        const el = document.createElement('textarea');
        el.value = text;
        document.body.appendChild(el);
        el.select();
        document.execCommand('copy');
        document.body.removeChild(el);
        showToast();
    }
}

function showToast() {
    const toastEl = document.getElementById('copyToast');
    if (toastEl) {
        const toast = new bootstrap.Toast(toastEl);
        toast.show();
    }
}

document.addEventListener('DOMContentLoaded', renderContent);