		<!-- Show buttons -->
		<div class="div3">
		<?php
		$i = 0;
		foreach ($menus as $menu) {
			$i++;
			$shortcutBadge = ($i <= 12 ? '<span class="takepos-shortcut-badge" title="F'.$i.'">F'.$i.'</span>' : '');
			$buttonId = 'action'.$i;
			$actionKey = !empty($menu['id']) ? (string) $menu['id'] : '';
			$actionAttr = $actionKey !== '' ? ' data-takepos-action-id="'.dol_escape_htmltag($actionKey).'"' : '';
			echo '<button style="'.(empty($menu['style']) ? '' : $menu['style']).'" type="button" id="'.$buttonId.'"'.$actionAttr.' class="actionbutton" onclick="'.(empty($menu['action']) ? '' : $menu['action']).'">'.$shortcutBadge.$menu['title'].'</button>';
		}

		if (getDolGlobalString('TAKEPOS_HIDE_HEAD_BAR') && !getDolGlobalString('TAKEPOS_HIDE_SEARCH')) {
			print '<!-- Show the search input text -->'."\n";
			print '<div class="margintoponly">';
			print '<input type="text" id="search" class="input-search-takepos input-nobottom" name="search" onkeyup="Search2(\''.dol_escape_js($keyCodeForEnter).'\', null, event);" style="width: 80%; width:calc(100% - 51px); font-size: 150%;" placeholder="'.dol_escape_htmltag($langs->trans("Search")).'" autofocus> ';
			print '<button type="button" class="button marginleftonly hideonsmartphone" onclick="Search2(\''.dol_escape_js($keyCodeForEnter).'\', null, event);">'.dol_escape_htmltag($langs->trans('Search')).'</button>'; print '<a href="#" class="marginleftonly hideonsmartphone takepos-search-clear" onclick="return ClearSearch(false, event);">'.img_picto('', 'searchclear').'</a>';
			print '</div>';
		}
		?>
		</div>
