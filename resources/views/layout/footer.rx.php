<script src="<?= asset("js/main.js"); ?>"></script>

<!-- Footer daur ulang juga ygy -->
<footer class="d-flex flex-column fixed-bottom bg-dark text-center text-white py-3 z-2" id="dynamic-footer">
    <div class="container" id="text-section">
        <p id="footer-text" class="mb-0">© <span id="current-year"></span> Native-PHP. All Rights Reserved.</p>
    </div>
</footer>

<script>
    const currentYear = new Date().getFullYear();
    document.getElementById("current-year").textContent = currentYear;
    // document.addEventListener('DOMContentLoaded', function() {
    //     if (!localStorage.getItem('user_id')) {
    //         alert('Please log in first.');
    //         window.location.href = '/login';
    //     }
    // });
    // document.getElementById('logout-btn').addEventListener('click', function() {
    //     localStorage.removeItem('user_id');
    //     alert('You have been logged out.');
    //     window.location.href = '/login';
    // });
</script>

<style>
    footer {
        background-color: #1e1e1e;
        /* Dark background */
        color: #d9d9d9;
        /* Light text color */
    }

    footer p {
        font-size: 0.875rem;
        /* Smaller font size */
        letter-spacing: 1px;
        text-transform: uppercase;
        margin: 0;
    }

    footer #footer-text {
        font-weight: 500;
    }

    footer #extra-text {
        font-size: 0.75rem;
        /* Smaller for extra info */
        color: #bbb;
        /* Lighter text */
    }

    footer a:hover {
        color: #00b3b3;
        text-decoration: none;
    }
</style>


</body>

</html>