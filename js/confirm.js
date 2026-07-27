function confirmUsernameChange(event) {
    const input = event.target.querySelector('input[name="username"]');
    const original = input.getAttribute('data-original');
    
    if (input.value.trim() !== original) {
        if (!confirm("Are you sure you want to change your username?")) {
            event.preventDefault();
            return false;
        }
    }
    return true;
}

function confirmPasswordChange(event) {
    if (!confirm("Are you sure you want to update your password?")) {
        event.preventDefault();
        return false;
    }
    return true;
}