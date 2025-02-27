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


/*
 * Widget view modes: whether to display the header statically or on mouse hovering (configurable on the widget form).
 */

const ZBX_WIDGET_VIEW_MODE_NORMAL = 0;
const ZBX_WIDGET_VIEW_MODE_HIDDEN_HEADER = 1;

/*
 * Widget states, managed by the dashboard page.
 */

// Initial state of widget: the widget has never yet been displayed on the dashboard page.
const WIDGET_STATE_INITIAL = 'initial';

// Active state of widget: the widget is being displayed on the active dashboard page and is updating periodically.
const WIDGET_STATE_ACTIVE = 'active';

// Inactive state of widget: the widget has been active recently, but is currently hidden on an inactive dashboard page.
const WIDGET_STATE_INACTIVE = 'inactive';

// Destroyed state of widget: the widget has been deleted from the dashboard page.
const WIDGET_STATE_DESTROYED = 'destroyed';

/*
 * Events thrown by widgets to inform the dashboard page about user interaction with the widget, which may impact the
 * dashboard page and other widgets.
 */

// Widget edit event: informs the dashboard page to enter the editing mode.
const WIDGET_EVENT_EDIT = 'widget-edit';

// Widget actions event: informs the dashboard page to display the widget actions popup menu.
const WIDGET_EVENT_ACTIONS = 'widget-actions';

// Widget enter event: informs the dashboard page to focus the widget and un-focus other widgets.
const WIDGET_EVENT_ENTER = 'widget-enter';

// Widget leave event: informs the dashboard page to un-focus the widget.
const WIDGET_EVENT_LEAVE = 'widget-leave';

// Widget before-update event: thrown by a widget immediately before the update cycle has started.
const WIDGET_EVENT_BEFORE_UPDATE = 'widget-before-update';

// Widget after-update event: thrown by a widget immediately after the update cycle has finished.
const WIDGET_EVENT_AFTER_UPDATE = 'widget-after-update';

// Widget copy event: informs the dashboard page to copy the widget to the local storage.
const WIDGET_EVENT_COPY = 'widget-copy';

// Widget paste event: informs the dashboard page to paste the stored widget over the current one.
const WIDGET_EVENT_PASTE = 'widget-paste';

// Widget delete event: informs the dashboard page to delete the widget.
const WIDGET_EVENT_DELETE = 'widget-delete';

/*
 * The base class of all dashboard widgets. Depending on widget needs, it can be instantiated directly or be extended.
 */
class CWidgetBase {

	/**
	 * Widget constructor. Invoked by a dashboard page.
	 *
	 * @param {string}      type                Widget type ("id" field of the manifest.json).
	 * @param {string}      name                Widget name to display in the header.
	 * @param {number}      view_mode           One of ZBX_WIDGET_VIEW_MODE_NORMAL, ZBX_WIDGET_VIEW_MODE_HIDDEN_HEADER.
	 * @param {Object}      fields              Widget field values (widget configuration data).
	 *
	 * @param {Object}      defaults            Widget type defaults.
	 *        {string}      defaults.name           Default name to display in the header, if no custom name given.
	 *        {Object}      defaults.size           Default size to use when creating new widgets.
	 *        {number}      defaults.size.width     Default width.
	 *        {number}      defaults.size.height    Default height
	 *        {string}      defaults.js_class       JavaScript class name.
	 *
	 * @param {string|null} widgetid            Widget ID stored in the database, or null for new widgets.
	 *
	 * @param {Object|null} pos                 Position and size of the widget (in dashboard coordinates).
	 *        {number}      pos.x               Horizontal position.
	 *        {number}      pos.y               Vertical position.
	 *        {number}      pos.width           Widget width.
	 *        {number}      pos.height          Widget height.
	 *
	 * @param {boolean}     is_new              Create a visual zoom effect when adding new widgets.
	 * @param {number}      rf_rate             Update cycle rate (refresh rate) in seconds. Supported values: 0 (no
	 *                                          refresh), 10, 30, 60, 120, 600 or 900 seconds.
	 * @param {Object}      dashboard           Essential data of the dashboard object.
	 *        {string|null} dashboard.dashboardid   Dashboard ID.
	 *        {string|null} dashboard.templateid    Template ID (used for template and host dashboards).
	 *
	 * @param {Object}      dashboard_page      Essential data of the dashboard page object.
	 *        {string}      dashboard_page.unique_id  Run-time, unique ID of the dashboard page.
	 *
	 * @param {number}      cell_width          Dashboard page cell width in percentage.
	 * @param {number}      cell_height         Dashboard page cell height in pixels.
	 * @param {number}      min_rows            Minimum number of dashboard cell rows per single widget.
	 * @param {boolean}     is_editable         Whether to display the "Edit" button.
	 * @param {boolean}     is_edit_mode        Whether the widget is being created in the editing mode.
	 * @param {boolean}     can_edit_dashboards Whether the user has access to creating and editing dashboards.
	 *
	 * @param {Object|null} time_period         Selected time period (if widget.use_time_selector in manifest.json is
	 *                                          set to true in any of the loaded widgets), or null.
	 *        {string}      time_period.from    Relative time of period start (like "now-1h").
	 *        {number}      time_period.from_ts Timestamp of period start.
	 *        {string}      time_period.to      Relative time of period end (like "now").
	 *        {number}      time_period.to_ts   Timestamp of period end.
	 *
	 * @param {string|null}	dynamic_hostid      ID of the dynamically selected host on a dashboard (if any of the
	 *                                          widgets has the "dynamic" checkbox field configured and checked in the
	 *                                          widget configuration), or null.
	 * @param {string|null} csrf_token          CSRF token for AJAX requests.
	 * @param {string}      unique_id           Run-time, unique ID of the widget.
	 */
	constructor({
		type,
		name = '',
		view_mode,
		fields,
		defaults,
		widgetid = null,
		pos = null,
		is_new,
		rf_rate,
		dashboard,
		dashboard_page,
		cell_width,
		cell_height,
		min_rows,
		is_editable,
		is_edit_mode,
		can_edit_dashboards,
		time_period,
		dynamic_hostid,
		csrf_token = null,
		unique_id
	}) {
		this._target = document.createElement('div');

		this._type = type;
		this._name = name;
		this._view_mode = view_mode;
		this._fields = fields;
		this._defaults = defaults;
		this._widgetid = widgetid;
		this._pos = pos;
		this._is_new = is_new;
		this._rf_rate = rf_rate;

		this._dashboard = {
			templateid: dashboard.templateid,
			dashboardid: dashboard.dashboardid
		};

		this._dashboard_page = {
			unique_id: dashboard_page.unique_id
		};

		this._cell_width = cell_width;
		this._cell_height = cell_height;
		this._min_rows = min_rows;
		this._is_editable = is_editable;
		this._is_edit_mode = is_edit_mode;
		this._can_edit_dashboards = can_edit_dashboards;
		this._time_period = time_period;
		this._dynamic_hostid = dynamic_hostid;
		this._csrf_token = csrf_token;
		this._unique_id = unique_id;

		this._init();
	}

	/**
	 * Define initial data. Invoked once, upon instantiation.
	 */
	_init() {
		this._css_classes = {
			actions: 'dashboard-grid-widget-actions',
			container: 'dashboard-grid-widget-container',
			contents: 'dashboard-grid-widget-contents',
			messages: 'dashboard-grid-widget-messages',
			body: 'dashboard-grid-widget-body',
			debug: 'dashboard-grid-widget-debug',
			focus: 'dashboard-grid-widget-focus',
			header: 'dashboard-grid-widget-header',
			hidden_header: 'dashboard-grid-widget-hidden-header',
			mask: 'dashboard-grid-widget-mask',
			root: 'dashboard-grid-widget',
			resize_handle: 'ui-resizable-handle'
		};

		this._state = WIDGET_STATE_INITIAL;

		this._contents_size	= {};
		this._update_timeout_id = null;
		this._update_interval_id = null;
		this._update_abort_controller = null;
		this._is_updating_paused = false;
		this._update_retry_sec = 3;
		this._show_preloader_asap = true;
		this._resizable_handles = [];

		this._hide_preloader_animation_frame = null;

		this._events = {};

		this.onInitialize();
	}

	/**
	 * Stub method redefined in class.widget.js.
	 */
	onInitialize() {
	}

	/**
	 * Get current state.
	 *
	 * @returns {string}  WIDGET_STATE_INITIAL | WIDGET_STATE_INACTIVE | WIDGET_STATE_ACTIVE | WIDGET_STATE_DESTROYED.
	 */
	getState() {
		return this._state;
	}

	// Logical state control methods.

	/**
	 * Create widget view (HTML objects). Invoked once, before the first activation of the dashboard page.
	 */
	start() {
		if (this._state !== WIDGET_STATE_INITIAL) {
			throw new Error('Unsupported state change.');
		}

		this._state = WIDGET_STATE_INACTIVE;

		this._makeView();

		if (this._pos !== null) {
			this.setPos(this._pos);
		}

		this._registerEvents();

		this.onStart();
	}

	/**
	 * Stub method redefined in class.widget.js.
	 */
	onStart() {
	}

	/**
	 * Start processing DOM events and start updating immediately. Invoked on each activation of the dashboard page.
	 */
	activate() {
		if (this._state !== WIDGET_STATE_INACTIVE) {
			throw new Error('Unsupported state change.');
		}

		this._state = WIDGET_STATE_ACTIVE;

		this.onActivate();

		this._activateEvents();
		this._startUpdating();
	}

	/**
	 * Stub method redefined in class.widget.js.
	 */
	onActivate() {
	}

	/**
	 * Stop processing DOM events and stop updating immediately. Invoked on each deactivation of the dashboard page.
	 */
	deactivate() {
		if (this._state !== WIDGET_STATE_ACTIVE) {
			throw new Error('Unsupported state change.');
		}

		this._state = WIDGET_STATE_INACTIVE;

		this.onDeactivate();

		if (this._is_new) {
			this._is_new = false;
			this._target.classList.remove('new-widget');
		}

		this._deactivateEvents();
		this._stopUpdating();
	}

	/**
	 * Stub method redefined in class.widget.js.
	 */
	onDeactivate() {
	}

	/**
	 * Destroy the widget which has already been started.
	 *
	 * Invoked once, when the widget or the dashboard page gets deleted.
	 */
	destroy() {
		if (this._state === WIDGET_STATE_ACTIVE) {
			this.deactivate();
		}

		if (this._state !== WIDGET_STATE_INACTIVE) {
			throw new Error('Unsupported state change.');
		}

		this._state = WIDGET_STATE_DESTROYED;

		this.onDestroy();
	}

	/**
	 * Stub method redefined in class.widget.js.
	 */
	onDestroy() {
	}

	// External events management methods.

	/**
	 * Check whether the widget is in editing mode.
	 *
	 * @returns {boolean}
	 */
	isEditMode() {
		return this._is_edit_mode;
	}

	/**
	 * Set widget to editing mode. This is one-way action.
	 */
	setEditMode() {
		this._is_edit_mode = true;

		if (this._state === WIDGET_STATE_ACTIVE) {
			this._stopUpdating({do_abort: false});
		}

		this._target.classList.add('ui-draggable', 'ui-resizable');

		this.onEdit();
	}

	/**
	 * Stub method redefined in class.widget.js.
	 */
	onEdit() {
	}

	/**
	 * Check whether the widget supports dynamic hosts (overriding the host selected in the configuration).
	 *
	 * The host selection control will be displayed on the dashboard, if any of the loaded widgets has such support.
	 *
	 * @returns {boolean}
	 */
	supportsDynamicHosts() {
		return this._fields.dynamic === '1';
	}

	/**
	 * Get the dynamic host currently in use. Invoked if the widget supports dynamic hosts.
	 *
	 * @returns {string|null}
	 */
	getDynamicHost() {
		return this._dynamic_hostid;
	}

	/**
	 * Set the dynamic host. Invoked if the widget supports dynamic hosts.
	 *
	 * @param {string|null} dynamic_hostid
	 */
	setDynamicHost(dynamic_hostid) {
		this._dynamic_hostid = dynamic_hostid;

		if (this._state === WIDGET_STATE_ACTIVE) {
			this._startUpdating();
		}
	}

	/**
	 * Set the time period selected in the time selector of the dashboard.
	 *
	 * @param {Object|null} time_period  Selected time period (if widget.use_time_selector in manifest.json is set to
	 *                                   true in any of the loaded widgets), or null.
	 *        {string}      time_period.from     Relative time of period start (like "now-1h").
	 *        {number}      time_period.from_ts  Timestamp of period start.
	 *        {string}      time_period.to       Relative time of period end (like "now").
	 *        {number}      time_period.to_ts    Timestamp of period end.
	 */
	setTimePeriod(time_period) {
		this._time_period = time_period;
	}

	/**
	 * Find whether the widget is currently entered (focused) my mouse or keyboard. Only one widget can be entered at a
	 * time.
	 *
	 * @returns {boolean}
	 */
	isEntered() {
		return this._target.classList.contains(this._css_classes.focus);
	}

	/**
	 * Enter (focus) the widget. Caused by mouse hovering or keyboard navigation. Only one widget can be entered at a
	 * time.
	 */
	enter() {
		if (this._is_edit_mode) {
			this._addResizeHandles();
		}

		this._target.classList.add(this._css_classes.focus);
	}

	/**
	 * Remove focus from the widget. Caused by mouse hovering or keyboard navigation.
	 */
	leave() {
		if (this._is_edit_mode) {
			this._removeResizeHandles();
		}

		if (this._header.contains(document.activeElement)) {
			document.activeElement.blur();
		}

		this._target.classList.remove(this._css_classes.focus);
	}

	/**
	 * Get number of header lines the widget displays when focused.
	 *
	 * @returns {number}
	 */
	getNumHeaderLines() {
		return this._view_mode === ZBX_WIDGET_VIEW_MODE_HIDDEN_HEADER ? 1 : 0;
	}

	/**
	 * Is widget currently being resized?
	 *
	 * @returns {boolean}
	 */
	_isResizing() {
		return this._target.classList.contains('ui-resizable-resizing');
	}

	/**
	 * Set widget resizing state.
	 *
	 * @param {boolean} is_resizing
	 */
	setResizing(is_resizing) {
		this._target.classList.toggle('ui-resizable-resizing', is_resizing);
	}

	/**
	 * Is widget currently being dragged?
	 *
	 * @returns {boolean}
	 */
	_isDragging() {
		return this._target.classList.contains('ui-draggable-dragging');
	}

	/**
	 * Set widget dragging state.
	 *
	 * @param {boolean} is_dragging
	 */
	setDragging(is_dragging) {
		this._target.classList.toggle('ui-draggable-dragging', is_dragging);
	}

	/**
	 * Are there context menus open or hints displayed for the widget?
	 *
	 * @returns {boolean}
	 */
	isUserInteracting() {
		return this._target
			.querySelectorAll('[data-expanded="true"], [aria-expanded="true"][aria-haspopup="true"]').length > 0;
	}

	/**
	 * Take whatever action is required on each resize event of the widget contents' container.
	 */
	resize() {
		this.onResize();
	}

	/**
	 * Stub method redefined in class.widget.js.
	 */
	onResize() {
	}

	// Data interface methods.

	/**
	 * Get the unique ID of the widget (runtime, dynamically generated).
	 *
	 * @returns {string}
	 */
	getUniqueId() {
		return this._unique_id;
	}

	/**
	 * Get the widget type ("id" field of the manifest.json).
	 *
	 * @returns {string}
	 */
	getType() {
		return this._type;
	}

	/**
	 * Get custom widget name (can be empty).
	 *
	 * @returns {string}
	 */
	getName() {
		return this._name;
	}

	/**
	 * Set custom widget name and, if not empty, display it in the header. Otherwise, display the default name.
	 *
	 * @param {string} name
	 */
	_setName(name) {
		this._name = name;
		this._setHeaderName(this._name !== '' ? this._name : this._defaults.name);
	}

	/**
	 * Get widget name to be displayed in the header (either custom, if not empty, or the default one).
	 *
	 * @returns {string}
	 */
	getHeaderName() {
		return this._name !== '' ? this._name : this._defaults.name;
	}

	/**
	 * Display the specified widget name in the header.
	 *
	 * @param {string} name
	 */
	_setHeaderName(name) {
		if (this._state !== WIDGET_STATE_INITIAL) {
			this._header.querySelector('h4').textContent = name;
		}
	}

	// Data interface methods.

	/**
	 * Check if widget header is set to be always displayed or displayed only when the widget is entered (focused).
	 *
	 * @returns {number}  One of ZBX_WIDGET_VIEW_MODE_HIDDEN_HEADER, ZBX_WIDGET_VIEW_MODE_NORMAL.
	 */
	getViewMode() {
		return this._view_mode;
	}

	/**
	 * Set widget header to be either always displayed or displayed only when the widget is entered (focused).
	 *
	 * @param {number} view_mode  One of ZBX_WIDGET_VIEW_MODE_HIDDEN_HEADER, ZBX_WIDGET_VIEW_MODE_NORMAL.
	 */
	_setViewMode(view_mode) {
		if (this._view_mode !== view_mode) {
			this._view_mode = view_mode;
			this._target.classList.toggle(this._css_classes.hidden_header,
				this._view_mode === ZBX_WIDGET_VIEW_MODE_HIDDEN_HEADER
			);
		}
	}

	/**
	 * Get widget field values (widget configuration data).
	 *
	 * @returns {Object}
	 */
	getFields() {
		return this._fields;
	}

	/**
	 * Set widget field values (widget configuration data).
	 *
	 * @param {Object} fields
	 */
	_setFields(fields) {
		this._fields = fields;
	}

	/**
	 * Get widget ID.
	 *
	 * @returns {string|null}  Widget ID stored in the database, or null for new widgets.
	 */
	getWidgetId() {
		return this._widgetid;
	}

	/**
	 * Stub method redefined in class.widget.js.
	 */
	hasPadding() {
	}

	/**
	 * Update padding of the widget contents' container. Invoked when widget properties have changed.
	 */
	_updatePadding() {
		if (this._state !== WIDGET_STATE_INITIAL) {
			this._contents.classList.toggle('no-padding', !this.hasPadding());
		}
	}

	/**
	 * Update widget properties and start updating immediately.
	 *
	 * @param {string|undefined} name       Widget name to display in the header.
	 * @param {number|undefined} view_mode  One of ZBX_WIDGET_VIEW_MODE_NORMAL, ZBX_WIDGET_VIEW_MODE_HIDDEN_HEADER.
	 * @param {Object|undefined} fields     Widget field values (widget configuration data).
	 */
	updateProperties({name, view_mode, fields}) {
		if (name !== undefined) {
			this._setName(name);
		}

		if (view_mode !== undefined) {
			this._setViewMode(view_mode);
		}

		if (fields !== undefined) {
			this._setFields(fields);
		}

		this._updatePadding();

		this._show_preloader_asap = true;

		if (this._state === WIDGET_STATE_ACTIVE) {
			this._startUpdating();
		}
	}

	/**
	 * Get update cycle rate (refresh rate) in seconds.
	 *
	 * @returns {number}  Supported values: 0 (no refresh), 10, 30, 60, 120, 600 or 900 seconds.
	 */
	getRfRate() {
		return this._rf_rate;
	}

	/**
	 * Set update cycle rate (refresh rate) in seconds.
	 *
	 * @param {number} rf_rate  Supported values: 0 (no refresh), 10, 30, 60, 120, 600 or 900 seconds.
	 */
	_setRfRate(rf_rate) {
		this._rf_rate = rf_rate;

		if (this._widgetid !== null) {
			const curl = new Curl('zabbix.php');

			curl.setArgument('action', 'dashboard.widget.rfrate');
			curl.setArgument('_csrf_token', this._csrf_token);

			fetch(curl.getUrl(), {
				method: 'POST',
				headers: {'Content-Type': 'application/json'},
				body: JSON.stringify({widgetid: this._widgetid, rf_rate})
			})
				.then((response) => response.json())
				.then((response) => {
					if ('error' in response) {
						throw {error: response.error};
					}
				})
				.catch((exception) => {
					console.log('Could not update widget refresh rate:', exception);
				});
		}
	}

	/**
	 * Get widget data for purpose of copying the widget.
	 *
	 * @param {boolean} is_single_copy  Whether copying a single widget or copying a whole dashboard page.
	 *
	 * @returns {Object}
	 */
	getDataCopy({is_single_copy}) {
		const data = {
			type: this._type,
			name: this._name,
			view_mode: this._view_mode,
			fields: this._fields,
			pos: is_single_copy
				? {
					width: this._pos.width,
					height: this._pos.height
				}
				: this._pos,
			rf_rate: this._rf_rate
		};

		if (is_single_copy) {
			data.dashboard = {
				templateid: this._dashboard.templateid
			};
		}

		return data;
	}

	/**
	 * Get widget data for storing it in the database.
	 *
	 * @returns {Object}
	 */
	save() {
		return {
			widgetid: this._widgetid ?? undefined,
			pos: this._pos,
			type: this._type,
			name: this._name,
			view_mode: this._view_mode,
			fields: Object.keys(this._fields).length > 0 ? this._fields : undefined
		};
	}

	/**
	 * Get context menu to display when actions button is clicked.
	 *
	 * @param {boolean} can_paste_widget  Whether a copied widget is ready to be pasted over the current one.
	 *
	 * @returns {Object[]}
	 */
	getActionsContextMenu({can_paste_widget}) {
		let menu = [];
		let menu_actions = [];

		if (this._can_edit_dashboards && (this._dashboard.templateid === null || this._dynamic_hostid === null)) {
			menu_actions.push({
				label: t('Copy'),
				clickCallback: () => this.fire(WIDGET_EVENT_COPY)
			});
		}

		if (this._is_edit_mode) {
			menu_actions.push({
				label: t('Paste'),
				disabled: can_paste_widget === false,
				clickCallback: () => this.fire(WIDGET_EVENT_PASTE)
			});

			menu_actions.push({
				label: t('Delete'),
				clickCallback: () => this.fire(WIDGET_EVENT_DELETE)
			});
		}

		if (menu_actions.length) {
			menu.push({
				label: t('Actions'),
				items: menu_actions
			});
		}

		if (!this._is_edit_mode) {
			const refresh_interval_section = {
				label: t('Refresh interval'),
				items: []
			};

			const rf_rates = new Map([
				[0, t('No refresh')],
				[10, t('10 seconds')],
				[30, t('30 seconds')],
				[60, t('1 minute')],
				[120, t('2 minutes')],
				[600, t('10 minutes')],
				[900, t('15 minutes')]
			]);

			for (const [rf_rate, label] of rf_rates.entries()) {
				refresh_interval_section.items.push({
					label: label,
					selected: rf_rate === this._rf_rate,
					clickCallback: () => {
						this._setRfRate(rf_rate);

						if (this._state === WIDGET_STATE_ACTIVE) {
							if (this._rf_rate > 0) {
								this._startUpdating();
							}
							else {
								this._stopUpdating();
							}
						}
					}
				});
			}

			menu.push(refresh_interval_section);
		}

		return menu;
	}

	// Content updating methods.

	/**
	 * Start updating the widget. Invoked on activation of the widget or when the update is required immediately.
	 *
	 * @param {number}       delay_sec       Delay seconds before the update.
	 * @param {boolean|null} do_update_once  Whether the widget is required to update once.
	 */
	_startUpdating(delay_sec = 0, {do_update_once = null} = {}) {
		if (do_update_once === null) {
			do_update_once = this._is_edit_mode;
		}

		this._stopUpdating({do_abort: false});

		if (delay_sec > 0) {
			this._update_timeout_id = setTimeout(() => {
				this._update_timeout_id = null;
				this._startUpdating(0, {do_update_once});
			}, delay_sec * 1000);
		}
		else {
			if (!do_update_once && this._rf_rate > 0) {
				this._update_interval_id = setInterval(() => {
					this._update(do_update_once);
				}, this._rf_rate * 1000);
			}

			this._update(do_update_once);
		}
	}

	/**
	 * Stop updating the widget. Invoked on deactivation of the widget or when the update is required to restart.
	 *
	 * @param {boolean} do_abort  Whether to abort the active update request.
	 */
	_stopUpdating({do_abort = true} = {}) {
		if (this._update_timeout_id !== null) {
			clearTimeout(this._update_timeout_id);
			this._update_timeout_id = null;
		}

		if (this._update_interval_id !== null) {
			clearInterval(this._update_interval_id);
			this._update_interval_id = null;
		}

		if (do_abort && this._update_abort_controller !== null) {
			this._update_abort_controller.abort();
		}
	}

	/**
	 * Pause updating the widget whether the widget is active.
	 */
	_pauseUpdating() {
		this._is_updating_paused = true;
	}

	/**
	 * Resume updating the widget whether the widget is active.
	 */
	_resumeUpdating() {
		this._is_updating_paused = false;
	}

	/**
	 * Organize the update cycle of the widget.
	 *
	 * @param {boolean} do_update_once  Whether the widget is required to update once.
	 */
	_update(do_update_once) {
		if (this._update_abort_controller !== null || this._is_updating_paused || this.isUserInteracting()) {
			this._startUpdating(1, {do_update_once});

			return;
		}

		this.fire(WIDGET_EVENT_BEFORE_UPDATE);

		this._contents_size = this._getContentsSize();

		this._update_abort_controller = new AbortController();

		if (this._show_preloader_asap) {
			this._show_preloader_asap = false;
			this._showPreloader();
		}
		else {
			this._schedulePreloader();
		}

		new Promise((resolve) => resolve(this.promiseUpdate()))
			.then(() => this._hidePreloader())
			.catch((exception) => {
				console.log('Could not update widget:', exception);

				if (this._update_abort_controller.signal.aborted) {
					this._hidePreloader();
				}
				else {
					this._startUpdating(this._update_retry_sec, {do_update_once});
				}
			})
			.finally(() => {
				this._update_abort_controller = null;

				this.fire(WIDGET_EVENT_AFTER_UPDATE);
			});
	}

	/**
	 * Stub method redefined in class.widget.js.
	 */
	promiseUpdate() {
	}

	// Widget view methods.

	/**
	 * Get main HTML container of the widget.
	 *
	 * @returns {HTMLDivElement}
	 */
	getView() {
		return this._target;
	}

	/**
	 * Get CSS class name for the specified container or state.
	 *
	 * @param {string} name  Container or state name.
	 *
	 * @returns {string}
	 */
	getCssClass(name) {
		return this._css_classes[name];
	}

	/**
	 * Get position and size of the widget (in dashboard coordinates).
	 *
	 * @returns {{x: number, y: number, width: number, height: number}|null}
	 */
	getPos() {
		return this._pos;
	}

	/**
	 * Set size and position the widget on the dashboard page.
	 *
	 * @param {Object} pos         Position and size of the widget (in dashboard coordinates).
	 *        {number} pos.x       Horizontal position.
	 *        {number} pos.y       Vertical position.
	 *        {number} pos.width   Widget width.
	 *        {number} pos.height  Widget height.
	 *
	 * @param {boolean} is_managed  Whether physically setting the position and size is managed from the outside.
	 */
	setPos(pos, {is_managed = false} = {}) {
		this._pos = pos;

		if (!is_managed) {
			this._target.style.left = `${this._cell_width * this._pos.x}%`;
			this._target.style.top = `${this._cell_height * this._pos.y}px`;
			this._target.style.width = `${this._cell_width * this._pos.width}%`;
			this._target.style.height = `${this._cell_height * this._pos.height}px`;
		}
	}

	/**
	 * Calculate which of the four sides are affected by the resize handle.
	 *
	 * @param {HTMLElement} resize_handle  One of eight dots by which the widget can be resized in editing mode.
	 *
	 * @returns {{top: boolean, left: boolean, bottom: boolean, right: boolean}}
	 */
	getResizeHandleSides(resize_handle) {
		return {
			top: resize_handle.classList.contains('ui-resizable-nw')
				|| resize_handle.classList.contains('ui-resizable-n')
				|| resize_handle.classList.contains('ui-resizable-ne'),
			right: resize_handle.classList.contains('ui-resizable-ne')
				|| resize_handle.classList.contains('ui-resizable-e')
				|| resize_handle.classList.contains('ui-resizable-se'),
			bottom: resize_handle.classList.contains('ui-resizable-se')
				|| resize_handle.classList.contains('ui-resizable-s')
				|| resize_handle.classList.contains('ui-resizable-sw'),
			left: resize_handle.classList.contains('ui-resizable-sw')
				|| resize_handle.classList.contains('ui-resizable-w')
				|| resize_handle.classList.contains('ui-resizable-nw')
		};
	}

	/**
	 * Add eight resize handles to the widget by which the widget can be resized in editing mode. Invoked when the
	 * widget is entered (focused).
	 */
	_addResizeHandles() {
		this._resizable_handles = {};

		for (const direction of ['n', 'e', 's', 'w', 'ne', 'se', 'sw', 'nw']) {
			const resizable_handle = document.createElement('div');

			resizable_handle.classList.add('ui-resizable-handle', `ui-resizable-${direction}`);

			if (['n', 'e', 's', 'w'].includes(direction)) {
				const ui_resize_dot = document.createElement('div');

				ui_resize_dot.classList.add('ui-resize-dot');
				resizable_handle.appendChild(ui_resize_dot);

				const ui_resizable_border = document.createElement('div');

				ui_resizable_border.classList.add(`ui-resizable-border-${direction}`);
				resizable_handle.appendChild(ui_resizable_border);
			}

			this._target.append(resizable_handle);
			this._resizable_handles[direction] = resizable_handle;
		}
	}

	/**
	 * Remove eight resize handles from the widget by which the widget can be resized in editing mode. Invoked when the
	 * widget is left (unfocused).
	 */
	_removeResizeHandles() {
		for (const resizable_handle of Object.values(this._resizable_handles)) {
			resizable_handle.remove();
		}

		this._resizable_handles = {};
	}

	/**
	 * Calculate viewport dimensions of the contents' container.
	 *
	 * @returns {{height: number, width: number}}
	 */
	_getContentsSize() {
		const computed_style = getComputedStyle(this._contents);

		const width = Math.floor(
			parseFloat(computed_style.width)
				- parseFloat(computed_style.paddingLeft) - parseFloat(computed_style.paddingRight)
				- parseFloat(computed_style.borderLeftWidth) - parseFloat(computed_style.borderRightWidth)
		);

		const height = Math.floor(
			parseFloat(computed_style.height)
				- parseFloat(computed_style.paddingTop) - parseFloat(computed_style.paddingBottom)
				- parseFloat(computed_style.borderTopWidth) - parseFloat(computed_style.borderBottomWidth)
		);

		return {width, height};
	}

	/**
	 * Update error messages.
	 *
	 * @param {string[]}    messages
	 * @param {string|null} title
	 */
	_updateMessages(messages = [], title = null) {
		this._messages.innerHTML = '';

		if (messages.length > 0 || title !== null) {
			const message_box = makeMessageBox('bad', messages, title)[0];

			this._messages.appendChild(message_box);
		}
	}

	/**
	 * Update info buttons in the widget header.
	 *
	 * @param {Object[]} info
	 *        {string}   info[].icon
	 *        {string}   info[].hint
	 */
	_updateInfo(info = []) {
		for (const li of this._actions.querySelectorAll('.widget-info-button')) {
			li.remove();
		}

		for (let i = info.length - 1; i >= 0; i--) {
			const li = document.createElement('li');

			li.classList.add('widget-info-button');

			const li_button = document.createElement('button');

			li_button.type = 'button';
			li_button.setAttribute('data-hintbox', '1');
			li_button.setAttribute('data-hintbox-static', '1');
			li_button.classList.add(ZBX_STYLE_BTN_ICON, info[i].icon);
			li.appendChild(li_button);

			const li_div = document.createElement('div');

			li_div.innerHTML = info[i].hint;
			li_div.classList.add('hint-box');
			li_div.style.display = 'none';
			li.appendChild(li_div);

			this._actions.prepend(li);
		}
	}

	/**
	 * Update debug information.
	 *
	 * @param {string} debug
	 */
	_updateDebug(debug = '') {
		this._debug.innerHTML = debug;
	}

	/**
	 * Show data preloader immediately. Invoked before the first update cycle of the widget.
	 */
	_showPreloader() {
		// Fixed Safari 16 bug: removing preloader classes on animation frame to ensure removal of icons.

		if (this._hide_preloader_animation_frame !== null) {
			cancelAnimationFrame(this._hide_preloader_animation_frame);
			this._hide_preloader_animation_frame = null;
		}

		this._body.classList.add('is-loading');
		this._body.classList.remove('is-loading-fadein', 'delayed-15s');
	}

	/**
	 * Hide data preloader.
	 */
	_hidePreloader() {
		// Fixed Safari 16 bug: removing preloader classes on animation frame to ensure removal of icons.

		if (this._hide_preloader_animation_frame !== null) {
			return;
		}

		this._hide_preloader_animation_frame = requestAnimationFrame(() => {
			this._body.classList.remove('is-loading', 'is-loading-fadein', 'delayed-15s');
			this._hide_preloader_animation_frame = null;
		});
	}

	/**
	 * Schedule showing data preloader after 15 seconds. Invoked before regular update cycle of the widget.
	 */
	_schedulePreloader() {
		// Fixed Safari 16 bug: removing preloader classes on animation frame to ensure removal of icons.

		if (this._hide_preloader_animation_frame !== null) {
			cancelAnimationFrame(this._hide_preloader_animation_frame);
			this._hide_preloader_animation_frame = null;
		}

		this._body.classList.add('is-loading', 'is-loading-fadein', 'delayed-15s');
	}

	/**
	 * Create DOM structure for the widget. Invoked once, on widget start.
	 */
	_makeView() {
		this._container = document.createElement('div');
		this._container.classList.add(this._css_classes.container);

		this._header = document.createElement('div');
		this._header.classList.add(this._css_classes.header);

		const header_h4 = document.createElement('h4');

		header_h4.textContent = this._name !== '' ? this._name : this._defaults.name;
		this._header.appendChild(header_h4);

		this._actions = document.createElement('ul');
		this._actions.classList.add(this._css_classes.actions);

		if (this._is_editable) {
			this._button_edit = document.createElement('button');
			this._button_edit.type = 'button';
			this._button_edit.title = t('Edit')
			this._button_edit.classList.add(ZBX_STYLE_BTN_ICON, ZBX_ICON_COG_FILLED, 'js-widget-edit');

			const li = document.createElement('li');

			li.appendChild(this._button_edit);
			this._actions.appendChild(li);
		}

		this._button_actions = document.createElement('button');
		this._button_actions.type = 'button';
		this._button_actions.title = t('Actions');
		this._button_actions.setAttribute('aria-expanded', 'false');
		this._button_actions.setAttribute('aria-haspopup', 'true');
		this._button_actions.classList.add(ZBX_STYLE_BTN_ICON, ZBX_ICON_MORE, 'js-widget-action');

		const li = document.createElement('li');

		li.appendChild(this._button_actions);
		this._actions.appendChild(li);

		this._header.append(this._actions);

		this._container.appendChild(this._header);

		this._contents = document.createElement('div');
		this._contents.classList.add(this._css_classes.contents);
		this._contents.classList.add(`dashboard-widget-${this._type}`);
		this._contents.classList.toggle('no-padding', !this.hasPadding());

		this._messages = document.createElement('div');
		this._messages.classList.add(this._css_classes.messages);
		this._contents.appendChild(this._messages);

		this._body = document.createElement('div');
		this._body.classList.add(this._css_classes.body);
		this._contents.appendChild(this._body);

		this._debug = document.createElement('div');
		this._debug.classList.add(this._css_classes.debug);
		this._contents.appendChild(this._debug);

		this._container.appendChild(this._contents);

		this._target.appendChild(this._container);
		this._target.classList.add(this._css_classes.root);
		this._target.classList.toggle('ui-draggable', this._is_edit_mode);
		this._target.classList.toggle('ui-resizable', this._is_edit_mode);
		this._target.classList.toggle(this._css_classes.hidden_header,
			this._view_mode === ZBX_WIDGET_VIEW_MODE_HIDDEN_HEADER
		);
		this._target.classList.toggle('new-widget', this._is_new);

		this._target.style.minWidth = `${this._cell_width}%`;
		this._target.style.minHeight = `${this._cell_height}px`;
	}

	// Internal events management methods.

	/**
	 * Create event listeners. Invoked once, upon widget initialization.
	 */
	_registerEvents() {
		this._events = {
			actions: (e) => {
				this.fire(WIDGET_EVENT_ACTIONS, {mouse_event: e});
			},

			edit: () => {
				this.fire(WIDGET_EVENT_EDIT);
			},

			focusin: () => {
				this.fire(WIDGET_EVENT_ENTER);
			},

			focusout: () => {
				this.fire(WIDGET_EVENT_LEAVE);
			},

			enter: () => {
				this.fire(WIDGET_EVENT_ENTER);
			},

			leave: () => {
				this.fire(WIDGET_EVENT_LEAVE);
			},

			...this._events
		};
	}

	/**
	 * Activate event listeners. Invoked on each activation of the dashboard page.
	 */
	_activateEvents() {
		this._button_actions.addEventListener('click', this._events.actions);

		if (this._is_editable) {
			this._button_edit.addEventListener('click', this._events.edit);
		}

		this._target.addEventListener('mousemove', this._events.enter);
		this._target.addEventListener('mouseleave', this._events.leave);
		this._header.addEventListener('focusin', this._events.focusin);
		this._header.addEventListener('focusout', this._events.focusout);
	}

	/**
	 * Deactivate event listeners. Invoked on each deactivation of the dashboard page.
	 */
	_deactivateEvents() {
		this._button_actions.removeEventListener('click', this._events.actions);

		if (this._is_editable) {
			this._button_edit.removeEventListener('click', this._events.edit);
		}

		this._target.removeEventListener('mousemove', this._events.enter);
		this._target.removeEventListener('mouseleave', this._events.leave);
		this._header.removeEventListener('focusin', this._events.focusin);
		this._header.removeEventListener('focusout', this._events.focusout);
	}

	/**
	 * Attach event listener to widget events.
	 *
	 * @param {string}       type
	 * @param {function}     listener
	 * @param {Object|false} options
	 *
	 * @returns {CWidgetBase}
	 */
	on(type, listener, options = false) {
		this._target.addEventListener(type, listener, options);

		return this;
	}

	/**
	 * Detach event listener from widget events.
	 *
	 * @param {string}       type
	 * @param {function}     listener
	 * @param {Object|false} options
	 *
	 * @returns {CWidgetBase}
	 */
	off(type, listener, options = false) {
		this._target.removeEventListener(type, listener, options);

		return this;
	}

	/**
	 * Dispatch widget event.
	 *
	 * @param {string} type
	 * @param {Object} detail
	 * @param {Object} options
	 *
	 * @returns {boolean}
	 */
	fire(type, detail = {}, options = {}) {
		return this._target.dispatchEvent(new CustomEvent(type, {...options, detail: {target: this, ...detail}}));
	}
}
