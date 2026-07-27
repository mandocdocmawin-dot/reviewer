function renderMarkdown() {
    document.querySelectorAll('.markdown-content').forEach(el => {
        const raw = el.getAttribute('data-markdown');
        if (raw && !el.getAttribute('data-rendered')) {
            el.innerHTML = marked.parse(raw);
            el.setAttribute('data-rendered', 'true');
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    const dayFilter = document.getElementById('dayFilter');
    const scheduleList = document.getElementById('scheduleList');
    
    const savedDay = localStorage.getItem('selectedDay');
    const realToday = new Date().toLocaleDateString('en-US', { weekday: 'long' });
    const initialDay = savedDay || realToday;

    if (dayFilter) {
        dayFilter.value = initialDay;
        loadSchedules(initialDay);
    }

    renderMarkdown();

    const editModalEl = document.getElementById('editScheduleModal');
    if (editModalEl) {
        editModalEl.addEventListener('show.bs.modal', event => {
            const button = event.relatedTarget;
            const id = button.getAttribute('data-id');
            const title = button.getAttribute('data-title');
            const day = button.getAttribute('data-day');
            const time = button.getAttribute('data-time');
            const description = button.getAttribute('data-description');

            const form = editModalEl.querySelector('form');
            if(form) {
                form.querySelector('input[name="schedule_id"]').value = id;
                form.querySelector('input[name="title"]').value = title;
                form.querySelector('select[name="day"]').value = day;
                form.querySelector('input[name="time"]').value = time;
                form.querySelector('textarea[name="description"]').value = description;
            }
        });
    }

    if (dayFilter) {
        dayFilter.addEventListener('change', function() {
            localStorage.setItem('selectedDay', this.value);
            loadSchedules(this.value, 1); 
        });
    }

    if (scheduleList) {
        scheduleList.addEventListener('click', function(e) {
            const btn = e.target.closest('.pagination-btn');
            if (btn) {
                e.preventDefault();
                const page = btn.getAttribute('data-page');
                const currentDay = dayFilter ? dayFilter.value : initialDay;
                loadSchedules(currentDay, page);
            }
        });
    }

    function setupAjaxForm(formSelector, modalId) {
        const form = document.querySelector(formSelector);
        if (!form) return;

        form.addEventListener('submit', function(e) {
            e.preventDefault(); 

            const formData = new FormData(this);
            
            fetch('schedule.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest' 
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    const modalEl = document.getElementById(modalId);
                    const modalInstance = bootstrap.Modal.getInstance(modalEl);
                    if (modalInstance) {
                        modalInstance.hide();
                    }

                    if (modalId === 'addScheduleModal') {
                        form.reset();
                    }

                    loadSchedules(dayFilter.value);

                } else {
                    alert('Error: ' + (data.message || 'Unknown error occurred'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An unexpected error occurred.');
            });
        });
    }

    setupAjaxForm('#addScheduleModal form', 'addScheduleModal');
    setupAjaxForm('#editScheduleModal form', 'editScheduleModal');
});

function loadSchedules(day, page = 1) {
    if (!scheduleList) return;
    
    scheduleList.style.opacity = '0.5';
    fetch(`schedule.php?ajax_day=${encodeURIComponent(day)}&page=${page}`)
        .then(response => response.text())
        .then(html => {
            scheduleList.innerHTML = html;
            scheduleList.style.opacity = '1';
            renderMarkdown();
        })
        .catch(err => {
            console.error('Error fetching schedules:', err);
            scheduleList.style.opacity = '1';
        });
}