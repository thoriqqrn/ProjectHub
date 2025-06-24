<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login & Register</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            background-color: #f8f9fa;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .card {
            width: 100%;
            max-width: 400px;
            padding: 20px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body>
    <div class="container d-flex justify-content-center align-items-center">
        <div class="card">
            <h3 class="fw-bold text-center" id="formTitle">🔑 Login</h3>
            <form id="authForm" action="../backend/auth.php" method="POST">
                <input type="hidden" name="mode" id="mode" value="login">
                <div class="mb-3">
                    <label class="form-label">👤 Username</label>
                    <input type="text" class="form-control" name="username" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">🔒 Password</label>
                    <input type="password" class="form-control" name="password" required>
                </div>
                <button type="submit" class="btn btn-primary w-100" id="submitBtn">🚀 Login</button>
                <p class="mt-3 text-center">
                    <a href="#" id="toggleForm">Belum punya akun? Register</a>
                </p>
            </form>
        </div>
    </div>

    <script>
        const toggleForm = document.getElementById("toggleForm");
        const formTitle = document.getElementById("formTitle");
        const submitBtn = document.getElementById("submitBtn");
        const modeInput = document.getElementById("mode");

        toggleForm.addEventListener("click", function(event) {
            event.preventDefault();

            if (modeInput.value === "login") {
                modeInput.value = "register";
                formTitle.textContent = "📝 Register";
                submitBtn.textContent = "🚀 Register";
                toggleForm.textContent = "Sudah punya akun? Login";
            } else {
                modeInput.value = "login";
                formTitle.textContent = "🔑 Login";
                submitBtn.textContent = "🚀 Login";
                toggleForm.textContent = "Belum punya akun? Register";
            }
        });

        // ✅ SweetAlert berdasarkan parameter URL
        const urlParams = new URLSearchParams(window.location.search);
        const status = urlParams.get('status');
        const msg = urlParams.get('msg');

        if (status && msg) {
            Swal.fire({
                icon: status === 'success' ? 'success' : 'error',
                title: decodeURIComponent(msg),
                showConfirmButton: false,
                timer: 1500
            });
        }
    </script>
</body>
</html>
