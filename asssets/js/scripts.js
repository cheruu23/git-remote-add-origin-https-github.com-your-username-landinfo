// Example: Confirm before deleting a user
function confirmDelete() {
    return confirm("Are you sure you want to delete this user?");
}


    document.getElementById("toggle-sidebar").addEventListener("click", function() {
        document.getElementById("sidebar").classList.toggle("active");
        document.getElementById("main-content").classList.toggle("active");
    });

    document.getElementById("notification-icon").addEventListener("click", function() {
        document.getElementById("notification-dropdown").classList.toggle("show");
    });

    window.addEventListener("click", function(event) {
        if (!event.target.matches(".notification-icon, .notification-icon *")) {
            document.getElementById("notification-dropdown").classList.remove("show");
        }
    });
