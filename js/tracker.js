document.addEventListener('DOMContentLoaded', function() {
    
    // --- AJAX Helper Function ---
    function sendAjax(formData) {
        formData.append('ajax', 'true');
        
        // Add loading state to list
        const list = document.getElementById('activity-list');
        list.classList.add('loading');

        fetch('user_tracker.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // If success, simply reload the list content to reflect changes
                reloadList(); 
            } else {
                alert('An error occurred.');
                list.classList.remove('loading');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            list.classList.remove('loading');
        });
    }

    // Function to reload just the list content
    function reloadList() {
        const urlParams = new URLSearchParams(window.location.search);
        const filter = urlParams.get('filter') || 'all';
        const page = urlParams.get('page') || 1;

        fetch(`user_tracker.php?ajax=true&filter=${filter}&page=${page}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('activity-list').innerHTML = html;
            document.getElementById('activity-list').classList.remove('loading');
        });
    }

    // --- Add Activity Form ---
    const addForm = document.getElementById('addActivityForm');
    if (addForm) {
        addForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            
            // Close modal
            const modalEl = document.getElementById('addActivityModal');
            const modal = bootstrap.Modal.getInstance(modalEl);
            modal.hide();
            
            this.reset();
            sendAjax(formData);
        });
    }

    // --- Edit Activity Form ---
    const editForm = document.getElementById('editActivityForm');
    if (editForm) {
        editForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);

            // Close modal
            const modalEl = document.getElementById('editActivityModal');
            const modal = bootstrap.Modal.getInstance(modalEl);
            modal.hide();

            sendAjax(formData);
        });
    }

    // --- Event Delegation for Dropdown Actions ---
    document.getElementById('activity-list').addEventListener('click', function(e) {
        
        // 1. Toggle Status
        const toggleBtn = e.target.closest('.toggle-status-btn');
        if (toggleBtn) {
            e.preventDefault();
            const id = toggleBtn.getAttribute('data-id');
            const formData = new FormData();
            formData.append('toggle_activity_id', id);
            sendAjax(formData);
        }

        // 2. Delete Activity
        const deleteBtn = e.target.closest('.delete-activity-btn');
        if (deleteBtn) {
            e.preventDefault();
            if (confirm('Are you sure you want to delete this task?')) {
                const id = deleteBtn.getAttribute('data-id');
                const formData = new FormData();
                formData.append('delete_activity_id', id);
                sendAjax(formData);
            }
        }

        // 3. Edit Activity (Populate Modal)
        const editBtn = e.target.closest('.edit-activity-btn');
        if (editBtn) {
            e.preventDefault();
            
            // Get data attributes
            const id = editBtn.getAttribute('data-id');
            const title = editBtn.getAttribute('data-title');
            const type = editBtn.getAttribute('data-type');
            const dueDate = editBtn.getAttribute('data-due-date');

            // Populate form
            document.getElementById('edit_activity_id').value = id;
            document.getElementById('edit_title').value = title;
            document.getElementById('edit_type').value = type;
            document.getElementById('edit_due_date').value = dueDate;

            // Show modal
            const editModal = new bootstrap.Modal(document.getElementById('editActivityModal'));
            editModal.show();
        }
    });

    // --- Pagination (Keep AJAX feeling) ---
    document.getElementById('activity-list').addEventListener('click', function(e) {
        const pageLink = e.target.closest('.pagination-btn');
        if (pageLink && !pageLink.classList.contains('active')) {
            e.preventDefault();
            const url = pageLink.getAttribute('href');
            
            // Update URL bar without reload
            window.history.pushState({}, '', url);

            // Fetch content
            document.getElementById('activity-list').classList.add('loading');
            fetch(url + '&ajax=true')
            .then(response => response.text())
            .then(html => {
                document.getElementById('activity-list').innerHTML = html;
                document.getElementById('activity-list').classList.remove('loading');
            });
        }
    });

});