/**
 * Filter UI for the Project Overview plugin.
 *
 * Wires the filter pill row at the top of the page:
 *   - select2 init for users / projects / other filters / columns, plus the
 *     `__all` sentinel mutex that keeps "show everything" mutually exclusive
 *     with concrete picks.
 *   - A custom dropdownAdapter that puts a search input at the top of the
 *     open panel (select2 v4 otherwise only renders search inline in the
 *     hidden selection bar for multi-selects).
 *   - Select-all / Deselect-all toggle inside the dropdown.
 *   - Keyboard nav inside the dropdown (ArrowDown/Up, Space toggles the
 *     focused option, Enter clicks the bulk toggle, Escape closes).
 *   - Tab / Shift+Tab between filter pills.
 *   - Collapsible "filters" container with localStorage persistence.
 *   - Live filter refresh + dirty tracking against the active view.
 *
 * Live updates delegate back to the entrypoint via two callbacks so this
 * module stays import-direction-clean (no cycle with `project-overview.js`):
 *   - `refreshViewTable(form)` — re-fetch the active view's table.
 *   - `toggleUnsavedIndicator(viewId, hasChanges)` — flip the tab / sidebar
 *     unsaved badge and the Save changes button visibility.
 *
 * Bundled into the same `project-overview.js` output as the rest of the
 * plugin's JS (single entrypoint in `webpack.config.js`).
 */

import flatpickr from 'flatpickr';
import { Danish } from 'flatpickr/dist/l10n/da.js';

/**
 * Initializes the collapsible filters toggle with localStorage persistence.
 */
export function initFiltersToggle() {
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
 * @param {object} callbacks
 * @param {(form: HTMLFormElement) => void} callbacks.refreshViewTable
 *   Re-fetch the active view's table — invoked (debounced) on any filter
 *   change.
 * @param {(viewId: string|null, hasChanges: boolean) => void} callbacks.toggleUnsavedIndicator
 *   Flip the per-view dirty indicator (tab badge + sidebar badge + Save
 *   changes button visibility).
 * @return {void} This function does not return a value.
 */
export function initProjectOverviewFilters({
  refreshViewTable,
  toggleUnsavedIndicator,
}) {
  // Sync save button visibility with the active tab on each filters reload (the
  // "new" tab always shows the button; other tabs only show it when dirty).
  updateSaveBtnVisibility();
  updateResetBtnVisibility();

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
    fpInstance.config.onChange.push(function (selectedDates) {
      if (selectedDates && selectedDates.length === 2) {
        onFilterChange();
      }
    });
  }
}

/**
 * Wire the document-level select2:opening / select2:open handlers for the
 * four filter dropdowns. Delegated on document so they cover each fresh
 * select2 instance after HTMX swaps; idempotent via a global flag so
 * repeated invocations don't stack listeners.
 *
 * Capture-phase key handling for the dropdown search input runs inside
 * `installDropdownKeyboardNav`:
 *   - Enter: select2's default Enter on multi-select is "select-only", but
 *     we want toggle behavior matching what a mouse click does. Triggering
 *     mouseup on the highlighted result reuses select2's own click handler,
 *     which already implements toggle.
 *   - Escape: select2 closes the dropdown, but in our layout something in
 *     the post-close focus dance reopens it almost immediately. We can't
 *     reliably stop the reopen at the keydown level (it survives even
 *     stopImmediatePropagation from a capture-phase handler), so instead we
 *     mark the select "suppress next open" and cancel any `select2:opening`
 *     event that fires within a short window after Escape.
 */
export function installFilterDropdownEnhancements() {
  if (window._povFilterDropdownEnhancementsWired) return;
  window._povFilterDropdownEnhancementsWired = true;

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
}

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
export function installFilterTabNavigation() {
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

/**
 * Capture all filter field values from the form for later restoration.
 *
 * @param {HTMLFormElement} form
 * @returns {object} Field values keyed by name.
 */
export function captureFormState(form) {
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
export function restoreFormState(state) {
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

export function serializeFilterForm(form) {
  const formData = new FormData(form);
  // Exclude metadata fields that don't represent filter state
  formData.delete('action');
  formData.delete('overwriteView');
  formData.delete('view');
  formData.delete('subscribeToken');
  return new URLSearchParams(formData).toString();
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
export function shouldShowSaveChangesBtn(viewId) {
  if (viewId === '__new') return true;
  return !!(
    window._viewsWithUnsavedChanges && window._viewsWithUnsavedChanges[viewId]
  );
}

/**
 * Whether the Reset changes button should be visible for the given view id.
 * Unlike the Save button, Reset is only meaningful when the user has
 * perturbed the form — even on the synthetic `__new` tab — because resetting
 * an already-pristine state would be a no-op.
 *
 * @param {string} viewId
 * @returns {boolean}
 */
export function shouldShowResetChangesBtn(viewId) {
  return !!(
    window._viewsWithUnsavedChanges && window._viewsWithUnsavedChanges[viewId]
  );
}

/**
 * Read the active view from #selectedViewId and toggle the Save changes button
 * accordingly.
 */
export function updateSaveBtnVisibility() {
  const activeViewId = document.getElementById('selectedViewId');
  const saveBtn = document.querySelector('.save-changes-btn');
  if (!activeViewId || !saveBtn) return;
  saveBtn.style.display = shouldShowSaveChangesBtn(activeViewId.value)
    ? ''
    : 'none';
}

/**
 * Read the active view from #selectedViewId and toggle the Reset changes
 * button accordingly.
 */
export function updateResetBtnVisibility() {
  const activeViewId = document.getElementById('selectedViewId');
  const resetBtn = document.querySelector('.reset-changes-btn');
  if (!activeViewId || !resetBtn) return;
  resetBtn.style.display = shouldShowResetChangesBtn(activeViewId.value)
    ? ''
    : 'none';
}

/**
 * Wire the document-level click handler for the Reset changes button.
 * Delegated on document so a single listener covers the button across HTMX
 * swaps of the filters partial. Idempotent via a global flag.
 *
 * On click: clear the active view's dirty indicator (tab/sidebar badges,
 * cached form data) and trigger HTMX to re-fetch the canonical filters
 * partial for that view. For a saved view this restores the server-stored
 * configuration; for `__new` it restores the default initial configuration.
 * The `htmx:afterSettle` handler in the entrypoint then refreshes the table
 * to match the freshly-loaded filters.
 *
 * @param {object} callbacks
 * @param {(viewId: string|null, hasChanges: boolean) => void} callbacks.toggleUnsavedIndicator
 *   Clear the per-view dirty indicator before triggering the HTMX swap so
 *   the tab/sidebar badges flip immediately rather than after the network
 *   round-trip.
 */
export function installFilterReset({ toggleUnsavedIndicator }) {
  if (window._povFilterResetWired) return;
  window._povFilterResetWired = true;

  document.addEventListener('click', function (e) {
    const btn = e.target.closest('.reset-changes-btn');
    if (!btn) return;
    e.preventDefault();

    const form = document.getElementById('filtersForm');
    const viewField = form?.querySelector('input[name="view"]');
    const activeViewIdInput = document.getElementById('selectedViewId');
    const viewId =
      (viewField && viewField.value) ||
      (activeViewIdInput && activeViewIdInput.value) ||
      null;
    if (!viewId) return;

    // Clear the dirty indicator (tab badge, sidebar badge, cached form data,
    // save/reset button visibility) before the swap so the UI updates
    // immediately. _viewInitialStates is also dropped so the post-swap
    // `initProjectOverviewFilters` re-captures it from the canonical form.
    toggleUnsavedIndicator(viewId, false);
    if (window._viewInitialStates) {
      delete window._viewInitialStates[viewId];
    }

    // Mark this swap as a reset so the entrypoint's afterSettle handler
    // also refreshes the table (the canonical filters may differ from what
    // the table currently shows).
    window._povResetPendingViewId = viewId;

    const container = document.getElementById('filtersContainer');
    if (!container) return;
    // Use htmx.ajax() rather than htmx.trigger(container, 'load') — the
    // `hx-trigger="load"` on #filtersContainer is one-shot and only fires
    // when the element is first inserted into the DOM, so a programmatic
    // re-trigger wouldn't issue the request. htmx.ajax() bypasses the
    // trigger configuration and issues the swap directly.
    htmx.ajax(
      'GET',
      '/ProjectOverview/ProjectOverview/loadFilters/' +
        encodeURIComponent(viewId),
      { target: '#filtersContainer', swap: 'innerHTML' }
    );
  });
}
