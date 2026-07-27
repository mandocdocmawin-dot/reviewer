const urlParams = new URLSearchParams(window.location.search);
let currentMode = urlParams.get('mode') || 'list';
if (!['list', 'card', 'notes'].includes(currentMode)) {
    currentMode = 'list';
}

let cardIndex = 0;

document.addEventListener('DOMContentLoaded', () => {
    updateUIButtons();
    // Note: PHP renders initial state, so we don't force render on load to prevent flash
    // unless mode is different from default 'list'
    if (currentMode !== 'list') {
        renderContent();
    }
});

function setMode(mode) {
    currentMode = mode;
    
    const url = new URL(window.location);
    url.searchParams.set('mode', mode);
    window.history.replaceState({}, '', url);

    updateUIButtons();
    renderContent();
}

function updateUIButtons() {
    document.querySelectorAll('.mode-btn').forEach(b => b.classList.remove('active'));
    const activeBtn = document.getElementById('btn-' + currentMode);
    if(activeBtn) activeBtn.classList.add('active');
}

function renderContent() {
    const container = document.getElementById('contentDisplay');
    if (!container) return;
    
    container.innerHTML = '';
    container.style.opacity = '0';

    setTimeout(() => {
        const startIndex = (currentPage - 1) * itemsPerPage;
        let html = '';

        if (currentMode === 'list') {
            // Refactored Header with padding and flex alignment
            html += `
                <div class="d-flex justify-content-between align-items-center px-3 px-lg-4 py-3 border-bottom">
                    <h5 class="mb-0 text-muted">Review List</h5>
                    <button class="btn btn-sm btn-outline-secondary rounded-pill" onclick="copyContent()" title="Copy to Clipboard">
                        <i class="fas fa-copy me-1"></i> Copy
                    </button>
                </div>`;
            
            if (!questions || questions.length === 0) {
                 html += '<div class="text-center py-5 text-muted">No questions available.</div>';
            } else {
                html += '<div class="list-group list-group-flush">';
                questions.forEach((q, i) => {
                    html += `
                    <div class="list-group-item px-3 px-lg-4 py-3 border-light">
                        <div class="d-flex align-items-start">
                            <span class="badge bg-light text-secondary border me-3 mt-1">#${startIndex + i + 1}</span>
                            <div class="flex-grow-1">
                                <h6 class="mb-0 fw-bold text-dark">${q.question}</h6>
                            </div>
                        </div>
                    </div>`;
                });
                html += '</div>';
            }
        } 
        else if (currentMode === 'card') {
             if (!questions || questions.length === 0) {
                 html = '<div class="text-center py-5 text-muted">No flashcards available.</div>';
             } else {
                 const q = questions[cardIndex];
                 html = `
                 <div class="d-flex justify-content-between align-items-center px-3 px-lg-4 py-3">
                    <h5 class="mb-0 text-muted">Flashcard ${cardIndex + 1} of ${questions.length}</h5>
                    <span class="badge bg-success bg-opacity-10 text-success">Page Item ${cardIndex + 1}</span>
                 </div>
                 
                 <div class="px-3 px-lg-4">
                     <div class="flashcard mb-4" onclick="this.classList.toggle('flipped')">
                        <div class="flashcard-inner">
                            <div class="flashcard-front">
                                <div>
                                    <h4 class="fw-bold mb-3">Question</h4>
                                    <p class="lead">${q.question}</p>
                                    <p class="text-muted small mt-4"><i class="fas fa-sync-alt me-1"></i> Click to flip</p>
                                </div>
                            </div>
                            <div class="flashcard-back">
                                <div>
                                    <h4 class="fw-bold mb-3">Answer</h4>
                                    <p class="lead">${q.answer}</p>
                                </div>
                            </div>
                        </div>
                     </div>
                     
                     <div class="d-flex justify-content-center gap-2 mb-3">
                        <button class="btn btn-outline-secondary rounded-circle" style="width: 45px; height: 45px;" onclick="prevCard()" ${cardIndex === 0 ? 'disabled' : ''}>
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <button class="btn btn-outline-secondary rounded-circle" style="width: 45px; height: 45px;" onclick="nextCard()" ${cardIndex === questions.length - 1 ? 'disabled' : ''}>
                            <i class="fas fa-chevron-right"></i>
                        </button>
                     </div>
                 </div>
                 `;
             }
        }
        else if (currentMode === 'notes') {
            html += `
                <div class="d-flex justify-content-between align-items-center px-3 px-lg-4 py-3 border-bottom">
                    <h5 class="mb-0 text-muted">Answer Key</h5>
                    <button class="btn btn-sm btn-outline-secondary rounded-pill" onclick="copyContent()" title="Copy to Clipboard">
                        <i class="fas fa-copy me-1"></i> Copy
                    </button>
                </div>`;
                
            if (!questions || questions.length === 0) {
                 html += '<div class="text-center py-5 text-muted">No data available.</div>';
            } else {
                html += '<div class="list-group list-group-flush">';
                questions.forEach((q, i) => {
                    html += `
                    <div class="list-group-item px-3 px-lg-4 py-3 border-light">
                        <div class="mb-2">
                            <span class="badge bg-light text-secondary border me-2">Q${startIndex + i + 1}</span>
                            <span class="fw-bold text-dark">${q.question}</span>
                        </div>
                        <div class="p-3 bg-light rounded-3 text-success border border-success border-opacity-25">
                            <i class="fas fa-check-circle me-2"></i> <strong>Answer:</strong> ${q.answer}
                        </div>
                    </div>`;
                });
                html += '</div>';
            }
        }

        container.innerHTML = html;
        container.style.opacity = '1';
    }, 200);
}

function nextCard() {
    if (cardIndex < questions.length - 1) {
        cardIndex++;
        renderContent();
    }
}

function prevCard() {
    if (cardIndex > 0) {
        cardIndex--;
        renderContent();
    }
}

function copyContent() {
    let text = "";
    const startIndex = (currentPage - 1) * itemsPerPage;
    
    if (currentMode === 'list') {
        text = questions.map((q, i) => `${startIndex + i + 1}. ${q.question}`).join('\n');
    } else if (currentMode === 'card') {
        const q = questions[cardIndex];
        text = `Q: ${q.question}\nA: ${q.answer}`;
    } else {
        text = questions.map((q, i) => `${startIndex + i + 1}. ${q.question}\nAnswer: ${q.answer}`).join('\n\n');
    }

    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(showToast);
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