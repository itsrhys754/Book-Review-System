function showCustomConfirmModal(deleteUrl, message, title) {
    // Set the delete URL dynamically
    document.getElementById('confirmDeleteBtn').onclick = function() {
        window.location.href = deleteUrl; // Redirect to the delete URL
    };

    // Set the modal title dynamically
    document.getElementById('modalTitle').innerText = title || "Confirm Deletion";

    // Set the warning message dynamically
    document.getElementById('warningMessage').innerText = message || "Are you sure you want to delete this item?";

    // Show the modal
    $('#customConfirmModal').modal('show');
}

// Attach event listener to the delete buttons
document.querySelectorAll('.delete-btn, .make-moderator-btn').forEach(function(button) {
    button.addEventListener('click', function(event) {
        event.preventDefault(); // Prevent the default button behavior
        const deleteUrl = this.getAttribute('data-url'); // Get the URL from the data attribute
        const message = this.getAttribute('data-message') || "Are you sure you want to delete this item?"; // Get the message
        const title = this.getAttribute('data-title') || "Confirm Deletion"; // Get the title
        showCustomConfirmModal(deleteUrl, message, title); // Show the custom modal with dynamic content
    });
});