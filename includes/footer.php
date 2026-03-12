 </main>
 <footer class="footer-principal">
     <div class="footer-contenu">
         <p>Manchester City Stats &copy; <?php echo date('Y'); ?></p>
         <p class="footer-slogan">Together, we rise.</p>
         <div class="footer-logos">
             <div style="
                    display:inline-flex;
                    align-items:center;
                    gap:0.3rem;
                    background:rgba(255,255,255,0.05);
                    border:1px solid rgba(255,255,255,0.08);
                    border-radius:50px;
                    padding:0.4rem 0.5rem;">
                 <a href="https://www.mancity.com" target="_blank" style="
                        display:inline-flex;
                        align-items:center;
                        justify-content:center;
                        width:35px;
                        height:35px;
                        border-radius:50px;
                        transition:background 0.3s ease;"
                     onmouseover="this.style.background='rgba(108,171,221,0.15)'"
                     onmouseout="this.style.background='transparent'">
                     <img src="https://upload.wikimedia.org/wikipedia/en/e/eb/Manchester_City_FC_badge.svg"
                         alt="Man City" width="35" height="35">
                 </a>
                 <a href="https://www.premierleague.com" target="_blank" style="
                        display:inline-flex;
                        align-items:center;
                        justify-content:center;
                        width:35px;
                        height:35px;
                        border-radius:50%;
                        background-color: #38003c;
                        overflow: hidden;
                        transition:background 0.3s ease;"
                     onmouseover="this.style.background='rgba(108,171,221,0.15)'"
                     onmouseout="this.style.background='#38003c'">
                     <img src="/ProjetUnivers/uploads/premiereleague.ico"
                         alt="Premier League" width="35" height="35" style="object-fit: cover;">
                 </a>
             </div>
         </div>
     </div>
 </footer>

<!-- Toast Container -->
<div id="toast-container" class="toast-container"></div>

<?php if (isset($_SESSION['toast'])): ?>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        showToast("<?php echo $_SESSION['toast']['message']; ?>", "<?php echo $_SESSION['toast']['type']; ?>");
    });
</script>
<?php unset($_SESSION['toast']); endif; ?>

<script>
function showToast(message, type = 'info') {
    const container = document.getElementById('toast-container');
    if (!container) return;

    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    
    let icon = 'ℹ️';
    if(type === 'success') icon = '✅';
    if(type === 'error') icon = '❌';

    toast.innerHTML = `<span>${icon}</span> <span>${message}</span>`;
    container.appendChild(toast);

    setTimeout(() => {
        toast.style.animation = 'toastOut 0.5s ease forwards';
        setTimeout(() => toast.remove(), 500);
    }, 4000);
}

const themeBtn = document.getElementById('theme-btn');
const themeIcon = document.getElementById('theme-icon');
const html = document.documentElement;

const savedTheme = localStorage.getItem('theme') || 'dark';
html.setAttribute('data-theme', savedTheme);
updateThemeIcon(savedTheme);

if (themeBtn) {
    themeBtn.addEventListener('click', () => {
        const currentTheme = html.getAttribute('data-theme');
        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
        html.setAttribute('data-theme', newTheme);
        if (newTheme === 'light') {
            html.classList.add('entering-light');
            setTimeout(() => html.classList.remove('entering-light'), 750);
        }
        localStorage.setItem('theme', newTheme);
        updateThemeIcon(newTheme);
        showToast(`Mode ${newTheme === 'dark' ? 'Sombre' : 'Clair'} activé`, 'info');
    });
}

function updateThemeIcon(theme) {
    if (!themeIcon) return;
    if (theme === 'dark') {
        themeIcon.innerText = '🌙';
    } else {
        themeIcon.innerText = '☀️';
    }
}

(function () {
    if (!('IntersectionObserver' in window)) return;

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.06, rootMargin: '0px 0px -20px 0px' });

    const sel = '.titre-page, .sous-titre-page, .search-bar-wrapper, ' +
                '.stat-card, .carte, .tableau-container, ' +
                '.form-card, .profil-grande-bulle, .profil-bulle, ' +
                '.pagination, .player-stats-header';

    document.querySelectorAll(sel).forEach(el => el.classList.add('animate-in'));

    document.querySelectorAll('.grille-stats, .profil-bulles-container').forEach(grid => {
        Array.from(grid.children).forEach((child, i) => {
            if (child.classList.contains('animate-in')) {
                child.style.transitionDelay = (i * 0.07) + 's';
            }
        });
    });

    document.querySelectorAll(sel).forEach(el => observer.observe(el));
})();

</script>

 </body>

 </html>