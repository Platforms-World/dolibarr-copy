<?php if ($productStudioEnabled) { ?>
    <button type="button" id="takepos-shortcuts-launcher"
            onclick="toggleTakeposShortcutsDrawer();"
            aria-controls="takepos-shortcuts-drawer"
            aria-expanded="false">
        <span class="fa fa-layer-group"></span>
        <span><?php echo dol_escape_htmltag($langs->trans('TakeposShortcutsLauncher')); ?></span>
    </button>

    <aside id="takepos-shortcuts-drawer"
           aria-label="<?php echo dol_escape_htmltag($langs->trans('TakeposShortcutsTitle')); ?>"
           aria-hidden="true">

        <div class="takepos-shortcuts-head">
            <div class="takepos-shortcuts-title"><?php echo dol_escape_htmltag($langs->trans('TakeposShortcutsTitle')); ?></div>
            <button type="button" id="takepos-shortcuts-close"
                    onclick="closeTakeposShortcutsDrawer();"
                    aria-label="<?php echo dol_escape_htmltag($langs->trans('TakeposShortcutsClose')); ?>">&times;</button>
        </div>

        <div class="takepos-shortcuts-search">
            <input type="text"
                   id="takepos-shortcuts-search-input"
                   placeholder="<?php echo dol_escape_htmltag($langs->trans('TakeposShortcutsSearch', 'Search shortcuts...')); ?>"
                   autocomplete="off"
                   oninput="filterTakeposShortcuts(this.value);">
            <span class="takepos-shortcuts-search-icon">&#128269;</span>
        </div>

        <div id="takepos-shortcuts-panel">
            <?php foreach ($shortcutUiSections as $sectionCode => $sectionMeta) { ?>
                <?php if (empty($shortcutsByUiSection[$sectionCode])) { continue; } ?>
                <?php $sectionCollapsed = empty($sectionMeta['default_open']); ?>
                <div id="takepos-shortcut-section-<?php echo dol_escape_htmltag($sectionCode); ?>"
                     class="takepos-shortcut-section<?php echo $sectionCollapsed ? ' is-collapsed' : ''; ?>">

                    <button type="button"
                            class="takepos-shortcut-header"
                            onclick="toggleShortcutSection('<?php echo dol_escape_js($sectionCode); ?>');"
                            aria-expanded="<?php echo $sectionCollapsed ? 'false' : 'true'; ?>">
                        <span><?php echo dol_escape_htmltag($sectionMeta['label']); ?></span>
                        <span class="fa fa-chevron-down chevron" aria-hidden="true"></span>
                    </button>

                    <div class="takepos-shortcut-body">
                        <?php foreach ($shortcutsByUiSection[$sectionCode] as $item) { ?>
                            <a onclick="openWorkspaceShortcut(<?php echo (int) $item['index']; ?>); return false;"
                               class="nohover takepos-shortcut-link"
                               href="#"
                               title="<?php echo dol_escape_htmltag($item['label']); ?>"
                               aria-label="<?php echo dol_escape_htmltag($item['label']); ?>">
                            <span class="takepos-shortcut-icon" aria-hidden="true">
                                <span class="<?php echo dol_escape_htmltag($item['icon']); ?>"></span>
                            </span>
                                <span class="takepos-shortcut-text"><?php echo dol_escape_htmltag($item['label']); ?></span>
                            </a>
                        <?php } ?>
                    </div>
                </div>
            <?php } ?>
        </div>
    </aside>

    <script>
        (function () {
            /* ── Accordion: open one section, close all others ────────────────────── */
            window.toggleShortcutSection = function (code) {
                var allSections = document.querySelectorAll('#takepos-shortcuts-panel .takepos-shortcut-section');
                var target = document.getElementById('takepos-shortcut-section-' + code);
                if (!target) return;

                var isAlreadyOpen = !target.classList.contains('is-collapsed');

                /* Close every section first */
                allSections.forEach(function (sec) {
                    sec.classList.add('is-collapsed');
                    var btn = sec.querySelector('.takepos-shortcut-header');
                    if (btn) btn.setAttribute('aria-expanded', 'false');
                });

                /* If the clicked one was closed, open it */
                if (isAlreadyOpen) {
                    /* clicking an open section just closes it — all stay closed */
                } else {
                    target.classList.remove('is-collapsed');
                    var targetBtn = target.querySelector('.takepos-shortcut-header');
                    if (targetBtn) targetBtn.setAttribute('aria-expanded', 'true');

                    /* Scroll the opened section into view inside the panel */
                    setTimeout(function () {
                        target.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                    }, 50);
                }
            };

            /* ── Search: expand matching sections, collapse empty ones ────────────── */
            window.filterTakeposShortcuts = function (query) {
                var q = (query || '').trim().toLowerCase();
                var panel = document.getElementById('takepos-shortcuts-panel');
                if (!panel) return;

                if (q === '') {
                    /* Reset: restore original collapsed state */
                    panel.querySelectorAll('.takepos-shortcut-section').forEach(function (sec) {
                        sec.querySelectorAll('.takepos-shortcut-link').forEach(function (lnk) {
                            lnk.classList.remove('tpv2-hidden');
                        });
                        sec.classList.remove('tpv2-section-hidden');
                    });
                    return;
                }

                /* Close all first, then open sections that have matches */
                var firstMatch = null;
                panel.querySelectorAll('.takepos-shortcut-section').forEach(function (sec) {
                    var links = sec.querySelectorAll('.takepos-shortcut-link');
                    var anyVisible = false;
                    links.forEach(function (lnk) {
                        var label = (lnk.getAttribute('title') || lnk.textContent || '').toLowerCase();
                        var hide = label.indexOf(q) === -1;
                        lnk.classList.toggle('tpv2-hidden', hide);
                        if (!hide) anyVisible = true;
                    });
                    if (anyVisible) {
                        sec.classList.remove('is-collapsed', 'tpv2-section-hidden');
                        var btn = sec.querySelector('.takepos-shortcut-header');
                        if (btn) btn.setAttribute('aria-expanded', 'true');
                        if (!firstMatch) firstMatch = sec;
                    } else {
                        sec.classList.add('is-collapsed', 'tpv2-section-hidden');
                    }
                });
            };
        })();
    </script>
<?php } ?>