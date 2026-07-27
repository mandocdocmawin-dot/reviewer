document.addEventListener('DOMContentLoaded', function () {
    var editModal = document.getElementById('editReviewModal');
    if (editModal) {
        editModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            var id = button.getAttribute('data-id');
            var question = button.getAttribute('data-question');
            var answer = button.getAttribute('data-answer');

            editModal.querySelector('#edit-id').value = id;
            editModal.querySelector('#edit-question').value = question;
            editModal.querySelector('#edit-answer').value = answer;
        });
    }
});

function loadPage(pageNumber) {
    if (event) event.preventDefault();
    
    const listContainer = document.getElementById('review-list-container');
    const paginationContainer = document.getElementById('pagination-container');

    listContainer.classList.add('loading');
    
    fetch(`create_review.php?ajax=1&page=${pageNumber}`)
        .then(response => response.json())
        .then(data => {
            listContainer.innerHTML = data.list;
            paginationContainer.innerHTML = data.pagination;
            
            listContainer.classList.remove('loading');
            
            listContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });

            const url = new URL(window.location);
            url.searchParams.set('page', pageNumber);
            window.history.pushState({}, '', url);

            document.querySelectorAll('input[name="page"]').forEach(input => {
                input.value = pageNumber;
            });
        })
        .catch(error => {
            console.error('Error loading page:', error);
            listContainer.classList.remove('loading');
            alert('Failed to load content. Please try again.');
        });
}