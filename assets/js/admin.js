(function () {
	'use strict';

	document.addEventListener('submit', function (event) {
		var form = event.target;

		if (!form.classList || !form.classList.contains('wccm-delete-form')) {
			return;
		}

		var confirmed = window.confirm(wccmAdminConfirmMessage());
		if (!confirmed) {
			event.preventDefault();
		}
	});

	document.addEventListener('DOMContentLoaded', function () {
		bindSuggestedIncreaseControls();
		initWooCommerceProductSearch();
		bindCopyableSecrets();
		bindMetaboxQuickAdd();
		maybeScrollToMappings();
		initFieldValidation();
		bindBulkRestoreTrigger();
	});

	function wccmAdminConfirmMessage() {
		if (window.wccmAdmin && window.wccmAdmin.confirmDelete) {
			return window.wccmAdmin.confirmDelete;
		}

		return '';
	}

	function bindSuggestedIncreaseControls() {
		var selectors = document.querySelectorAll('select[name="suggested_increase_limit_mode"], select[name="suggested_increase_mode"]');

		selectors.forEach(function (select) {
			var form = select.closest('form');
			if (!form) {
				return;
			}

			var field = form.querySelector('[data-wccm-suggested-increase-percentage]');
			if (!field) {
				return;
			}

			var updateVisibility = function () {
				var shouldShow = false;

				if (select.name === 'suggested_increase_limit_mode') {
					shouldShow = select.value !== 'none';
				} else {
					shouldShow = select.value === 'percent';
				}

				field.hidden = !shouldShow;
			};

			select.addEventListener('change', updateVisibility);
			updateVisibility();
		});
	}

	function initWooCommerceProductSearch() {
		var fields = document.querySelectorAll('.wccm-product-search');
		var adminConfig = window.wccmAdmin || {};

		if (!fields.length) {
			return;
		}

		initNativeProductSearchFallback(fields, adminConfig);
	}

	function initNativeProductSearchFallback(fields, adminConfig) {
		fields.forEach(function (select) {
			if (select.dataset.wccmNativeSearch === '1') {
				return;
			}

			select.dataset.wccmNativeSearch = '1';

			var originalName = select.getAttribute('name') || 'product_id';
			var selected = select.options[select.selectedIndex];
			var hidden = document.createElement('input');
			var wrapper = document.createElement('div');
			var input = document.createElement('input');
			var results = document.createElement('div');
			var timer = null;

			hidden.type = 'hidden';
			hidden.name = originalName;
			hidden.value = select.value || '';
			select.removeAttribute('name');
			select.required = false;
			select.hidden = true;

			wrapper.className = 'wccm-native-product-search';
			input.type = 'search';
			input.autocomplete = 'off';
			input.placeholder = select.getAttribute('data-placeholder') || adminConfig.searchProductsPlaceholder || 'Search product by name or SKU...';
			input.value = selected && selected.value ? selected.textContent : '';
			results.className = 'wccm-native-product-search-results';
			results.hidden = true;

			wrapper.appendChild(hidden);
			wrapper.appendChild(input);
			wrapper.appendChild(results);
			select.parentNode.insertBefore(wrapper, select.nextSibling);

			input.addEventListener('input', function () {
				hidden.value = '';
				input.setCustomValidity('');
				window.clearTimeout(timer);

				var term = input.value.trim();
				if (term.length < 3) {
					results.hidden = true;
					results.innerHTML = '';
					return;
				}

				timer = window.setTimeout(function () {
					searchProducts(select, adminConfig, term, results, function (id, text) {
						hidden.value = id;
						input.value = text;
						results.hidden = true;
						results.innerHTML = '';
					});
				}, 250);
			});

			var form = select.closest('form');
			if (form) {
				form.addEventListener('submit', function (event) {
					if (!hidden.value) {
						event.preventDefault();
						input.setCustomValidity(adminConfig.labelSelectProduct || 'Select a WooCommerce product from the search results.');
						input.reportValidity();
					}
				});
			}
		});
	}

	function searchProducts(select, adminConfig, term, results, onSelect) {
		var params = new URLSearchParams({
			action: select.getAttribute('data-action') || 'woocommerce_json_search_products_and_variations',
			security: select.getAttribute('data-security') || adminConfig.searchProductsNonce || '',
			term: term,
			limit: '20'
		});

		results.hidden = false;
		results.innerHTML = '<div class="wccm-native-product-search-message">' + (adminConfig.labelSearching || 'Searching...') + '</div>';

		window.fetch((adminConfig.ajaxUrl || window.ajaxurl) + '?' + params.toString(), {
			credentials: 'same-origin',
			headers: {
				accept: 'application/json'
			}
		})
			.then(function (response) {
				return response.json();
			})
			.then(function (data) {
				var products = normalizeProductSearchResults(data);

				if (!products.length) {
					results.innerHTML = '<div class="wccm-native-product-search-message">' + (adminConfig.labelNoProducts || 'No products found.') + '</div>';
					return;
				}

				results.innerHTML = '';
				products.forEach(function (product) {
					var button = document.createElement('button');
					button.type = 'button';
					button.className = 'wccm-native-product-search-result';
					button.textContent = product.text;
					button.addEventListener('click', function () {
						onSelect(product.id, product.text);
					});
					results.appendChild(button);
				});
			})
			.catch(function () {
				results.innerHTML = '<div class="wccm-native-product-search-message">' + (adminConfig.labelSearchFailed || 'Product search failed.') + '</div>';
			});
	}

	function normalizeProductSearchResults(data) {
		if (data && Array.isArray(data.results)) {
			return data.results.map(function (item) {
				return {
					id: String(item.id || ''),
					text: String(item.text || item.id || '')
				};
			}).filter(function (item) {
				return item.id && item.text;
			});
		}

		return Object.keys(data || {}).map(function (id) {
			var item = data[id];
			return {
				id: item && item.id ? String(item.id) : String(id),
				text: item && item.text ? String(item.text) : String(item || id)
			};
		}).filter(function (item) {
			return item.id && item.text;
		});
	}

	function bindMetaboxQuickAdd() {
		var section = document.getElementById('wccm-quick-add-section');
		if (!section) {
			return;
		}

		var btn       = document.getElementById('wccm-quick-add-btn');
		var msgEl     = document.getElementById('wccm-quick-add-message');
		var noMapEl   = document.getElementById('wccm-no-mappings');
		var tableEl   = document.getElementById('wccm-mappings-table');
		var tbodyEl   = document.getElementById('wccm-mappings-tbody');
		var adminConfig = window.wccmAdmin || {};

		btn.addEventListener('click', function () {
			var urlInput    = section.querySelector('[name="wccm_product_mapping_competitor_url"]');
			var nameInput   = section.querySelector('[name="wccm_product_mapping_competitor_name"]');
			var currInput   = section.querySelector('[name="wccm_product_mapping_currency"]');
			var marginInput = section.querySelector('[name="wccm_product_mapping_min_margin_percentage"]');
			var activeEl    = section.querySelector('[name="wccm_product_mapping_active"]');

			var url = urlInput ? urlInput.value.trim() : '';
			if (!url) {
				showQuickAddMsg(msgEl, 'error', adminConfig.labelEnterUrl || 'Enter a competitor product URL.');
				if (urlInput) { urlInput.focus(); }
				return;
			}

			if (tbodyEl) {
				var existingLinks = tbodyEl.querySelectorAll('a[href]');
				for (var i = 0; i < existingLinks.length; i++) {
					if (existingLinks[i].href === url || existingLinks[i].getAttribute('href') === url) {
						showQuickAddMsg(msgEl, 'error', adminConfig.labelDuplicateUrl || 'This competitor URL is already mapped to this product.');
						if (urlInput) { urlInput.focus(); }
						return;
					}
				}
			}

			btn.disabled    = true;
			btn.textContent = adminConfig.labelAdding || 'Adding...';
			showQuickAddMsg(msgEl, '', '');

			var body = new URLSearchParams({
				action:                  'wccm_quick_add_mapping',
				_ajax_nonce:             section.dataset.nonce || '',
				product_id:              section.dataset.productId || '',
				competitor_url:          url,
				competitor_name:         nameInput   ? nameInput.value.trim()   : '',
				currency:                currInput   ? currInput.value.trim()   : '',
				min_margin_percentage:   marginInput ? marginInput.value        : '20',
				active:                  activeEl    ? activeEl.value           : '1'
			});

			window.fetch(adminConfig.ajaxUrl || window.ajaxurl, {
				method:      'POST',
				credentials: 'same-origin',
				headers:     { 'Content-Type': 'application/x-www-form-urlencoded' },
				body:        body.toString()
			})
				.then(function (r) { return r.json(); })
				.then(function (data) {
					if (!data.success) {
						showQuickAddMsg(msgEl, 'error', (typeof data.data === 'string' ? data.data : '') || (adminConfig.labelRequestFailed || 'Request failed.'));
						return;
					}

					if (tbodyEl && data.data.row_html) {
						var parser = new DOMParser();
						var parsed = parser.parseFromString(data.data.row_html, 'text/html');
						var newRow = parsed.querySelector('tr');
						if (newRow) {
							tbodyEl.appendChild(document.adoptNode(newRow));
						}
					}
					if (tableEl)  { tableEl.hidden  = false; }
					if (noMapEl)  { noMapEl.hidden   = true;  }

					if (urlInput)  { urlInput.value  = ''; }
					if (nameInput) { nameInput.value = ''; }

					showQuickAddMsg(msgEl, 'success', data.data.message || (adminConfig.labelAdded || 'Competitor added.'));
				})
				.catch(function () {
					showQuickAddMsg(msgEl, 'error', adminConfig.labelRequestFailed || 'Request failed.');
				})
				.finally(function () {
					btn.disabled    = false;
					btn.textContent = adminConfig.labelAddCompetitor || 'Add competitor';
				});
		});
	}

	function showQuickAddMsg(el, type, text) {
		if (!el) { return; }
		el.textContent = text;
		el.style.color  = type === 'error' ? '#cc1818' : (type === 'success' ? '#00a32a' : '');
		el.hidden       = !text;
	}

	function maybeScrollToMappings() {
		var params = new URLSearchParams(window.location.search);
		if (params.get('wccm_scroll') !== 'mappings') {
			return;
		}
		var panel = document.getElementById('wccm-mappings-panel');
		if (!panel) {
			return;
		}
		panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
		params.delete('wccm_scroll');
		var newUrl = window.location.pathname + (params.toString() ? '?' + params.toString() : '');
		window.history.replaceState(null, '', newUrl);
	}

	function initFieldValidation() {
		var urlField = document.getElementById('wccm-competitor-url');
		if (urlField) {
			var urlError = document.getElementById('wccm-competitor-url-error');
			urlField.addEventListener('blur', function () {
				var val = urlField.value.trim();
				if (val && !isValidHttpUrl(val)) {
					showFieldError(urlError, urlField, (window.wccmAdmin && window.wccmAdmin.labelInvalidUrl) || 'Enter a full URL starting with https:// or http://');
				} else {
					clearFieldError(urlError, urlField);
				}
			});
			urlField.addEventListener('input', function () {
				clearFieldError(urlError, urlField);
			});
		}

		[['wccm-price-selector', 'wccm-price-selector-error'], ['wccm-stock-selector', 'wccm-stock-selector-error']].forEach(function (pair) {
			var field = document.getElementById(pair[0]);
			var error = document.getElementById(pair[1]);
			if (!field || !error) {
				return;
			}
			field.addEventListener('blur', function () {
				var val = field.value.trim();
				if (val && !isValidCssSelector(val)) {
					showFieldError(error, field, (window.wccmAdmin && window.wccmAdmin.labelInvalidSelector) || 'Invalid CSS selector syntax');
				} else {
					clearFieldError(error, field);
				}
			});
			field.addEventListener('input', function () {
				clearFieldError(error, field);
			});
		});
	}

	function isValidHttpUrl(val) {
		try {
			var u = new URL(val);
			return u.protocol === 'http:' || u.protocol === 'https:';
		} catch (e) {
			return false;
		}
	}

	function isValidCssSelector(sel) {
		try {
			document.createDocumentFragment().querySelector(sel);
			return true;
		} catch (e) {
			return false;
		}
	}

	function showFieldError(errorEl, field, message) {
		if (!errorEl) { return; }
		errorEl.textContent = message;
		errorEl.hidden = false;
		field.setAttribute('aria-invalid', 'true');
		field.classList.add('wccm-input-error');
	}

	function clearFieldError(errorEl, field) {
		if (!errorEl) { return; }
		errorEl.hidden = true;
		field.removeAttribute('aria-invalid');
		field.classList.remove('wccm-input-error');
	}

	function bindBulkRestoreTrigger() {
		var trigger = document.getElementById('wccm-restore-trigger');
		var confirm = document.getElementById('wccm-restore-confirm');
		var cancel  = document.getElementById('wccm-restore-cancel');

		if (!trigger || !confirm) {
			return;
		}

		trigger.addEventListener('click', function () {
			confirm.hidden = false;
			trigger.hidden = true;
		});

		if (cancel) {
			cancel.addEventListener('click', function () {
				confirm.hidden = true;
				trigger.hidden = false;
			});
		}
	}

	function bindCopyableSecrets() {
		document.querySelectorAll('.wccm-copyable-secret').forEach(function (input) {
			input.addEventListener('focus', function () {
				input.select();
			});

			input.addEventListener('click', function () {
				input.select();
			});
		});
	}
})();
