<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db_conn.php';
require_once __DIR__ . '/repositories/ThreadRepository.php';
require_once __DIR__ . '/repositories/CommentRepository.php';
require_once __DIR__ . '/repositories/TopicRepository.php';
require_once __DIR__ . '/repositories/BookmarkRepository.php';
require_once __DIR__ . '/controllers/CommentController.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$user = auth_user();

$threadId = trim($_GET['id'] ?? '');
if (empty($threadId)) {
    redirect(BASE_URL . '/');
}

$threadRepo  = new ThreadRepository(DBH);
$commentRepo = new CommentRepository(DBH);
$topicRepo   = new TopicRepository(DBH);
$bookmarkRepo = new BookmarkRepository(DBH);

$thread = $threadRepo->findById($threadId);
if (!$thread) {
    set_flash('error', 'Thread tidak ditemukan.');
    redirect(BASE_URL . '/');
}

$topics      = $topicRepo->getByThread($threadId);
$comments    = $commentRepo->getByThread($threadId);
$commentTree = build_comment_tree($comments);
$isBookmarked = $user ? $bookmarkRepo->exists($user['user_id'], $threadId) : false;

// Handle tambah komentar
$commentError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'comment') {
    if (!$user) {
        redirect(BASE_URL . '/auth/login.php');
    }
    if (!verify_csrf()) {
        $commentError = 'Permintaan tidak valid. Coba lagi.';
    } else {
        $result = handle_add_comment($commentRepo, $user);
        if ($result['success']) {
            redirect(BASE_URL . '/thread.php?id=' . $threadId . '#komentar');
        } else {
            $commentError = $result['message'];
        }
    }
}

// Handle hapus komentar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_comment') {
    if (!$user || !verify_csrf()) redirect(BASE_URL . '/thread.php?id=' . $threadId);
    $cid = trim($_POST['comment_id'] ?? '');
    handle_delete_comment($commentRepo, $cid, $user);
    redirect(BASE_URL . '/thread.php?id=' . $threadId . '#komentar');
}

// Handle hapus thread
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_thread') {
    if (!$user || !verify_csrf()) redirect(BASE_URL . '/');
    require_once __DIR__ . '/controllers/ThreadController.php';
    $result = handle_delete_thread($threadRepo, $threadId, $user);
    if ($result['success']) {
        set_flash('success', 'Thread berhasil dihapus.');
        redirect(BASE_URL . '/');
    }
    set_flash('error', $result['message'] ?? 'Gagal menghapus thread.');
    redirect(BASE_URL . '/thread.php?id=' . $threadId);
}

$flash  = get_flash();
$pageCSS = ['homepage.css', 'thread.css'];
$title = e($thread['thread_title']);
?>
<!DOCTYPE html>
<html lang="id">
<?php include_once __DIR__ . '/components/metadata.php'; ?>

<body>
    <?php include_once __DIR__ . '/components/navbar.php'; ?>

    <main>
        <?php if ($flash): ?>
            <div class="flash-message flash-<?= e($flash['type']) ?>" style="margin-bottom:1.5rem;">
                <?= e($flash['message']) ?>
            </div>
        <?php endif; ?>

        <?php if ($user && $user['status'] === 'restricted'): ?>
            <div class="warning-banner">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" flex-shrink="0"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                <div>
                    <strong>Akun kamu sedang dibatasi.</strong>
                    Kamu tidak dapat membuat komentar saat ini karena mendapat tindakan dari moderator.
                </div>
            </div>
        <?php endif; ?>

        <div class="thread-detail-layout">
            <!-- Main Content -->
            <div>
                <!-- Thread Post -->
                <article class="thread-post">
                    <div class="thread-post-header">
                        <div class="thread-post-meta">
                            <div class="thread-post-avatar">
                                <?= e(mb_strtoupper(mb_substr($thread['author_fullname'], 0, 1))) ?>
                            </div>
                            <div class="thread-post-author-info">
                                <a href="<?= BASE_URL ?>/profile/?u=<?= e($thread['author_username']) ?>" class="thread-post-author-name">
                                    <?= e($thread['author_fullname']) ?>
                                </a>
                                <time class="thread-post-time" datetime="<?= e($thread['created_at']) ?>">
                                    <?= time_ago($thread['created_at']) ?>
                                    <?= $thread['updated_at'] !== $thread['created_at'] ? ' · diedit' : '' ?>
                                </time>
                            </div>
                        </div>

                        <?php if (!empty($topics)): ?>
                            <div class="thread-post-topics">
                                <?php foreach ($topics as $t): ?>
                                    <a href="<?= BASE_URL ?>/?topic=<?= e($t['topic_id']) ?>" class="thread-topic-badge">
                                        <?= e($t['topic_name']) ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <h1 class="thread-post-title"><?= e($thread['thread_title']) ?></h1>
                    </div>

                    <div class="thread-post-body">
                        <?= nl2br(e($thread['thread_description'])) ?>
                    </div>

                    <div class="thread-post-footer">
                        <div class="thread-stats">
                            <span class="thread-stat">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                                <?= (int)$thread['comment_count'] ?> komentar
                            </span>
                        </div>

                        <div class="thread-post-actions">
                            <!-- Share -->
                            <button class="thread-action-btn btn-share"
                                data-url="<?= BASE_URL ?>/thread.php?id=<?= e($threadId) ?>"
                                data-title="<?= e($thread['thread_title']) ?>">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
                                Bagikan
                            </button>

                            <?php if ($user): ?>
                                <!-- Bookmark -->
                                <button class="thread-action-btn btn-bookmark <?= $isBookmarked ? 'bookmarked' : '' ?>"
                                    data-thread-id="<?= e($threadId) ?>"
                                    data-action="<?= BASE_URL ?>/api/bookmark.php">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="<?= $isBookmarked ? 'currentColor' : 'none' ?>" stroke="currentColor" stroke-width="2"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
                                    <?= $isBookmarked ? 'Tersimpan' : 'Simpan' ?>
                                </button>

                                <!-- Report -->
                                <button class="thread-action-btn" id="btn-report-thread"
                                    onclick="document.getElementById('report-modal').style.display='flex'">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/></svg>
                                    Laporkan
                                </button>

                                <!-- Edit/Hapus (hanya pemilik atau admin) -->
                                <?php if ($thread['author_id'] === $user['user_id'] || has_role('moderator', 'superadmin')): ?>
                                    <a href="<?= BASE_URL ?>/forum/edit-thread.php?id=<?= e($threadId) ?>" class="thread-action-btn">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                        Edit
                                    </a>

                                    <form method="POST" style="display:inline;" id="delete-thread-form"
                                        onsubmit="return confirm('Hapus thread ini? Tindakan ini tidak dapat dibatalkan.')">
                                        <input type="hidden" name="action" value="delete_thread">
                                        <input type="hidden" name="csrf_token" value="<?= generate_csrf() ?>">
                                        <button type="submit" class="thread-action-btn danger" style="color:var(--danger,#ef4444);">
                                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                                            Hapus
                                        </button>
                                    </form>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>

                <!-- Comments Section -->
                <section class="comments-section" id="komentar">
                    <div class="comments-header">
                        💬 <?= (int)$thread['comment_count'] ?> Komentar
                    </div>

                    <!-- Form komentar baru -->
                    <?php if ($user && $user['status'] !== 'restricted'): ?>
                        <div class="comment-form-wrapper">
                            <?php if ($commentError): ?>
                                <div class="flash-message flash-error" style="margin-bottom:0.75rem;">
                                    <?= e($commentError) ?>
                                </div>
                            <?php endif; ?>
                            <form method="POST" action="">
                                <input type="hidden" name="action" value="comment">
                                <input type="hidden" name="thread_id" value="<?= e($threadId) ?>">
                                <input type="hidden" name="csrf_token" value="<?= generate_csrf() ?>">
                                <div class="comment-form-inner">
                                    <div class="comment-form-avatar">
                                        <?= e(mb_strtoupper(mb_substr($user['fullname'], 0, 1))) ?>
                                    </div>
                                    <div class="comment-form-field">
                                        <textarea name="content" class="comment-textarea"
                                            placeholder="Tulis komentar kamu..." rows="3" id="comment-textarea"></textarea>
                                        <div class="comment-form-submit">
                                            <button type="submit" class="btn btn-primary btn-sm" id="btn-submit-comment">
                                                Kirim Komentar
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    <?php elseif (!$user): ?>
                        <div class="comment-login-prompt">
                            <a href="<?= BASE_URL ?>/auth/login.php">Login</a> atau
                            <a href="<?= BASE_URL ?>/auth/register.php">daftar</a> untuk ikut berdiskusi.
                        </div>
                    <?php endif; ?>

                    <!-- Daftar komentar -->
                    <div class="comment-list" id="comment-list">
                        <?php if (empty($commentTree)): ?>
                            <div class="empty-state" style="padding: 2rem;">
                                <p>Belum ada komentar. Jadilah yang pertama berkomentar!</p>
                            </div>
                        <?php else: ?>
                            <?php
                            function render_comment(array $comment, array $user = null, string $threadId = '', string $baseUrl = '', int $depth = 0): void
                            {
                                $isOwner = $user && ($comment['author_id'] === $user['user_id']);
                                $canMod  = $user && (isset($user['role']) && in_array($user['role'], ['moderator', 'superadmin']));
                            ?>
                            <div class="comment-item <?= $depth > 0 ? 'comment-reply' : '' ?>" id="comment-<?= e($comment['comment_id']) ?>">
                                <div class="comment-item-header">
                                    <div class="comment-avatar">
                                        <?= e(mb_strtoupper(mb_substr($comment['author_fullname'], 0, 1))) ?>
                                    </div>
                                    <a href="<?= $baseUrl ?>/profile/?u=<?= e($comment['author_username']) ?>" class="comment-author">
                                        <?= e($comment['author_fullname']) ?>
                                    </a>
                                    <time class="comment-time"><?= time_ago($comment['created_at']) ?></time>
                                </div>

                                <div class="comment-content"><?= nl2br(e($comment['content'])) ?></div>

                                <div class="comment-actions">
                                    <?php if ($user && $user['status'] !== 'restricted'): ?>
                                        <!-- Bug 2 fix: tombol Balas ada di bawah komentar, toggle langsung form di bawahnya -->
                                        <!-- Bug 3 fix: btn-reply-toggle sekarang bisa hide juga (toggle) dengan teks dinamis -->
                                        <button class="comment-action-btn btn-reply-toggle"
                                            data-target="reply-form-<?= e($comment['comment_id']) ?>"
                                            id="btn-reply-<?= e($comment['comment_id']) ?>">
                                            <svg class="icon-reply" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 17 4 12 9 7"/><path d="M20 18v-2a4 4 0 0 0-4-4H4"/></svg>
                                            <svg class="icon-close" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                            <span class="reply-btn-text">Balas</span>
                                        </button>
                                    <?php endif; ?>

                                    <?php if ($isOwner || $canMod): ?>
                                        <form method="POST" style="display:inline;"
                                            onsubmit="return confirm('Hapus komentar ini?')">
                                            <input type="hidden" name="action" value="delete_comment">
                                            <input type="hidden" name="comment_id" value="<?= e($comment['comment_id']) ?>">
                                            <input type="hidden" name="csrf_token" value="<?= generate_csrf() ?>">
                                            <button type="submit" class="comment-action-btn danger">
                                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                                                Hapus
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>

                                <!-- Form balas (tersembunyi, toggle via tombol Balas di atas) -->
                                <?php if ($user && $user['status'] !== 'restricted'): ?>
                                    <div class="reply-form-wrapper" id="reply-form-<?= e($comment['comment_id']) ?>">
                                        <form method="POST" action="">
                                            <input type="hidden" name="action" value="comment">
                                            <input type="hidden" name="thread_id" value="<?= e($threadId) ?>">
                                            <input type="hidden" name="parent_comment_id" value="<?= e($comment['comment_id']) ?>">
                                            <input type="hidden" name="csrf_token" value="<?= generate_csrf() ?>">
                                            <div class="comment-form-inner" style="margin-top:0.5rem;">
                                                <div class="comment-form-avatar" style="width:1.75rem;height:1.75rem;font-size:0.7rem;">
                                                    <?= e(mb_strtoupper(mb_substr($user['fullname'], 0, 1))) ?>
                                                </div>
                                                <div class="comment-form-field">
                                                    <textarea name="content" class="comment-textarea" rows="2"
                                                        placeholder="Tulis balasan..."></textarea>
                                                    <div class="comment-form-submit" style="gap:0.5rem;display:flex;justify-content:flex-end;">
                                                        <button type="button" class="btn btn-ghost btn-primary btn-sm btn-cancel-reply"
                                                            data-target="reply-form-<?= e($comment['comment_id']) ?>"
                                                            data-btn="btn-reply-<?= e($comment['comment_id']) ?>">
                                                            Batal
                                                        </button>
                                                        <button type="submit" class="btn btn-primary btn-sm">Kirim Balasan</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                <?php endif; ?>

                                <!-- Replies nested -->
                                <?php if (!empty($comment['replies'])): ?>
                                    <div class="comment-replies">
                                        <?php foreach ($comment['replies'] as $reply): ?>
                                            <?php render_comment($reply, $user, $threadId, $baseUrl, $depth + 1); ?>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php
                            }

                            foreach ($commentTree as $comment) {
                                render_comment($comment, $user, $threadId, BASE_URL);
                            }
                            ?>
                        <?php endif; ?>
                    </div>
                </section>
            </div>

            <!-- Sidebar -->
            <aside class="thread-sidebar">
                <div class="sidebar-card">
                    <p class="sidebar-card-title">Tentang Thread</p>
                    <div style="font-size:0.875rem;color:var(--gray-600,#4b5563);">
                        <p style="margin-bottom:0.5rem;">
                            <strong>Dibuat:</strong> <?= date('d M Y', strtotime($thread['created_at'])) ?>
                        </p>
                        <p style="margin-bottom:0.5rem;">
                            <strong>Komentar:</strong> <?= (int)$thread['comment_count'] ?>
                        </p>
                        <?php if (!empty($topics)): ?>
                            <p style="margin-bottom:0.5rem;"><strong>Topik:</strong></p>
                            <div class="thread-topics">
                                <?php foreach ($topics as $t): ?>
                                    <a href="<?= BASE_URL ?>/?topic=<?= e($t['topic_id']) ?>" class="thread-topic-badge">
                                        <?= e($t['topic_name']) ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="sidebar-card">
                    <p class="sidebar-card-title">Tentang Penulis</p>
                    <div style="display:flex;align-items:center;gap:0.625rem;">
                        <div class="thread-post-avatar" style="width:2.25rem;height:2.25rem;font-size:0.875rem;">
                            <?= e(mb_strtoupper(mb_substr($thread['author_fullname'], 0, 1))) ?>
                        </div>
                        <div>
                            <a href="<?= BASE_URL ?>/profile/?u=<?= e($thread['author_username']) ?>"
                               style="font-weight:600;font-size:0.875rem;color:var(--gray-900,#111827);">
                                <?= e($thread['author_fullname']) ?>
                            </a>
                            <div style="font-size:0.8rem;color:var(--gray-400,#9ca3af);">@<?= e($thread['author_username']) ?></div>
                        </div>
                    </div>
                </div>

                <a href="<?= BASE_URL ?>/" class="btn btn-ghost btn-primary btn-sm" style="width:100%;justify-content:center;">
                    ← Kembali ke Forum
                </a>
            </aside>
        </div>
    </main>

    <?php include_once __DIR__ . '/components/footer.php'; ?>

    <!-- Report Modal -->
    <?php if ($user): ?>
    <div id="report-modal" style="display:none;position:fixed;inset:0;z-index:1000;background:rgba(0,0,0,0.5);align-items:center;justify-content:center;padding:1rem;">
        <div style="background:white;border-radius:20px;padding:2rem;max-width:440px;width:100%;">
            <h3 style="margin-bottom:0.5rem;">Laporkan Thread</h3>
            <p style="color:var(--gray-500,#6b7280);font-size:0.875rem;margin-bottom:1.25rem;">
                Ceritakan mengapa thread ini melanggar ketentuan komunitas ForIT.
            </p>
            <form method="POST" action="<?= BASE_URL ?>/api/report.php">
                <input type="hidden" name="thread_id" value="<?= e($threadId) ?>">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf() ?>">
                <textarea name="reason" placeholder="Alasan pelaporan..." rows="4"
                    class="comment-textarea" style="margin-bottom:1rem;" required></textarea>
                <div style="display:flex;gap:0.75rem;justify-content:flex-end;">
                    <button type="button" class="btn btn-ghost btn-primary btn-sm"
                        onclick="document.getElementById('report-modal').style.display='none'">
                        Batal
                    </button>
                    <button type="submit" class="btn btn-danger btn-sm">Kirim Laporan</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script>
    // Bug 2 & 3 fix: Toggle reply form dengan ikon dan teks dinamis
    // Klik "Balas" → buka form + ganti ikon jadi X + teks "Tutup"
    // Klik lagi atau "Batal" → tutup form kembali
    function openReplyForm(btn, form) {
        form.classList.add('open');
        btn.querySelector('.icon-reply').style.display = 'none';
        btn.querySelector('.icon-close').style.display = '';
        btn.querySelector('.reply-btn-text').textContent = 'Tutup';
        btn.classList.add('active-reply');
        form.querySelector('textarea')?.focus();
    }

    function closeReplyForm(btn, form) {
        form.classList.remove('open');
        btn.querySelector('.icon-reply').style.display = '';
        btn.querySelector('.icon-close').style.display = 'none';
        btn.querySelector('.reply-btn-text').textContent = 'Balas';
        btn.classList.remove('active-reply');
        if (form.querySelector('textarea')) form.querySelector('textarea').value = '';
    }

    document.querySelectorAll('.btn-reply-toggle').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const form = document.getElementById(this.dataset.target);
            if (!form) return;
            if (form.classList.contains('open')) {
                closeReplyForm(this, form);
            } else {
                openReplyForm(this, form);
            }
        });
    });

    // Tombol Batal di dalam form balasan
    document.querySelectorAll('.btn-cancel-reply').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const form   = document.getElementById(this.dataset.target);
            const toggle = document.getElementById(this.dataset.btn);
            if (form && toggle) closeReplyForm(toggle, form);
        });
    });

    // Pagination / Load More Komentar
    document.addEventListener('DOMContentLoaded', function() {
        const ROOT_LIMIT = 8;
        const NESTED_LIMIT = 4;

        // Logika untuk Komentar Utama (Root) -> tombol pill di tengah
        function createRootLoadMoreBtn(container) {
            const items = Array.from(container.children).filter(el => el.matches('.comment-item:not(.comment-reply)'));
            if (items.length <= ROOT_LIMIT) return;

            let currentIndex = ROOT_LIMIT;
            
            // Sembunyikan item yang melebihi batas
            for (let i = currentIndex; i < items.length; i++) {
                items[i].style.display = 'none';
            }

            const btnWrapper = document.createElement('div');
            btnWrapper.style.textAlign = 'center';
            btnWrapper.style.marginTop = '1rem';
            btnWrapper.style.marginBottom = '0.5rem';

            const btn = document.createElement('button');
            btn.className = 'btn btn-ghost btn-primary btn-sm';
            btn.style.borderRadius = '50px';
            btn.innerHTML = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:0.3rem;vertical-align:middle"><polyline points="6 9 12 15 18 9"/></svg> Komentar lainnya`;

            btn.addEventListener('click', function() {
                const nextIndex = currentIndex + ROOT_LIMIT;
                for (let i = currentIndex; i < Math.min(nextIndex, items.length); i++) {
                    items[i].style.display = '';
                }
                currentIndex = nextIndex;
                if (currentIndex >= items.length) {
                    btnWrapper.remove();
                }
            });

            btnWrapper.appendChild(btn);
            container.appendChild(btnWrapper);
        }

        // Logika untuk Balasan Bersarang (Nested) -> teks inline gaya Instagram
        function createNestedLoadMoreBtn(container) {
            const items = Array.from(container.children).filter(el => el.matches('.comment-item.comment-reply'));
            const total = items.length;
            if (total === 0) return;

            // Sembunyikan SEMUA balasan di awal
            let currentIndex = 0;
            for (let i = currentIndex; i < total; i++) {
                items[i].style.display = 'none';
            }

            const btnWrapper = document.createElement('div');
            btnWrapper.style.marginTop = '0.5rem';
            btnWrapper.style.marginBottom = '1rem';

            const btn = document.createElement('button');
            btn.style.background = 'none';
            btn.style.border = 'none';
            btn.style.color = 'var(--gray-500, #6b7280)';
            btn.style.fontSize = '0.8125rem';
            btn.style.fontWeight = '600';
            btn.style.cursor = 'pointer';
            btn.style.padding = '0';
            btn.style.display = 'inline-flex';
            btn.style.alignItems = 'center';
            btn.style.fontFamily = 'inherit';

            // Hover effect
            btn.addEventListener('mouseover', () => btn.style.color = 'var(--gray-800, #1f2937)');
            btn.addEventListener('mouseout', () => btn.style.color = 'var(--gray-500, #6b7280)');

            const updateBtnText = () => {
                const remaining = total - currentIndex;
                if (currentIndex === 0) {
                    btn.innerHTML = `<span style="margin-right:0.5rem; letter-spacing:-1px;">——</span> Lihat balasan (${remaining})`;
                } else {
                    btn.innerHTML = `<span style="margin-right:0.5rem; letter-spacing:-1px;">——</span> Lihat balasan lainnya (${remaining})`;
                }
            };

            updateBtnText();

            btn.addEventListener('click', function() {
                const nextIndex = currentIndex + NESTED_LIMIT;
                for (let i = currentIndex; i < Math.min(nextIndex, total); i++) {
                    items[i].style.display = '';
                }
                currentIndex = nextIndex;
                
                if (currentIndex >= total) {
                    btnWrapper.remove();
                } else {
                    updateBtnText();
                    // Pindahkan tombol tepat setelah balasan terakhir yang ditampilkan
                    // (yaitu sebelum elemen balasan yang masih tersembunyi)
                    container.insertBefore(btnWrapper, items[currentIndex]);
                }
            });

            btnWrapper.appendChild(btn);
            // Masukkan tombol di awal kontainer balasan (karena belum ada yang tampil)
            container.insertBefore(btnWrapper, items[0] || container.firstChild);
        }

        // Terapkan ke komentar utama
        const commentList = document.getElementById('comment-list');
        if (commentList) {
            createRootLoadMoreBtn(commentList);
        }

        // Terapkan ke setiap blok balasan bersarang (nested)
        document.querySelectorAll('.comment-replies').forEach(function(repliesContainer) {
            createNestedLoadMoreBtn(repliesContainer);
        });
    });

    // Share button
    document.querySelectorAll('.btn-share').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const url   = this.dataset.url;
            const title = this.dataset.title;
            if (navigator.share) {
                navigator.share({ title, url }).catch(() => {});
            } else {
                navigator.clipboard.writeText(url).then(() => {
                    const orig = this.innerHTML;
                    this.innerHTML = `<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg> Disalin!`;
                    setTimeout(() => { this.innerHTML = orig; }, 2000);
                });
            }
        });
    });

    // Bookmark AJAX
    document.querySelectorAll('.btn-bookmark').forEach(function(btn) {
        btn.addEventListener('click', async function() {
            try {
                const resp = await fetch(this.dataset.action, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        thread_id:  this.dataset.threadId,
                        csrf_token: '<?= generate_csrf() ?>'
                    })
                });
                const data = await resp.json();
                if (data.bookmarked) {
                    this.classList.add('bookmarked');
                    this.innerHTML = `<svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="2"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg> Tersimpan`;
                } else {
                    this.classList.remove('bookmarked');
                    this.innerHTML = `<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg> Simpan`;
                }
            } catch(e) {}
        });
    });

    // Klik di luar modal report → tutup
    document.getElementById('report-modal')?.addEventListener('click', function(e) {
        if (e.target === this) this.style.display = 'none';
    });
    </script>
</body>
</html>
