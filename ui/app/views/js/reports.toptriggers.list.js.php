<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


/**
 * @var CView $this
 */
?>

<script type="text/x-jquery-tmpl" id="filter-tag-row-tmpl">
	<?= CTagFilterFieldHelper::getTemplate() ?>
</script>

<script>
	const view = new class {

		init({timeline}) {
			this.#initTagFilter();

			timeControl.addObject('toptriggers', timeline, {
				id: 'timeline_1',
				domid: 'toptriggers',
				loadSBox: 0,
				loadImage: 0,
				dynamic: 0
			});

			timeControl.processObjects();
		}

		#initTagFilter() {
			jQuery('#filter-tags')
				.dynamicRows({template: '#filter-tag-row-tmpl'})
				.on('afteradd.dynamicRows', function () {
					const rows = this.querySelectorAll('.form_row');

					new CTagFilterItem(rows[rows.length - 1]);
				});

			for (const row of document.querySelectorAll('#filter-tags .form_row')) {
				new CTagFilterItem(row);
			}
		}

		editHost(hostid) {
			const host_data = {hostid};

			this.#openHostPopup(host_data);
		}

		#openHostPopup(host_data) {
			const original_url = location.href;
			const overlay = PopUp('popup.host.edit', host_data, {
				dialogueid: 'host_edit',
				dialogue_class: 'modal-popup-large',
				prevent_navigation: true
			});

			overlay.$dialogue[0].addEventListener('dialogue.create', (e) => this.#reload(e.detail.success));
			overlay.$dialogue[0].addEventListener('dialogue.update', (e) => this.#reload(e.detail.success));
			overlay.$dialogue[0].addEventListener('dialogue.delete', (e) => this.#reload(e.detail.success));
			overlay.$dialogue[0].addEventListener('overlay.close', () => {
				history.replaceState({}, '', original_url);
			});
		}

		#reload(success) {
			postMessageOk(success.title);

			if ('messages' in success) {
				postMessageDetails('success', success.messages);
			}

			location.href = location.href;
		}
	};
</script>
