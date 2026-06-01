/**
 * Sidebar view navigation for the Project Overview plugin.
 *
 * The saved-view items themselves are injected server-side into Leantime's
 * global menu by `register.php::addProjectOverviewMenuPoint()` — see that
 * function for the HTML shape (each `<a class="projectoverview-view-item">`
 * carries `data-view-key` plus htmx attrs). This module layers extra
 * client-side behaviour on top:
 *
 *   - Clicks on a view item drive the (CSS-hidden) jQuery UI tabs widget so
 *     the existing activate handler runs.
 *   - The active sidebar item is mirrored from `#selectedViewId`.
 *   - Saved-view items live inside a max-height scroller so a long list
 *     can't push the layout.
 *   - Drag-reorder is scoped to plugin view items only.
 *   - The 3-dot trigger inside each item opens the shared `#view-context-menu`.
 *
 * The module exports the two functions that callers outside it need:
 *   - `initSidebarViewNavigation()` — wires everything on page load.
 *   - `applySidebarActiveState()` — re-applied from the tabs activate handler
 *     so the sidebar stays in sync when the active view changes.
 *
 * Bundled into the same `project-overview.js` output as the rest of the
 * plugin's JS (single entrypoint in `webpack.config.js`).
 */

/**
 * Wire all sidebar behaviour on the ProjectOverview page. No-op on other
 * pages — detected via the presence of `#filtersContainer`.
 */
export function initSidebarViewNavigation() {
  if (!document.getElementById('filtersContainer')) return;

  applySidebarActiveState();

  document.body.addEventListener('click', function (e) {
    // Context-menu trigger (a child of the <a>) — intercept before the link.
    const trigger = e.target.closest('[data-context-trigger]');
    if (trigger) {
      const link = trigger.closest('a.projectoverview-view-item');
      if (link) {
        e.preventDefault();
        e.stopPropagation();
        openSidebarContextMenu(link, trigger);
      }
      return;
    }

    const link = e.target.closest('a.projectoverview-view-item');
    if (!link) return;

    const viewKey = link.getAttribute('data-view-key');
    if (!viewKey) return;

    // Drive the (hidden) tabs widget so its activate handler does the
    // bookkeeping. htmx fires its own hx-get on this same click independently.
    if (window.jQuery && window.jQuery.fn.tabs) {
      const $tab = window.jQuery(
        '#projectOverviewTabs li[data-target="' +
          viewKey.replace(/"/g, '\\"') +
          '"]'
      );
      const tabIndex = $tab.index();
      if (tabIndex >= 0) {
        try {
          window
            .jQuery('#projectOverviewTabs')
            .tabs('option', 'active', tabIndex);
        } catch (err) {
          console.warn('[ProjectOverview] tabs() activation failed', err);
        }
      }
    }

    applySidebarActiveState();
  });

  // Move all saved-view <li>s into a scrollable wrapper so the sidebar can't
  // grow unbounded when the user has many views. The "+ New view" item is
  // deliberately left outside so it stays pinned at the bottom and visible.
  // Must run before initSidebarSortable() since the sortable binds to the
  // <ul> the items actually live in after this move.
  wrapSidebarViewListInScroller();

  initSidebarSortable();

  // After the views live inside the scroll container, make sure the active
  // one is visible — if the page was loaded with ?view=<id> pointing at an
  // entry that sits past the scroller's fold, the user would otherwise see
  // an empty/unrelated portion of the list.
  scrollActiveSidebarViewIntoView();
}

/**
 * Mark the sidebar <li> whose link's `data-view-key` matches `#selectedViewId`
 * with `.projectoverview-view-active`, clearing it from all other view items.
 * Called from the tabs activate handler in the main entrypoint when the active
 * view changes, so the sidebar stays in sync.
 */
export function applySidebarActiveState() {
  const selected = document.getElementById('selectedViewId');
  const activeKey = selected ? selected.value : null;
  document
    .querySelectorAll('a.projectoverview-view-item')
    .forEach(function (link) {
      const li = link.parentElement;
      if (!li) return;
      li.classList.toggle(
        'projectoverview-view-active',
        link.getAttribute('data-view-key') === activeKey
      );
    });
}

/**
 * Scroll the currently-active sidebar view into the visible area of the
 * scrollable view list. No-op when the active item is already fully visible.
 */
function scrollActiveSidebarViewIntoView() {
  const active = document.querySelector('li.projectoverview-view-active');
  if (!active) return;
  // `block: 'nearest'` minimizes scroll: only moves when the element is out
  // of the viewport of its nearest scrollable ancestor, which is our inner
  // <ul.projectoverview-views-inner-list>.
  active.scrollIntoView({ block: 'nearest' });
}

/**
 * Wraps the saved-view <li>s in a max-height + overflow:auto container so a
 * long view list scrolls inside the sidebar instead of pushing the layout.
 * Idempotent — does nothing if the wrapper already exists.
 */
function wrapSidebarViewListInScroller() {
  if (document.querySelector('.projectoverview-views-scroller')) return;

  const firstView = document.querySelector('a.projectoverview-view-item');
  if (!firstView) return;
  const firstLi = firstView.closest('li');
  const outerUl = firstLi ? firstLi.parentElement : null;
  if (!outerUl) return;

  // Collect every <li> whose anchor is a view item AND not the "+ New view"
  // entry. Order is preserved by walking the outer <ul>'s children.
  const viewLis = Array.from(outerUl.children).filter(function (li) {
    const a = li.querySelector('a.projectoverview-view-item');
    return a && !a.classList.contains('projectoverview-new-view-item');
  });
  if (viewLis.length === 0) return;

  const wrapperLi = document.createElement('li');
  wrapperLi.className = 'projectoverview-views-scroller';

  const innerUl = document.createElement('ul');
  innerUl.className = 'projectoverview-views-inner-list';
  for (const li of viewLis) {
    innerUl.appendChild(li);
  }
  wrapperLi.appendChild(innerUl);

  // Insert the wrapper at the position the first view occupied (which is now
  // adjacent to the "+ New view" item).
  outerUl.insertBefore(wrapperLi, outerUl.firstChild);
  // Move it to just before the "+ New view" item — outerUl.firstChild was the
  // first remaining child after removing the views, which may not be the
  // intended position. Recalculate explicitly.
  const newViewLi = outerUl.querySelector(
    'a.projectoverview-new-view-item'
  )?.parentElement;
  if (newViewLi) {
    outerUl.insertBefore(wrapperLi, newViewLi);
  }
}

/**
 * Open the shared #view-context-menu anchored to the trigger element (the
 * 3-dot span). Anchoring to the trigger instead of the whole <li> keeps the
 * menu visually attached to what was clicked.
 *
 * @param {HTMLAnchorElement} link     The sidebar <a> for the view.
 * @param {HTMLElement}       [anchor] Element to position the menu next to.
 *                                     Falls back to the trigger inside `link`,
 *                                     then to the link itself.
 */
function openSidebarContextMenu(link, anchor) {
  const viewId = link.getAttribute('data-view-key');
  if (!viewId || viewId === '__new') return;

  const contextMenu = document.getElementById('view-context-menu');
  if (!contextMenu) return;

  const isSubscription =
    link.getAttribute('data-is-subscription') === 'true' ||
    link.getAttribute('data-is-transient-subscription') === 'true';
  const sharedViewId = link.getAttribute('data-shared-view-id') || '';
  const labelSpan = link.querySelector('.view-label');
  const currentName = labelSpan ? labelSpan.textContent.trim() : '';
  // #view-context-menu is position: fixed, so top/left are viewport-relative.
  // Left anchors to the main content area so the popup sits outside the
  // sidebar at a predictable column. Top mirrors the clicked view <li>'s
  // viewport y so the popup appears at the same vertical as the clicked row.
  const mainContent =
    document.querySelector('.maincontentinner') ||
    document.querySelector('.maincontent');
  const mainContentLeft = mainContent
    ? mainContent.getBoundingClientRect().left
    : (anchor || link).getBoundingClientRect().right + 4;
  const rowTop = link.parentElement
    ? link.parentElement.getBoundingClientRect().top
    : (anchor || link).getBoundingClientRect().top;

  contextMenu.dataset.mode = isSubscription ? 'subscription' : 'owned';
  contextMenu.style.left = mainContentLeft + 'px';
  contextMenu.style.top = rowTop + 'px';
  contextMenu.classList.add('shown');

  const title = contextMenu.querySelector('#contextMenuTitle');
  if (title) title.textContent = currentName;
  const setVal = function (sel, v) {
    const el = contextMenu.querySelector(sel);
    if (el) el.value = v;
  };
  setVal('input[name="viewName"]', currentName);
  setVal('input[name="view"]', viewId);
  setVal('input[name="sharedViewId"]', sharedViewId);

  if (!isSubscription) {
    requestAnimationFrame(function () {
      const input = contextMenu.querySelector('input[name="viewName"]');
      if (input) input.focus();
    });
  }
}

/**
 * Apply jQuery UI Sortable to the sidebar <ul> containing the view items.
 * The `items` selector restricts dragging to plugin view items only — other
 * menu entries (Dashboard, Project Hub, etc.) and the "+ New view" item stay
 * fixed in place.
 */
function initSidebarSortable() {
  const firstViewItem = document.querySelector('a.projectoverview-view-item');
  if (!firstViewItem) return;
  const ul = firstViewItem.closest('ul');
  if (!ul) return;
  if (!window.jQuery || !window.jQuery.fn.sortable) return;

  window.jQuery(ul).sortable({
    items:
      'li:has(a.projectoverview-view-item):not(:has(a.projectoverview-new-view-item))',
    axis: 'y',
    tolerance: 'pointer',
    handle: 'a.projectoverview-view-item',
    // Without forcePlaceholderSize, the placeholder collapses to its content
    // height (effectively nothing for an empty <li>), making the drop target
    // ambiguous. Pairing it with a custom placeholder class lets us match the
    // height of the dragged item.
    placeholder: 'projectoverview-view-placeholder',
    forcePlaceholderSize: true,
    // Disambiguate click vs. drag: the cursor must move ~5px AND the press has
    // to last ~150ms before the drag engages. Without these, jQuery UI starts
    // a drag on any pixel of mouse movement, so quick clicks with slight
    // wobble get hijacked into drags and the click action never fires.
    delay: 150,
    distance: 5,
    update: function () {
      const newOrder = Array.from(
        ul.querySelectorAll(
          'a.projectoverview-view-item:not(.projectoverview-new-view-item)'
        )
      ).map(function (a) {
        return a.getAttribute('data-view-key');
      });
      window.jQuery.ajax({
        dataType: 'json',
        url: '/ProjectOverview/ProjectOverview/post',
        method: 'POST',
        data: { action: 'saveTabOrder', order: newOrder },
        error: function (xhr, status, error) {
          console.error('[ProjectOverview] saveTabOrder failed', error);
        },
      });
    },
  });
}
