<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - Movie Watchlist</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: system-ui, sans-serif; background: #0f172a; color: #e2e8f0; margin: 0; }
        header { display: flex; justify-content: space-between; align-items: center; padding: 1rem 2rem; background: #1e293b; }
        header h1 { font-size: 1.1rem; margin: 0; }
        header button { background: #ef4444; border: none; color: white; padding: .5rem 1rem; border-radius: 6px; cursor: pointer; }
        main { padding: 2rem; max-width: 1100px; margin: 0 auto; }
        .tabs { display: flex; gap: 1rem; margin-bottom: 1.5rem; }
        .tabs button { background: #1e293b; border: 1px solid #334155; color: #e2e8f0; padding: .5rem 1rem; border-radius: 6px; cursor: pointer; }
        .tabs button.active { background: #6366f1; border-color: #6366f1; }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 1rem; }
        .movie-card { background: #1e293b; border-radius: 10px; overflow: hidden; }
        .movie-card img { width: 100%; height: 240px; object-fit: cover; display: block; background: #334155; }
        .movie-card .body { padding: .7rem; }
        .movie-card h3 { font-size: .9rem; margin: 0 0 .5rem; }
        .movie-card button { width: 100%; padding: .4rem; font-size: .8rem; background: #6366f1; border: none; border-radius: 6px; color: white; cursor: pointer; margin-top: .3rem; }
        .badge { display: inline-block; padding: .15rem .5rem; border-radius: 999px; font-size: .7rem; background: #334155; }
        .badge.watched { background: #16a34a; }
        .badge.watching { background: #ca8a04; }
        .modal-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,.6); display: none; align-items: center; justify-content: center; }
        .modal-backdrop.show { display: flex; }
        .modal { background: #1e293b; padding: 1.5rem; border-radius: 10px; width: 320px; }
        .modal label { font-size: .8rem; display: block; margin: .6rem 0 .2rem; }
        .modal select, .modal input, .modal textarea { width: 100%; padding: .5rem; border-radius: 6px; border: 1px solid #334155; background: #0f172a; color: #e2e8f0; }
        .modal .actions { display: flex; gap: .5rem; margin-top: 1rem; }
        .modal .actions button { flex: 1; padding: .5rem; border: none; border-radius: 6px; cursor: pointer; }
        .btn-save { background: #6366f1; color: white; }
        .btn-cancel { background: #334155; color: white; }
        .loading, .empty { text-align: center; padding: 2rem; color: #94a3b8; }
    </style>
</head>
<body>
    <header>
        <h1>🎬 Movie Watchlist — <span id="userName"></span></h1>
        <button onclick="logout()">Logout</button>
    </header>

    <main>
        <div class="tabs">
            <button id="tabMovies" class="active" onclick="switchTab('movies')">Semua Movie</button>
            <button id="tabWatchlist" onclick="switchTab('watchlist')">Watchlist Saya</button>
        </div>

        <div id="content" class="grid">
            <div class="loading">Memuat data...</div>
        </div>
    </main>

    <div class="modal-backdrop" id="modalBackdrop">
        <div class="modal">
            <h3 id="modalTitle">Tambah ke Watchlist</h3>
            <input type="hidden" id="watchlistId">
            <input type="hidden" id="movieId">
            <label>Status</label>
            <select id="status">
                <option value="plan_to_watch">Plan to Watch</option>
                <option value="watching">Watching</option>
                <option value="watched">Watched</option>
            </select>
            <label>Rating (1-10)</label>
            <input type="number" id="rating" min="1" max="10">
            <label>Catatan</label>
            <textarea id="notes" rows="3"></textarea>
            <div class="actions">
                <button class="btn-cancel" onclick="closeModal()">Batal</button>
                <button class="btn-save" onclick="saveWatchlist()">Simpan</button>
            </div>
        </div>
    </div>

    <script>
        const token = localStorage.getItem('token');
        const user = JSON.parse(localStorage.getItem('user') || 'null');

        if (!token || !user) {
            window.location.href = '/login';
        }

        document.getElementById('userName').innerText = user?.name || '';

        let currentTab = 'movies';
        let moviesCache = [];
        let watchlistCache = [];

        function logout() {
            localStorage.removeItem('token');
            localStorage.removeItem('user');
            window.location.href = '/login';
        }

        async function api(path, options = {}) {
            const res = await fetch('/api' + path, {
                ...options,
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'Authorization': 'Bearer ' + token,
                    ...(options.headers || {}),
                },
            });

            if (res.status === 401) {
                logout();
                return;
            }

            return res.json();
        }

        function switchTab(tab) {
            currentTab = tab;
            document.getElementById('tabMovies').classList.toggle('active', tab === 'movies');
            document.getElementById('tabWatchlist').classList.toggle('active', tab === 'watchlist');
            loadContent();
        }

        async function loadContent() {
            const content = document.getElementById('content');
            content.innerHTML = '<div class="loading">Memuat data...</div>';

            if (currentTab === 'movies') {
                const res = await api('/movies?per_page=30');
                moviesCache = res.data.data;
                renderMovies();
            } else {
                const res = await api('/watchlists');
                watchlistCache = res.data.data;
                renderWatchlist();
            }
        }

        function renderMovies() {
            const content = document.getElementById('content');
            if (!moviesCache.length) {
                content.innerHTML = '<div class="empty">Belum ada data movie</div>';
                return;
            }

            content.innerHTML = moviesCache.map(m => `
                <div class="movie-card">
                    <img src="${m.poster_url || 'https://placehold.co/180x240?text=No+Image'}" alt="${m.title}">
                    <div class="body">
                        <h3>${m.title}</h3>
                        <button onclick="openAddModal(${m.id})">+ Watchlist</button>
                    </div>
                </div>
            `).join('');
        }

        function renderWatchlist() {
            const content = document.getElementById('content');
            if (!watchlistCache.length) {
                content.innerHTML = '<div class="empty">Watchlist kamu masih kosong</div>';
                return;
            }

            content.innerHTML = watchlistCache.map(w => `
                <div class="movie-card">
                    <img src="${w.movie.poster_url || 'https://placehold.co/180x240?text=No+Image'}" alt="${w.movie.title}">
                    <div class="body">
                        <h3>${w.movie.title}</h3>
                        <span class="badge ${w.status}">${w.status.replace(/_/g, ' ')}</span>
                        ${w.rating ? `<div style="margin-top:.4rem;font-size:.8rem;">⭐ ${w.rating}/10</div>` : ''}
                        <button onclick='openEditModal(${JSON.stringify(w)})'>Edit</button>
                        <button style="background:#ef4444;" onclick="deleteWatchlist(${w.id})">Hapus</button>
                    </div>
                </div>
            `).join('');
        }

        function openAddModal(movieId) {
            document.getElementById('modalTitle').innerText = 'Tambah ke Watchlist';
            document.getElementById('watchlistId').value = '';
            document.getElementById('movieId').value = movieId;
            document.getElementById('status').value = 'plan_to_watch';
            document.getElementById('rating').value = '';
            document.getElementById('notes').value = '';
            document.getElementById('modalBackdrop').classList.add('show');
        }

        function openEditModal(w) {
            document.getElementById('modalTitle').innerText = 'Edit Watchlist';
            document.getElementById('watchlistId').value = w.id;
            document.getElementById('movieId').value = w.movie_id;
            document.getElementById('status').value = w.status;
            document.getElementById('rating').value = w.rating || '';
            document.getElementById('notes').value = w.notes || '';
            document.getElementById('modalBackdrop').classList.add('show');
        }

        function closeModal() {
            document.getElementById('modalBackdrop').classList.remove('show');
        }

        async function saveWatchlist() {
            const id = document.getElementById('watchlistId').value;
            const payload = {
                movie_id: parseInt(document.getElementById('movieId').value),
                status: document.getElementById('status').value,
                rating: document.getElementById('rating').value ? parseInt(document.getElementById('rating').value) : null,
                notes: document.getElementById('notes').value || null,
            };

            const res = id
                ? await api('/watchlists/' + id, { method: 'PUT', body: JSON.stringify(payload) })
                : await api('/watchlists', { method: 'POST', body: JSON.stringify(payload) });

            if (res && res.success === false) {
                alert(res.message);
                return;
            }

            closeModal();
            loadContent();
        }

        async function deleteWatchlist(id) {
            if (!confirm('Yakin mau hapus dari watchlist?')) return;
            await api('/watchlists/' + id, { method: 'DELETE' });
            loadContent();
        }

        loadContent();
    </script>
</body>
</html>