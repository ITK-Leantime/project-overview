/**
 * Project Overview bundle entrypoint.
 *
 * Subsystems that run on the project-overview page, in roughly the order they
 * appear in this file:
 *   - Filter pill init + select2 wiring (users / projects / other filters /
 *     columns) and the cross-pill mutex with the "All" sentinel.
 *   - View tabs widget (hidden in the redesigned UI, driven by sidebar clicks)
 *     including URL pushState, unsaved-changes tracking, and lazy-loading the
 *     active panel's table on activation.
 *   - Ticket-row inline edits (status, priority, due date, assignee, tags,
 *     hours, milestone) that PATCH the API and animate save success/error.
 *   - Lazy-load sentinel for paginated row insertion.
 *
 * Sidebar view navigation has been extracted to `./sidebar.js`; the two
 * functions imported below are the only cross-module touch points. New
 * subsystems should also be extracted to their own module under `assets/`
 * and wired in from here rather than appended to this file.
 */

import 'select2';
import 'select2/dist/css/select2.css';
import flatpickr from 'flatpickr';
import { Danish } from 'flatpickr/dist/l10n/da.js';
import 'flatpickr/dist/flatpickr.min.css';
import TomSelect from 'tom-select';
import 'tom-select/dist/css/tom-select.bootstrap5.css';
import './project-overview.css';
import {
  initSidebarViewNavigation,
  applySidebarActiveState,
} from './sidebar.js';

$(document).ready(function () {
  window.frontendDateFormat = $(document).find('#frontendDateFormat').val();
  initFiltersToggle();
  initProjectOverviewFilters();
  initProjectOverviewTable();
  initScrollToTopButton();
  initSaveChangesSubmit();
  initSidebarViewNavigation();
  initNewViewTipsDismiss();

  // begin HTMX swap events
  document.body.addEventListener('htmx:beforeSettle', (e) => {
    e.detail.target.style.visibility = 'hidden';
  });
  document.addEventListener('htmx:afterSettle', function (e) {
    e.detail.target.style.visibility = '';
    if (e.target.id === 'filtersContainer') {
      initProjectOverviewFilters();

      // Restore cached unsaved form state if returning to a dirty view
      const activeViewId = document.getElementById('selectedViewId');
      if (
        activeViewId &&
        window._viewCachedFormData &&
        window._viewCachedFormData[activeViewId.value]
      ) {
        restoreFormState(window._viewCachedFormData[activeViewId.value]);
      }

      // Restore save button visibility after HTMX replaces the filters DOM
      const saveBtn = document.querySelector('.save-changes-btn');
      if (saveBtn && activeViewId) {
        saveBtn.style.display = shouldShowSaveChangesBtn(activeViewId.value)
          ? ''
          : 'none';
      }

      // Lazy-load: if the active view panel has a placeholder, trigger a
      // table refresh. The "__new" tab is a special case — its panel renders
      // the help banner only on initial paint, so we also kick off a refresh
      // when it's active and no table is mounted yet. The refresh response
      // includes the help banner above the table (see the partial), so help
      // remains visible and the preview slots in alongside it.
      if (activeViewId && activeViewId.value) {
        const activePanel = document.getElementById(
          'view-' + activeViewId.value
        );
        const isNewTabWithoutPreview =
          activeViewId.value === '__new' &&
          activePanel &&
          !activePanel.querySelector('table');
        if (
          activePanel &&
          (activePanel.querySelector('.view-lazy-load') ||
            isNewTabWithoutPreview)
        ) {
          const form = document.getElementById('filtersForm');
          if (form) {
            refreshViewTable(form);
          }
        }
      }
    }
  });
  // end HTMX swap events
});

/**
 * Show the floating "scroll to top" button after the user scrolls down enough,
 * and smoothly return them to the top on click.
 */
function initScrollToTopButton() {
  const btn = document.getElementById('scrollToTopBtn');
  if (!btn) return;

  const SHOW_AFTER_PX = 320;
  let raf = 0;

  function update() {
    raf = 0;
    const shouldShow = window.scrollY > SHOW_AFTER_PX;
    if (shouldShow && btn.hasAttribute('hidden')) {
      btn.removeAttribute('hidden');
      // Force a paint so the transition runs from opacity:0 to 1
      requestAnimationFrame(() => btn.classList.add('is-visible'));
    } else if (!shouldShow && !btn.hasAttribute('hidden')) {
      btn.classList.remove('is-visible');
      // Wait for the fade-out before removing from layout
      setTimeout(() => {
        if (window.scrollY <= SHOW_AFTER_PX) btn.setAttribute('hidden', '');
      }, 200);
    }
  }

  window.addEventListener(
    'scroll',
    () => {
      if (raf) return;
      raf = requestAnimationFrame(update);
    },
    { passive: true }
  );

  btn.addEventListener('click', () => {
    const reduceMotion = window.matchMedia(
      '(prefers-reduced-motion: reduce)'
    ).matches;
    window.scrollTo({ top: 0, behavior: reduceMotion ? 'auto' : 'smooth' });
  });

  update();
}

/**
 * On submit of the filter form via the "Save changes" button, prompt for a name
 * if the active tab is the synthetic __new tab. Cancel = abort submit. Otherwise
 * the form submits normally (overwriteView=1 on the button overwrites the active
 * view, or the saveView handler creates a new view when view=__new).
 */
function initSaveChangesSubmit() {
  document.addEventListener('submit', function (e) {
    const form = e.target;
    if (!form || form.id !== 'filtersForm') return;

    // The filters form has exactly one submit affordance now (#saveChangesBtn),
    // so any submit it fires is a "Save changes". Other actions (rename, share,
    // delete, pin, copy) live in the separate context-menu form.

    // The active view: the form's hidden `view` field is rendered by the
    // filters template for the currently-loaded view, so it's the source of
    // truth. The page-level #selectedViewId is updated by the tab-activate
    // handler in JS but only after a tab click; on initial empty-state page
    // load (where no tab activate fires) the form is the only reliable source.
    const viewField = form.querySelector('input[name="view"]');
    const activeViewIdInput = document.getElementById('selectedViewId');
    const activeViewId =
      (viewField && viewField.value) ||
      (activeViewIdInput && activeViewIdInput.value) ||
      null;

    if (activeViewId !== '__new') return;

    // Defensive: ensure the field is `__new` (already is in normal flow, but
    // protects against any stale state).
    if (viewField) viewField.value = '__new';

    const promptText =
      (window.projectOverviewI18n &&
        window.projectOverviewI18n.newViewPromptName) ||
      'Name your new view';
    const name = window.prompt(promptText);
    if (name === null || name.trim() === '') {
      e.preventDefault();
      return;
    }
    const nameField = form.querySelector('#newViewName');
    if (nameField) nameField.value = name.trim();
  });
}

/**
 * Counterpart for the (CSS-hidden) horizontal tab strip — separate from the
 * sidebar scroll helper that lives in `./sidebar.js`. Used on init so a page
 * loaded with `?view=<id>` pointing at a tab past the fold of the scrollable
 * strip isn't hidden behind the sticky "+ New view" pin — the activate handler
 * already scrolls on user-driven tab switches.
 */
function scrollActiveTabIntoView() {
  const active = document.querySelector(
    '#projectOverviewTabs .ui-tabs-nav .ui-state-active'
  );
  if (!active) return;
  active.scrollIntoView({ inline: 'nearest', block: 'nearest' });
}

/**
 * Dismissal for the onboarding tips banner that lives at the top of the
 * `__new` view panel. The banner is re-rendered server-side on every refresh
 * of the panel (it's bundled into the table partial when viewId === '__new'),
 * so a one-shot `display: none` would come right back. Instead we persist a
 * flag in localStorage and apply a body-level class on every page load. CSS
 * keys off the class to hide the banner.
 *
 * Delegated on document so it survives every panel innerHTML swap.
 */
const NEW_VIEW_TIPS_DISMISSED_KEY = 'projectOverview.newViewTipsDismissed';

function initNewViewTipsDismiss() {
  if (localStorage.getItem(NEW_VIEW_TIPS_DISMISSED_KEY) === '1') {
    document.body.classList.add('projectoverview-new-view-tips-dismissed');
  }
  document.body.addEventListener('click', function (e) {
    if (!e.target.closest('.new-view-tips-dismiss')) return;
    localStorage.setItem(NEW_VIEW_TIPS_DISMISSED_KEY, '1');
    document.body.classList.add('projectoverview-new-view-tips-dismissed');
  });
}

/**
 * Initializes the collapsible filters toggle with localStorage persistence.
 */
function initFiltersToggle() {
  const STORAGE_KEY = 'projectOverview.filtersCollapsed';
  const toggle = document.getElementById('filtersToggle');
  const container = document.getElementById('filtersContainer');
  if (!toggle || !container) return;

  var label = toggle.querySelector('span');

  function updateLabel(collapsed) {
    label.textContent = collapsed ? toggle.dataset.show : toggle.dataset.hide;
  }

  // Restore saved state (disable transition to prevent animation on load)
  if (localStorage.getItem(STORAGE_KEY) === '1') {
    container.style.transition = 'none';
    container.classList.add('collapsed');
    toggle.classList.add('collapsed');
    updateLabel(true);
    // Re-enable transition after the browser has painted
    requestAnimationFrame(function () {
      requestAnimationFrame(function () {
        container.style.transition = '';
      });
    });
  }

  toggle.addEventListener('click', function () {
    const isCollapsed = container.classList.toggle('collapsed');
    toggle.classList.toggle('collapsed', isCollapsed);
    updateLabel(isCollapsed);
    localStorage.setItem(STORAGE_KEY, isCollapsed ? '1' : '0');
  });
}

/**
 * Initializes the project overview filters by setting up various UI components.
 *
 * @return {void} This function does not return a value.
 */
function initProjectOverviewFilters() {
  // Sync save button visibility with the active tab on each filters reload (the
  // "new" tab always shows the button; other tabs only show it when dirty).
  syncSaveChangesVisibility();

  // Build a dropdown adapter that puts a search input at the top of the open
  // dropdown panel. Select2 v4 only renders an inline search inside the
  // selection chip area for multi-selects, and our selection bar is hidden by
  // CSS — so without this, users have to type blind to filter.
  const dropdownWithSearch = buildDropdownAdapterWithSearch();

  // Translated pill labels. Used as `data-label` on each select2 wrapper and
  // as the "All" sentinel for `data-length`. CSS reads both via attr() so the
  // pseudo-element labels track the user's language.
  const i18n = window.projectOverviewI18n || {};
  const allLabel = i18n.pillAll || 'All';

  // Init date range select
  const dateRange = flatpickr('#dateRange', {
    mode: 'range',
    dateFormat: window.frontendDateFormat,
    allowInput: false,
    readonly: false,
    weekNumbers: true,
    locale: Danish,
    onChange: function (selectedDates) {
      if (selectedDates && selectedDates.length === 2) {
        const [startDate, endDate] = selectedDates;

        // Format dates to d-m-Y
        const formatDate = (date) => {
          const day = String(date.getDate()).padStart(2, '0');
          const month = String(date.getMonth() + 1).padStart(2, '0');
          const year = date.getFullYear();
          return `${day}-${month}-${year}`;
        };

        $('#fromDate').val(formatDate(startDate));
        $('#toDate').val(formatDate(endDate));
      }
    },
  });

  // Init filter select2
  const filterSelect = $('#filterSelect')
    .select2({
      closeOnSelect: false,
      dropdownCssClass: 'project-overview-dropdown',
      dropdownAdapter: dropdownWithSearch,
      // Skip the default matcher's fallback that matches against an optgroup's
      // own label — without this, typing "Priority"/"Status"/"Custom filters"
      // would expand the entire group. We only want option text to match.
      matcher: function matchOptionsOnly(params, data) {
        if (!params.term || !params.term.trim()) return data;
        if (data.children && data.children.length > 0) {
          const filtered = Object.assign({}, data, { children: [] });
          for (const child of data.children) {
            const m = matchOptionsOnly(params, child);
            if (m) filtered.children.push(m);
          }
          return filtered.children.length > 0 ? filtered : null;
        }
        const text = (data.text || '').toUpperCase();
        return text.indexOf(params.term.toUpperCase()) !== -1 ? data : null;
      },
    })
    .on('select2:select', () => {
      $(this).val(null).trigger('change');
    })
    .on('change.select2', () => {
      $(filterSelect)
        .next('.select2')
        .attr('data-length', function () {
          return filterSelect.select2('data')?.length;
        });
    });

  filterSelect
    .next('.select2')
    .attr('data-label', i18n.pillOtherFilters || 'Other filters')
    .attr('data-length', function () {
      return filterSelect.select2('data')?.length;
    });

  // Init project select2. The synthetic "__all" option represents the
  // "no filter — show every project" state. It's mutually exclusive with
  // real project selections: picking a project removes __all, picking __all
  // (or clearing every real selection) snaps the value back to ['__all'].
  const projectSelect = $('#projectSelect')
    .select2({
      closeOnSelect: false,
      dropdownCssClass: 'project-overview-dropdown',
      dropdownAdapter: dropdownWithSearch,
      matcher: function (params, data) {
        if (!params.term) return data;
        const text = (data.text || '').toUpperCase();
        return text.indexOf(params.term.toUpperCase()) !== -1 ? data : null;
      },
    })
    .on('select2:select', function (e) {
      const justPicked = e.params.data.id;
      const current = projectSelect.val() || [];
      if (justPicked === '__all') {
        if (current.length !== 1 || current[0] !== '__all') {
          applySelectMutex(projectSelect, ['__all']);
        }
      } else if (current.includes('__all')) {
        applySelectMutex(
          projectSelect,
          current.filter((v) => v !== '__all')
        );
      }
    })
    .on('select2:unselect', function () {
      const current = projectSelect.val() || [];
      if (current.length === 0) {
        applySelectMutex(projectSelect, ['__all']);
      }
    })
    .on('change.select2', () => {
      const vals = projectSelect.val() || [];
      const label = vals.includes('__all') ? allLabel : vals.length;
      projectSelect.next('.select2').attr('data-length', label);
    });

  projectSelect
    .next('.select2')
    .attr('data-label', i18n.pillProjects || 'Projects');
  (function setInitialProjectLength() {
    const vals = projectSelect.val() || [];
    const label = vals.includes('__all') ? allLabel : vals.length;
    projectSelect.next('.select2').attr('data-length', label);
  })();

  // Init column select2. "__all" mirrors the projects/users dropdowns:
  // mutually exclusive with concrete column picks, default when nothing is
  // chosen, and stripped server-side so an empty stored columns array stays
  // canonical for "show every column".
  const columnSelect = $('#columnSelect')
    .select2({
      closeOnSelect: false,
      dropdownCssClass: 'project-overview-dropdown',
      dropdownAdapter: dropdownWithSearch,
    })
    .on('select2:select', function (e) {
      const justPicked = e.params.data.id;
      const current = columnSelect.val() || [];
      if (justPicked === '__all') {
        if (current.length !== 1 || current[0] !== '__all') {
          applySelectMutex(columnSelect, ['__all']);
        }
      } else if (current.includes('__all')) {
        applySelectMutex(
          columnSelect,
          current.filter((v) => v !== '__all')
        );
      }
    })
    .on('select2:unselect', function () {
      const current = columnSelect.val() || [];
      if (current.length === 0) {
        applySelectMutex(columnSelect, ['__all']);
      }
    })
    .on('change.select2', () => {
      const vals = columnSelect.val() || [];
      const label = vals.includes('__all') ? allLabel : vals.length;
      columnSelect.next('.select2').attr('data-length', label);
    });

  columnSelect
    .next('.select2')
    .attr('data-label', i18n.pillColumns || 'Columns');
  (function setInitialColumnLength() {
    const vals = columnSelect.val() || [];
    const label = vals.includes('__all') ? allLabel : vals.length;
    columnSelect.next('.select2').attr('data-length', label);
  })();

  // Init date range select
  $('#dateOptions')
    .on('change', function () {
      const dateRangeElement = $(document).find('div.date-range-filter');
      const dateRangeInput = $('#dateRange');
      const selectedOption = $(this).find('option:selected');

      // Get pre-calculated dates from data attributes
      const startDate = selectedOption.data('start-date');
      const endDate = selectedOption.data('end-date');

      if (startDate && endDate) {
        // Parse YYYY-MM-DD format to Date objects
        const [startYear, startMonth, startDay] = startDate
          .split('-')
          .map(Number);
        const [endYear, endMonth, endDay] = endDate.split('-').map(Number);

        const start = new Date(startYear, startMonth - 1, startDay);
        const end = new Date(endYear, endMonth - 1, endDay);

        dateRange.setDate([start, end]);
        dateRange.set('clickOpens', false);
        $(dateRangeElement).addClass('date-range-disabled');
        dateRangeInput.prop('readonly', true);
      } else if (selectedOption.val() === 'custom') {
        dateRange.set('clickOpens', true);
        $(dateRangeElement).removeClass('date-range-disabled');
        dateRangeInput.prop('readonly', false);
      }
    })
    .trigger('change');

  // Init assignee select2. Like the project select, "__all" is mutually
  // exclusive with concrete user selections — including the "unassigned"
  // sentinel — and snaps back as the default when the list is emptied.
  const userSelect = $('#userSelect')
    .select2({
      closeOnSelect: false,
      dropdownCssClass: 'project-overview-dropdown',
      dropdownAdapter: dropdownWithSearch,
      matcher: function (params, data) {
        if (!params.term) return data;
        const keywords = params.term.split(' ');
        const text = data.text.toUpperCase();
        for (const keyword of keywords) {
          if (text.indexOf(keyword.toUpperCase()) === -1) return null;
        }
        return data;
      },
    })
    .on('select2:select', function (e) {
      const justPicked = e.params.data.id;
      const current = userSelect.val() || [];
      if (justPicked === '__all') {
        if (current.length !== 1 || current[0] !== '__all') {
          applySelectMutex(userSelect, ['__all']);
        }
      } else if (current.includes('__all')) {
        applySelectMutex(
          userSelect,
          current.filter((v) => v !== '__all')
        );
      }
    })
    .on('select2:unselect', function () {
      const current = userSelect.val() || [];
      if (current.length === 0) {
        applySelectMutex(userSelect, ['__all']);
      }
    })
    .on('change.select2', () => {
      const vals = userSelect.val() || [];
      const label = vals.includes('__all') ? allLabel : vals.length;
      userSelect.next('.select2').attr('data-length', label);
    });

  userSelect.next('.select2').attr('data-label', i18n.pillUsers || 'Users');
  (function setInitialUserLength() {
    const vals = userSelect.val() || [];
    const label = vals.includes('__all') ? allLabel : vals.length;
    userSelect.next('.select2').attr('data-length', label);
  })();

  // Re-enable disabled fields on submit so their values are included in POST data
  const filtersForm = document.getElementById('filtersForm');
  if (filtersForm) {
    filtersForm.addEventListener('submit', function () {
      filtersForm
        .querySelectorAll('select[disabled], input[disabled]')
        .forEach(function (el) {
          el.disabled = false;
        });
    });
  }

  // --- Live filter update: refresh table on filter change ---
  if (!filtersForm || filtersForm.dataset.isSubscription === 'true') return;

  // Use the form's hidden `view` field (rendered by the template for the just-
  // loaded view) as the source of truth. The page-level #selectedViewId can
  // still hold the previously-active view's id at this point in some HTMX
  // swap orderings.
  const viewField = filtersForm.querySelector('input[name="view"]');
  const currentViewId = viewField
    ? viewField.value
    : document.getElementById('selectedViewId')?.value || null;

  // Store the initial state for the current view, and ensure dirty tracking
  // for this view starts clean (the form was just rendered fresh by the
  // server, so any "dirty" mark from a previous tab session is stale).
  if (currentViewId) {
    if (!window._viewInitialStates) window._viewInitialStates = {};
    window._viewInitialStates[currentViewId] = serializeFilterForm(filtersForm);
    if (window._viewsWithUnsavedChanges) {
      window._viewsWithUnsavedChanges[currentViewId] = false;
    }
  }

  let filterDebounceTimer = null;

  function onFilterChange() {
    const vf = filtersForm.querySelector('input[name="view"]');
    const activeViewId = vf
      ? vf.value
      : document.getElementById('selectedViewId')?.value || null;
    const initialState =
      activeViewId && window._viewInitialStates
        ? window._viewInitialStates[activeViewId]
        : null;
    const hasChanges =
      initialState !== undefined &&
      initialState !== null &&
      serializeFilterForm(filtersForm) !== initialState;
    toggleUnsavedIndicator(activeViewId, hasChanges);

    clearTimeout(filterDebounceTimer);
    filterDebounceTimer = setTimeout(function () {
      refreshViewTable(filtersForm);
    }, 300);
  }

  $('#userSelect').on('change.select2', onFilterChange);
  $('#filterSelect').on('change.select2', onFilterChange);
  $('#projectSelect').on('change.select2', onFilterChange);
  $('#columnSelect').on('change.select2', onFilterChange);
  $('#dateOptions').on('change', onFilterChange);

  // Extend flatpickr onChange to also trigger filter refresh
  const fpInstance = document.getElementById('dateRange')?._flatpickr;
  if (fpInstance) {
    const originalOnChange = fpInstance.config.onChange;
    fpInstance.config.onChange.push(function (selectedDates) {
      if (selectedDates && selectedDates.length === 2) {
        onFilterChange();
      }
    });
  }
}

function initProjectOverviewTable() {
  // Init tags select for each row.
  initTagsSelects();

  installFilterTabNavigation();

  // Capture-phase key handling for the dropdown search input.
  //   - Enter: select2's default Enter on multi-select is "select-only", but
  //     we want toggle behavior matching what a mouse click does. Triggering
  //     mouseup on the highlighted result reuses select2's own click handler,
  //     which already implements toggle.
  //   - Escape: select2 closes the dropdown, but in our layout something in
  //     the post-close focus dance reopens it almost immediately. We can't
  //     reliably stop the reopen at the keydown level (it survives even
  //     stopImmediatePropagation from a capture-phase handler), so instead we
  //     mark the select "suppress next open" and cancel any `select2:opening`
  //     event that fires within a short window after Escape. The
  //     `select2:opening` listener is delegated at document level so it
  //     covers each fresh select2 instance after HTMX swaps.
  $(document).on(
    'select2:opening',
    '#filterSelect, #projectSelect, #columnSelect, #userSelect',
    function (e) {
      const $select = $(this);
      if ($select.data('_povSuppressOpen')) {
        e.preventDefault();
        $select.removeData('_povSuppressOpen');
      }
    }
  );

  $(document).on(
    'select2:open',
    '#filterSelect, #projectSelect, #columnSelect, #userSelect',
    function () {
      const $select = $(this);
      const dropdown = document.querySelector('.project-overview-dropdown');
      const input = dropdown?.querySelector('.select2-search__field');
      if (!input || !dropdown) return;

      // Add the bulk toggle button for the three multi-select filters that use
      // the __all sentinel (Other filters has optgroups and no __all, so skip).
      const supportsBulkToggle = [
        '#userSelect',
        '#projectSelect',
        '#columnSelect',
      ].includes('#' + ($select.attr('id') || ''));
      if (supportsBulkToggle) {
        installBulkToggleButton(dropdown, $select);
      }

      installDropdownKeyboardNav(dropdown, $select, input);
    }
  );

  // Wire the lazy-load buttons on the initially-rendered active panel.
  // (Inactive tabs hold a placeholder until activated, then refreshViewTable
  // re-attaches the buttons for them.)
  document.querySelectorAll('[id^="view-"]').forEach(function (panel) {
    if (panel.querySelector('.lazy-row-sentinel')) {
      attachLazyLoad(panel);
    }
  });

  const contextMenu = $('#view-context-menu');

  // Start sorting
  // Status change
  $(document).on('click', '.dropdown-item .table-button.status', function () {
    const [ticketId, newStatus, className, name] = $(this)
      .data('args')
      .split(',');
    changeStatus(ticketId, newStatus, className, name);
  });

  // Priority change
  $(document).on('click', '.dropdown-item .table-button.priority', function () {
    const [ticketId, newPriority, label] = $(this).data('args').split(',');
    changePriority(ticketId, newPriority, label);
  });

  // begin sorting
  document.addEventListener('click', function (e) {
    const th = e.target.closest('[id^=sort_]');
    if (th) {
      changeSortBy(th.id.replace('sort_', ''), th);
    }
  });

  $(document).on('change', '[id^=due-date-]', function () {
    const ticketId = $(this).data('ticketid');
    changeDueDate(event, ticketId, $(this).val());
  });

  $(document).on('change', '[id^=assigned-user-]', function () {
    const idArg = this.id.split('-')[2];
    changeAssignedUser(event, idArg, this.value);
  });

  $(document).on('change', '[id^=plan-hours-]', function () {
    const idArg = this.id.split('-')[2];
    changePlanHours(event, idArg, this.value);
  });

  $(document).on('change', '[id^=remaining-hours-]', function () {
    const idArg = this.id.split('-')[2];
    changeHoursRemaining(event, idArg, this.value);
  });

  $(document).on('change', '[id^=milestone-select-]', function () {
    const idArg = this.id.split('-')[2];
    changeMilestone(event, idArg, this.value);
  });
  // end sorting

  // Init click event on context menu
  $(document).on('click', 'span.tab-context-menu', ({ target }) => {
    const currentName = $(target).siblings('.tab-link').first().text().trim();
    const triggerRect = target.getBoundingClientRect();
    const liRect = target.parentElement.getBoundingClientRect();
    const tab = $(target).parent();
    const viewId = tab.data('target');
    // Both stored subscriptions and live transient subscriptions need the
    // subscription-mode menu (Pin / Save as copy).
    const isSubscription =
      tab.data('is-subscription') === true ||
      tab.data('is-transient-subscription') === true;
    const subscribeToken = tab.data('subscribe-token') || '';
    $('.settings-for-target').text(viewId);
    contextMenu
      .attr('data-mode', isSubscription ? 'subscription' : 'owned')
      .css({
        left: `${liRect.left}px`,
        top: `${triggerRect.bottom + 12}px`,
      })
      .addClass('shown')
      .find('#contextMenuTitle')
      .text(currentName)
      .end()
      .find('input[name="viewName"]')
      .val(currentName)
      .end()
      .find('input[name="view"]')
      .val(viewId)
      .end()
      .find('input[name="subscribeToken"]')
      .val(subscribeToken);

    if (!isSubscription) {
      requestAnimationFrame(() => {
        contextMenu.find('input[name="viewName"]').focus();
      });
    }
  });

  // Close context menu when clicked outside.
  $(document).on('click', function (event) {
    if (
      !$(event.target).closest('#view-context-menu').length &&
      !$(event.target).closest('span.tab-context-menu').length
    ) {
      contextMenu.removeClass('shown');
    }
  });
  // Close .tab-context-menu when clicking on any other tab.
  $(document).on('click', '#projectOverviewTabs > ul > li', ({ target }) => {
    if (!$(target).closest('span.tab-context-menu').length) {
      contextMenu.removeClass('shown');
    }
  });
  // Check if URL has a view parameter
  const urlParams = new URLSearchParams(window.location.search);
  const urlViewId = urlParams.get('view');
  let selectedViewId = $(document).find('#selectedViewId').val();

  // If URL has a view parameter and it exists in the tabs, use that
  if (urlViewId && window.jQuery(`li[data-target='${urlViewId}']`).length > 0) {
    selectedViewId = urlViewId;
    window.jQuery('#selectedViewId').val(urlViewId);
  }

  // Use window.jQuery to access the globally loaded jQuery UI
  const $projectOverviewTabs = window.jQuery('#projectOverviewTabs');

  // Init view tabs with sorting
  $projectOverviewTabs
    .tabs({
      beforeActivate: function (event, ui) {
        // Cache unsaved form state before switching away
        const currentViewId = window.jQuery('#selectedViewId').val();
        if (
          currentViewId &&
          window._viewsWithUnsavedChanges &&
          window._viewsWithUnsavedChanges[currentViewId]
        ) {
          const form = document.getElementById('filtersForm');
          if (form) {
            if (!window._viewCachedFormData) window._viewCachedFormData = {};
            window._viewCachedFormData[currentViewId] = captureFormState(form);
          }
        }
      },
      activate: function (event, ui) {
        window.jQuery('#edit-time-log-modal').removeClass('shown');

        if (ui.newTab && ui.newTab[0]) {
          ui.newTab[0].scrollIntoView({
            inline: 'nearest',
            block: 'nearest',
            behavior: 'smooth',
          });
        }

        // Update URL when tab is activated
        const viewId = ui.newPanel.attr('id').replace('view-', '');

        // Sync save button and unsaved banner with the newly active view
        const saveBtn = document.querySelector('.save-changes-btn');
        if (saveBtn) {
          saveBtn.style.display = shouldShowSaveChangesBtn(viewId)
            ? ''
            : 'none';
        }
        const url = new URL(window.location.href);
        url.searchParams.set('view', viewId);
        window.history.pushState({ view: viewId }, '', url);

        // Update hidden input
        window.jQuery('#selectedViewId').val(viewId);

        // Keep the sidebar in sync (covers popstate and programmatic activation
        // paths, since sidebar clicks also re-apply explicitly).
        applySidebarActiveState();
      },
      active: window.jQuery(`li[data-target='${selectedViewId}']`).index(),
    })
    .find('ul')
    .sortable({
      // The synthetic "new" tab is pinned rightmost via CSS (order: 999) and is
      // also excluded from the saved order payload below. Excluding it from the
      // sortable item set prevents the user from grabbing it.
      items: 'li:not([data-target="__new"])',
      axis: 'x',
      tolerance: 'pointer',
      delay: 150,
      distance: 5,
      update: function (event, ui) {
        var newOrder = window
          .jQuery(this)
          .sortable('toArray', { attribute: 'data-target' })
          .filter(function (id) {
            return id !== '__new';
          });

        // Send AJAX request to save the new order
        window.jQuery.ajax({
          dataType: 'json',
          url: 'ProjectOverview/ProjectOverview/post',
          method: 'POST',
          data: {
            action: 'saveTabOrder',
            order: newOrder,
          },
          success: function (response) {
            if (response.status === 'success') {
              window.jQuery.growl({
                message: response.message || 'Tab order saved successfully',
              });
            } else {
              window.jQuery.growl({
                message: response.message || 'Failed to save tab order',
              });
            }
          },
          error: function (xhr, status, error) {
            window.jQuery.growl({
              message: 'Error saving tab order: ' + error,
            });
          },
        });
      },
    });

  // Fade in after initialization
  $projectOverviewTabs.removeClass('is-hidden');

  scrollActiveTabIntoView();

  // Set initial URL state
  if (!urlViewId) {
    const url = new URL(window.location.href);
    url.searchParams.set('view', selectedViewId);
    window.history.replaceState({ view: selectedViewId }, '', url);
  }

  // Handle browser back/forward buttons
  window.addEventListener('popstate', function (event) {
    if (event.state && event.state.viewId) {
      const viewIndex = window
        .jQuery(`li[data-target='${event.state.viewId}']`)
        .index();
      if (viewIndex >= 0) {
        $projectOverviewTabs.tabs('option', 'active', viewIndex);
        window.jQuery('#selectedView').val(event.state.viewId);

        // Trigger HTMX to load the filters for this view
        const hxGetUrl = `/ProjectOverview/ProjectOverview/loadFilters/${encodeURIComponent(event.state.viewId)}`;
        window.jQuery('#filtersContainer').attr('hx-get', hxGetUrl);
        htmx.trigger('#filtersContainer', 'load');
      }
    }
  });

  // Open share modal from context menu
  document.addEventListener('click', function (e) {
    const shareBtn = e.target.closest('button.view-share');
    if (!shareBtn) return;

    e.preventDefault();
    const viewId = document.querySelector(
      '#view-context-menu input[name="view"]'
    ).value;
    const modal = document.getElementById('share-view-modal');
    const input = document.getElementById('share-link-input');

    input.value = 'Loading...';
    modal.classList.add('shown');
    document.getElementById('view-context-menu').classList.remove('shown');

    jQuery.ajax({
      type: 'POST',
      url: '/ProjectOverview/ProjectOverview/generateShareLink',
      data: { view: viewId },
      dataType: 'json',
      success: function (response) {
        if (response.success && response.shareToken) {
          input.value =
            window.location.origin +
            '/ProjectOverview/ProjectOverview?subscribe=' +
            response.shareToken;
        } else {
          input.value = 'Error generating link';
        }
      },
      error: function () {
        input.value = 'Error generating link';
      },
    });
  });

  // Copy share link from modal input
  document.addEventListener('click', function (e) {
    if (!e.target.closest('.share-modal-copy-btn')) return;
    const input = document.getElementById('share-link-input');
    const btn = e.target.closest('.share-modal-copy-btn');
    const originalText = btn.textContent;
    const copiedText = btn.dataset.copied || 'Copied';

    navigator.clipboard.writeText(input.value).then(function () {
      btn.textContent = copiedText;
      setTimeout(function () {
        btn.textContent = originalText;
      }, 2000);
    });
  });

  // Close share modal
  document.addEventListener('click', function (e) {
    if (e.target.closest('.share-modal-close')) {
      document.getElementById('share-view-modal').classList.remove('shown');
    }
    if (e.target.id === 'share-view-modal') {
      e.target.classList.remove('shown');
    }
  });
}

function initSingleTagSelect(selectElement) {
  if (!selectElement || selectElement.tomselect) return;

  const allTags = window.allTags || [];
  const ticketId = selectElement.dataset.ticketId;

  try {
    new TomSelect(selectElement, {
      plugins: ['remove_button'],
      maxItems: null,
      create: true,
      persist: false,
      openOnFocus: false,
      loadThrottle: 300,
      load: function (query, callback) {
        if (!query.length) {
          this.close();
          return callback();
        }

        const filtered = allTags
          .filter((tag) => tag.toLowerCase().includes(query.toLowerCase()))
          .slice(0, 50)
          .map((tag) => ({ value: tag, text: tag }));

        callback(filtered);
      },
      onChange: function (values) {
        const tagsString = Array.isArray(values) ? values.join(',') : values;
        changeTags({ target: selectElement }, ticketId, tagsString);
      },
    });
  } catch (err) {
    console.error(
      '[ProjectOverview] TomSelect init failed for',
      selectElement,
      err
    );
  }
}

function initTagsSelects() {
  document.querySelectorAll('.ticket-tags-select').forEach(initSingleTagSelect);
}

function changeStatus(ticketId, newStatusId, newClass, newLabel) {
  if (newStatusId !== undefined && ticketId) {
    jQuery
      .ajax({
        type: 'PATCH',
        url: leantime.appUrl + '/api/tickets',
        data: {
          id: ticketId,
          status: newStatusId,
        },
      })
      .done(() => {
        // In this way, the UI does not reflect the actual data, which is not good.
        // But if I instead create a get-request it returns 200 and an otherwise empty
        // response. So this is what I chose to do, and is also what is done in
        // in other places (I am looking at you ticketcontroller.js).

        // Update ALL buttons with this ID (same ticket can appear in multiple views)
        document
          .querySelectorAll(`#status-ticket-${ticketId}`)
          .forEach((button) => {
            button.className = `table-button table-button-status ${newClass}`;
            const circle = button.querySelector('.status-circle');
            if (circle) {
              circle.className = `status-circle ${newClass}`;
            }
            const label = button.querySelector('#status-label');
            if (label) {
              label.textContent = newLabel;
            }
          });
      });
  }
}

// change priority ajax
function changePriority(ticketId, newPriorityId, newLabel) {
  if (newPriorityId && ticketId) {
    jQuery
      .ajax({
        type: 'PATCH',
        url: leantime.appUrl + '/api/tickets',
        data: {
          id: ticketId,
          priority: newPriorityId,
        },
      })
      .done(() => {
        // In this way, the UI does not reflect the actual data, which is not good.
        // But if I instead create a get-request it returns 200 and an otherwise empty
        // response. So this is what I chose to do, and is also what is done in
        // in other places (I am looking at you ticketcontroller.js).

        // Update ALL buttons with this ID (same ticket can appear in multiple views)
        document
          .querySelectorAll(`#priority-ticket-${ticketId}`)
          .forEach((button) => {
            button.className = `table-button table-button-status`;
            const circle = button.querySelector('.priority-circle');
            if (circle) {
              circle.className = `priority-circle priority-bg-${newPriorityId}`;
            }
            const label = button.querySelector('#priority-label');
            if (label) {
              label.textContent = newLabel;
            }
          });
      });
  }
}

// Change duedate ajax
function changeDueDate(event, ticketId, newDueDate) {
  const parentElement = jQuery(event.target).closest('td');

  if (newDueDate && ticketId) {
    const dueDate = window.jQuery.datepicker.formatDate(
      leantime.dateHelper.getFormatFromSettings('dateformat', 'jquery'),
      new Date(newDueDate)
    );
    jQuery
      .ajax({
        type: 'PATCH',
        url: leantime.appUrl + '/api/tickets',
        data: {
          id: ticketId,
          dateToFinish: dueDate,
        },
      })
      .then(() => {
        saveSuccess(parentElement);
      })
      .fail(() => {
        saveError(parentElement);
      });
  }
}

// Change assigned user ajax
function changeAssignedUser(event, ticketId, userId) {
  const parentElement = jQuery(event.target).closest('td');

  if (userId && ticketId) {
    jQuery
      .ajax({
        type: 'PATCH',
        url: leantime.appUrl + '/api/tickets',
        data: {
          id: ticketId,
          editorId: userId,
        },
      })
      .then(() => {
        saveSuccess(parentElement);
      })
      .fail(() => {
        saveError(parentElement);
      });
  }
}

// Change plan hours ajax
function changePlanHours(event, ticketId, newPlanHours) {
  const parentElement = jQuery(event.target).closest('td');

  if (newPlanHours && ticketId) {
    jQuery
      .ajax({
        type: 'PATCH',
        url: leantime.appUrl + '/api/tickets',
        data: {
          id: ticketId,
          planHours: newPlanHours,
        },
      })
      .then(() => {
        saveSuccess(parentElement);
      })
      .fail(() => {
        saveError(parentElement);
      });
  }
}

// Change hours remaining ajax
function changeHoursRemaining(event, ticketId, newHoursRemaining) {
  const parentElement = jQuery(event.target).closest('td');

  if (newHoursRemaining && ticketId) {
    jQuery
      .ajax({
        type: 'PATCH',
        url: leantime.appUrl + '/api/tickets',
        data: {
          id: ticketId,
          hourRemaining: newHoursRemaining,
        },
      })
      .then(() => {
        saveSuccess(parentElement);
      })
      .fail(() => {
        saveError(parentElement);
      });
  }
}

// Change milestone ajax
function changeMilestone(event, ticketId, newMilestoneId) {
  const parentElement = jQuery(event.target).closest('td');
  if (newMilestoneId && ticketId) {
    jQuery
      .ajax({
        type: 'PATCH',
        url: leantime.appUrl + '/api/tickets',
        data: {
          id: ticketId,
          milestoneid: newMilestoneId,
        },
      })
      .then(() => {
        saveSuccess(parentElement);
      })
      .fail(() => {
        saveError(parentElement);
      });
  }
}

// Change tags ajax
function changeTags(event, ticketId, newTags) {
  const parentElement = jQuery(event.target).closest('td');

  if (ticketId) {
    jQuery
      .ajax({
        type: 'PATCH',
        url: leantime.appUrl + '/api/tickets',
        data: {
          id: ticketId,
          tags: newTags || '',
        },
      })
      .then(() => {
        saveSuccess(parentElement);
      })
      .fail(() => {
        saveError(parentElement);
      });
  }
}

// Change sort — client-side DOM sort + silent persist
function changeSortBy(sortBy, clickedTh) {
  const table = clickedTh.closest('table');
  if (!table) return;

  const headers = Array.from(table.querySelectorAll('thead th'));

  // Toggle direction
  const currentCol = table.dataset.sortBy;
  const currentDir = table.dataset.sortDir;
  let direction = 'asc';
  if (currentCol === sortBy && currentDir === 'asc') direction = 'desc';
  table.dataset.sortBy = sortBy;
  table.dataset.sortDir = direction;

  // Update visual indicators immediately
  headers.forEach(function (th) {
    th.classList.remove('sort-asc', 'sort-desc');
  });
  clickedTh.classList.add(direction === 'asc' ? 'sort-asc' : 'sort-desc');

  // Persist sort preference (silent — server is the source of truth on next render)
  const viewId = document.getElementById('selectedViewId');
  if (viewId && viewId.value) {
    fetch(window.location.pathname, {
      method: 'POST',
      credentials: 'include',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: new URLSearchParams({
        action: 'saveSortOrder',
        view: viewId.value,
        sortBy: sortBy,
        sortDirection: direction.toUpperCase(),
      }),
    });
  }

  // Re-fetch from server with the new sort. With pagination, client-side sort
  // would only reorder the visible page; the server sorts the full dataset and
  // resets pagination to page 1.
  const form = document.getElementById('filtersForm');
  if (form) {
    refreshViewTable(form);
  }
}

// --- Live filter helpers ---

/**
 * Capture all filter field values from the form for later restoration.
 *
 * @param {HTMLFormElement} form
 * @returns {object} Field values keyed by name.
 */
/**
 * Build a select2 dropdownAdapter that renders a search input at the top of
 * the open dropdown panel. Required because select2 v4 puts the search inline
 * inside the selection bar for multi-selects, and our selection bars are
 * hidden by CSS — so without this, users have to type blind to filter.
 *
 * Select2's bundled AMD shim (almond) defers `require([...], cb)` via
 * setTimeout unless you pass `forceSync=true` as the 4th argument, so we
 * forward that flag — otherwise the callback would fire after we've already
 * returned `undefined`.
 *
 * @returns {Function} A constructor to pass as `dropdownAdapter` to select2().
 */
function buildDropdownAdapterWithSearch() {
  let Adapter;
  $.fn.select2.amd.require(
    [
      'select2/dropdown',
      'select2/dropdown/search',
      'select2/dropdown/dropdownCss',
      'select2/dropdown/attachBody',
      'select2/utils',
    ],
    function (Dropdown, DropdownSearch, DropdownCss, AttachBody, Utils) {
      // Decorator order matches select2's default chain (see dist
      // select2.js around the `options.dropdownAdapter == null` branch):
      // base → search → dropdownCss → attachBody. CloseOnSelect is
      // intentionally omitted so `closeOnSelect: false` stays in effect.
      let A = Utils.Decorate(Dropdown, DropdownSearch);
      A = Utils.Decorate(A, DropdownCss);
      A = Utils.Decorate(A, AttachBody);
      Adapter = A;
    },
    null,
    true
  );
  return Adapter;
}

/**
 * Update the underlying <select> value and re-sync the open select2 dropdown's
 * visible checkboxes. We need this because select2 v4 with `closeOnSelect:
 * false` does not redraw the open results panel when val() is changed
 * programmatically — so a mutex change (e.g. "remove __all when a real
 * option is picked") would take effect in the data but the user would still
 * see __all visually ticked.
 *
 * @param {JQuery} $select The wrapped select (must have an id attribute).
 * @param {string[]} newVals The new value array.
 */
function applySelectMutex($select, newVals) {
  $select.val(newVals).trigger('change');

  const selectId = $select.attr('id');
  if (!selectId) return;
  const results = document.getElementById('select2-' + selectId + '-results');
  if (!results) return;

  const valSet = new Set(newVals.map(String));
  const optionIdRegex = new RegExp(
    '^select2-' + selectId + '-result-[^-]+-(.+)$'
  );
  results.querySelectorAll('.select2-results__option').forEach(function (li) {
    const m = (li.id || '').match(optionIdRegex);
    if (!m) return;
    const isSelected = valSet.has(m[1]);
    li.setAttribute('aria-selected', isSelected ? 'true' : 'false');
    li.classList.toggle('select2-results__option--selected', isSelected);
  });
}

/**
 * Return the IDs of every concrete option in the <select>, excluding the
 * synthetic "__all" sentinel. Used by the bulk toggle to compute the
 * "everything ticked" state without depending on what select2 currently
 * renders (the user may have filtered the visible list via search).
 *
 * @param {JQuery} $select The wrapped multi-select.
 * @returns {string[]} Concrete option values.
 */
function getConcreteOptionIds($select) {
  return $select
    .find('option')
    .map(function () {
      return this.value;
    })
    .get()
    .filter((v) => v !== '__all');
}

/**
 * "All selected" for the bulk toggle = every concrete option is ticked.
 * The __all sentinel is treated as "no concrete picks" — visually nothing in
 * the list looks ticked — so the button reads "Select all" in that state.
 *
 * @param {string[]} values Current select value (array form).
 * @param {string[]} concreteIds All non-__all option IDs.
 * @returns {boolean}
 */
function allConcreteSelected(values, concreteIds) {
  if (!values || !values.length) return false;
  if (values.includes('__all')) return false;
  if (concreteIds.length === 0) return false;
  const set = new Set(values.map(String));
  return concreteIds.every((id) => set.has(String(id)));
}

/**
 * Insert a Select-all/Deselect-all toggle button into the open dropdown
 * panel and wire it to flip between "every concrete option ticked" and
 * "only __all ticked". The button label tracks the underlying select's
 * value via change.select2 so manual ticks keep it in sync; the listener
 * is bound on the dropdown element, so it's garbage-collected with the
 * panel when select2 closes.
 *
 * @param {HTMLElement} dropdown The .project-overview-dropdown panel root.
 * @param {JQuery} $select The wrapped multi-select being opened.
 */
function installBulkToggleButton(dropdown, $select) {
  const i18n = window.projectOverviewI18n || {};
  const selectAllLabel = i18n.selectAll || 'Select all';
  const deselectAllLabel = i18n.deselectAll || 'Deselect all';
  const concreteIds = getConcreteOptionIds($select);

  // select2 keeps the dropdown DOM around between opens (it just
  // attaches/detaches via the AttachBody adapter), so we'd append a fresh
  // button on every reopen. Replace any existing one before appending.
  dropdown
    .querySelectorAll('.project-overview-dropdown__toggle-all')
    .forEach((el) => el.remove());

  const button = document.createElement('button');
  button.type = 'button';
  button.className = 'project-overview-dropdown__toggle-all';

  // Place the button inline inside the search row so it shares horizontal
  // space with the input. The container gets a flex layout via CSS.
  const searchContainer = dropdown.querySelector('.select2-search--dropdown');
  if (searchContainer) {
    searchContainer.classList.add('select2-search--with-toggle-all');
    searchContainer.appendChild(button);
  } else {
    dropdown.insertBefore(button, dropdown.firstChild);
  }

  function syncLabel() {
    const vals = $select.val() || [];
    button.textContent = allConcreteSelected(vals, concreteIds)
      ? deselectAllLabel
      : selectAllLabel;
  }
  syncLabel();

  button.addEventListener('click', function (e) {
    e.preventDefault();
    const vals = $select.val() || [];
    if (allConcreteSelected(vals, concreteIds)) {
      applySelectMutex($select, ['__all']);
    } else {
      applySelectMutex($select, concreteIds);
    }
    syncLabel();
    // Return focus to the search input so the user can continue to
    // navigate the dropdown via keyboard after clicking the button.
    const input = dropdown.querySelector('.select2-search__field');
    if (input) input.focus();
  });

  // Clear any leftover listeners from a previous open (same dropdown DOM is
  // reused, but each open creates a new syncLabel closure).
  $select.off('change.povBulkToggle');
  $select.on('change.povBulkToggle', syncLabel);
}

/**
 * Wire keyboard navigation inside the open dropdown:
 *   - Search input: Enter clicks the Select-all/Deselect-all button when
 *     one is present, Escape closes the dropdown, ArrowDown hands focus to
 *     the first selectable option.
 *   - Options: ArrowDown/ArrowUp move focus between options. ArrowUp on the
 *     first option returns focus to the search input. Space toggles the
 *     focused option. Escape closes the dropdown.
 *
 * Moving DOM focus onto the <li> is what lets Space toggle selection without
 * conflicting with typing a space character in the search field.
 *
 * @param {HTMLElement} dropdown The .project-overview-dropdown panel root.
 * @param {JQuery} $select The wrapped multi-select being opened.
 * @param {HTMLElement} input The search input inside the dropdown.
 */
function installDropdownKeyboardNav(dropdown, $select, input) {
  // select2 reuses the same dropdown DOM (and the same search input /
  // results container) across reopens of the same select — without this
  // guard each open would stack another keydown listener.
  if (dropdown.dataset.povKeyboardWired === 'true') return;
  dropdown.dataset.povKeyboardWired = 'true';

  const resultsContainer = dropdown.querySelector('.select2-results__options');

  function focusableOptions() {
    if (!resultsContainer) return [];
    return Array.from(
      resultsContainer.querySelectorAll(
        '.select2-results__option[aria-selected]'
      )
    );
  }

  function clearHighlight() {
    if (!resultsContainer) return;
    resultsContainer
      .querySelectorAll('.select2-results__option--highlighted')
      .forEach((li) =>
        li.classList.remove('select2-results__option--highlighted')
      );
  }

  function focusOption(li) {
    if (!li) return;
    li.setAttribute('tabindex', '-1');
    clearHighlight();
    li.classList.add('select2-results__option--highlighted');
    li.focus();
    if (typeof li.scrollIntoView === 'function') {
      li.scrollIntoView({ block: 'nearest' });
    }
  }

  function returnFocusToSearch() {
    input.focus();
    clearHighlight();
    // select2 re-applies --highlighted (commonly to the first selected
    // option) on its own focus/results listeners that run after ours. A
    // short-lived MutationObserver strips it as it gets re-added; it
    // disconnects on the next user interaction or after a safety timeout.
    if (!resultsContainer) return;
    const observer = new MutationObserver(clearHighlight);
    observer.observe(resultsContainer, {
      attributes: true,
      attributeFilter: ['class'],
      subtree: true,
    });
    const stop = () => observer.disconnect();
    input.addEventListener('input', stop, { once: true });
    input.addEventListener('keydown', stop, { once: true });
    setTimeout(stop, 500);
  }

  function closeDropdown() {
    $select.data('_povSuppressOpen', true);
    $select.select2('close');
    setTimeout(() => $select.removeData('_povSuppressOpen'), 200);
  }

  input.addEventListener(
    'keydown',
    function (e) {
      const isEscape = e.key === 'Escape' || e.keyCode === 27;
      const isEnter = e.key === 'Enter' || e.keyCode === 13;
      const isDown = e.key === 'ArrowDown' || e.keyCode === 40;
      if (!isEscape && !isEnter && !isDown) return;
      e.preventDefault();
      e.stopPropagation();
      e.stopImmediatePropagation();
      if (isEscape) {
        closeDropdown();
        return;
      }
      if (isEnter) {
        // From the search input, Enter triggers the Select-all/Deselect-all
        // button when one is present. For dropdowns without a toggle button
        // (Other filters), Enter does nothing — selection is via Space on a
        // focused option after ArrowDown.
        const toggle = dropdown.querySelector(
          '.project-overview-dropdown__toggle-all'
        );
        if (toggle) toggle.click();
        return;
      }
      // ArrowDown: hand focus to the first selectable option so SPACE can be
      // used to toggle selection without inserting a space in the search box.
      const opts = focusableOptions();
      if (opts.length) focusOption(opts[0]);
    },
    true
  );

  if (!resultsContainer) return;
  resultsContainer.addEventListener(
    'keydown',
    function (e) {
      const target = e.target.closest('.select2-results__option');
      if (!target) return;
      const isDown = e.key === 'ArrowDown' || e.keyCode === 40;
      const isUp = e.key === 'ArrowUp' || e.keyCode === 38;
      const isSpace = e.key === ' ' || e.keyCode === 32;
      const isEscape = e.key === 'Escape' || e.keyCode === 27;
      if (!isDown && !isUp && !isSpace && !isEscape) return;
      e.preventDefault();
      e.stopPropagation();
      if (isEscape) {
        closeDropdown();
        return;
      }
      if (isSpace) {
        $(target).trigger('mouseup');
        return;
      }
      const opts = focusableOptions();
      const idx = opts.indexOf(target);
      if (isDown) {
        const next = opts[idx + 1];
        if (next) focusOption(next);
        return;
      }
      // ArrowUp: previous option, or back to the search input from the top.
      if (idx <= 0) {
        returnFocusToSearch();
        return;
      }
      focusOption(opts[idx - 1]);
    },
    true
  );
}

/**
 * Tab / Shift+Tab between filter dropdowns: closes the currently open
 * filter (if any) and opens the next/previous one. Native selects can't
 * be opened programmatically, so the entry for `#dateOptions` just gets
 * focus. Disabled filters are skipped.
 *
 * Idempotent — uses a global flag so repeated HTMX swaps that re-init the
 * table won't stack multiple listeners.
 */
function installFilterTabNavigation() {
  if (window._povFilterTabWired) return;
  window._povFilterTabWired = true;

  const filterList = [
    { id: '#userSelect', type: 'select2' },
    { id: '#dateOptions', type: 'native' },
    { id: '#dateRange', type: 'flatpickr' },
    { id: '#projectSelect', type: 'select2' },
    { id: '#filterSelect', type: 'select2' },
    { id: '#columnSelect', type: 'select2' },
  ];

  function isOpen(f) {
    if (f.type === 'select2') {
      return !!$(f.id).data('select2')?.isOpen?.();
    }
    if (f.type === 'flatpickr') {
      return !!document.getElementById('dateRange')?._flatpickr?.isOpen;
    }
    return false;
  }

  function isDisabled(f) {
    const $el = $(f.id);
    return !$el.length || $el.is(':disabled');
  }

  function currentIndex() {
    for (let i = 0; i < filterList.length; i++) {
      if (isOpen(filterList[i])) return i;
    }
    // No dropdown open — fall back to the focused filter proxy.
    const active = document.activeElement;
    if (!active) return -1;
    for (let i = 0; i < filterList.length; i++) {
      const $el = $(filterList[i].id);
      if (!$el.length) continue;
      const proxy =
        filterList[i].type === 'select2' ? $el.next('.select2')[0] : $el[0];
      if (proxy && (proxy === active || proxy.contains(active))) return i;
    }
    return -1;
  }

  function closeFilter(f) {
    if (f.type === 'select2') $(f.id).select2('close');
    else if (f.type === 'flatpickr') {
      document.getElementById('dateRange')?._flatpickr?.close();
    }
  }

  function openFilter(f) {
    if (f.type === 'select2') {
      $(f.id).select2('open');
      return;
    }
    if (f.type === 'flatpickr') {
      const el = document.getElementById('dateRange');
      if (!el) return;
      el.focus();
      // Honor the existing "only opens for custom date type" rule.
      if (el._flatpickr?.config?.clickOpens) el._flatpickr.open();
      return;
    }
    // Native <select> can't be opened programmatically — just focus it.
    document.querySelector(f.id)?.focus();
  }

  document.addEventListener(
    'keydown',
    function (e) {
      if (e.key !== 'Tab') return;
      const idx = currentIndex();
      if (idx === -1) return;
      const dir = e.shiftKey ? -1 : 1;
      let nextIdx = idx + dir;
      while (
        nextIdx >= 0 &&
        nextIdx < filterList.length &&
        isDisabled(filterList[nextIdx])
      ) {
        nextIdx += dir;
      }
      if (nextIdx < 0 || nextIdx >= filterList.length) {
        // No filter on that side — let native Tab leave the filter row.
        closeFilter(filterList[idx]);
        return;
      }
      e.preventDefault();
      e.stopPropagation();
      closeFilter(filterList[idx]);
      openFilter(filterList[nextIdx]);
    },
    true
  );
}

function captureFormState(form) {
  return {
    users: $('#userSelect', form).val() || [],
    filters: $('#filterSelect', form).val() || [],
    projects: $('#projectSelect', form).val() || [],
    columns: $('#columnSelect', form).val() || [],
    dateType: $('#dateOptions', form).val(),
    fromDate: $('#fromDate', form).val(),
    toDate: $('#toDate', form).val(),
    dateRangeText: $('#dateRange', form).val(),
  };
}

/**
 * Restore previously captured form state into the current filter form.
 * Triggers change events so select2/flatpickr update, and the table refreshes.
 *
 * @param {object} state The state object from captureFormState.
 */
function restoreFormState(state) {
  // Restore select2 multi-selects (set values without triggering change yet)
  $('#userSelect').val(state.users).trigger('change.select2');
  $('#filterSelect').val(state.filters).trigger('change.select2');
  $('#projectSelect')
    .val(state.projects || [])
    .trigger('change.select2');
  $('#columnSelect').val(state.columns).trigger('change.select2');

  // Restore date type (triggers the dateRange toggle handler)
  $('#dateOptions').val(state.dateType).trigger('change');

  // For custom dates, restore the actual date values after the dateType handler ran
  if (state.dateType === 'custom') {
    $('#fromDate').val(state.fromDate);
    $('#toDate').val(state.toDate);

    const fp = document.getElementById('dateRange')?._flatpickr;
    if (fp && state.fromDate && state.toDate) {
      // Parse dd-mm-yyyy to Date objects
      const parseDMY = (str) => {
        const [d, m, y] = str.split('-').map(Number);
        return new Date(y, m - 1, d);
      };
      fp.setDate([parseDMY(state.fromDate), parseDMY(state.toDate)], false);
    }
  }
}

function serializeFilterForm(form) {
  const formData = new FormData(form);
  // Exclude metadata fields that don't represent filter state
  formData.delete('action');
  formData.delete('overwriteView');
  formData.delete('view');
  formData.delete('subscribeToken');
  return new URLSearchParams(formData).toString();
}

function refreshViewTable(form) {
  const viewId = document.getElementById('selectedViewId');
  if (!viewId || !viewId.value) return;

  const formData = new FormData(form);

  // Include current sort state from the active table
  const activePanel = document.getElementById('view-' + viewId.value);
  if (activePanel) {
    const table = activePanel.querySelector('table');
    if (table) {
      formData.set('sortBy', table.dataset.sortBy || 'priority');
      formData.set(
        'sortDirection',
        (table.dataset.sortDir || 'asc').toUpperCase()
      );
    }
  }

  // Always start at page 1 — refreshViewTable is called for filter, sort, and
  // lazy-load events, all of which should reset pagination.
  formData.set('page', '1');

  // Cancel any in-flight refresh or lazy-load on this panel so a slow
  // earlier response can't overwrite fresh data.
  if (activePanel) {
    teardownLazyLoad(activePanel);
    if (activePanel._refreshController) {
      activePanel._refreshController.abort();
    }
  }
  const controller = new AbortController();
  if (activePanel) activePanel._refreshController = controller;

  fetch(
    '/ProjectOverview/ProjectOverview/loadViewTable/' +
      encodeURIComponent(viewId.value),
    {
      method: 'POST',
      credentials: 'include',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      body: new URLSearchParams(formData),
      signal: controller.signal,
    }
  )
    .then(function (response) {
      if (!response.ok) {
        return response.text().then(function (body) {
          const err = new Error('HTTP ' + response.status);
          err.status = response.status;
          err.responseBody = body;
          throw err;
        });
      }
      return response.text();
    })
    .then(function (html) {
      if (controller.signal.aborted) return;
      if (!activePanel) return;
      activePanel.innerHTML = html;
      // Re-init components inside the new table
      initTagsSelects();
      if (typeof tippy === 'function') {
        tippy(activePanel.querySelectorAll('[data-tippy-content]'));
      }
      attachLazyLoad(activePanel);
    })
    .catch(function (err) {
      if (err.name === 'AbortError') return;
      console.error(
        '[ProjectOverview] View load failed:',
        err,
        err.responseBody || ''
      );
      if (activePanel) {
        const isAuthError = err.status === 401 || err.status === 403;
        const msg = isAuthError
          ? (window.projectOverviewI18n &&
              window.projectOverviewI18n.sessionExpired) ||
            '[i18n missing] session_expired'
          : (window.projectOverviewI18n &&
              window.projectOverviewI18n.couldNotLoadView) ||
            '[i18n missing] could_not_load_view';
        const errEl = document.createElement('div');
        errEl.className = 'lazy-row-status lazy-row-error';
        errEl.style.padding = '24px';

        const icon = document.createElement('span');
        icon.className = 'lazy-row-status-icon';
        icon.setAttribute('aria-hidden', 'true');
        icon.textContent = '⚠';

        const text = document.createElement('span');
        text.className = 'lazy-row-status-text';
        text.textContent = msg;

        errEl.append(icon, text);
        activePanel.replaceChildren(errEl);
      }
    })
    .finally(function () {
      if (activePanel && activePanel._refreshController === controller) {
        activePanel._refreshController = null;
      }
    });
}

/**
 * Wire the manual "Load more" + Retry buttons inside the sentinel for `panel`.
 * Idempotent — clones the buttons before re-binding so we never stack
 * listeners across repeated calls (e.g., after a splice).
 *
 * @param {HTMLElement} panel
 */
function attachLazyLoad(panel) {
  if (!panel) return;
  teardownLazyLoad(panel);

  const sentinel = panel.querySelector('.lazy-row-sentinel');
  if (!sentinel) return;

  bindSentinelButtons(panel, sentinel);
}

/**
 * Cancel any in-flight lazy-load fetch on `panel`. Safe to call before or
 * after the panel's DOM has been replaced.
 *
 * @param {HTMLElement} panel
 */
function teardownLazyLoad(panel) {
  if (!panel) return;
  if (panel._lazyController) {
    panel._lazyController.abort();
    panel._lazyController = null;
  }
}

function bindSentinelButtons(panel, sentinel) {
  const loadBtn = sentinel.querySelector('.lazy-row-load-more');
  if (loadBtn) {
    loadBtn.addEventListener('click', function () {
      loadNextLazyPage(panel, sentinel);
    });
  }
  const retryBtn = sentinel.querySelector('.lazy-row-retry');
  if (retryBtn) {
    retryBtn.addEventListener('click', function () {
      loadNextLazyPage(panel, sentinel);
    });
  }
}

/**
 * Fetch the next page for `panel`'s sentinel and splice the response in.
 *
 * @param {HTMLElement} panel
 * @param {HTMLTableRowElement} sentinel
 */
function loadNextLazyPage(panel, sentinel) {
  if (sentinel.dataset.state === 'loading') return;
  const url = sentinel.dataset.nextUrl;
  const page = sentinel.dataset.nextPage;
  if (!url || !page) return;

  sentinel.dataset.state = 'loading';
  showLazyLoading(sentinel);

  const form = document.getElementById('filtersForm');
  const formData = form ? new FormData(form) : new FormData();

  const table = panel.querySelector('table');
  if (table) {
    formData.set('sortBy', table.dataset.sortBy || 'priority');
    formData.set(
      'sortDirection',
      (table.dataset.sortDir || 'asc').toUpperCase()
    );
  }
  formData.set('page', page);

  // Cancel any in-flight lazy-load fetch on this panel before issuing a new one.
  if (panel._lazyController) {
    panel._lazyController.abort();
  }
  const controller = new AbortController();
  panel._lazyController = controller;

  fetch(url, {
    method: 'POST',
    credentials: 'include',
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
    body: new URLSearchParams(formData),
    signal: controller.signal,
  })
    .then(function (response) {
      if (!response.ok) {
        // Read the body so the message isn't a useless "HTTP 500"
        return response.text().then(function (body) {
          const err = new Error('HTTP ' + response.status);
          err.status = response.status;
          err.responseBody = body;
          throw err;
        });
      }
      return response.text();
    })
    .then(function (html) {
      if (controller.signal.aborted) return;
      if (!sentinel.isConnected) return;

      // Use DOMParser for robust fragment parsing — handles whitespace,
      // partial markup, and edge cases more reliably than innerHTML on tbody.
      const doc = new DOMParser().parseFromString(
        '<table><tbody>' + (html || '') + '</tbody></table>',
        'text/html'
      );
      const parsedTbody = doc.querySelector('tbody');
      const newRows = parsedTbody ? Array.from(parsedTbody.children) : [];

      const parent = sentinel.parentNode;
      if (!parent) return;

      try {
        // Splice in the new rows, then drop the spent sentinel.
        for (const row of newRows) {
          parent.insertBefore(row, sentinel);
        }
        sentinel.remove();

        // Initialize TomSelect on any tag-selects we just added. We do this
        // explicitly per-row in addition to the global initTagsSelects() call
        // so newly-inserted selects get wired even if the global pass misses
        // them for any reason.
        for (const row of newRows) {
          if (row.querySelectorAll) {
            row.querySelectorAll('.ticket-tags-select').forEach(function (sel) {
              if (!sel.tomselect) initSingleTagSelect(sel);
            });
          }
        }
        // And cover any holdouts via the global pass.
        initTagsSelects();

        if (typeof tippy === 'function') {
          tippy(
            panel.querySelectorAll(
              '[data-tippy-content]:not([data-tippy-instance])'
            )
          );
        }

        // Re-attach to the new sentinel (no-op when this was the last page).
        attachLazyLoad(panel);
      } catch (spliceErr) {
        console.error('[ProjectOverview] Lazy-load splice failed:', spliceErr);
        const live = panel.querySelector('.lazy-row-sentinel');
        const target = live && live.isConnected ? live : sentinel;
        if (target && target.isConnected) {
          target.dataset.state = 'error';
          showLazyError(
            target,
            (window.projectOverviewI18n &&
              window.projectOverviewI18n.failedToInsertRows) ||
              '[i18n missing] failed_to_insert_rows'
          );
        }
      }
    })
    .catch(function (err) {
      if (err.name === 'AbortError') return;
      console.error(
        '[ProjectOverview] Lazy-load failed:',
        err,
        err.responseBody || ''
      );
      // Make sure the sentinel is in a clickable error state, even if
      // something downstream blew up before we got there.
      const live = panel.querySelector('.lazy-row-sentinel');
      const target = live || sentinel;
      if (target && target.isConnected) {
        target.dataset.state = 'error';
        const isAuthError = err.status === 401 || err.status === 403;
        const msg = isAuthError
          ? (window.projectOverviewI18n &&
              window.projectOverviewI18n.sessionExpired) ||
            '[i18n missing] session_expired'
          : (window.projectOverviewI18n &&
              window.projectOverviewI18n.couldNotLoadMoreRows) ||
            '[i18n missing] could_not_load_more_rows';
        showLazyError(target, msg);
      }
    })
    .finally(function () {
      if (panel._lazyController === controller) {
        panel._lazyController = null;
      }
    });
}

function setSentinelState(sentinel, visible) {
  ['ready', 'loading', 'error'].forEach(function (key) {
    const node = sentinel.querySelector('.lazy-row-' + key);
    if (!node) return;
    if (key === visible) node.removeAttribute('hidden');
    else node.setAttribute('hidden', '');
  });
}

function showLazyLoading(sentinel) {
  setSentinelState(sentinel, 'loading');
}

function showLazyError(sentinel, message) {
  setSentinelState(sentinel, 'error');
  const error = sentinel.querySelector('.lazy-row-error');
  const text = error ? error.querySelector('.lazy-row-status-text') : null;
  if (text && message) text.textContent = message;
  // After error, allow another click to retry.
  sentinel.dataset.state = 'ready';
}

function toggleUnsavedIndicator(targetViewId, hasChanges) {
  if (!targetViewId) return;

  // Track which views have unsaved changes
  if (!window._viewsWithUnsavedChanges) window._viewsWithUnsavedChanges = {};
  window._viewsWithUnsavedChanges[targetViewId] = hasChanges;

  // Clear cached form data when changes are reverted
  if (!hasChanges && window._viewCachedFormData) {
    delete window._viewCachedFormData[targetViewId];
  }

  const tab = document.querySelector(
    '#projectOverviewTabs > ul > li[data-target="' + targetViewId + '"]'
  );
  if (tab) {
    tab.classList.toggle('has-unsaved-changes', hasChanges);
  }

  // Mirror onto the sidebar item.
  const sidebarLink = document.querySelector(
    'a.projectoverview-view-item[data-view-key="' + targetViewId + '"]'
  );
  if (sidebarLink && sidebarLink.parentElement) {
    sidebarLink.parentElement.classList.toggle(
      'has-unsaved-changes',
      hasChanges
    );
  }

  // The save-changes button is visible when the active view has unsaved changes,
  // or whenever the synthetic "new" tab is active (it always wants to be saveable
  // once the user has interacted with it; the dirty-tracking handles the latter).
  syncSaveChangesVisibility();
}

/**
 * Whether the Save changes button should be visible for the given view id.
 * Real views show the button only when they have unsaved changes; the
 * synthetic `__new` tab always shows it — its default filter configuration
 * is already a valid view that the user can save as-is without first
 * perturbing a filter.
 *
 * @param {string} viewId
 * @returns {boolean}
 */
function shouldShowSaveChangesBtn(viewId) {
  if (viewId === '__new') return true;
  return !!(
    window._viewsWithUnsavedChanges && window._viewsWithUnsavedChanges[viewId]
  );
}

/**
 * Read the active view from #selectedViewId and toggle the Save changes button
 * accordingly.
 */
function syncSaveChangesVisibility() {
  const activeViewId = document.getElementById('selectedViewId');
  const saveBtn = document.querySelector('.save-changes-btn');
  if (!activeViewId || !saveBtn) return;
  saveBtn.style.display = shouldShowSaveChangesBtn(activeViewId.value)
    ? ''
    : 'none';
}

// Save success animation
function saveSuccess(elem) {
  elem.addClass('save-success');

  setTimeout(() => {
    elem.removeClass('save-success');
  }, 1000);
}

// Save error animation
function saveError(elem) {
  elem.addClass('save-error');

  setTimeout(() => {
    elem.removeClass('save-error');
  }, 1000);
}
