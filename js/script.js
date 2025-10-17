// Additional JavaScript functionality can be added here
document.addEventListener('DOMContentLoaded', function() {
    // Auto-format dates if needed
    const dateInputs = document.querySelectorAll('input[type="date"]');
    dateInputs.forEach(input => {
        if (!input.value) {
            input.value = new Date().toISOString().split('T')[0];
        }
    });
});