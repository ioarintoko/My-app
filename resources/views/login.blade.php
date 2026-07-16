<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Login - Movie Watchlist</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #0f172a; color: #e2e8f0; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
        .card { background: #1e293b; padding: 2rem; border-radius: 12px; width: 320px; box-shadow: 0 10px 30px rgba(0,0,0,.3); }
        h1 { font-size: 1.25rem; margin-bottom: 1.5rem; }
        input { width: 100%; padding: .6rem; margin-bottom: .8rem; border-radius: 6px; border: 1px solid #334155; background: #0f172a; color: #e2e8f0; box-sizing: border-box; }
        button { width: 100%; padding: .7rem; background: #6366f1; border: none; border-radius: 6px; color: white; font-weight: 600; cursor: pointer; }
        button:hover { background: #4f46e5; }
        .error { color: #f87171; font-size: .85rem; margin-bottom: .8rem; min-height: 1rem; }
        .toggle { text-align: center; margin-top: 1rem; font-size: .85rem; color: #94a3b8; cursor: pointer; }
    </style>
</head>
<body>
    <div class="card">
        <h1 id="formTitle">Login ke Movie Watchlist</h1>
        <div class="error" id="errorMsg"></div>

        <input type="text" id="name" placeholder="Nama" style="display:none">
        <input type="email" id="email" placeholder="Email">
        <input type="password" id="password" placeholder="Password">
        <input type="password" id="password_confirmation" placeholder="Konfirmasi Password" style="display:none">

        <button onclick="submitForm()">Login</button>
        <div class="toggle" onclick="toggleMode()">Belum punya akun? Register</div>
    </div>

    <script>
        let isRegister = false;

        function toggleMode() {
            isRegister = !isRegister;
            document.getElementById('formTitle').innerText = isRegister ? 'Daftar Akun Baru' : 'Login ke Movie Watchlist';
            document.getElementById('name').style.display = isRegister ? 'block' : 'none';
            document.getElementById('password_confirmation').style.display = isRegister ? 'block' : 'none';
            document.querySelector('button').innerText = isRegister ? 'Register' : 'Login';
            document.querySelector('.toggle').innerText = isRegister ? 'Sudah punya akun? Login' : 'Belum punya akun? Register';
            document.getElementById('errorMsg').innerText = '';
        }

        async function submitForm() {
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            const errorEl = document.getElementById('errorMsg');
            errorEl.innerText = '';

            const url = isRegister ? '/api/auth/register' : '/api/auth/login';
            const body = isRegister
                ? {
                    name: document.getElementById('name').value,
                    email,
                    password,
                    password_confirmation: document.getElementById('password_confirmation').value,
                  }
                : { email, password };

            try {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify(body),
                });
                const data = await res.json();

                if (!res.ok) {
                    errorEl.innerText = data.message || 'Terjadi kesalahan';
                    if (data.errors) {
                        errorEl.innerText = Object.values(data.errors).flat().join(', ');
                    }
                    return;
                }

                localStorage.setItem('token', data.data.access_token);
                localStorage.setItem('user', JSON.stringify(data.data.user));
                window.location.href = '/dashboard';
            } catch (e) {
                errorEl.innerText = 'Gagal terhubung ke server';
            }
        }
    </script>
</body>
</html>