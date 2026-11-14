</main>
<?php if (function_exists('isAdmin') && !isAdmin()) { include __DIR__ . '/preview_drawer.php'; } ?>
<script src="../assets/ui.js?v=2"></script>

<script>

    const menuButton = document.getElementById("menuButton");
    const sidebarOverlay = document.getElementById("sidebarOverlay");

    // The menu button: on a wide screen it collapses the sidebar to icons (so a wide table or class sheet gets the room); on a narrow one it slides it in.
    try { if (localStorage.getItem("sidebarCollapsed") === "1") document.body.classList.add("sidebar-collapsed"); } catch (e) {}
    menuButton.addEventListener("click", function () {
        if (window.innerWidth >= 1000) {
            var c = document.body.classList.toggle("sidebar-collapsed");
            try { localStorage.setItem("sidebarCollapsed", c ? "1" : "0"); } catch (e) {}
        } else {
            document.body.classList.toggle("sidebar-open");
        }
    });

    sidebarOverlay.addEventListener("click", function () {
        document.body.classList.remove("sidebar-open");
    });

    const profileButton = document.getElementById("profileButton");
    const profileDropdown = document.getElementById("profileDropdown");
    // The notification bell doesn't render at all for Admin (no student-data
    // access), so these can be null — every use below is null-guarded.
    const notifButton = document.getElementById("notifButton");
    const notifDropdown = document.getElementById("notifDropdown");

    profileButton.addEventListener("click", function (event) {
        event.stopPropagation();
        if (notifDropdown) notifDropdown.classList.remove("show");
        profileDropdown.classList.toggle("show");
    });

    if (notifButton && notifDropdown) {
        notifButton.addEventListener("click", function (event) {
            event.stopPropagation();
            profileDropdown.classList.remove("show");
            notifDropdown.classList.toggle("show");
        });
    }

    document.addEventListener("click", function (event) {
        if (!profileDropdown.contains(event.target) && !profileButton.contains(event.target)) {
            profileDropdown.classList.remove("show");
        }
        if (notifDropdown && !notifDropdown.contains(event.target) && !notifButton.contains(event.target)) {
            notifDropdown.classList.remove("show");
        }
    });


</script>
<script>
// the reason of an excused absence: point at an E (or tap it) and the reason is shown, on every page
(function () {
    var tip = document.createElement('div'); tip.id = 'amTip'; tip.className = 'am-tip'; tip.hidden = true; document.body.appendChild(tip);
    function show(td) { var n = td.getAttribute('data-note'); if (!n) return; tip.textContent = 'Excused — ' + n; tip.hidden = false; var r = td.getBoundingClientRect(); var w = tip.offsetWidth; var x = Math.max(8, Math.min(window.innerWidth - w - 8, r.left + r.width / 2 - w / 2)); tip.style.left = x + 'px'; tip.style.top = (r.bottom + 8 + window.scrollY - window.scrollY) + 'px'; }
    function hide() { tip.hidden = true; }
    document.addEventListener('mouseover', function (e) { var td = e.target.closest && e.target.closest('.am-c[data-note]'); if (td) show(td); });
    document.addEventListener('mouseout', function (e) { if (e.target.closest && e.target.closest('.am-c[data-note]')) hide(); });
    document.addEventListener('click', function (e) { var td = e.target.closest && e.target.closest('.am-c[data-note]'); if (td) { show(td); setTimeout(hide, 3500); } else { hide(); } });
    window.addEventListener('scroll', hide, true);
})();
</script>
<script src="../assets/table-tools.js?v=1"></script>

